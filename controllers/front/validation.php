<?php
class VentiValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cartId = Tools::getValue('cart_id');
        $cart = new Cart($cartId);
        
        $customer = new Customer($cart->id_customer);
        $orderId = Order::getIdByCartId($cart->id);

        if ($orderId) {
            $order = new Order($orderId);
            $currentState = (int)$order->current_state;

            if ($currentState === (int)Configuration::get('PS_OS_PAYMENT')) {
                $ready = true;
                Tools::redirect(
                    'index.php?controller=order-confirmation'
                    . '&id_cart=' . $cart->id
                    . '&id_module=' . $this->module->id
                    . '&id_order=' . $orderId
                    . '&key=' . $customer->secure_key
                );
            } 
        } 
        
        $this->context->smarty->assign(['order_id' => $orderId]);
        $this->setTemplate('module:venti/views/templates/front/waiting.tpl');
    }
}
