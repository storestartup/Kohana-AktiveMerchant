<?php

/**
 * Wrapper class for AktiveMerchant library (php port of ruby's ActiveMerchant)
 * 
 */
class AktiveMerchant {

    private $gateway;

    /**
     * Creates a new instance of the AktiveMerchant wrapper, using the default driver
     * specified in the config file. Optionally specify a different driver
     * 
     * @param mixed $driver optionally specify the driver, ex AuthorizeNet or Beanstream
     */
    public function __construct($driver=NULL)
    {
        if ($driver == NULL)
        {
            $driver = Arr::get(Kohana::config('aktivemerchant'),'default_gateway','AuthorizeNet');
        }
        // get the default payment driver from config
        $config = Arr::get(Kohana::config('aktivemerchant'), $driver, array());
        // try to determine driver class name
        $class = 'Merchant_Billing_Gateway_' . $driver;

        $this->gateway = new $class($config);

        // Make sure this driver is valid
        if (!($this->gateway instanceof Merchant_Billing_Gateway))
        {
            throw new Exception(
                    get_class($this->gateway) . ' is not a valid payment driver!'
            );
        }
    }

    /**
     *
     * @param float $amount
     * @param Merchant_Billing_CreditCard $card
     * @param array $options
     * @return Merchant_Billing_Response 
     */
    public function purchase($amount, Merchant_Billing_CreditCard $card, $options = array())
    {
        return $this->gateway->purchase($amount, $card, $options);
    }

    /**
     *
     * @param float $amount
     * @param Merchant_Billing_CreditCard $card
     * @param array $options
     * @return Merchant_Billing_Response 
     */
    public function authorize($amount, Merchant_Billing_CreditCard $card, $options = array())
    {
        return $this->gateway->authorize($amount, $card, $options);
    }

    /**
     *
     * @param float $amount
     * @param string $transaction_id
     * @param array $options
     * @return Merchant_Billing_Response 
     */
    public function capture($amount, $transaction_id, $options = array())
    {
        return $this->gateway->capture($amount, $transaction_id, $options);
    }

    /**
     *
     * @param float $amount
     * @param string $transaction_id
     * @param array $options
     * @return Merchant_Billing_Response 
     */
    public function credit($amount, $transaction_id, $options = array())
    {
        return $this->gateway->credit($amount, $transaction_id, $options);
    }

    /**
     *
     * @param string $transaction_id
     * @param array $options
     * @return Merchant_Billing_Response 
     */
    public function void($transaction_id, $options = array())
    {
        return $this->gateway->void($transaction_id, $options);
    }

}

