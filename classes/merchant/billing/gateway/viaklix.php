<?php

class Merchant_Billing_Gateway_Viaklix extends Merchant_Billing_Gateway {
    protected $test_url = 'https://demo.viaklix.com/process.asp';
    protected $live_url = 'https://www.viaklix.com/process.asp';
    protected $delimiter = "\r\n";

    protected $actions = array(
'purchase' => 'SALE',
 'credit' => 'CREDIT'
    );

    const APPROVED = '0';

    public static $supported_cardtypes = array('visa', 'master', 'american_express');
    public static $supported_countries = array('US');
    public static $display_name = 'ViaKLIX';
    public static $homepage_url = 'http://viaklix.com';
    
    protected $form = array();
    protected $options = array();

    public function __construct($options = array())
    {
        $this->required_options('login, password', $options);

        $this->options = $options;
    }

    // Make a purchase  

    public function purchase($money, Merchant_Billing_CreditCard $creditcard, $options = array())
    {
        $this->form = array();
        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->add_customer_data($options);
        return $this->commit('purchase', $money);
    }

    // Make a credit to a card (Void can only be done from the virtual terminal)
    // Viaklix does not support credits by reference. You must pass in the credit card
    public function credit($money, Merchant_Billing_CreditCard $creditcard, $options = array())
    {
        $this->form = array();
        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->add_customer_data($options);
        return $this->commit('credit', $money);
    }
    
    public function is_test()
    {
        return isset($this->options['test']) AND $this->options['test'];
    }

    protected function add_customer_data($options)
    {
        $this->form['email'] = $this->get_value($options, 'email', 100);
        $this->form['customer_code'] = $this->get_value($options, 'customer', 10);
    }

    protected function add_invoice($options)
    {
        if (isset($options['order_id']))
        {
            $this->form['invoice_number'] = $options['order_id'];
        }
        else
        {
            $this->form['invoice_number'] = $this->get_value($options, 'invioce', 10);
        }
        $this->form['description'] = $this->get_value($options, 'description', 255);
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
            $this->form['ship_to_address'] = $this->get_value($shipping_address, 'address1', 30);
            $this->form['ship_to_city'] = $this->get_value($shipping_address, 'city', 30);
            $this->form['ship_to_state'] = $this->get_value($shipping_address, 'state', 10);
            $this->form['ship_to_company'] = $this->get_value($shipping_address, 'company', 50);
            $this->form['ship_to_country'] = $this->get_value($shipping_address, 'country', 50);
            $this->form['ship_to_zip'] = $this->get_value($shipping_address, 'zip', 10);
        }
    }

    protected function parse_first_and_last_name($value)
    {
        $name = explode(' ', $value);

        $last_name = array_pop($name);
        $first_name = implode(' ', $name);
        return array(
            $first_name,
            $last_name
        );
    }

    protected function add_creditcard(Merchant_Billing_CreditCard $creditcard)
    {
        $this->form['card_number'] = $creditcard->number;
        $this->form['exp_date'] = $this->expdate($creditcard);

        if ($creditcard->require_verification_value)
        {
            $this->add_verification_value($creditcard);
        }
        
        // add card not present by default
        $this->form['card_present']='N';

        $this->form['first_name'] = strlen($creditcard->first_name) > 20 ? substr($creditcard->first_name, 0, 20) : $creditcard->first_name;
        $this->form['last_name'] = strlen($creditcard->last_name) > 30 ? substr($creditcard->last_name, 0, 30) : $creditcard->last_name;
    }
    
    protected function add_verification_value(Merchant_Billing_CreditCard $creditcard)
    {
        $this->form['cvv2cvc2'] = $creditcard->verification_value; 
        $this->form['cvv2'] = 'present';
    }
    
    protected function preamble()
    {
        $result = array(
            'merchant_id'=>$this->options['login'],
            'pin'=>  $this->options['password'],
            'show_form'=>'false',
            'test_mode'=>$this->is_test()?'TRUE':'FALSE',
            'result_format'=>'ASCII'
        );
        
        if (isset($this->options['user']) AND !empty($this->options['user']))
        {
            $result['user_id'] = $this->options['user'];
        }
        
        return $result;
    }
    
    protected function commit($action, $money, $parameters = array())
    {
        $parameters['amount'] = $this->amount($money);
        $parameters['transaction_type'] = $this->actions[$action];
        
        $url = $this->is_test() ? $this->test_url : $this->live_url;
        
        $response = $this->parse($this->ssl_post($url, $this->post_data($parameters)));
        
        return new Merchant_Billing_Response(
                Arr::get($response,'result') == self::APPROVED,
                $this->message_from($response),
                $response,
                array(
                    'test'=>$this->is_test(),
                    'authorization'=>Arr::get($response,'approval_code'),
                    'transaction_id'=>Arr::get($response,'txn_id'),
                    'avs_result'=>array('code'=>Arr::get($response,'avs_response')),
                    'cvv_result'=>Arr::get($response,'cvv2_response'),
                    'processor'=>self::$display_name
                )
        );
    }
    
    protected function message_from($response)
    {
        return Arr::get($response,'result_message');
    }
    
    protected function post_data($params)
    {
        $result = $this->preamble();
        $result = array_merge($result, $params);
        $result = array_merge($result, $this->form);
        
        // prepend ssl_ to all array keys
        $data = array();
        foreach ($result as $key=>$value)
        {
            $data["ssl_$key"]=$value;
        }
        
        return $this->urlize($data);
    }
    
    protected function expdate(Merchant_Billing_CreditCard $creditcard)
    {
        $year = $this->cc_format($creditcard->year, 'two_digits');
        $month = $this->cc_format($creditcard->month, 'two_digits');
        return $month . $year;
    }
    
    protected function parse($msg)
    {
        $resp = array();
        
        foreach (explode($this->delimiter, $msg) as $pair)
        {
            list($key,$value) = explode('=',$pair);
            $key = str_replace('ssl_', '', $key);
            $resp[$key] = trim($value);
        }
        
        return $resp;
    }

    protected function get_value($arr, $key, $limit = FALSE)
    {
        if (!isset($arr[$key]))
            return NULL;
        else
        {
            if ($limit !== FALSE)
            {
                return strlen($arr[$key]) > $limit ? substr($arr[$key], 0, $limit) : $arr[$key];
            }
            else
            {
                return $arr[$key];
            }
        }
    }

}
