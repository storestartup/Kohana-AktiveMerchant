<?php

class Merchant_Billing_Gateway_Chargeitpro extends Merchant_Billing_Gateway
{

    const LIVE_WSDL = 'https://secure.chargeitpro.com/soap/gate/0AE595C1/chargeitpro.wsdl';
    const TEST_WSDL = 'https://sandbox.chargeitpro.com/soap/gate/0AE595C1/chargeitpro.wsdl';

    private $client;
    private $token;

    public function __construct($options)
    {
        // check for required options (api credentials)
        $this->required_options('sourcekey,pin', $options);

        // determine url to connect to
        $wsdl_url = Arr::get($options, 'test_mode', true) ? self::TEST_WSDL : self::LIVE_WSDL;
        
        Kohana::$log->add(Log::DEBUG,"Chargeitpro gateway initialized with wsdl_url=$wsdl_url");

        // create soap client
        $this->client = new SoapClient($wsdl_url);

        // build security token for the api (based on CIP soap api documentation)
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
        $billing_address=Arr::get($options,'billing_address',array());
        
        $request = array(
            'command' => 'sale',
            'CustomerID'=>Arr::get($options,'customer_id'),
            'ClientIP' => $_SERVER['REMOTE_ADDR'],
            'AccountHolder' => trim($creditcard->first_name . ' ' . $creditcard->last_name),
            'Details' => array(
                'OrderID' => Arr::get($options, 'id'),
                'Amount' => $money,
                'Subtotal' => Arr::get($options, 'subtotal'),
                'Tax' => Arr::get($options, 'tax', 0),
                'Shipping' => Arr::get($options, 'shipping', 0),
                'Discount' => Arr::get($options, 'discount', 0)
            ),
            'CreditCardData' => array(
                'CardNumber' => $creditcard->number,
                'CardExpiration' => $creditcard->month . $creditcard->year,
                'CardCode' => $creditcard->verification_value,
                'AvsStreet' => Arr::get($billing_address,'address1'),
                'AvsZip' => Arr::get($billing_address,'zip')
            )
        );
        
        // billing address
        $request['BillingAddress']=array(
            'Street'=>Arr::get($billing_address,'address1'),
            'City'=>Arr::get($billing_address,'city'),
            'State'=>Arr::get($billing_address,'state'),
            'Zip'=>Arr::get($billing_address,'zip'),
            'Country'=>Arr::get($billing_address,'country')
        );
        // shipping address
        $shipping_address=Arr::get($options,'shipping_address',array());
        $request['ShippingAddress']=array(
            'Street'=>Arr::get($shipping_address,'address1'),
            'City'=>Arr::get($shipping_address,'city'),
            'State'=>Arr::get($shipping_address,'state'),
            'Zip'=>Arr::get($shipping_address,'zip'),
            'Country'=>Arr::get($shipping_address,'country')
        );
        
        // line items
        $request['LineItems']=array();
        foreach (Arr::get($options,'items',array()) as $item)
        {
            $request['LineItems'] []= array(
               'ProductRefNum'=>Arr::get($item,'id'),
                'SKU'=>Arr::get($item,'sku'),
                'ProductName'=>Arr::get($item,'name'),
                'UnitPrice'=>Arr::get($item,'price'),
                'Qty'=>Arr::get($item,'qty'),
                'Taxable'=>Arr::get($item,'taxable',false)
            );
        }

        try
        {
            Kohana::$log->add(Log::DEBUG, 'Posting to CIP: ' . Debug::dump($request));
            $response = $this->client->runTransaction($this->token, $request);
            Kohana::$log->add(Log::DEBUG, 'Response from CIP: ' . Debug::dump($response));
            if ($response->ResultCode == 'A')
            {
                return new Merchant_Billing_Response(
                        TRUE, 'Card successfully charged', $response, array(
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
                        FALSE, $response->Error, $response
                );
            }
        }
        catch (SoapFault $ex)
        {
            Kohana::$log->add(Log::ERROR, $ex);
            Kohana::$log->add(Log::ERROR, $this->client->__getLastRequest());
            Kohana::$log->add(Log::ERROR, $this->client->__getLastResponse());
            // soap error
            return new Merchant_Billing_Response(FALSE, $ex->getMessage());
        }
    }
    
    /**
     *
     * @param number                      $money
     * @param Merchant_Billing_CreditCard $creditcard
     * @param array                       $options
     *
     * @return Merchant_Billing_Response
     */
    public function authorize($money, Merchant_Billing_CreditCard $creditcard, $options = array())
    {
        $request = array(
            'command' => 'authonly',
            'ClientIP' => $_SERVER['REMOTE_ADDR'],
            'AccountHolder' => trim($creditcard->first_name . ' ' . $creditcard->last_name),
            'Details' => array(
                'Amount' => $money
            ),
            'CreditCardData' => array(
                'CardNumber' => $creditcard->number,
                'CardExpiration' => $creditcard->month . $creditcard->year,
                'CardCode' => $creditcard->verification_value
            )
        );

        try
        {
            Kohana::$log->add(Log::DEBUG, 'Posting to CIP: ' . Debug::dump($request));
            $response = $this->client->runTransaction($this->token, $request);
            Kohana::$log->add(Log::DEBUG, 'Response from CIP: ' . Debug::dump($response));
            if ($response->ResultCode == 'A')
            {
                return new Merchant_Billing_Response(
                        TRUE, 'Card successfully charged', $response, array(
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
                        FALSE, $response->Error, $response
                );
            }
        }
        catch (SoapFault $ex)
        {
            Kohana::$log->add(Log::ERROR, $ex);
            Kohana::$log->add(Log::ERROR, $this->client->__getLastRequest());
            Kohana::$log->add(Log::ERROR, $this->client->__getLastResponse());
            // soap error
            return new Merchant_Billing_Response(FALSE, $ex->getMessage());
        }
    }

    /**
     *
     * @param string $transaction_id (RefNum)
     * @param array  $options
     *
     * @return Merchant_Billing_Response
     */
    public function void($transaction_id, $options = array())
    {
        try
        {
            $this->client->voidTransaction($this->token, $transaction_id);
            return new Merchant_Billing_Response(true, 'Transaction successfully voided');
        }
        catch (SoapFault $ex)
        {
            Kohana::$log->add(Log::ERROR, $ex);
            Kohana::$log->add(Log::ERROR, $this->client->__getLastRequest());
            Kohana::$log->add(Log::ERROR, $this->client->__getLastResponse());
            // soap error
            return new Merchant_Billing_Response(false, $ex->getMessage());
        }
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
        $default_request = array(
            'RefNum' => $identification,
            'Details' => array(
                'Amount' => $money
            )
        );
        $request = array_merge($default_request, $options);
        try
        {
            Kohana::$log->add(Log::DEBUG, 'Posting to CIP: ' . Debug::dump($request));
            $response = $this->client->runTransaction($this->token, $request);
            Kohana::$log->add(Log::DEBUG, 'Response from CIP: ' . Debug::dump($response));
            if ($response->ResultCode == 'A')
            {
                return new Merchant_Billing_Response(
                        TRUE, 'Card successfully credited', $response, array(
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
                        FALSE, $response->Error, $response
                );
            }
        }
        catch (SoapFault $ex)
        {
            Kohana::$log->add(Log::ERROR, $ex);
            Kohana::$log->add(Log::ERROR, $this->client->__getLastRequest());
            Kohana::$log->add(Log::ERROR, $this->client->__getLastResponse());
            // soap error
            return new Merchant_Billing_Response(FALSE, $ex->getMessage());
        }
    }

    /**
     * 
     * @param string $transaction_id
     * @return string $customer_id
     */
    public function create_customer_from_transaction($transaction_id)
    {
        try
        {
            Kohana::$log->add(Log::DEBUG, 'creating customer from transaction_id: '.$transaction_id);
            $response = $this->client->convertTranToCust($this->token, $transaction_id);
            Kohana::$log->add(Log::DEBUG, 'Response from CIP: ' . Debug::dump($response));
            return $response;
        }
        catch (SoapFault $ex)
        {
            Kohana::$log->add(Log::ERROR, $ex);
            Kohana::$log->add(Log::ERROR, $this->client->__getLastRequest());
            Kohana::$log->add(Log::ERROR, $this->client->__getLastResponse());
            // soap error
            return false;
        }
    }
    
    /**
     *
     * @param number                      $money
     * @param Merchant_Billing_CreditCard $creditcard
     * @param array                       $options
     */
    public function recurring($money, Merchant_Billing_CreditCard $card, $options = array())
    {
        
    }

    /**
     *
     * @param string                      $subscription_id subscription id returned from recurring method
     * @param Merchant_Billing_CreditCard $creditcard
     *
     * @return Merchant_Billing_Response
     */
    public function update_recurring($subscription_id, Merchant_Billing_CreditCard $creditcard)
    {

    }

    /**
     *
     * @param string $subscription_id subscription id return from recurring method
     *
     * @return Merchant_Billing_Response
     */
    public function cancel_recurring($subscription_id)
    {

    }

}
