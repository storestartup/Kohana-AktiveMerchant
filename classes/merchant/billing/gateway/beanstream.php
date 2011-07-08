<?php

class Merchant_Billing_Gateway_Beanstream extends Merchant_Billing_Gateway_Beanstream_Core {

    /**
     * Authorizes a credit card or check for the specified amount
     * 
     * @param float $money the amount to authorize
     * @param mixed $source  a Merchant_Billing_CreditCard , Merchant_Billing_Check, or a customerCode for payment profile processing
     * @param array $options
     * @return Merchant_Billing_Response 
     */
    public function authorize($money, $source, $options = array())
    {
        $this->post = array();
        $this->add_amount($money);
        $this->add_invoice($options);
        $this->add_source($source);
        $this->add_address($options);
        $this->add_transaction_type('authorization');
        return $this->commit();
    }

    /**
     * Process a purchase against the beanstream gateway
     * 
     * @param float $money amount of purchase
     * @param mixed $source a Merchant_Billing_CreditCard , Merchant_Billing_Check, or a customerCode for payment profile processing
     * @param array $options additional options
     * @return Merchant_Billing_Response 
     */
    public function purchase($money, $source, $options = array())
    {
        $this->post = array();
        $this->add_amount($money);
        $this->add_invoice($options);
        $this->add_source($source);
        $this->add_address($options);
        $this->add_transaction_type($this->purchase_action($source));
        return $this->commit();
    }

    /**
     * Voids a previous transaction
     * 
     * @param string $authorization the transaction id to void
     * @param array $options
     * @return Merchant_Billing_Response 
     */
    public function void($authorization, $options = array())
    {
        list($reference, $amount, $type) = $this->split_auth($authorization);

        $this->post = array();
        $this->add_reference($reference);
        $this->add_original_amount($amount);
        $this->add_transaction_type($this->void_action($type));
        return $this->commit();
    }

    /**
     * Creates a payment profile
     * 
     * @param Merchant_Billing_CreditCard $credit_card
     * @param array $options
     * @return Merchant_Billing_Response 
     */
    public function store(Merchant_Billing_CreditCard $credit_card, $options = array())
    {
        $this->post = array();
        $this->add_address($options);
        $this->add_credit_card($credit_card);
        $this->add_secure_profile_variables($options);
        return $this->commit(true);
    }

    /**
     * Marks a payment profile for deletion by changing the status to C (closed).
     * Closed profiles will have to be removed manually 
     * 
     * @param string $vault_id the payment profile customerCode to update
     * @return Merchant_Billing_Response 
     */
    public function delete($vault_id)
    {
       return $this->update($vault_id, false, array('status' => 'C'));
    }


    /**
     *Updates a payment profile
     * 
     * @param string $vault_id the payment profile customerCode to update
     * @param Merchant_Billing_CreditCard $credit_card set as much as you'd like to update
     * @param mixed $options array of additional options to change
     * @return Merchant_Billing_Response 
     */
    public function update($vault_id, Merchant_Billing_CreditCard $credit_card, $options = array())
    {
        $this->post = array();
        $this->add_address($options);
        $this->add_credit_card($credit_card);
        $options = array_merge($options, array('vault_id' => $vault_id, 'operation' => $this->secure_profile_action('modify')));
        $this->add_secure_profile_variables($options);
        return $this->commit(true);
    }

}