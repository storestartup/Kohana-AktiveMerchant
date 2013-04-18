<?php

class Merchant_Billing_Gateway_Chargeitpro extends Merchant_Billing_Gateway
{

    //private static $wsdl = 'https://secure.chargeitpro.com/soap/gate/0AE595C1/chargeitpro.wsdl';
    
    private static $wsdl = 'https://sandbox.chargeitpro.com/soap/gate/0AE595C1/chargeitpro.wsdl';
    
    private $client;
    private $token;

    public function __construct($options)
    {
        $this->required_options('sourcekey,pin', $options);

        $this->client = new SoapClient(self::$wsdl);
        // build security token
        // generate random seed value
        $seed = time() . rand();

        // make hash value using sha1 function
        $hash = sha1($options['sourcekey'] . $seed . $options['pin']);

        // assembly ueSecurityToken as an array
        $this->token = array(
            'SourceKey' => $options['sourcekey'],
            'PinHash' => array(
                'Type' => 'sha1',
                'Seed' => $seed,
                'HashValue' => $hash
            ),
            'ClientIP' => $_SERVER['REMOTE_ADDR'],
        );
    }

    /**
     *
     * @param number                      $money
     * @param Merchant_Billing_CreditCard $creditcard
     * @param array                       $options
     *
     * @return Merchant_Billing_Response
     */
    public function purchase($money, Merchant_Billing_CreditCard $creditcard, $options = array())
    {
        $request = array(
            'command' => 'sale',
            'AccountHolder' => trim($creditcard->first_name . ' ' . $creditcard->last_name),
            'Details' => array(
                'Description' => 'Example Transaction',
                'Amount' => $money,
                'Invoice' => '44539'
            ),
            'CreditCardData' => array(
                'CardNumber' => $creditcard->number,
                'CardExpiration' => $creditcard->month . $creditcard->year,
                'AvsStreet' => '1234 Main Street',
                'AvsZip' => '99281',
                'CardCode' => $creditcard->verification_value
            )
        );

        try
        {
            Kohana::$log->add(Log::DEBUG, 'Posting to CIP: '.Debug::dump($request));
            $response = $this->client->runTransaction($this->token, $request);
            Kohana::$log->add(Log::DEBUG, 'Response from CIP: '.Debug::dump($response));
            if ($response->ResultCode == 'A')
            {
                return new Merchant_Billing_Response(
                                TRUE,
                                'Card successfully charged',
                                $response,
                                array(
                                    'test' => FALSE,
                                    'authorization' => $response->AuthCode,
                                    'transaction_id' => $response->RefNum,
                                    'fraud_review' => FALSE,
                                    'avs_result' => $response->AvsResultCode,
                                    'cvv_result' => $response->CardCodeResultCode,
                                    'processor' => 'Chargeitpro'
                                )
                );
            }
            else
            {
                return new Merchant_Billing_Response(
                                FALSE,
                                $response->Result,
                                $response
                );
            }
        }
        catch (SoapFault $ex)
        {
            Kohana::$log->add(Log::ERROR,$ex);
            Kohana::$log->add(Log::ERROR,  $this->client->__getLastRequest());
            Kohana::$log->add(Log::ERROR,$this->client->__getLastResponse());
            // soap error
            return new Merchant_Billing_Response(FALSE, $ex->getMessage());
        }
    }

    /**
     *
     * @param string $authorization
     * @param array  $options
     *
     * @return Merchant_Billing_Response
     */
    public function void($authorization, $options = array())
    {
        $this->post = array('trans_id' => $authorization);
        return $this->commit('VOID', null);
    }

    /**
     *
     * @param number $money
     * @param string $identification
     * @param array  $options
     *
     * @return Merchant_Billing_Response
     */
    public function credit($money, $identification, $options = array())
    {
        $this->required_options('card_number', $options);
        $this->post = array(
            'trans_id' => $identification,
            'card_num' => $options['card_number']
        );


        $this->add_invoice($options);
        return $this->commit('CREDIT', $money);
    }

    private function add_invoice($options)
    {
        $this->post['invoice_num'] = isset($options['order_id']) ? $options['order_id'] : null;
        $this->post['description'] = isset($options['description']) ? $options['description'] : null;
    }

    private function add_creditcard(Merchant_Billing_CreditCard $creditcard)
    {
        $this->data['card'] = array(
            'number' => $creditcard->number,
            'exp_month' => $creditcard->month,
            'exp_year' => $creditcard->year,
            'cvc' => $creditcard->verification_value,
            'name' => trim($creditcard->first_name . ' ' . $creditcard->last_name)
        );
    }

    private function add_address($options)
    {
        $address = isset($options['billing_address']) ? $options['billing_address'] : $options['address'];
        $this->post['address'] = isset($address['address']) ? $address['address'] : null;
        $this->post['company'] = isset($address['company']) ? $address['company'] : null;
        $this->post['phone'] = isset($address['phone']) ? $address['phone'] : null;
        $this->post['zip'] = isset($address['zip']) ? $address['zip'] : null;
        $this->post['city'] = isset($address['city']) ? $address['city'] : null;
        $this->post['country'] = isset($address['country']) ? $address['country'] : null;
        $this->post['state'] = isset($address['state']) ? $address['state'] : 'n/a';
    }

    private function add_customer_data($options)
    {
        if (isset($options['email']))
        {
            $this->post['email'] = isset($options['email']) ? $options['email'] : null;
            $this->post['email_customer'] = false;
        }

        if (isset($options['customer']))
        {
            $this->post['cust_id'] = $options['customer'];
        }

        if (isset($options['ip']))
        {
            $this->post['customer_ip'] = $options['ip'];
        }
    }

    private function add_duplicate_window()
    {
        if ($this->duplicate_window != null)
        {
            $this->post['duplicate_window'] = $this->duplicate_window;
        }
    }

}
