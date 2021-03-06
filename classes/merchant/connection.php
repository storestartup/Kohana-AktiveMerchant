<?php
/**
 * Description of Connection
 *
 * @package Aktive Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Connection {
  
  private $endpoint;
  private $log_level;

  public function __construct($endpoint) {
    $this->endpoint = $endpoint;
    $this->log_level = Arr::get(Kohana::$config->load('aktivemerchant'), 'log_level', Log::DEBUG);
  }

  public function request ($method, $body, $options = array()){
    
    $timeout    = isset($options['timeout']) ? $options['timeout'] : '0';
    $user_agent = isset($options['user_agent']) ? $options['user_agent'] : null;
    $headers    = isset($options['headers']) ? $options['headers'] : array();

    $server = parse_url($this->endpoint);

    if (!isset($server['port']))
      $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;

    if (!isset($server['path'])) $server['path'] = '/';

    if (isset($server['user']) && isset($server['pass']))
      $headers[] = 'Authorization: Basic ' . base64_encode($server['user'] . ':' . $server['pass']);

    $transaction_url = $server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : '');

    Kohana::$log->add($this->log_level, "Sending to [$transaction_url] [$body]");

    if ( function_exists('curl_init') ) {
      $curl = curl_init($transaction_url);

      curl_setopt($curl, CURLOPT_PORT, $server['port']);
      curl_setopt($curl, CURLOPT_HEADER, 0);
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
      curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT , $timeout);
      if ( isset($user_agent))
      {
            curl_setopt($curl, CURLOPT_USERAGENT,$user_agent);
      }

      if ($method == 'post')
      {
            curl_setopt($curl, CURLOPT_POST, 1);
      }
      
      curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

      $response = curl_exec($curl);
      
      $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      
      curl_close($curl);

      Kohana::$log->add($this->log_level, "Received http status [$httpcode] and body [$response]");

      return $response;
    } else {
      throw new Exception ('curl is not installed!');
    }
  }
}
?>
