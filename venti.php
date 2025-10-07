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

        $this->displayName = $this->l('Venti');
        $this->description = $this->l('Plugin de Ventipay para PrestaShop');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
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
        $orderState->color = '#4169E1'; // opcional, color de la etiqueta
        $orderState->unremovable = true; // que no se pueda borrar desde el back office
        $orderState->logable = false; // no genera un mensaje en el historial
        $orderState->send_email = false; // no envía mail automático
        $orderState->module_name = $this->name; // opcional, identifica que pertenece a tu módulo
        $orderState->add();

        // Guardar el ID en la configuración para usarlo luego
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
        $newOption->setCallToActionText($this->l('Pagar con Venti'))
            ->setLogo(_MODULE_DIR_ . $this->name . '/logo_venti.png')
            ->setAction($this->context->link->getModuleLink($this->name, 'checkout', [], true));

        return [$newOption];
    }

    public function getContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink(self::ADMIN_VENTI_CONFIGURATION_CONTROLLER)
        );
    }
}
