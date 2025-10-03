<?php

class VentiCheckoutModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        
        if (!$this->module->active || !$cart->id) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $mode = Configuration::get('VENTI_TEST_MODE');
        $apiKey = $mode ? Configuration::get('VENTI_API_KEY_TEST') : Configuration::get('VENTI_API_KEY_LIVE');

        $products = $cart->getProducts();

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('VENTI_OS_PENDING'),
            (float)$cart->getOrderTotal(true, Cart::BOTH),
            $this->module->displayName,
            null,
            [],
            (int)$cart->id_currency,
            false,
            $this->context->customer->secure_key
        );

        $orderId = $this->module->currentOrder;
        $order = new Order($orderId);
        $currency = new Currency($order->id_currency);
        $items = [];

        foreach ($products as $product) {
            $items[] = [
                'quantity'   => (int) $product['cart_quantity'],
                'name'       => $product['name'],
                'unit_price' => (float) $product['price_wt'], // precio unitario con impuestos
                'sku'        => !empty($product['reference']) ? $product['reference'] : null, // si tiene referencia
            ];
        }

        $body = [
          'items' => $items,
          'currency' => $currency->iso_code,
          'success_url' => $this->context->link->getModuleLink($this->module->name, 'validation', ['cart_id' => $cart->id], true),
          'notification_url' => $this->context->link->getModuleLink($this->module->name, 'webhook', ['ps_order_id' => (int)$orderId], true),
          'notification_events' => ['checkout.paid'],
          'metadata' => [
            'ps_order_id' => $orderId,
          ],
        ];
     
        $ch = curl_init('https://api.ventipay.com/v1/checkouts');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
          die('Error en cURL: ' . curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        $payment = new OrderPayment();
        $payment->order_reference = $order->reference;
        $payment->transaction_id = $data['id'];
        $payment->payment_method = $this->module->displayName;
        $payment->amount = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $payment->id_currency = (int)$currency->id;
        $payment->conversion_rate = 1;

        $payment->add();

        Tools::redirect($data['url']);
    }
}
