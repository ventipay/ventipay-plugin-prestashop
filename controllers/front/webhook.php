<?php
class VentiWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $cartId = (int) Tools::getValue('cart_id');

        if (!$cartId) {
          http_response_code(400);
          die('cart_id_required');
        }

        $cart = new Cart($cartId);

        if (!Validate::isLoadedObject($cart)) {
            http_response_code(404);
            die('cart_not_found');
        }

        // Check if order already exists for this cart (idempotency)
        $existingOrderId = (int) Order::getIdByCartId($cartId);
        if ($existingOrderId) {
            http_response_code(200);
            die('already_processed');
        }

        $row = Db::getInstance()->getRow(
            'SELECT checkout_id FROM `' . _DB_PREFIX_ . 'venti_checkout` WHERE id_cart = ' . (int) $cartId
        );

        if (!$row || empty($row['checkout_id'])) {
            http_response_code(404);
            die('checkout_id_not_found');
        }

        $checkoutId = $row['checkout_id'];

        // Check the checkout ID format to prevent unnecessary API calls
        if (strpos($checkoutId, 'chk_') !== 0) {
            http_response_code(400);
            die('invalid_checkout_id');
        }

        $mode = Configuration::get('VENTI_TEST_MODE');
        $apiKey = $mode ? Configuration::get('VENTI_API_KEY_TEST') : Configuration::get('VENTI_API_KEY_LIVE');

        $ch = curl_init('https://hnkk19gv-8081.use.devtunnels.ms/v1/checkouts/' . urlencode($checkoutId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/json',
          'User-Agent: ventipay-plugin-prestashop/' . Venti::VERSION
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        // Check if the cart_id in the metadata matches the cart_id from the request
        $metadataCartId = (int) $data['metadata']['cart_id'];
        if ($metadataCartId !== $cartId) {
            http_response_code(400);
            die('cart_id_mismatch');
        }

        $status = strtolower(trim((string) ($data['status'] ?? '')));

        switch ($status) {
          case 'paid':
              $customer = new Customer($cart->id_customer);

              $this->module->validateOrder(
                  (int) $cart->id,
                  (int) Configuration::get('PS_OS_PAYMENT'),
                  (float) $cart->getOrderTotal(true, Cart::BOTH),
                  $this->module->displayName,
                  null,
                  ['transaction_id' => $checkoutId],
                  (int) $cart->id_currency,
                  false,
                  $customer->secure_key
              );
              break;

          case 'expired':
              break;

          default:
              http_response_code(200);
              die('checkout_status_invalid');
        }

        http_response_code(200);
        die('OK');
    }
}
