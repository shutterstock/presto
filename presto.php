<?php
namespace Presto;

/**
* Php REST Orchestration
* Generic RESTful client for interacting with RESTful services.
* Automatic retries in the event of connection failures,
* configurable number of retries and delays between retries.
* 
* Configuration is performed externally so it is abstracted out.
* The service client is configured through loadConfig function.
* Configuration is stored in a static variable, so it
* is a "global" configuration across all instances.
*
* A Response class instance is always returned. This contains 
* the full curl response plus the data payload returned by the
* service request.
* 
* Response meta data:
* 	url, content_type, http_code, filetime, redirect_count, 
* 	total_time, namelookup_time, connect_time, pretransfer_time, starttransfer_time, redirect_time,
* 	header_size, request_size, size_upload, size_download,  
* 	download_content_length, upload_content_length, 
* 	speed_download, speed_upload,
* 	certinfo, ssl_verify_result
* 
* 
* Example implementation of a Service:
*
* // Should be loaded and configure already by framework
* // Line is present to indicate dependency
* require_once('services.class.php');
*
* class ExampleService {
* 	public $serviceClient	= null;
*	public $service_name	= 'example';
*	public $service_url		= null;
* 
* 	public function __construct() {
* 		$this->serviceClient	= new ServicesClient();
*		$this->service_url		= $this->serviceClient->getServiceConfig($this->service_name)->url;
* 	}
* 
* 	public function getExample($url) {
* 		$response	= $this->serviceClient->get($url, array());
* 		if ( $response->is_success && $response->http_code==200 ) {
* 			$data	= json_decode($response->data);
* 			return $data;
* 		} else {
* 			return 0;
* 		}
* 	}
* 
* }
*
*/

class Presto {

	/**
	 * @var string
	 */
	const VERSION 			= 'v1.0';

	/**
	 * @var string
	 */
	public $error_log_prefix	= 'PRESTO CLIENT: ';

	/**
	 * @var integer
	 */
	public $retries_max			= 1;

	/**
	 * @var integer
	 */
	public $retry_delay			= 200000; //In microseconds (millionths of a second)

	/**
	 * @var boolean
	 */
	public $log_retries			= false;

	/**
	 * In seconds, what is considered a slow response
	 * A non-zero value indicates to log slow responses
	 *
	 * @var integer
	 */
	public static $slow_response_default = 1;
	public $slow_response		= null;

	/**
	 * Whether to trigger an error if a service cannot be contacted
	 *
	 * @var boolean
	 */
	public $trigger_error		= false;

	/**
	 * Whether queueing mode is enabled
	 *
	 * @var boolean single or queue
	 */
	public $queue_enabled		= false;

	/**
	 * Queueing array for processing request simultaneously
	 *
	 * @var array
	 */
	private static $request_queue		= array();

	/**
	 * multi_curl handle for processing simultaneous curl requests
	 *
	 * @var object
	 */
	private static $queue_handle		= null;

	/**
	 * Convenience variable for storing configuration information
	 *
	 * @var object
	 */
	public static $config		= array();

	/**
	 * Default CURL options to set, use CURLOPT constants
	 *
	 * @var array
	 */
	public static $curl_opts_defaults	= array(
		CURLOPT_CONNECTTIMEOUT	=> 2,
		CURLOPT_TIMEOUT_MS		=> 10000,
		CURLOPT_USERAGENT		=> 'Presto ',
		CURLOPT_REFERER			=> '',
		CURLOPT_FOLLOWLOCATION	=> false,
		CURLOPT_RETURNTRANSFER	=> true,
		CURLOPT_HEADER			=> true,
		CURLOPT_ENCODING		=> 'utf-8',
		CURLOPT_HTTPHEADER		=> array(
			'Accept'			=> 'application/json',
			'Accept-Language'	=> 'en-us,en',
			'Accept-Encoding'	=> 'gzip, deflate'
		)
	);

	/**
	 * CURL options to use in the instance
	 * initialized from $curl_opts_defaults
	 *
	 * @var array
	 */
	public $curl_opts			= array();

	/**
	 * @var array
	 */
	public static $profiling	= array(
	);

	/**
	 * @var integer maximum number of profiling entries to record
	 */
	public static $profiling_max	= 20;

	/**
	 * @var integer number of profiling entries recorded
	 */
	public static $profiling_cntr	= 0;

	/**
	 * @var Response instance
	 */
	public $response			= null;

	/**
	 * Constructor 
	 *
	 * @param array $curl_opts curl options to set/override
	 */
	public function __construct($curl_opts=null ) {
		$this->curl_opts	= self::$curl_opts_defaults;
		// Append version to user agent
		$this->curl_opts[CURLOPT_USERAGENT]	.= self::VERSION;
		// Update default options if passed
		if ( is_array($curl_opts) ) {
			foreach($curl_opts as $k=>$v) {
				$this->curl_opts[$k]	= $v;
			}
		}

		$this->slow_response = self::$slow_response_default;
	}

	public static function initQueue() {
		self::$request_queue	= array();
		self::$queue_handle		= curl_multi_init();
	}

	/**
	 * Convenience function to load configuration for how to communicate with different services
	 *
	 * @param array $config configuration for services
	 */
	public static function loadConfig($config) {
		self::$config	= $config;
	}

	/**
	 * Convenience function to get the configuration information for the named configuration
	 *
	 * @param string $service_name name/key of service to retrieve configuration for
	 * @return array configuration for configured service
	 */
	public static function getServiceConfig($service_name) {
		if ( isset(self::$config->$service_name) ) {
			return self::$config->$service_name;
		} else {
			return null;
		}
	}

	/**
	 * Authorization method and options to use
	 *
	 * @param string $username user name to use
	 * @param string $password password to use
	 * @param string $auth_type authentication method to use
	 */
	public function setAuth($username, $password, $auth_type=null) {
		if ( is_null($auth_type) ) {
			$auth_type	= CURLAUTH_BASIC;
		}
		$this->curl_opts[CURLOPT_HTTPAUTH] = $auth_type;
		$this->curl_opts[CURLOPT_USERPWD] = $username . ":" . $password;
	}


	/**
	 * Set referrer
	 *
	 * @param string $ref referrer string to set
	 */
	public function setReferer($ref) {
		$this->curl_opts[CURLOPT_REFERER]	= $ref;
	}

	/**
	 * Set custom headers, with optional overwrite of existing
	 *
	 * @param array $hdrs key/value list of headers to set
	 * @param boolean $overwrite_existing whether to merge or overwrite existing headers
	 * @return void
	 */
	public function setHeaders($hdrs, $overwrite_existing=false) {
		if ( $overwrite_existing ) {
			$this->curl_opts[CURLOPT_HTTPHEADER]	= $hdrs;
		} else {
			$this->curl_opts[CURLOPT_HTTPHEADER]	= array_merge($this->curl_opts[CURLOPT_HTTPHEADER], $hdrs);
		}
	}


	/**
	 * Create the request to the specified service
	 * Request success/failure can be checked with
	 *  $response->is_success
	 * HTTP response of a successful request can be checked with
	 *  $response->http_code
	 *
	 * @param string $url URL to use for the request
	 * @param array $options additional CURL options to set/override for the request
	 * @param object $callback function to callback after request is executed
	 * @return object Response class instance or callback response
	 */
	public function makeRequest($url, $options=null, $callback=null) {
		if ( $url=='' ) {
			debug_print_backtrace();
		}
		static $retries	= 0;
		if ( is_array($options) ) {
			// Check for headers option
			if ( isset($options[CURLOPT_HTTPHEADER]) ) {
				$options[CURLOPT_HTTPHEADER] = array_merge($this->curl_opts[CURLOPT_HTTPHEADER], $options[CURLOPT_HTTPHEADER]);
			}
			// Merge in passed options. Since array is numeric array_merge can't be used
			foreach($this->curl_opts as $k=>$v) {
				if ( !array_key_exists($k, $options) ) {
					$options[$k]	= $v;
				}
			}
		} else {
			$options		= $this->curl_opts;
		}
		// Convert headers to flat array
		$curl_hdrs			= array();
		foreach($options[CURLOPT_HTTPHEADER] as $k=>$v) {
			$curl_hdrs[]	= $k.': '.$v;
		}
		$options[CURLOPT_HTTPHEADER]	= $curl_hdrs;
		//Accept-Language: en-us,en;q=0.5
		// initialize response variables
		$header				= '';
		$cinfo 				= array();
		$ch 				= curl_init($url);
		curl_setopt_array($ch, $options);
		if ( $this->queue_enabled ) {
			$this->queueRequest($ch, $url, $callback);
		} else {
			$response	= $this->executeRequest($ch, $options);
			// Check for callback function
			if ( is_object($callback) ) {
				return $callback($response);
			} else {
				return $response;
			}
		}
	}

	/**
	 * Execute the curl request
	 *
	 * @param object $ch curl handle to execute
	 * @return object Response class instance
	 */
	public function executeRequest($ch, $options=null) {
		static $retries	= 0;
		$cresult			= curl_exec($ch);
		$cinfo				= curl_getinfo($ch);
		if ( $cresult===false ) {
			// Request failed
			$cinfo			= curl_getinfo($ch);
			$cinfo			= array(
				'is_success'=> false,
				'url'		=> $cinfo['url'],		// this variable doesn't exist in this function -Brent
				'errorno'	=> curl_errno($ch),
				'error'		=> curl_error($ch),
				'queue'		=> 'off'
			);
			// Check retry count
			$retries++;
			if ( $retries < $this->retries_max ) {
				self::logProfiling($cinfo);
				if ( $this->log_retries ) {
					$this->logError('retrying request - ('.$cinfo['errorno'].') '.$cinfo['error'].' :: '.$cinfo['url']);
				}
				usleep($this->retry_delay);
				return $this->makeRequest($cinfo['url'], $options);
			} else {
				$this->logError('max retries ('.$retries.') reached - ('.$cinfo['errorno'].') '.$cinfo['error'].' :: '.$cinfo['url']);
			}
		} else {
			$cinfo['is_success']	= true;
			$cinfo['queue']			= 'off';
			// parse out header from body
			list($header, $cresult)	= explode("\r\n\r\n", $cresult, 2);
			if($header == 'HTTP/1.1 100 Continue'){ // discard preliminary "Continue" response if present
				list($header, $cresult) = explode("\r\n\r\n", $cresult, 2);
			}
		}
		$retries			= 0;
		// Check for slow request logging
		if ( $this->slow_response && ($this->slow_response < $cinfo['total_time']) ) {
			$this->logError('SLOW SERVICE RESPONSE ('.$cinfo['total_time'].'s) from '.$cinfo['url']);
		}
		self::logProfiling($cinfo);
		$this->response = new Response($cinfo, $cresult, $header);
		return $this->response;

	}

	/**
	 * Queue a request for processing when doing simultaneous requests
	 *
	 * @param string $url URL to use for the request
	 * @param object $callback function to call after request is processed
	 * @param array $options additional CURL options to set/override for the request
	 */
	public function queueRequest($curl_handle, $url, $callback) {
		if ( !is_resource(self::$queue_handle) ) {
			self::initQueue();
		}
		self::$request_queue[]	= array(
			'url'		=> $url,
			'handle'	=> $curl_handle,
			'callback'	=> $callback,
			'response'	=> ''
		);
		curl_multi_add_handle(self::$queue_handle, $curl_handle);
	}

	public static function processQueue() {
		if ( count(self::$request_queue)==0 ) {
			return false;
		}
		// Send out requests
		do {
		    $cme = curl_multi_exec(self::$queue_handle, $active);
		} while ($cme == CURLM_CALL_MULTI_PERFORM);

		// Monitor requests until no more data returned
		while ($active && $cme == CURLM_OK) {
            if (curl_multi_select(self::$queue_handle) != -1) { 
                do { 
                    $cme = curl_multi_exec(self::$queue_handle, $active); 
                } while ($cme == CURLM_CALL_MULTI_PERFORM); 
            } 
        } 
		foreach(self::$request_queue as $k=>$q) {
			$err	 	= curl_error($q['handle']);
			if ( !empty($err) ) {
				$cinfo	= array(
					'is_success'=> false,
					'url'		=> $q['url'],
					'errorno'	=> curl_errno($q['handle']),
					'error'		=> $err,
					'queue'		=> 'ON'
				);
				$cresult		= false;
				$header			= '';

			} else {
				$cinfo			= curl_getinfo($q['handle']);
				$cinfo['is_success']	= true;
				$cinfo['queue']			= 'ON';
				// parse out header from body
				$body			= curl_multi_getcontent($q['handle']);
				list($header, $cresult)	= explode("\r\n\r\n", $body, 2);

			}
			self::logProfiling($cinfo);
			self::$request_queue[$k]['response'] 	= new Response($cinfo, $cresult, $header);
			$callback			= $q['callback'];
			$callback(self::$request_queue[$k]['response']);

			curl_multi_remove_handle(self::$queue_handle, self::$request_queue[$k]['handle']);
			//unset($this->request_queue[$i]['handle']);
		}
		//close the handles
		curl_multi_close(self::$queue_handle);
		// Reset queue
		self::$request_queue	= array();
		return true;
	}

	/**
	 * OPTIONS request
	 *
	 * @param string $url URL to use for the request
	 * @param array $options additional CURL options to set/override for the request
	 * @return object Response class instance
	 */
	public function options($url, $options=null) {
		return $this->custom('OPTIONS', $url, $options);
	}

	/**
	 * HEAD request
	 *
	 * @param string $url URL to use for the request
	 * @param array $options additional CURL options to set/override for the request
	 * @return object Response class instance
	 */
	public function head($url, $options=null) {
		if ( !is_array($options) ) {
			$options	= array();
		}
		$options[CURLOPT_NOBODY]	= true;
		return $this->makeRequest($url, $options);
	}

	/**
	 * GET request
	 *
	 * @param string $url URL to use for the request
	 * @param mixed $url_params url parameters
	 * @param object $callback function to call after request
	 * @param array $options additional CURL options to set/override for the request
	 * @return object Response class instance
	 */
	public function get($url, $url_params=null, $callback=null, $options=null) {
		if ( !is_array($options) ) {
			$options	= array();
		}

		if ( !is_null($url_params) ) {
			if ( is_array($url_params) ) {
				if ( count($url_params)>0 ) {
					$url	.= '?'.http_build_query($url_params);
				}
			} else {
				$url	.= '?'.$url_params;
			}
		}

		$options[CURLOPT_HTTPGET]	= true;
		return $this->makeRequest($url, $options, $callback);
	}

	/**
	 * POST request
	 * To post a file, use the $data array and set the value to @/path/to/file
	 *
	 * @param string $url URL to use for the request
	 * @param array $options additional CURL options to set/override for the request
	 * @return object Response class instance
	 */
	public function post($url, $data, $callback=null, $options=null) {
		if ( !is_array($options) ) {
			$options	= array();
		}
		$options[CURLOPT_POST]			= true;
		$options[CURLOPT_POSTFIELDS]	= $data;
		return $this->makeRequest($url, $options, $callback);
	}

	/**
	 * PUT request
	 * To post a file, use the $data array and set the value to @/path/to/file
	 *
	 * @param string $url URL to use for the request
	 * @param string/array $data additional data to submit with request
	 * @param array $options additional CURL options to set/override for the request
	 * @return object Response class instance
	 */
	public function put($url, $data, $callback=null, $options=null) {
		return $this->custom('PUT', $url, $data, $callback, $options);
	}

	/**
	 * DELETE request
	 *
	 * @param string $url URL to use for the request
	 * @param string/array $data additional data to submit with request
	 * @param array $options additional CURL options to set/override for the request
	 * @return object Response class instance
	 */
	public function delete($url, $data, $callback=null, $options=null) {
		return $this->custom('DELETE', $url, $data, $callback, $options);
	}

	/**
	 * Custom request
	 *
	 * @param string $method HTTP method to use for the request
	 * @param string $url URL to use for the request
	 * @param array $options additional CURL options to set/override for the request
	 * @return object Response class instance
	 */
	public function custom($method, $url, $data=null, $callback=null, $options=null) {
		if ( !is_array($options) ) {
			$options	= array();
		}
		$options[CURLOPT_CUSTOMREQUEST]	= $method;
		if(isset($data)){
			$options[CURLOPT_POSTFIELDS] = $data;
		}
		return $this->makeRequest($url, $options, $callback);
	}

	/**
	 * Convert an array to a flattened URL parameter structure
	 * This will create a URL parameter string with repeating param keys
	 * If this special behavior is not needed, http_build_query should be used
	 *
	 * @param array $params key/value list of url parameters to use
	 * @param string $delimiter optional alternative delimiter to use
	 */
	public function arrayToUrlParams($params, $delimiter='&') {
		$url_params	= array();
		foreach($params as $k=>$v) {
			if ( is_array($v) ) {
				foreach($v as $v1) {
					$url_params[]	= urlencode($k).'='.urlencode($v1);
				}
			} else {
				$url_params[]	= urlencode($k).'='.urlencode($v);
			}
		}
		$params		= implode($delimiter, $url_params);
		return $params;
	}

	/**
	 * Log an error
	 *
	 * @param string $msg error message to log
	 */
	public function logError($msg) {
		if ( $this->trigger_error ) {
			trigger_error($this->error_log_prefix . $msg);
		} else {
			error_log($this->error_log_prefix . $msg);
		}
	}

	/**
	 * Log profiling information for service requests
	 *
	 * @param array $log_data profiling information to log
	 */
	public static function logProfiling($log_data) {
		if ( self::$profiling_cntr > self::$profiling_max ) {
			return;
		}
		self::$profiling_cntr++;
		if ( isset($log_data['errorno']) ) {
			self::$profiling[]	= array(
				'url'		=> $log_data['url'],
				'errorno'	=> $log_data['errorno'],
				'error'		=> $log_data['error'],
				'queue'		=> $log_data['queue']
			);

		} else {
			self::$profiling[]	= array(
				'url'		=> $log_data['url'],
				'http_code'	=> $log_data['http_code'],
				'total_time'=> $log_data['total_time'],
				'pretransfer_time'=> $log_data['pretransfer_time'],
				'queue'		=> $log_data['queue']
			);

		}

	}

	public function getProfiling() {
		return self::$profiling;
	}

}


/**
* Response class for a service request
* Meta data information can be accessed directly for simplicity
*/
class Response {
	/**
	 * @var array
	 */
	public $meta	= array();

	/**
	 * @var array
	 */
	public $header	= array();

	/**
	 * @var string
	 */
	public $data	= null;

	/**
	 * Constructor
	 *
	 * @param array $meta curl_getinfo information
	 * @param string $data response content from service request 
	 */
	public function __construct($meta, $data, $header=null) {
		$this->meta	= $meta;
		$this->data	= $data;
		if ( !is_null($header) ) {
			$this->parseHeader($header);
		}
	}

	/**
	 * Convenience magic method for accessing meta data
	 *
	 * @param string $meta_key curl_getinfo meta data key
	 */
	public function __get($meta_key) {
		// Check meta data for matching key
		if ( array_key_exists($meta_key, $this->meta) ) {
			return $this->meta[$meta_key];
		}
		// Check header for matching key
		if ( array_key_exists($meta_key, $this->header) ) {
			return $this->header[$meta_key];
		}
		trigger_error('PRESTO RESPONSE: reference to invalid meta key - '.$meta_key);
		return null;
	}

	/**
	 * Parse response headers into an array
	 *
	 * @param string $header headers from request
	 */
	public function parseHeader($header) {
		// There is a way to do this with regex
		$header_lines	= explode("\r\n", $header);
		if ( count($header_lines)>0 ) {
			foreach($header_lines as $l) {
				$h	= explode(':', $l);
				if ( count($h)>1 ) {
					$label = array_shift($h);
					$this->header[$label]	= implode(':',$h);
				}
			}
		} else {
			$this->header	= array();
		}
	}

}
