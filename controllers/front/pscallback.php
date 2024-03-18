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
class XaigatePsCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $id_order = Tools::getValue('orderId');
        $status = Tools::getValue('status');
        try {
            $state_success = Configuration::get('XAIGATE_ID_STATE_SUCCESS');
            $state_fail = Configuration::get('XAIGATE_ID_STATE_FAIL');
            $order = new Order((int) $id_order);
            if (Validate::isLoadedObject($order) && $status == 'Paid') {
                $order_history = new OrderHistory();
                $order_history->id_order = (int) $id_order;
                $order_history->changeIdOrderState($state_success, $id_order);
                $order_history->addWithemail(true);
            } else {
                $order_history = new OrderHistory();
                $order_history->id_order = (int) $id_order;
                $order_history->changeIdOrderState($state_fail, $id_order);
                $order_history->addWithemail(true);
            }
            header('Content-Type:');
            exit;
        } catch (Exception $e) {
            header('Content-Type:');
            exit;
        }
    }
}
