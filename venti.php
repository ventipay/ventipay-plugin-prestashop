<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Venti extends PaymentModule
{
    const ADMIN_VENTI_CONFIGURATION_CONTROLLER = 'AdminConfigureVentiPrestashop';
    const HOOKS = [
        'paymentOptions',
        'actionOrderSlipAdd'
    ];
    const VENTI_TEST_MODE = 'VENTI_TEST_MODE';
    const VENTI_API_KEY_TEST = 'VENTI_API_KEY_TEST';
    const VENTI_API_KEY_LIVE = 'VENTI_API_KEY_LIVE';

    public function __construct()
    {
        $this->name = 'venti';
        $this->tab = 'payments_gateways';
        $this->version = '0.0.1';
        $this->author = 'Venti';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'Venti';
        $this->description = 'Plugin de Venti para PrestaShop';
        $this->confirmUninstall = '¿Estás seguro/a que deseas desinstalar este módulo de pago?';
    }

    public function install()
    {
        return (bool) parent::install()
            && $this->installOrderState()
            && (bool) $this->registerHook(static::HOOKS)
            && $this->installConfiguration()
            && $this->installTabs();
    }

    public function uninstall()
    {
        return (bool) parent::uninstall()
            && $this->uninstallOrderState()
            && $this->uninstallConfiguration()
            && $this->uninstallTabs();
    }

    public function installTabs()
    {
        if (Tab::getIdFromClassName(static::ADMIN_VENTI_CONFIGURATION_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = static::ADMIN_VENTI_CONFIGURATION_CONTROLLER;
        $tab->module = $this->name;
        $tab->active = true;
        $tab->id_parent = -1;
        $tab->name = array_fill_keys(
            Language::getIDs(false),
            $this->displayName
        );

        return (bool) $tab->add();
    }

    public function uninstallTabs()
    {
        $id_tab = (int) Tab::getIdFromClassName(static::ADMIN_VENTI_CONFIGURATION_CONTROLLER);

        if ($id_tab) {
            $tab = new Tab($id_tab);

            return (bool) $tab->delete();
        }

        return true;
    }

    private function installConfiguration()
    {
        return (bool) Configuration::updateGlobalValue(static::VENTI_TEST_MODE, 0)
            && (bool) Configuration::updateGlobalValue(static::VENTI_API_KEY_TEST, '')
            && (bool) Configuration::updateGlobalValue(static::VENTI_API_KEY_LIVE, '');
    }

    /**
     * Uninstall module configuration
     *
     * @return bool
     */
    private function uninstallConfiguration()
    {
        return (bool) Configuration::deleteByName(static::VENTI_TEST_MODE)
            && (bool) Configuration::deleteByName(static::VENTI_API_KEY_TEST)
            && (bool) Configuration::deleteByName(static::VENTI_API_KEY_LIVE);
    }

    private function installOrderState(): bool
    {
        $existingId = (int) Configuration::get('VENTI_OS_PENDING');
        if ($existingId && Validate::isLoadedObject(new OrderState($existingId))) {
            return true;
        }

        $orderState = new OrderState();
        $orderState->name = [
            (int)Configuration::get('PS_LANG_DEFAULT') => 'Waiting for Venti Payment'
        ];
        $orderState->color = '#4169E1';
        $orderState->unremovable = true;
        $orderState->logable = false;
        $orderState->send_email = false;
        $orderState->module_name = $this->name;
        $orderState->add();

        return Configuration::updateValue('VENTI_OS_PENDING', (int)$orderState->id);
    }

    public function uninstallOrderState(): bool
    {
        $pendingId = (int) Configuration::get('VENTI_OS_PENDING');
        if ($pendingId) {
            $orderState = new OrderState($pendingId);
            if (Validate::isLoadedObject($orderState)) {
                $orderState->module_name = null;
                $orderState->unremovable = false;
                $orderState->update();
            }
        }

        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setCallToActionText($this->l('Paga con Venti'))
                  ->setLogo(_MODULE_DIR_ . $this->name . '/logo_checkout.png')
                  ->setAction($this->context->link->getModuleLink($this->name, 'checkout', [], true));

        return [$newOption];
    }

    public function getContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink(self::ADMIN_VENTI_CONFIGURATION_CONTROLLER)
        );
    }

    public function hookActionOrderSlipAdd($params)
    {
        $order = $params['order'];
        $orderSlip = $params['orderSlipCreated'];

        if ($order->module !== $this->name) {
            return;
        }

        $amount = (float) $orderSlip->amount + (float) $orderSlip->total_shipping_tax_incl;
    
        if ($amount <= 0) {
            return;
        }

        $payments = $order->getOrderPayments();

        $checkoutId = null;
        foreach ($payments as $payment) {
            if ($payment->payment_method === $this->displayName) {
                $checkoutId = $payment->transaction_id;
                break;
            }
        }

        $mode = Configuration::get('VENTI_TEST_MODE');
        $apiKey = $mode ? Configuration::get('VENTI_API_KEY_TEST') : Configuration::get('VENTI_API_KEY_LIVE');

        $ch = curl_init('https://api.ventipay.com/v1/checkouts/' . $checkoutId . '/refund');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'amount' => $amount
        ]));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $ch = curl_init('https://api.ventipay.com/v1/checkouts/' . $checkoutId . '/refund');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'amount' => $amount
            ]));
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        if ($httpCode !== 200) {
            $orderSlip->delete();
            throw new PrestaShopException('El reembolso no fue realizado. Por favor, intenta nuevamente.');
        }

        $data = json_decode($response, true);

        $order->current_state = Configuration::get('PS_OS_REFUND');
        $order->save();

        $history = new OrderHistory();
        $history->id_order = $order->id;
        $history->id_employee = (int)$this->context->employee->id ?? 0;
        $history->date_add = date('Y-m-d H:i:s'); 
        $history->changeIdOrderState(Configuration::get('PS_OS_REFUND'), $order, true);
        $history->addWithemail(false);

        PrestaShopLogger::addLog("Reembolso de {$amount} ejecutado para pedido {$order->id}");
    }
}
