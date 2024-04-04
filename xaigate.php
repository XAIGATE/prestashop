<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
class Xaigate extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'xaigate';
        $this->tab = 'payments_gateways';
        $this->version = '2.1.2';
        $this->author = 'XaiGate';
        $this->need_instance = 0;
        $this->controllers = ['payment', 'validation', 'orderstate'];
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        // Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('XaiGate Payment System');
        $this->module_key = 'af12951c1d29113d8bd5dc8eca90bdb6';
        $this->description = $this->l('XAIGATE - Cryptocurrency Payment Gateway');

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        Configuration::updateValue('XAIGATE_API_KEY', '');
        Configuration::updateValue('XAIGATE_SHOP_NAME', '');
        Configuration::updateValue('XAIGATE_ID_STATE_AFTER_CREATE', 3);
        Configuration::updateValue('XAIGATE_ID_STATE_SUCCESS', 2);
        Configuration::updateValue('XAIGATE_ID_STATE_FAIL', 8);
        Configuration::updateValue('XAIGATE_SUCCESSFUL_URL', '');
        Configuration::updateValue('XAIGATE_UNSUCCESSFUL_URL', '');

        return parent::install() && $this->registerHook('header') && $this->registerHook('displayBackOfficeHeader') && $this->registerHook('paymentOptions') && $this->registerHook('displayPaymentReturn');
    }

    public function uninstall()
    {
        Configuration::deleteByName('XAIGATE_API_KEY');
        Configuration::deleteByName('XAIGATE_SHOP_NAME');
        Configuration::deleteByName('XAIGATE_ID_STATE_AFTER_CREATE');
        Configuration::deleteByName('XAIGATE_ID_STATE_SUCCESS');
        Configuration::deleteByName('XAIGATE_ID_STATE_FAIL');
        Configuration::deleteByName('XAIGATE_SUCCESSFUL_URL', '');
        Configuration::deleteByName('XAIGATE_UNSUCCESSFUL_URL', '');
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        // If values have been submitted in the form, process.
        if (((bool) Tools::isSubmit('submitXAIGATEModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign(
            [
                'callback_link' => $this->context->link->getModuleLink($this->name, 'pscallback'),
                'module_dir' => $this->_path,
            ]
        );

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitXAIGATEModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('To get an ApiKey, go to the "Credential" section in your personal account.'),
                        'name' => 'XAIGATE_API_KEY',
                        'label' => $this->l('ApiKey'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'XAIGATE_SHOP_NAME',
                        'label' => $this->l('Shop name'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Order state'),
                        'name' => 'XAIGATE_ID_STATE_AFTER_CREATE',
                        'desc' => $this->l('Order state after create.'),
                        'options' => [
                            'query' => OrderState::getOrderStates($this->context->language->id),
                            'name' => 'name',
                            'id' => 'id_order_state',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Successful payment status'),
                        'name' => 'XAIGATE_ID_STATE_SUCCESS',
                        'options' => [
                            'query' => OrderState::getOrderStates($this->context->language->id),
                            'name' => 'name',
                            'id' => 'id_order_state',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Status of failed payment'),
                        'name' => 'XAIGATE_ID_STATE_FAIL',
                        'options' => [
                            'query' => OrderState::getOrderStates($this->context->language->id),
                            'name' => 'name',
                            'id' => 'id_order_state',
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'XAIGATE_SUCCESSFUL_URL',
                        'label' => $this->l('Successful URL'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'XAIGATE_UNSUCCESSFUL_URL',
                        'label' => $this->l('Unsuccessful URL'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return [
            'XAIGATE_API_KEY' => Configuration::get('XAIGATE_API_KEY'),
            'XAIGATE_SHOP_NAME' => Configuration::get('XAIGATE_SHOP_NAME'),
            'XAIGATE_ID_STATE_AFTER_CREATE' => Configuration::get('XAIGATE_ID_STATE_AFTER_CREATE'),
            'XAIGATE_ID_STATE_SUCCESS' => Configuration::get('XAIGATE_ID_STATE_SUCCESS'),
            'XAIGATE_ID_STATE_FAIL' => Configuration::get('XAIGATE_ID_STATE_FAIL'),
            'XAIGATE_SUCCESSFUL_URL' => Configuration::get('XAIGATE_SUCCESSFUL_URL'),
            'XAIGATE_UNSUCCESSFUL_URL' => Configuration::get('XAIGATE_UNSUCCESSFUL_URL'),
        ];
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $option = new PaymentOption();
        $option->setCallToActionText($this->l('Pay with USDT, BTC, LTC, ETH, XMR, XRP, BCH and other cryptocurrencies.'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
            ->setAdditionalInformation($this->l('Payment in cryptocurrency, you can pay even if you don\'t have it, the service will offer to buy cryptocurrency and pay for it.'));

        return [$option];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
}
