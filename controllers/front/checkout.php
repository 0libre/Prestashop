<?php
class Ps_HyggligpaymentCheckoutModuleFrontController extends ModuleFrontController
{
  public  $display_column_left = false;
  public  $display_column_right = false;
  public  $ssl = true;
  private $free_shipping;
  private $eid;
  private $secret;
  private $token;
  private $checkoutcart;
  private $hygglig;
  private $iframeData;
  private $baseURI;
  private $response;
  private $html;
  private $hyggligCart;
  private $update;
  private $push;
  private $pushCheck;

  public function setMedia(){
    parent::setMedia();
  }

  protected function getCart(){
    foreach ($this->context->cart->getProducts() as $product) {
      $price = $product['price_wt'];
      $product_reference = $product['reference'];
      $instructions = " ";
      if(isset($product['attributes'])){
        $instructions = $product['attributes'];
      }
      if(isset($product['instructions'])){
        $instructions .= "-" .$product['instructions'];
      }
      $product_name =  $product['name'];
      $this->checkoutcart[] = array(
        'ArticleNumber' => $product_reference,
        'ArticleName' => $product_name,
        'Description' => $instructions,
        'Quantity' => $product['cart_quantity'] * 100,
        'Price' => Tools::ps_round($price,_PS_PRICE_COMPUTE_PRECISION_)*100,
        'VAT' => (int) ($product['rate']) * 100,
      );
    }
  }

  protected function getShipping(){
    $shipping_cost_with_tax = $this->context->cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
    $shipping_cost_without_tax = $this->context->cart->getOrderTotal(false, Cart::ONLY_SHIPPING);
    if ($shipping_cost_without_tax > 0) {
      $shipping_tax_rate = ($shipping_cost_with_tax / $shipping_cost_without_tax) - 1;
      $this->checkoutcart[] = array(
        'ArticleNumber' => 1000002,
        'ArticleName' => 'Frakt',
        'Description' => 'frakt',
        'Quantity' => 100,
        'Price' => Tools::ps_round($shipping_cost_with_tax,_PS_PRICE_COMPUTE_PRECISION_)*100,
        'VAT' => (int) ($shipping_tax_rate * 10000),
      );
    }
  }

  protected function getGift(){
    if ($this->context->cart->gift == 1) {
      $cart_wrapping = $this->context->cart->getOrderTotal(true, Cart::ONLY_WRAPPING);
      if ($cart_wrapping > 0) {
        $wrapping_cost_excl = $this->context->cart->getOrderTotal(false, Cart::ONLY_WRAPPING);
        $wrapping_cost_incl = $this->context->cart->getOrderTotal(true, Cart::ONLY_WRAPPING);
        $wrapping_vat = (($wrapping_cost_incl / $wrapping_cost_excl) - 1) ;
        $this->checkoutcart[] = array(
          'ArticleNumber' => 1000003,
          'ArticleName' => 'Inslagning',
          'Description' => 'inslagning',
          'Quantity' => 100,
          'Price' => ($cart_wrapping * 100),
          'VAT' => (int) ($wrapping_vat * 10000),
        );
      }
    }
  }

  protected function getDiscount(){
    $totalDiscounts = $this->context->cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
    $totalCartValue = $this->context->cart->getOrderTotal(true, Cart::BOTH);
    if ($totalDiscounts > 0) {
      if ($totalDiscounts > $totalCartValue) {
        //Free order
        $totalCartValue = $this->context->cart->getOrderTotal(true, Cart::BOTH);
        $totalCartValue_tax_excl = $this->context->cart->getOrderTotal(false, Cart::BOTH);
        $common_tax_rate = (($totalCartValue / $totalCartValue_tax_excl) - 1) * 100;
        $common_tax_value = ($totalCartValue - $totalCartValue_tax_excl);
        $this->checkoutcart[] = array(
          'ArticleNumber' => 1000004,
          'ArticleName' => 'Rabatt',
          'Description' => 'Rabatt',
          'Quantity' => 100,
          'Price' => -($totalCartValue * 100),
          'VAT' => '2500',
        );
      }
      else {
        $totalDiscounts_tax_excl = $this->context->cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS);
        $common_tax_rate = (($totalDiscounts / $totalDiscounts_tax_excl) - 1) * 100;
        $common_tax_rate = Tools::ps_round($common_tax_rate, 0);
        $common_tax_value = ($totalDiscounts - $totalDiscounts_tax_excl);
        $totalCartValue -= $this->context->cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
        $this->checkoutcart[] = array(
          'ArticleNumber' => 1000004,
          'ArticleName' => 'Rabatt',
          'Description' => 'Rabatt',
          'quantity' => 100,
          'Price' => -number_format(($totalDiscounts * 100), _PS_PRICE_COMPUTE_PRECISION_, '.', ''),
          'VAT' => '2500',
        );
      }
    }
  }

  protected function getTotalCartValue(){
    return $this->context->cart->getOrderTotal(true, Cart::BOTH)*10000;
  }

  protected function getEID(){
    $this->eid = strtoupper(Configuration::get('HCO_SWEDEN_EID'));
  }

  protected function getSharedSecret(){
    $this->secret = strtoupper(Configuration::get('HCO_SWEDEN_SECRET'));
  }

  protected function getTermsPage(){
    return Configuration::get('HCO_SWEDEN_TERMS');
  }

  protected function getBaseURI(){
    if ((int) (Configuration::get('HCO_TESTMODE')) == 1) {
      $this->baseUri = 'https://sandbox.hygglig.com/Checkout/api/Checkout/';
    } else {
      $this->baseUri = 'https://www.hygglig.com/Checkout/api/Checkout/';
    }
  }

  protected function createNew(){
    global $cookie;
    $callbackPage = $this->context->link->getModuleLink('ps_hyggligpayment', 'thankyou');
    $checkout = $this->context->link->getModuleLink('ps_hyggligpayment', 'checkout');
    $this->getSharedSecret();
    $this->getEID();
    $startCheckoutCheckSum = (SHA1($this->getTotalCartValue(). $this->secret));
    $this->hygglig['MerchantKey'] = $this->eid;
    $this->hygglig['Checksum'] = $startCheckoutCheckSum;
    $this->hygglig['SuccessURL'] = $callbackPage;
    $this->hygglig['CheckoutURL'] = $checkout;
    $this->hygglig['PushNotificationURL'] = $checkout;
    $this->hygglig['TermsURL'] = $this->getTermsPage();
    $this->hygglig['OrderReference'] = (int) ($this->context->cart->id);
    $this->hygglig['Currency'] = 'SEK';
    $customer = new Customer((int)$cookie->id_customer);
    $customerEmail = $customer->email;
    $postcode = $customer->getAddresses((int)$cookie->id_lang)[0]['postcode'];
    if(isset($customerEmail) && isset($postcode) ){
      $this->hygglig['Email'] = $customer->email;
      $this->hygglig['Postcode'] = $postcode;
    }
    foreach ($this->checkoutcart as $item) {
      $this->hygglig['Articles'][] = $item;
    }
  }

  protected function createIframe(){
    $this->iframeData['MerchantKey'] = strtoupper($this->eid);
    $this->iframeData['Checksum'] = SHA1(strtoupper($this->token.$this->secret));
    $this->iframeData['Token'] = $this->token;
  }

  protected function createUpdate(){
    $this->update['MerchantKey'] = $this->eid;
    $this->update['Checksum'] = strtoupper(SHA1($this->getTotalCartValue() . $this->secret));
    $this->update['Token'] = strtoupper(Tools::getValue('token'));
    foreach ($this->checkoutcart as $item) {
      $this->update['Articles'][] = $item;
    }
  }

  protected function doCurl($api, $data){
    $ch = curl_init($this->baseUri . $api);
    curl_setopt_array($ch, array(
      CURLOPT_POST => TRUE,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
      ),
      CURLOPT_POSTFIELDS => json_encode($data) ////////
    ));
    $this->response = curl_exec($ch);
    curl_close($ch);
  }

  protected function getTokenFromResponse(){
    try{
      $nResponse = json_decode($this->response, true);
      $this->token = strtoupper($nResponse['Token']);
    }
    catch(Exception $e){
      echo $nResponse['Error'];
      die();
      return false;
    }
  }

  protected function getHtmlFromResponse(){
    try{
      $nResponse = json_decode($this->response, true);
      $this->html = $nResponse['HtmlText'];
    }
    catch(Exception $e){
      echo $nResponse['Error'];
      echo "ERROR";
      die();
      return false;
    }
  }

  protected function assignSummaryInformations(){
    $this->context->cart->getSummaryDetails();
    $wrapping_fees_tax_inc = $this->context->cart->getGiftWrappingPrice(true);
    $this->context->smarty->assign('discounts', $this->context->cart->getCartRules());
    $this->context->smarty->assign('cart_is_empty', false);
    $this->context->smarty->assign('gift', $this->context->cart->gift);
    $this->context->smarty->assign('gift_message', $this->context->cart->gift_message);
    $this->context->smarty->assign('giftAllowed', (int) (Configuration::get('PS_GIFT_WRAPPING')));
    $this->context->smarty->assign(
      'gift_wrapping_price',
      Tools::convertPrice(
        $wrapping_fees_tax_inc,
        new Currency($this->context->cart->id_currency)
        )
      );
      $this->context->smarty->assign(
        'message',
        Message::getMessageByCartId((int) ($this->context->cart->id))
      );
      $this->free_shipping = false;
      foreach ($this->context->cart->getCartRules() as $rule) {
        if ($rule['free_shipping']) {
          $this->free_shipping = true;
          break;
        }
      }
      $free_fees_price = 0;
      $configuration = Configuration::getMultiple(
        array(
          'PS_SHIPPING_FREE_PRICE',
          'PS_SHIPPING_FREE_WEIGHT'
        )
      );
      if (isset($configuration['PS_SHIPPING_FREE_PRICE']) &&
      $configuration['PS_SHIPPING_FREE_PRICE'] > 0) {
        $free_fees_price = Tools::convertPrice(
          (float) $configuration['PS_SHIPPING_FREE_PRICE'],
          Currency::getCurrencyInstance((int) $this->context->cart->id_currency)
        );
        $orderTotalwithDiscounts = $this->context->cart->getOrderTotal(
          true,
          Cart::BOTH_WITHOUT_SHIPPING,
          null,
          null,
          false
        );
        $left_to_get_free_shipping = $free_fees_price - $orderTotalwithDiscounts;
        $this->context->smarty->assign('left_to_get_free_shipping', str_replace(".",",",$left_to_get_free_shipping));
      }
      if (isset($configuration['PS_SHIPPING_FREE_WEIGHT']) &&
      $configuration['PS_SHIPPING_FREE_WEIGHT'] > 0) {
        $free_fees_weight = $configuration['PS_SHIPPING_FREE_WEIGHT'];
        $total_weight = $this->context->cart->getTotalWeight();
        $left_to_get_free_shipping_weight = $free_fees_weight - $total_weight;
        $this->context->smarty->assign(
          'left_to_get_free_shipping_weight',
          $left_to_get_free_shipping_weight
        );
      }
      $no_active_countries = 0;
      $show_sweden = true;
      $summary = $this->context->cart->getSummaryDetails();
      $customizedDatas = Product::getAllCustomizedDatas($this->context->cart->id);
      if ($customizedDatas) {
        foreach ($summary['products'] as &$productUpdate) {
          if (isset($productUpdate['id_product'])) {
            $productId = (int) $productUpdate['id_product'];
          } else {
            $productId = (int) $productUpdate['product_id'];
          }
          if (isset($productUpdate['id_product_attribute'])) {
            $productAttributeId = (int) $productUpdate['id_product_attribute'];
          } else {
            $productAttributeId = (int) $productUpdate['product_attribute_id'];
          }
          if (isset($customizedDatas[$productId][$productAttributeId])) {
            $productUpdate['tax_rate'] = Tax::getProductTaxRate(
              $productId,
              $this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')}
            );
          }
        }
        Product::addCustomizationPrice($summary['products'], $customizedDatas);
      }
      $cart_product_context = Context::getContext()->cloneContext();
      foreach ($summary['products'] as $key => &$product) {
        $product['quantity'] = $product['cart_quantity'];// for compatibility with 1.2 themes

        if ($cart_product_context->shop->id != $product['id_shop']) {
          $cart_product_context->shop = new Shop((int) $product['id_shop']);
        }
        $specific_price_output = null;
        $product['price_without_specific_price'] = Product::getPriceStatic(
          $product['id_product'],
          !Product::getTaxCalculationMethod(),
          $product['id_product_attribute'],
          2,
          null,
          false,
          false,
          1,
          false,
          null,
          null,
          null,
          $specific_price_output,
          true,
          true,
          $cart_product_context
        );
        if (Product::getTaxCalculationMethod()) {
          $product['is_discounted'] = $product['price_without_specific_price'] != $product['price'];
        } else {
          $product['is_discounted'] = $product['price_without_specific_price'] != $product['price_wt'];
        }
      }
      $available_cart_rules = CartRule::getCustomerCartRules(
        $this->context->language->id,
        (isset($this->context->customer->id) ? $this->context->customer->id : 0),
        true,
        true,
        true,
        $this->context->cart
      );
      $cart_cart_rules = $this->context->cart->getCartRules();
      foreach ($available_cart_rules as $key => $available_cart_rule) {
        if (!$available_cart_rule['highlight'] || strpos($available_cart_rule['code'], 'BO_ORDER_') === 0) {
          unset($available_cart_rules[$key]);
          continue;
        }
        foreach ($cart_cart_rules as $cart_cart_rule) {
          if ($available_cart_rule['id_cart_rule'] == $cart_cart_rule['id_cart_rule']) {
            unset($available_cart_rules[$key]);
            continue 2;
          }
        }
      }
      $id_country = Country::getByIso('se');
      if ($id_country > 0) {
        $delivery_option_list = $this->context->cart->getDeliveryOptionList(
          new Country($id_country),
          true
        );
      } else {
        $delivery_option_list = $this->context->cart->getDeliveryOptionList();
      }
      $delivery_option = $this->context->cart->getDeliveryOption( new Country($id_country),false,false );
      global $cookie;
      $show_option_allow_separate_package = (!$this->context->cart->isAllProductsInStock(true) &&
      Configuration::get('PS_SHIP_WHEN_AVAILABLE'));
      $this->context->smarty->assign($summary);
      $this->context->smarty->assign(array(
        'controllername' => 'checkout',
        'cookie' => $cookie,
        'delivery_option' => $delivery_option,
        'delivery_option_list' => $delivery_option_list,
        'token_cart' => Tools::getToken(false),
        'isVirtualCart' => $this->context->cart->isVirtualCart(),
        'productNumber' => $this->context->cart->nbProducts(),
        'voucherAllowed' => CartRule::isFeatureActive(),
        'shippingCost' => $this->context->cart->getOrderTotal(true, Cart::ONLY_SHIPPING),
        'shippingCostTaxExc' => $this->context->cart->getOrderTotal(false, Cart::ONLY_SHIPPING),
        'customizedDatas' => $customizedDatas,
        'CUSTOMIZE_FILE' => Product::CUSTOMIZE_FILE,
        'CUSTOMIZE_TEXTFIELD' => Product::CUSTOMIZE_TEXTFIELD,
        'lastProductAdded' => $this->context->cart->getLastProduct(),
        'displayVouchers' => $available_cart_rules,
        'advanced_payment_api' => true,
        'currencySign' => $this->context->currency->sign,
        'currencyRate' => $this->context->currency->conversion_rate,
        'currencyFormat' => $this->context->currency->format,
        'currencyBlank' => $this->context->currency->blank,
        'show_option_allow_separate_package' => $show_option_allow_separate_package,
      ));
      $this->context->smarty->assign(array(
        'hookDisplayBeforeCarrier' => Hook::exec('DisplayBeforeCarrier', $summary),
        'hookDisplayAfterCarrier' => Hook::exec('DisplayAfterCarrier', $summary),
      ));
    }

    protected function pushreader(){
      $this->getEID();
      $this->getSharedSecret();
      $this->push = $_POST;
      $this->pushCheck = strtoupper(sha1($this->push['orderReference'].strtolower($this->secret)));
    }

    protected function createCartObj(){
      $this->getCart();
      $this->getShipping();
      $this->getGift();
      $this->getDiscount();
    }

    public function initContent(){
      parent::initContent();
      if (!isset($this->context->cart->id)) {
        Tools::redirect('index.php');
      }
      $currency = new Currency($this->context->cart->id_currency);
      if ($currency->iso_code != "SEK") {
        Tools::redirect('order');
      }
      if (isset($this->context->cart) and $this->context->cart->nbProducts() > 0) {
        if (!$this->context->cart->checkQuantities()) {
          Tools::redirect('index');
        } else {
          $minimal_purchase = Tools::convertPrice((float)Configuration::get('PS_PURCHASE_MINIMUM'), $currency);
          if ($this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS) < $minimal_purchase) {
            Tools::redirect('index');
          }
          session_start();
          try {
            $this->createCartObj();
            $this->createNew();
            $this->getBaseURI();
            $this->doCurl('StartCheckout',$this->hygglig);
            $this->getTokenFromResponse();
            $this->createIframe();
            $this->doCurl('GetIFrame',$this->iframeData);
            $this->getHtmlFromResponse();
            $this->assignSummaryInformations();
            $this->hyggligCart = $this->cart_presenter->present($this->context->cart);
            $this->context->smarty->assign(array(
              'hygglig_checkout' => $this->html,
              'hygglig_token' => $this->token,
              'free_shipping' => $this->free_shipping,
              'HCO_SHOWLINK' => (int) Configuration::get('HCO_SHOWLINK'),
              'hcourl' => $this->hygglig['CheckoutURL'],
              'cart' => $this->hyggligCart,
            ));	                }
            catch (Exception $e) {
              $this->context->smarty->assign('hygglig_error', $e->getMessage());
            }
          }
        } else {
          $this->context->smarty->assign('hygglig_error', 'empty_cart');
        }
        $this->setTemplate('module:ps_hyggligpayment/views/templates/front/hco_twocolumns.tpl');
      }

      public function postProcess(){
        if (Tools::getIsset('do_update_call')) {
          $this->getEID();
          $this->getSharedSecret();
          $this->createCartObj();
          $this->createUpdate();
          $this->getBaseURI();
          $this->doCurl('UpdateOrder',$this->update);
          exit();
        }
        if (Tools::getIsset('pushCheckSum')) {
          $this->pushreader();
          if($this->push['pushCheckSum'] == $this->pushCheck){
            $cart = new Cart($this->push['orderReference']);
            if(!$cart->OrderExists()){
              Context::getContext()->currency = new Currency((int) $cart->id_currency);
              $id_customer = (int) (Customer::customerExists($this->push['email'], true, true));
              $delivery_address_id = null;
              $invoice_address_id = null;
              $shipping_country_id = Country::getByIso('SE');
              $invoice_country_id = Country::getByIso('SE');
              if ($id_customer > 0) {
                $customer = new Customer($id_customer);
                foreach ($customer->getAddresses($cart->id_lang) as $address) {
                  if ($address['firstname'] 		== $this->push['firstName']
                  && $address['lastname'] 		== $this->push['lastName']
                  && $address['city'] 			== $this->push['city']
                  && $address['address1'] 		== $this->push['address']
                  && $address['postcode'] 		== $this->push['postalCode']
                  && $address['phone_mobile'] 	== $this->push['phoneNumber']
                  && $address['id_country'] 		== $shipping_country_id) {
                    $cart->id_address_delivery 	= $address['id_address'];
                    $delivery_address_id 		= $address['id_address'];
                  }
                  if ($address['firstname'] 		== $this->push['firstName']
                  && $address['lastname'] 		== $this->push['lastName']
                  && $address['city'] 			== $this->push['city']
                  && $address['address1'] 		== $this->push['address']
                  && $address['postcode'] 		== $this->push['postalCode']
                  && $address['phone_mobile'] 	== $this->push['phoneNumber']
                  && $address['id_country'] 		== $invoice_country_id) {
                    $cart->id_address_invoice 	= $address['id_address'];
                    $invoice_address_id 		= $address['id_address'];
                  }
                }
              }
              else {
                $password = Tools::passwdGen(8);
                $customer = new Customer();
                $customer->firstname = $this->push['firstName'];
                $customer->lastname = $this->push['lastName'];
                $customer->email = $this->push['email'];
                $customer->passwd = Tools::encrypt($password);
                $customer->is_guest = 0;
                $customer->id_default_group = (int) (Configuration::get('PS_CUSTOMER_GROUP', null, $cart->id_shop));
                $customer->newsletter = 0;
                $customer->optin = 0;
                $customer->active = 1;
                $customer->id_gender = 9;
                $customer->add();
              }
              if ($invoice_address_id == null) {
                $address = new Address();
                $address->firstname = $this->push['firstName'];
                $address->lastname = $this->push['lastName'];
                $address->address1 = $this->push['address'];
                $address->postcode = $this->push['postalCode'];
                $address->phone = $this->push['phoneNumber'];
                $address->phone_mobile = $this->push['phoneNumber'];
                $address->city = $this->push['city'];
                $address->id_country = $invoice_country_id;
                $address->id_customer = $customer->id;
                $address->alias = 'Adress från Hygglig';
                $address->add();
                $cart->id_address_invoice = $address->id;
                $invoice_address_id = $address->id;
              }
              if ($delivery_address_id == null) {
                $cart->id_address_delivery = $address->id;
                $delivery_address_id = $address->id;
              }
              $new_delivery_options = array();
              $new_delivery_options[(int) ($delivery_address_id)] = $cart->id_carrier.',';
              $new_delivery_options_serialized = serialize($new_delivery_options);
              $update_sql = 'UPDATE '._DB_PREFIX_.'cart '.
              'SET delivery_option=\''.$new_delivery_options_serialized.
              '\' WHERE id_cart='.(int) $cart->id;
              Db::getInstance()->execute($update_sql);
              if ($cart->id_carrier > 0) {
                $cart->delivery_option = $new_delivery_options_serialized;
              } else {
                $cart->delivery_option = '';
              }
              $update_sql = 'UPDATE '._DB_PREFIX_.'cart_product '.
              'SET id_address_delivery='.(int) $delivery_address_id.
              ' WHERE id_cart='.(int) $cart->id;
              Db::getInstance()->execute($update_sql);
              $update_sql = 'UPDATE '._DB_PREFIX_.'customization '.
              'SET id_address_delivery='.(int) $delivery_address_id.
              ' WHERE id_cart='.(int) $cart->id;
              Db::getInstance()->execute($update_sql);
              $cart->getPackageList(true);
              $cart->getDeliveryOptionList(null, true);
              $cart->id_customer = $customer->id;
              $cart->secure_key = $customer->secure_key;
              $cart->save();
              $update_sql = 'UPDATE '._DB_PREFIX_.'cart '.
              'SET id_customer='.(int) $customer->id.
              ', secure_key=\''.pSQL($customer->secure_key).
              '\' WHERE id_cart='.(int) $cart->id;
              Db::getInstance()->execute($update_sql);
              $extra = array();
              $extra['transaction_id'] = $this->push['orderNumber'];
              $cache_id = 'objectmodel_cart_'.$cart->id.'*';
              Cache::clean($cache_id);
              $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
              $this->module->validateOrder(
                (int)$cart->id,
                Configuration::get('PS_OS_PAYMENT'),
                $total,
                $this->module->displayName,
                null,
                $extra,
                $cart->id_currency,
                false,
                $cart->secure_key
              );
            }
          }
          echo "Push done";
          exit;
        }
      }
    }
