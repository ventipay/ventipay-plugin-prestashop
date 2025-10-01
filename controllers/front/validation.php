<?php
class VentiValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // Aquí decides qué hacer cuando el cliente selecciona tu método de pago
        // Por ejemplo: crear el pedido en estado pending_payment
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_BANKWIRE'), // estado "pendiente de pago"
            $cart->getOrderTotal(true, Cart::BOTH),
            $this->module->displayName,
            null,
            [],
            null,
            false,
            $customer->secure_key
        );

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id
            . '&id_module=' . $this->module->id
            . '&id_order=' . $this->module->currentOrder
            . '&key=' . $customer->secure_key);
    }
}
