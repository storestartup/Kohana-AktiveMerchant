<?php
/**
 * Description of Response
 *
 * @package Aktive Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Billing_Response {

    /**
     *
     * @var bool $success 
     */
  public   $success;
  
  /**
   *
   * @var string $message 
   */
  public   $message;
  
  /**
   *
   * @var array 
   */
  public $params;
  
  /**
   *
   * @var bool 
   */
  public   $test;
  /**
   *
   * @var string 
   */
  public   $authorization;
  
  /**
   *
   * @var string 
   */
  public $transaction_id;
  
  /**
   *
   * @var Merchant_Billing_AvsResult 
   */
  public   $avs_result;
  /**
   *
   * @var Merchant_Billing_CvvResult
   */
  public   $cvv_result;
  /**
   *
   * @var mixed 
   */
  public   $fraud_review;
  
  /**
   *
   * @var mixed the processing gateway
   */
  public $processor;

  /**
   *
   * @param boolean $success
   * @param string $message
   * @param array $params
   * @param array $options
   */
  public function __construct($success, $message, $params = array(), $options = array() ) {
    $this->success = $success;
    $this->message = $message;
    $this->params  = $params;

    $this->test          = Arr::get($options,'test',FALSE);
    $this->authorization = Arr::get($options,'authorization');
    $this->transaction_id=Arr::get($options,'transaction_id');
    $this->fraud_review  = Arr::get($options,'fraud_review');
    $this->avs_result    = isset($options['avs_result']) ? new Merchant_Billing_AvsResult($options['avs_result']) : null;
    $this->cvv_result    = isset($options['cvv_result']) ? new Merchant_Billing_CvvResult($options['cvv_result']) : null;
    $this->processor=Arr::get($options,'processor');
  }

  public function  __get($name) {
    return isset($this->$name) ? $this->$name : Arr::get($this->params,$name);
  }

}
?>
