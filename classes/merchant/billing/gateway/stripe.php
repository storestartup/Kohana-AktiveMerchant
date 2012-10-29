<?php

class Merchant_Billing_Gateway_Stripe extends Merchant_Billing_Gateway
{

    private $data = array();

    public function __construct($options)
    {
        $this->required_options('api_key', $options);
        // load strip api
        require_once Kohana::find_file('vendor', 'stripe/lib/Stripe');
        Stripe::setApiKey($options['api_key']);
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
        $this->data['amount'] = round($money * 100);
        $this->data['currency'] = 'usd';
        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        try
        {
            $response = Stripe_Charge::create($this->data);
            Kohana::$log->add(Log::DEBUG, "Strip_Charge response: " . Debug::dump($response));
            return new Merchant_Billing_Response(
                            TRUE,
                            'Card successfully charged',
                            $response,
                            array(
                                'test' => !$response->livemode,
                                'authorization' => '',
                                'transaction_id' => $response->id,
                                'fraud_review' => FALSE,
                                'avs_result' => '',
                                'cvv_result' => '',
                                'processor' => 'Stripe'
                            )
            );
        }
        catch (Exception $ex)
        {
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
