<?php
class VentiCheckoutModuleFrontController extends ModuleFrontController
{
    const SUPPORTED_CURRENCIES = [
        'CLF' => ['precision' => 4],
        'CLP' => ['precision' => 0],
        'EUR' => ['precision' => 2],
        'USD' => ['precision' => 2]
    ];
    
    public static function isSupported ($currency) {
        if (!isset(self::SUPPORTED_CURRENCIES[$currency])) {
            return false;
        }

        return true;
    }

    public function postProcess()
    {
        $cart = $this->context->cart;
        
        if (!$this->module->active || !$cart->id) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = new Currency($cart->id_currency);

        if (!$this->isSupported($currency->iso_code)) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['message' => 'currency_not_supported']);
            exit;
        }

        $mode = Configuration::get('VENTI_TEST_MODE');
        $apiKey = $mode ? Configuration::get('VENTI_API_KEY_TEST') : Configuration::get('VENTI_API_KEY_LIVE');

        $getCurrency = self::SUPPORTED_CURRENCIES[$currency->iso_code];

        $total = round($cart->getOrderTotal(true, Cart::BOTH), $getCurrency['precision']);
        $amount = (int) ($total * pow(10, $getCurrency['precision']));

        $items[] = [
            'unit_price' => $amount,
            'quantity' => 1,
        ];
       
        $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');

        $body = [
          'items' => $items,
          'currency' => $currency->iso_code,
          'source' => 'prestashop',
          'success_url' => $this->context->link->getModuleLink($this->module->name, 'validation', ['cart_id' => $cart->id], true),
          'notification_url' => $this->context->link->getModuleLink($this->module->name, 'webhook', ['cart_id' => (int)$cart->id], true),
          'notification_events' => ['checkout.paid', 'checkout.expired'],
          'expires_at' => $expiresAt,
          'metadata' => [
            'cart_id' => (int) $cart->id
          ]
        ];
     
        $ch = curl_init('https://hnkk19gv-8081.use.devtunnels.ms/v1/checkouts');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: ventipay-plugin-prestashop/' . Venti::VERSION
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
          die('Error en cURL: ' . curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        $existing = Db::getInstance()->getRow(
            'SELECT id_cart FROM `' . _DB_PREFIX_ . 'venti_checkout` WHERE id_cart = ' . (int) $cart->id
        );

        if ($existing) {
            Db::getInstance()->update('venti_checkout', [
                'checkout_id' => pSQL($data['id']),
                'date_add' => date('Y-m-d H:i:s'),
            ], 'id_cart = ' . (int) $cart->id);
        } else {
            Db::getInstance()->insert('venti_checkout', [
                'id_cart' => (int) $cart->id,
                'checkout_id' => pSQL($data['id']),
                'date_add' => date('Y-m-d H:i:s'),
            ]);
        }

        Tools::redirect($data['url']);
    }
}
