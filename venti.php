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
        'paymentReturn',
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
            && (bool) $this->registerHook(static::HOOKS)
            && $this->installConfiguration()
            && $this->installTabs();
    }

    public function uninstall()
    {
        return (bool) parent::uninstall()
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

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setCallToActionText($this->l('Pagar con Venti'))
            ->setAction($this->context->link->getModuleLink($this->name, 'checkout', [], true));

        return [$newOption];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }

    public function getContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink(self::ADMIN_VENTI_CONFIGURATION_CONTROLLER)
        );
    }
}
