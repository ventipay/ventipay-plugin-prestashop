<?php
class VentiWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $orderId = (int) Tools::getValue('ps_order_id');

        if (!$orderId) {
          http_response_code(400);
          die('order_id_required');
        }

        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            http_response_code(404);
            die('order_not_found');
        }

        if ((int)$order->current_state !== (int)Configuration::get('VENTI_OS_PENDING')) {
          http_response_code(400);
          die('order_already_processed');
        }

        $payments = $order->getOrderPayments();

        $checkoutId = null;
        foreach ($payments as $payment) {
            if ($payment->payment_method === $this->module->displayName) {
                $checkoutId = $payment->transaction_id;
                break;
            }
        }

        if (!$checkoutId) {
            http_response_code(404);
            die('checkout_id_not_found');
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

        if (empty($data['status']) || $data['status'] !== 'paid') {
          http_response_code(400);
          die('checkout_not_paid');
        }
  
        $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
        $order->save();

        http_response_code(200);
        die('OK');
    }
}
