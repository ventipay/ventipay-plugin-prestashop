<?php
class VentiWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // 1️⃣ Leer body JSON del webhook
        $input = json_decode(file_get_contents('php://input'), true);
        $checkoutId = $input['data']['id'] ?? null;

        if (!$checkoutId) {
            http_response_code(400);
            die('checkout_id_required');
        }

        $mode = Configuration::get('VENTI_TEST_MODE');
        $apiKey = $mode ? Configuration::get('VENTI_API_KEY_TEST') : Configuration::get('VENTI_API_KEY_LIVE');

        $ch = curl_init('https://api.ventipay.com/v1/checkouts/' . $checkoutId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/json',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (empty($data['metadata']['ps_cart_id'])) {
          http_response_code(404);
          die('cart_id_not_found');
        } else if (empty($data['status']) || $data['status'] !== 'paid') {
          http_response_code(400);
          die('checkout_not_paid');
        }
  
        $cart = new Cart((int)$data['metadata']['ps_cart_id']);
        $orderId = Order::getIdByCartId($cart->id);

        if ($orderId) {
            http_response_code(400);
            die('order_already_created');
        }

        $customer = new Customer($cart->id_customer);

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_PAYMENT'),
            (float)$cart->getOrderTotal(true, Cart::BOTH),
            $this->module->displayName,
            null,
            ['transaction_id' => $checkoutId],
            (int)$cart->id_currency,
            false,
            $customer->secure_key
        );

        http_response_code(200);
        die('OK');
    }
}
