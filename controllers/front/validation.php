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
class XaigateValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($this->module->active == false) {
            exit;
        }

        $cart = $this->context->cart;

        if ($cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->module->name) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            exit;
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $amount = (float) $cart->getOrderTotal(true, Cart::BOTH);

        Context::getContext()->cart = new Cart((int) $cart->id);
        Context::getContext()->customer = new Customer((int) $cart->id_customer);
        Context::getContext()->currency = new Currency((int) Context::getContext()->cart->id_currency);
        Context::getContext()->language = new Language((int) Context::getContext()->customer->id_lang);

        $secure_key = Context::getContext()->customer->secure_key;

        $module_name = $this->module->displayName;
        $currency_id = (int) Context::getContext()->currency->id;
        $permitted_currency = ['USD', 'RUB', 'EUR', 'GBP'];

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('XAIGATE_ID_STATE_AFTER_CREATE'),
            $amount,
            $module_name,
            $message,
            [],
            $currency_id,
            false,
            $secure_key
        );

        $order = new Order((int) $this->module->currentOrder);
        $description = [];
        foreach ($order->getProductsDetail() as $product) {
            $description[] = $product['product_quantity'] . ' × ' . $product['product_name'];
        }

        $is_currency = in_array(Context::getContext()->currency->iso_code, $permitted_currency);
        $data_request = [
            'shopName' => Configuration::get('XAIGATE_SHOP_NAME'),
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $is_currency ? Context::getContext()->currency->iso_code : 'USD',
            'orderId' => $this->module->currentOrder,
            'email' => $customer->email,
            'apiKey' => Configuration::get('XAIGATE_API_KEY'),
            'notifyUrl' => $this->context->link->getModuleLink($module_name, 'pscallback', [], true),
            'successUrl' => Configuration::get('XAIGATE_SUCCESSFUL_URL'),
            'failUrl' => Configuration::get('XAIGATE_UNSUCCESSFUL_URL'),
            'description' => implode(',', $description),
        ];

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_request);

        $headers = ['Content-Type: application/json'];

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data_request));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, 'https://wallet-api.xaigate.com/api/v1/invoice/create');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        curl_close($curl);

        $json_data = json_decode($result, true);
        if ($json_data['status'] == 'Pending' && $json_data['payUrl']) {
            Tools::redirect($json_data['payUrl'], '');
        } else {
            exit($this->module->getTranslator()->trans('This payment method is not available.', [], 'Modules.XAIGATE.Shop'));
        }
    }
}
