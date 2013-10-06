<?php

class Merchant_Billing_Gateway_Bluepay extends Merchant_Billing_Gateway
{
    const URL = 'https://secure.bluepay.com/interfaces/bp20post';
    
    private $account_id;
    private $secret_key;
    private $test_mode;
    
    public function __construct($options)
    {
        $this->required_options('account_id,secret_key',$options);
        $this->account_id=$options['account_id'];
        $this->secret_key=$options['secret_key'];
        $this->test_mode=Arr::get($options,'test_mode',true);
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
        $data=array(
            'TRANS_TYPE'=>'SALE',
            'AMOUNT'=>$money,
            'PAYMENT_ACCOUNT'=>$creditcard->number,
            'CARD_EXPIRE'=>$this->card_exp($creditcard),
            'CARD_CVV2'=>$creditcard->verification_value,
            'CUSTOMER_IP'=>Arr::get($_SERVER,'REMOTE_ADDR','127.0.0.1'),
            'INVOICE_ID'=>Arr::get($options,'id'),
            'NAME1'=>$creditcard->first_name,
            'NAME2'=>$creditcard->last_name,
            'ADDR1'=>Arr::get($billing_address,'address1'),
            'CITY'=>Arr::get($billing_address,'city'),
            'STATE'=>Arr::get($billing_address,'state'),
            'ZIP'=>Arr::get($billing_address,'zip'),
            'COUNTRY'=>Arr::get($billing_address,'country')
        );

        // check for optional fields
        if (isset($options['description']) && ! empty($options['description']))
            $data['MEMO']=$options['description'];
        if (isset($options['email']) && ! empty($options['email']))
            $data['EMAIL']=$options['email'];
        if (isset($options['phone']) && ! empty($options['phone']))
            $data['PHONE']=$options['phone'];

        $result=$this->post($data);
        
        if (Arr::get($result,'STATUS') == '1')
        {
            return new Merchant_Billing_Response(
                        TRUE, 'Card successfully charged', $result, array(
                    'test' => $this->test_mode,
                    'authorization' => Arr::get($result,'AUTH_CODE'),
                    'transaction_id' => Arr::get($result,'TRANS_ID'),
                    'fraud_review' => FALSE,
                    'avs_result' => array('code'=>Arr::get($result,'AVS')),
                    'cvv_result' => Arr::get($result,'CVV2'),
                    'processor' => 'Bluepay'
                        )
                );
        }
        else
        {
            return new Merchant_Billing_Response(false, Arr::get($result,'MESSAGE','Unknown error'), $result);
        }
    }

    public function purchase_transaction($money,$transaction_id,$rebilling=true,$options=array())
    {
        $data = array(
            'TRANS_TYPE'=>'SALE',
            'AMOUNT'=>$money,
            'MASTER_ID'=>$transaction_id,
            'CUSTOMER_IP'=>Arr::get($_SERVER,'REMOTE_ADDR','127.0.0.1')
        );
        if ($rebilling) $data['F_REBILLING']='1';

        // see if there's a description
        if (isset($options['description']) && ! empty($options['description']))
            $data['MEMO']=$options['description'];

        $result=$this->post($data);
        
        if (Arr::get($result,'STATUS') == '1')
        {
            return new Merchant_Billing_Response(
                        TRUE, 'Card successfully charged', $result, array(
                    'test' => $this->test_mode,
                    'authorization' => Arr::get($result,'AUTH_CODE'),
                    'transaction_id' => Arr::get($result,'TRANS_ID'),
                    'fraud_review' => FALSE,
                    'avs_result' => array('code'=>Arr::get($result,'AVS')),
                    'cvv_result' => Arr::get($result,'CVV2'),
                    'processor' => 'Bluepay'
                        )
                );
        }
        else
        {
            return new Merchant_Billing_Response(false, Arr::get($result,'MESSAGE','Unknown error'), $result);
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
        $billing_address=Arr::get($options,'billing_address',array());
        $data=array(
            'TRANS_TYPE'=>'AUTH',
            'AMOUNT'=>$money,
            'PAYMENT_ACCOUNT'=>$creditcard->number,
            'CARD_EXPIRE'=>$this->card_exp($creditcard),
            'CARD_CVV2'=>$creditcard->verification_value,
            'CUSTOMER_IP'=>Arr::get($_SERVER,'REMOTE_ADDR','127.0.0.1'),
            'INVOICE_ID'=>Arr::get($options,'id'),
            'NAME1'=>$creditcard->first_name,
            'NAME2'=>$creditcard->last_name,
            'ADDR1'=>Arr::get($billing_address,'address1'),
            'CITY'=>Arr::get($billing_address,'city'),
            'STATE'=>Arr::get($billing_address,'state'),
            'ZIP'=>Arr::get($billing_address,'zip'),
            'COUNTRY'=>Arr::get($billing_address,'country')
        );

        $result=$this->post($data);
        
        if (Arr::get($result,'STATUS') == '1')
        {
            return new Merchant_Billing_Response(
                        TRUE, 'Card successfully authorized', $result, array(
                    'test' => $this->test_mode,
                    'authorization' => Arr::get($result,'AUTH_CODE'),
                    'transaction_id' => Arr::get($result,'TRANS_ID'),
                    'fraud_review' => FALSE,
                    'avs_result' => array('code'=>Arr::get($result,'AVS')),
                    'cvv_result' => Arr::get($result,'CVV2'),
                    'processor' => 'Bluepay'
                        )
                );
        }
        else
        {
            return new Merchant_Billing_Response(false, Arr::get($result,'MESSAGE','Unknown error'), $result);
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
        $data=array(
            'TRANS_TYPE'=>'VOID',
            'MASTER_ID'=>$transaction_id
            );
        $result=$this->post($data);
        
        if (Arr::get($result,'STATUS') == '1')
        {
            return new Merchant_Billing_Response(
                        TRUE, 'Transaction successfully voided', $result, array(
                    'test' => $this->test_mode,
                    'authorization' => Arr::get($result,'AUTH_CODE'),
                    'transaction_id' => Arr::get($result,'TRANS_ID'),
                    'fraud_review' => FALSE,
                    'avs_result' => array('code'=>Arr::get($result,'AVS')),
                    'cvv_result' => Arr::get($result,'CVV2'),
                    'processor' => 'Bluepay'
                        )
                );
        }
        else
        {
            return new Merchant_Billing_Response(false, Arr::get($result,'MESSAGE','Unknown error'), $result);
        }
    }

    /**
     *
     * @param number $money
     * @param string $transaction_id
     * @param array  $options
     *
     * @return Merchant_Billing_Response
     */
    public function credit($money, $transaction_id, $options = array())
    {
        $data=array(
            'AMOUNT'=>$money,
            'TRANS_TYPE'=>'REFUND',
            'MASTER_ID'=>$transaction_id
            );
        $result=$this->post($data);
        
        if (Arr::get($result,'STATUS') == '1')
        {
            return new Merchant_Billing_Response(
                        TRUE, 'Transaction successfully credited', $result, array(
                    'test' => $this->test_mode,
                    'authorization' => Arr::get($result,'AUTH_CODE'),
                    'transaction_id' => Arr::get($result,'TRANS_ID'),
                    'fraud_review' => FALSE,
                    'avs_result' => array('code'=>Arr::get($result,'AVS')),
                    'cvv_result' => Arr::get($result,'CVV2'),
                    'processor' => 'Bluepay'
                        )
                );
        }
        else
        {
            return new Merchant_Billing_Response(false, Arr::get($result,'MESSAGE','Unknown error'), $result);
        }
    }
    
    /**
     * 
     * @param array $data
     * @return array
     */
    public function post($data=array())
    {
        $data['ACCOUNT_ID']=$this->account_id;
        $data['MODE'] = $this->test_mode ? 'TEST' : 'LIVE';
        
        // calculate and set tamper proof seal
        $data['TPS_DEF']='ACCOUNT_ID TRANS_TYPE AMOUNT';
        $data['TAMPER_PROOF_SEAL'] =  md5($this->secret_key . $this->account_id . Arr::get($data,'TRANS_TYPE') . Arr::get($data,'AMOUNT'));
        
        $ch=  curl_init(self::URL);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>  http_build_query($data)
        ));
        
        Kohana::$log->add(Log::DEBUG,"Posting to ".self::URL.' '.Debug::dump($data));
        
        $response=  curl_exec($ch);
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        Kohana::$log->add(Log::DEBUG,"response: $response");
       
        // parse the result string
        $result=array();
        parse_str($response,$result);
        return $result;
    }

    /**
    * @param Merchant_Billing_Credit_Card
    * @return string
    **/
    private function card_exp(Merchant_Billing_CreditCard $creditcard)
    {
        $exp=$creditcard->month;
        if (strlen($creditcard->year) > 2) $exp.=substr($creditcard->year,-2);
        else $exp.=$creditcard->year;
        return $exp;
    }
}
