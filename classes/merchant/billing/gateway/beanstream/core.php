<?php

class Merchant_Billing_Gateway_Beanstream_Core extends Merchant_Billing_Gateway {

    protected $URL = 'https://www.beanstream.com/scripts/process_transaction.asp';
    protected $SECURE_PROFILE_URL = 'https://www.beanstream.com/scripts/payment_profile.asp';
    protected $SP_SERVICE_VERSION = '1.1';
    public static $default_currency = 'USD';
    public static $supported_countries = array('US', 'CA');
    public static $supported_cardtypes = array('visa', 'master', 'american_express');
    public static $homepage_url = 'http://www.beanstream.com/';
    public static $display_name = 'Beanstream.com';
    protected $TRANSACTIONS = array(
        'authorization' => 'PA',
        'purchase' => 'P',
        'capture' => 'PAC',
        'refund' => 'R',
        'void' => 'VP',
        'check_purchase' => 'D',
        'check_refund' => 'C',
        'void_purchase' => 'VP',
        'void_refund' => 'VR'
    );
    protected $PROFILE_OPERATIONS = array(
        'new' => 'N',
        'modify' => 'M'
    );
    protected $CVD_CODES = array(
        '1' => 'M',
        '2' => 'N',
        '3' => 'I',
        '4' => 'S',
        '5' => 'U',
        '6' => 'P'
    );
    protected $AVS_CODES = array(
        '0' => 'R',
        '5' => 'I',
        '9' => 'I'
    );
    protected $post = array();

    function __construct($options = array())
    {
        $this->required_options('login', $options);
        $this->options = $options;
    }

    public function capture($money, $authorization, $options = array())
    {
        list($reference, $amount, $type) = $this->split_auth($authorization);

        $this->post = array();
        $this->add_amount($money);
        $this->add_reference($reference);
        $this->add_transaction_type('capture');
        return $this->commit();
    }

    public function refund($money, $source, $options = array())
    {
        $this->post = array();
        list($reference, $amount, $type) = $this->split_auth($source);
        $this->add_reference($reference);
        $this->add_transaction_type($this->refund_action($type));
        $this->add_amount($money);
        return $this->commit();
    }

    public function credit($money, $source, $options = array())
    {
        //deprecated Gateway::CREDIT_DEPRECATION_MESSAGE
        return $this->refund($money, $source, $options);
    }

    // private functions

    protected function purchase_action($source)
    {
        return ($this->card_brand($source) == "check") ? 'check_purchase' : 'purchase';
    }

    protected function void_action($original_transaction_type)
    {
        return ($original_transaction_type == $this->TRANSACTIONS['refund']) ? 'void_refund' : 'void_purchase';
    }

    protected function refund_action($type)
    {
        return ($type == $this->TRANSACTIONS['check_purchase']) ? 'check_refund' : 'refund';
    }

    protected function secure_profile_action($type)
    {
        return isset($this->PROFILE_OPERATIONS[$type]) ? $this->PROFILE_OPERATIONS[$type] : '';
    }

    protected function split_auth($string)
    {
        return explode(';', $string);
    }

    protected function add_amount($money)
    {
        $this->post['trnAmount'] = $this->amount($money);
    }

    protected function add_original_amount($amount)
    {
        $this->post['trnAmount'] = $amount;
    }

    protected function add_reference($reference)
    {
        $this->post['adjId'] = $reference;
    }

    protected function add_address($options)
    {
        //$this->prepare_address_for_non_american_countries($options);

        $billing_address = isset($options['billing_address']) ? $options['billing_address'] : $options['address'];

        $this->post['ordName'] = Arr::get($billing_address, 'name');
        $this->post['ordEmailAddress'] = Arr::get($options, 'email');
        $this->post['ordPhoneNumber'] = Arr::get($billing_address, 'phone');
        $this->post['ordAddress1'] = Arr::get($billing_address, 'address1');
        $this->post['ordAddress2'] = Arr::get($billing_address, 'address2');
        $this->post['ordCity'] = Arr::get($billing_address, 'city');
        $this->post['ordProvince'] = Arr::get($billing_address, 'state');
        $this->post['ordPostalCode'] = Arr::get($billing_address, 'zip');
        $this->post['ordCountry'] = Arr::get($billing_address, 'country');

        if (isset($options['shipping_address']))
        {
            $shipping_address = $options['shipping_address'];
            $this->post['shipName'] = Arr::get($shipping_address, 'name');
            $this->post['shipEmailAddress'] = Arr::get($options, 'email');
            $this->post['shipPhoneNumber'] = Arr::get($shipping_address, 'phone');
            $this->post['shipAddress1'] = Arr::get($shipping_address, 'address1');
            $this->post['shipAddress2'] = Arr::get($shipping_address, 'address2');
            $this->post['shipCity'] = Arr::get($shipping_address, 'city');
            $this->post['shipProvince'] = Arr::get($shipping_address, 'state');
            $this->post['shipPostalCode'] = Arr::get($shipping_address, 'zip');
            $this->post['shipCountry'] = Arr::get($shipping_address, 'country');
            $this->post['shippingMethod'] = Arr::get($shipping_address, 'shipping_method');
            $this->post['deliveryEstimate'] = Arr::get($shipping_address, 'delivery_estimate');
        }
    }

    protected function prepare_address_for_non_american_countries($options)
    {
        foreach (array($options['billing_address'], $options['shipping_address']) as $address)
        {
            if ($address['country'] != 'US' OR $address['country'] != 'CA')
            {
                $address['state'] = '--';
                if (!isset($address['zip']) OR empty($address['zip']))
                {
                    $address['zip'] = '00000';
                }
            }
        }
    }

    protected function add_invoice($options)
    {
        $this->post['trnOrderNumber'] = Arr::get($options, 'order_id');
        $this->post['trnComments'] = Arr::get($options, 'description');
        $this->post['ordItemPrice'] = $this->amount(Arr::get($options, 'subtotal'));
        $this->post['ordShippingPrice'] = $this->amount(Arr::get($options, 'shipping'));
        $this->post['ordTax1Price'] = $this->amount(isset($options['tax1']) ? $options['tax1'] : Arr::get($options, 'tax'));
        $this->post['ordTax2Price'] = $this->amount(Arr::get($options, 'tax2'));
        $this->post['ref1'] = Arr::get($options, 'custom');
    }

    protected function add_credit_card(Merchant_Billing_CreditCard $credit_card)
    {
        if ($credit_card)
        {
            $this->post['trnCardOwner'] = $credit_card->name();
            $this->post['trnCardNumber'] = $credit_card->number;
            $this->post['trnExpMonth'] = $this->cc_format($credit_card->month, 'two_digits');
            $this->post['trnExpYear'] = $this->cc_format($credit_card->year, 'two_digits');
            $this->post['trnCardCvd'] = $credit_card->verification_value;
        }
    }

    protected function add_check(Merchant_Billing_Check $check)
    {
        # The institution number of the consumer’s financial institution. Required for Canadian dollar EFT transactions.
        $this->post['institutionNumber'] = $check->institution_number;

        # The bank transit number of the consumer’s bank account. Required for Canadian dollar EFT transactions.
        $this->post['transitNumber'] = $check->transit_number;

        # The routing number of the consumer’s bank account.  Required for US dollar EFT transactions.
        $this->post['routingNumber'] = $check->routing_number;

        # The account number of the consumer’s bank account.  Required for both Canadian and US dollar EFT transactions.
        $this->post['accountNumber'] = $check->account_number;
    }

    protected function add_secure_profile_variables($options = array())
    {
        $this->post['serviceVersion'] = $this->SP_SERVICE_VERSION;
        $this->post['responseFormat'] = 'QS';
        $this->post['cardValidation'] = ($options['cardValidation'] == '1') ? '1' : '0';

        $this->post['operationType'] = isset($options['operationType']) ? $options['operationType'] : isset($options['operation']) ? $options['operation'] : $this->secure_profile_action('new');
        $this->post['customerCode'] = isset($options['billing_id']) ? $options['billing_id'] : isset($options['vault_id']) ? $options['vault_id'] : false;
        $this->post['status'] = $options['status'];
    }

    protected function parse($body)
    {
        $results = array();
        if (!empty($body))
        {
            //echo "Parsing raw response [$body]".PHP_EOL;
            foreach (explode('&', $body) as $pair)
            {
                $parts=explode('=',$pair);
                if (count($parts) > 1)
                {
                    list($key, $val) = $parts;
                    $results[$key] = urldecode($val);
                } 
            }
        }

        # Clean up the message text if there is any
        if (isset($results['messageText']))
        {
            //results[:messageText].gsub!(/<LI>/, "")
            //results[:messageText].gsub!(/(\.)?<br>/, ". ")
            //results[:messageText].strip!
        }

        return $results;
    }

    protected function commit($use_profile_api = false)
    {
        return $this->post($this->post_data($this->post, $use_profile_api), $use_profile_api);
    }

    /**
     * Posts the data to the gateway
     * 
     * @param array $data
     * @param mixed $use_profile_api
     * @return Merchant_Billing_Response 
     */
    protected function post($data, $use_profile_api=NULL)
    {
        $url = $use_profile_api ? $this->SECURE_PROFILE_URL : $this->URL;

        $response = $this->parse($this->ssl_post($url, $data));

        if (isset($response['customerCode']))
        {
            $response['customer_vault_id'] = $response['customerCode'];
        }
        return new Merchant_Billing_Response($this->success($response), $this->message_from($response), $response, array(
            'test' => $this->is_test() || Arr::get($response, 'authCode') == "TEST",
            'authorization' => $this->authorization_from($response),
            'transaction_id'=>Arr::get($response,'trnId'),
            'cvv_result' => Arr::get($this->CVD_CODES, Arr::get($response, 'cvdId')),
            'avs_result' => array('code' => (in_array(Arr::get($response, 'avsId'), $this->AVS_CODES) ? $this->AVS_CODES[$response['avsId']] : Arr::get($response, 'avsId'))
            )
                )
        );
    }

    protected function authorization_from($response)
    {
        return sprintf('%s;%s;%s', Arr::get($response,'trnId'), Arr::get($response,'trnAmount'), Arr::get($response,'trnType'));
    }

    protected function message_from($response)
    {
        return isset($response['messageText']) ? $response['messageText'] : Arr::get($response,'responseMessage');
    }

    protected function success($response)
    {
        return Arr::get($response, 'responseType') == 'R' || Arr::get($response, 'trnApproved') == '1' || Arr::get($response, 'responseCode') == '1';
    }

    protected function add_source($source)
    {
        if (is_string($source) OR is_int($source))
        {

            $this->post['customerCode'] = $source;
        }
        else
        {
            $this->card_brand($source) == 'check' ? $this->add_check($source) : $this->add_credit_card($source);
        }
    }

    protected function add_transaction_type($action)
    {
        $this->post['trnType'] = $this->TRANSACTIONS[$action];
    }

    protected function post_data($params, $use_profile_api)
    {
        $params['requestType'] = 'BACKEND';
        if ($use_profile_api)
        {
            $params['merchantId'] = $this->options['login'];
            $params['passCode'] = $this->options['secure_profile_api_key'];
        }
        else
        {
            $params['username'] = isset($this->options['user']) ? $this->options['user'] : NULL;
            $params['password'] = isset($this->options['password']) ? $this->options['password'] : NULL;
            $params['merchant_id'] = $this->options['login'];
        }
        $params['vbvEnabled'] = '0';
        $params['scEnabled'] = '0';

        $data = $this->urlize($params);

        // see if we need to append an md5 hash
        if (!isset($params['password']) AND isset($this->options['hash_key']))
        {
            // calculate the md5 hash
            $hash_value = md5($data . $this->options['hash_key']);
            // append it to the post data
            $data.="&hashValue=$hash_value";
        }

        return $data;
    }

}
