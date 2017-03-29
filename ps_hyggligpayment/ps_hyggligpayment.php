<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
if (!defined('_PS_VERSION_')) {
    exit;
}
class Ps_Hyggligpayment extends PaymentModule{
    private $_html = '';
    private $_postErrors = array();
    public function __construct(){

        $this->name = 'ps_hyggligpayment';
        $this->tab = 'payment_methods';
        $this->version = '1.8.0';
        $this->author = 'Marginalen Financial Services AB';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Hygglig');
        $this->description = $this->l('1.8 gateway for Hygglig.');
    }
    public function uninstall(){
        if (parent::uninstall() == false ||
            Configuration::deleteByName('HCO_SHOW_IN_PAYMENTS') == false ||
            Configuration::deleteByName('HCO_SWEDEN_SECRET') == false ||
            Configuration::deleteByName('HCO_SWEDEN_TERMS') == false ||
            Configuration::deleteByName('HCO_SWEDEN_EID') == false ||
            Configuration::deleteByName('HCO_SWEDEN') == false ||
            Configuration::deleteByName('HCO_IS_ACTIVE') == false ||
            Configuration::deleteByName('HCO_SHOWLINK') == false ||
            Configuration::deleteByName('HCO_TESTMODE') == false ||
            Configuration::deleteByName('HCO_ACTIVATE_STATE') == false ||
            Configuration::deleteByName('HCO_CANCEL_STATE') == false
        ) {
            return false;
        }
        $this->dropTables();
        return true;
    }
    public function install(){
        if (parent::install() == false ||
		$this->registerHook('moduleroutes') == false ||
		$this->registerHook('header') == false ||
		$this->registerHook('updateOrderStatus') == false ||
		$this->registerHook('displayFooter') == false) {
            return false;
        }
        return true;
    }
	public function hookModuleRoutes() {
		return array(
			'module-ps_hyggligpayment-checkout' => array(
				'controller' => 'checkout',
				'rule' => 'hygglig-checkout',
				'keywords' => array(),
				'params' => array(
					'fc' => 'module',
					'module' => $this->name,
				)
			),
			'module-ps_hyggligpayment-thankyou' => array(
				'controller' => 'thankyou',
				'rule' => 'hygglig-thankyou',
				'keywords' => array(),
				'params' => array(
					'fc' => 'module',
					'module' => $this->name,
				)
			),
		);
	}
	public function hookDisplayHeader($params){
		$this->context->controller->registerJavascript('modules-ps_hyggligpayment', 'modules/'.$this->name.'/js/hco_common.js', ['position' => 'bottom', 'priority' => 200]);
	}
	public function hookDisplayFooter($param){
		$this->smarty->assign(
			'hco_checkout_url',
			$this->context->link->getModuleLink('ps_hyggligpayment', 'checkout')
		);
		$this->smarty->assign(
			'hco_checkout_thnx',
			$this->context->link->getModuleLink('ps_hyggligpayment', 'thankyou')
		);
		return $this->display(__FILE__, 'hookDisplayFooter.tpl');
    }
	public function getContent(){
        $isSaved = false;
        if (Tools::isSubmit('btnCommonSubmit')) {
            Configuration::updateValue('HCO_SHOW_IN_PAYMENTS', (int) Tools::getValue('HCO_SHOW_IN_PAYMENTS'));
            Configuration::updateValue('HCO_SWEDEN_SECRET', Tools::getValue('HCO_SWEDEN_SECRET'));
			Configuration::updateValue('HCO_SWEDEN_TERMS', Tools::getValue('HCO_SWEDEN_TERMS'));
            Configuration::updateValue('HCO_SWEDEN_EID', (string) Tools::getValue('HCO_SWEDEN_EID'));
            Configuration::updateValue('HCO_SWEDEN', (int) Tools::getValue('HCO_SWEDEN'));
            Configuration::updateValue('HCO_IS_ACTIVE', (int) Tools::getValue('HCO_IS_ACTIVE'));
            Configuration::updateValue('HCO_MORDER_CONNECTION', Tools::getValue('HCO_MORDER_CONNECTION'));
            Configuration::updateValue('HCO_SHOWLINK', (int) Tools::getValue('HCO_SHOWLINK'));
            Configuration::updateValue('HCO_TESTMODE', Tools::getValue('HCO_TESTMODE'));
			Configuration::updateValue('HCO_ACTIVATE_STATE', implode(';', Tools::getValue('HCO_ACTIVATE_STATE')));
			Configuration::updateValue('HCO_CANCEL_STATE', implode(';', Tools::getValue('HCO_CANCEL_STATE')));
			$isSaved = true;
        }
        $this->context->smarty->assign(array(
            'isSaved' => $isSaved,
            'commonform' => $this->createCommonForm(),
            'REQUEST_URI' => Tools::safeOutput($_SERVER['REQUEST_URI']),
        ));
        return '<script type="text/javascript">var pwd_base_uri = "'.
        __PS_BASE_URI__.'";var pwd_refer = "'.
        (int) Tools::getValue('ref').'";</script>'.
        $this->display(__FILE__, 'views/templates/admin/hygglig_admin.tpl');
    }
	public function createCommonForm(){
        $states = OrderState::getOrderStates((int) $this->context->cookie->id_lang);
        $states[] = array('id_order_state' => '-1', 'name' => $this->l('Deactivated'));

        $fields_form = array();
        $fields_form[0]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Hygglig checkout settings'),
                    'icon' => 'icon-pencil',
                  ),
                'input' => array(
				array(
                    'type' => 'switch',
                    'label' => $this->l('Aktivera Hygglig'),
                    'name' => 'HCO_IS_ACTIVE',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'showlink_on',
                            'value' => 1,
                            'label' => $this->l('Yes'), ),
                        array(
                            'id' => 'showlink_off',
                            'value' => 0,
                            'label' => $this->l('No'), ),
                    ),
                    'desc' => $this->l('Aktivera Hygglig checkout i din kassa.'),
                ),
				array(
					'type' => 'text',
					'label' => $this->l('Handlar nyckel'),
					'name' => 'HCO_SWEDEN_EID',
					'required' => true,
					'desc' => $this->l('Din handlarnyckel: "00000000-0000-0000-0000-000000000000"'),
				),
				array(
					'type' => 'text',
					'label' => $this->l('Hemlig nyckel'),
					'name' => 'HCO_SWEDEN_SECRET',
					'required' => true,
					'desc' => $this->l('Din hemliga nyckel: "00000000-0000-0000-0000-000000000000"'),
				),
				array(
					'type' => 'text',
					'label' => $this->l('Köpvillkor'),
					'name' => 'HCO_SWEDEN_TERMS',
					'required' => true,
					'desc' => $this->l('Länk till dina villkor, t.ex. "' . _PS_BASE_URL_ .'/villkor"'),
				),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Testläge'),
                    'name' => 'HCO_TESTMODE',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'testmode_on',
                            'value' => 1,
                            'label' => $this->l('Yes'), ),
                        array(
                            'id' => 'testmode_off',
                            'value' => 0,
                            'label' => $this->l('No'), ),
                    ),
                    'desc' => $this->l('Aktivera testläget.'),
                ),
				array(
                    'type' => 'switch',
                    'label' => $this->l('Hygglig Management'),
                    'name' => 'HCO_MORDER_CONNECTION',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'morder_on',
                            'value' => 1,
                            'label' => $this->l('Yes'), ),
                        array(
                            'id' => 'morder_off',
                            'value' => 0,
                            'label' => $this->l('No'), ),
                    ),
                    'desc' => $this->l('Aktivera och avbryt orders automatiskt hos Hygglig.'),
                ),
                array(
                    'type' => 'select',
					'multiple' => true,
                    'label' => $this->l('Aktivera order status'),
                    'name' => 'HCO_ACTIVATE_STATE',
                    'desc' => $this->l('Ordern kommer att aktiveras hos Hygglig när statusen nedan väljs.'),
                    'options' => array(
                        'query' => $states,
                        'id' => 'id_order_state',
                        'name' => 'name',
                    ),
                ),
                array(
                    'type' => 'select',
					'multiple' => true,
                    'label' => $this->l('Avbryt order status'),
                    'name' => 'HCO_CANCEL_STATE',
                    'desc' => $this->l('Ordern kommer att avbrytas hos Hygglig när statusen nedan väljs.'),
                    'options' => array(
                        'query' => $states,
                        'id' => 'id_order_state',
                        'name' => 'name',
                    ),
                ),
				array(
                    'type' => 'switch',
                    'label' => $this->l('Visa länk till standardkassan'),
                    'name' => 'HCO_SHOWLINK',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'showlink_on',
                            'value' => 1,
                            'label' => $this->l('Yes'), ),
                        array(
                            'id' => 'showlink_off',
                            'value' => 0,
                            'label' => $this->l('No'), ),
                    ),
                    'desc' => $this->l('Visar en länk till din standardkassa.'),
                ),
                //common settings
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        if (Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')) {
            $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        } else {
            $helper->allow_employee_form_lang = 0;
        }

        $helper->submit_action = 'btnCommonSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).
        '&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm($fields_form);
    }
	public function getConfigFieldsValues(){
        return array(
            'HCO_SHOW_IN_PAYMENTS' => Tools::getValue(
                'HCO_SHOW_IN_PAYMENTS',
                Configuration::get('HCO_SHOW_IN_PAYMENTS')
            ),
            'HCO_TESTMODE' => Tools::getValue(
                'HCO_TESTMODE',
                Configuration::get('HCO_TESTMODE')
            ),
			'HCO_IS_ACTIVE' => Tools::getValue(
                'HCO_IS_ACTIVE',
                Configuration::get('HCO_IS_ACTIVE')
            ),
			'HCO_MORDER_CONNECTION' => Tools::getValue(
                'HCO_MORDER_CONNECTION',
                Configuration::get('HCO_MORDER_CONNECTION')
            ),
            'HCO_SWEDEN_EID' => Tools::getValue(
                'HCO_SWEDEN_EID',
                Configuration::get('HCO_SWEDEN_EID')
            ),
            'HCO_SWEDEN_SECRET' => Tools::getValue(
                'HCO_SWEDEN_SECRET',
                Configuration::get('HCO_SWEDEN_SECRET')
            ),
			'HCO_SWEDEN_TERMS' => Tools::getValue(
                'HCO_SWEDEN_TERMS',
                Configuration::get('HCO_SWEDEN_TERMS')
            ),
            'HCO_SWEDEN' => Tools::getValue(
                'HCO_SWEDEN',
                Configuration::get('HCO_SWEDEN')
            ),
            'HCO_ACTIVATE_STATE[]' => Tools::getValue(
                'HCO_ACTIVATE_STATE',
				explode(';', Configuration::get('HCO_ACTIVATE_STATE'))
            ),
			'HCO_CANCEL_STATE[]' => Tools::getValue(
                'HCO_CANCEL_STATE',
				explode(';', Configuration::get('HCO_CANCEL_STATE'))
            ),
            'HCO_SHOWLINK' => Tools::getValue(
                'HCO_SHOWLINK',
                Configuration::get('HCO_SHOWLINK')
            ),
        );
    }
	public function hookUpdateOrderStatus($params){

		//Check if connection is active
		if(Configuration::get('HCO_MORDER_CONNECTION')){
			$newOrderStatus = $params['newOrderStatus'];
			$statusId = $newOrderStatus->id;
			$sendId = explode(';', Configuration::get('HCO_ACTIVATE_STATE'));
			$cancelId = explode(';', Configuration::get('HCO_CANCEL_STATE'));
			//Check if status is part of Hygglig connection
			if(in_array($statusId, $sendId) || in_array($statusId, $cancelId)){
				$order = new Order((int) $params['id_order']);
				if ($order->module == 'ps_hyggligpayment') {
					$payments = $order->getOrderPaymentCollection();
					$key = Configuration::get('HCO_SWEDEN_EID');
					$secretkey = strtoupper(Configuration::get('HCO_SWEDEN_SECRET'));
					$hyggligId = $payments[0]->transaction_id;
					if ((int) (Configuration::get('HCO_TESTMODE')) == 1) {
						$tAddress = 'http://sandbox.hygglig.com/Manage/api/CheckoutOrder/';
					} else {
						$tAddress = 'https://www.hygglig.com/Manage/api/CheckoutOrder/';
					}

					if(in_array($statusId, $sendId)){
						$tAddress = $tAddress . 'SendOrder?';
					}
					else{
						if(in_array($statusId, $cancelId)){
							$tAddress = $tAddress . 'CancelOrder?';
						}
						else{
							die();
						}
					}
					//Create checksum
					$checksum = sha1(strtoupper($hyggligId . $secretkey));
					//Create postdata
					$postData = array(
						'mId' => $key,
						'ordernr' => $hyggligId,
						'mac' => $checksum
					);
					//CurlIt
					$ch = curl_init($tAddress . 'mId=' . $key . '&ordernr=' . $hyggligId . '&mac=' . $checksum);
					curl_setopt_array($ch, array(
						CURLOPT_POST => TRUE,
						CURLOPT_RETURNTRANSFER => 1,
						CURLOPT_HTTPHEADER => array(
							'Content-Type: application/json; encoding=utf-8'
						),
						CURLOPT_POSTFIELDS => json_encode($postdata)
					));
					// Send the request
					$response = curl_exec($ch);
				}
			}
		}
    }
}
