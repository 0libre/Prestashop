<?php
class Ps_HyggligpaymentThankyouModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $ssl = true;
	
    public function setMedia()
    {
        parent::setMedia();
    }
    public function initContent()
    {
		parent::initContent();
		session_start();		
			if(isset($_GET['token'])){
				$token = $_GET['token'];			
			}		
			if(isset($_POST['token'])){
				$token = $_POST['token'];			
			}
			if (!isset($token)) {
				Tools::redirect('index.php');
			}
			
			$url = $this->getURIForSuccessPage();
			$getiframeCheckSum = SHA1(strtoupper($token.strtoupper(Configuration::get('HCO_SWEDEN_SECRET'))));
			$create['MerchantKey'] = strtoupper(Configuration::get('HCO_SWEDEN_EID'));
			$create['Checksum'] = $getiframeCheckSum;
			$create['Token'] = $token;
			$ch = curl_init($url);
			curl_setopt_array($ch, array(
				CURLOPT_POST => TRUE,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json; encoding=utf-8'
				),
				CURLOPT_POSTFIELDS => json_encode($create)
			));
			$response = curl_exec($ch);
			if($response === FALSE){
				die(curl_error($ch));
			}
			else
			{
				curl_close($ch);
				$responseData = null;
				$responseData = json_decode($response,true,1024,JSON_BIGINT_AS_STRING);
				$text = $responseData["HtmlText"];
			}			
			$this->addJquery();
			$this->context->smarty->assign(array(
					'hygglig_html' => $text,
					'order' => 2,
				));
			$this->setTemplate('module:ps_hyggligpayment/views/templates/front/hco_thankyoupage.tpl');
		}
	protected function getURIForSuccessPage(){
		if ((int) (Configuration::get('HCO_TESTMODE')) == 1) {
			return 'http://sandbox.hygglig.com/Checkout/api/Checkout/GetIFrame/';
		} else {
			return 'https://www.hygglig.com/Checkout/api/Checkout/GetIFrame/';
		}
	}
}
