<?php

class Merchant_Billing_Gateway_Elavon extends Merchant_Billing_Gateway_Viaklix
{
    protected $test_url = 'https://demo.myvirtualmerchant.com/VirtualMerchantDemo/process.do';
    protected $live_url = 'https://www.myvirtualmerchant.com/VirtualMerchant/process.do';
    
    protected $delimiter = "\n";
    
    protected $actions = array(
        'purchase' => 'CCSALE',
        'credit' => 'CCCREDIT',
        'authorize' => 'CCAUTHONLY',
        'capture' => 'CCFORCE'
    );
    
    public static $supported_cardtypes = array('visa', 'master', 'american_express', 'discover');
    public static $supported_countries = array('US', 'CA');
    public static $display_name = 'Elavon MyVirtualMerchant';
    public static $homepage_url = 'http://www.elavon.com/';
    
    public function authorize($money, Merchant_Billing_CreditCard $creditcard, $options = array())
    {
        $this->form = array();
        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        $this->add_customer_data($options);
        return $this->commit('authorize', $money);
    }
    
    public function capture($money, $authorization, $options = array())
    {
        $this->required_options('credit_card', $options);
        
        $this->form = array();
        $this->add_reference($authorization);
        $this->add_invoice($options);
        $this->add_creditcard($options['credit_card']);
        $this->add_customer_data($options);
        return $this->commit('capture', $money);
    }
    
    private function add_reference($authorization)
    {
        $this->form['approval_code'] = $authorization;
    }
    
    protected function authorization_from($response)
    {
        return $response['approval_code'];
    }
    
    protected function add_verification_value(Merchant_Billing_CreditCard $creditcard)
    {
        $this->form['cvv2cvc2'] = $creditcard->verification_value; 
        $this->form['cvv2cvc2_indicator'] = '1';
    }
    
    protected function add_address($options)
    {
        $billing_address = isset($options['billing_address']) ? $options['billing_address'] : $options['address'];

        $this->form['avs_address'] = $this->get_value($billing_address, 'address1', 30);
        $this->form['address2'] = $this->get_value($billing_address, 'address2', 30);
        $this->form['avs_zip'] = $this->get_value($billing_address, 'zip', 10);
        $this->form['city'] = $this->get_value($billing_address, 'city', 30);
        $this->form['state'] = $this->get_value($billing_address, 'state', 10);
        $this->form['company'] = $this->get_value($billing_address, 'company', 50);
        $this->form['phone'] = $this->get_value($billing_address, 'phone', 20);
        $this->form['country'] = $this->get_value($billing_address, 'country', 50);

        // check for shipping address
        if (isset($options['shipping_address']))
        {
            $shipping_address = $options['shipping_address'];
            list($first_name, $last_name) = $this->parse_first_and_last_name($shipping_address['name']);
            $this->form['ship_to_first_name'] = strlen($first_name) > 20 ? substr($first_name, 0, 20) : $first_name;
            $this->form['ship_to_last_name'] = strlen($last_name) > 30 ? substr($last_name, 0, 30) : $last_name;
            $this->form['ship_to_address1'] = $this->get_value($shipping_address, 'address1', 30);
            $this->form['ship_to_address2'] = $this->get_value($shipping_address, 'address2', 30);
            $this->form['ship_to_city'] = $this->get_value($shipping_address, 'city', 30);
            $this->form['ship_to_state'] = $this->get_value($shipping_address, 'state', 10);
            $this->form['ship_to_company'] = $this->get_value($shipping_address, 'company', 50);
            $this->form['ship_to_country'] = $this->get_value($shipping_address, 'country', 50);
            $this->form['ship_to_zip'] = $this->get_value($shipping_address, 'zip', 10);
        }
    }
    
    protected function message_from($response)
    {
        return $this->success($response) ? $response['result_message'] : $response['errorMessage'];
        //success?(response) ? response['result_message'] : response['errorMessage']
    }
    
    protected function success($response)
    {
        return ! isset($response['errorMessage']);
        //!response.has_key?('errorMessage')
    }
    
}
