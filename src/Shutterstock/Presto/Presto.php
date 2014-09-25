<?php

namespace Shutterstock\Presto;

/**
 * PHP REST Orchestration
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
 */

class Presto
{

    /**
     * @var string
     */
    const VERSION = 'v1.0';

    /**
     * @var string
     */
    public $error_log_prefix = 'PRESTO CLIENT: ';

    /**
     * @var integer
     */
    public $retries_max = 1;

    /**
     * @var integer
     */
    public $retry_delay = 200000; //In microseconds (millionths of a second)

    /**
     * @var boolean
     */
    public $log_retries = false;

    /**
     * In seconds, what is considered a slow response
     * A non-zero value indicates to log slow responses
     *
     * @var integer
     */
    public static $slow_response_default = 0;
    public $slow_response = null;

    /**
     * Whether to trigger an error if a service cannot be contacted
     *
     * @var boolean
     */
    public $trigger_error = false;

    /**
     * Whether queueing mode is enabled
     *
     * @var boolean single or queue
     */
    public $queue_enabled = false;

    /**
     * Queueing array for processing request simultaneously
     *
     * @var array
     */
    private static $request_queue = [];

    /**
     * multi_curl handle for processing simultaneous curl requests
     *
     * @var object
     */
    private static $queue_handle = null;

    /**
     * Convenience variable for storing configuration information
     *
     * @var object
     */
    public static $config = array();

    /**
     * Default CURL options to set, use CURLOPT constants
     *
     * @var array
     */
    public static $curl_opts_defaults = [
        CURLOPT_CONNECTTIMEOUT  => 2,
        CURLOPT_TIMEOUT_MS      => 10000,
        CURLOPT_USERAGENT       => 'Presto ',
        CURLOPT_REFERER         => '',
        CURLOPT_FOLLOWLOCATION  => false,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HEADER          => true,
        CURLOPT_ENCODING        => 'utf-8',
        CURLOPT_HTTPHEADER      => [
            'Accept'                => 'application/json',
            'Accept-Language'       => 'en-us,en',
            'Accept-Encoding'       => 'gzip, deflate',
                                   ],
        ];

    /**
     * CURL options to use in the instance
     * initialized from $curl_opts_defaults
     *
     * @var array
     */
    public $curl_opts = [];

    /**
     * @var array
     */
    public static $profiling = [];

    /**
     * @var integer maximum number of profiling entries to record
     */
    public static $profiling_max = 20;

    /**
     * @var integer number of profiling entries recorded
     */
    public static $profiling_count = 0;

    /**
     * @var Response instance
     */
    public $response = null;

    /**
     * Constructor 
     *
     * @param  array  $curl_opts  curl options to set/override
     */
    public function __construct(array $curl_opts = [])
    {
        $this->curl_opts = self::$curl_opts_defaults;
        $this->curl_opts[CURLOPT_USERAGENT] .= self::VERSION;

        // Update default options if passed
        if (!empty($curl_opts)) {
            foreach ($curl_opts as $key => $value) {
                $this->curl_opts[$key] = $value;
            }
        }

        $this->slow_response = self::$slow_response_default;
    }

    /**
     * Manual trigger of curl queue init
     */
    public static function initQueue()
    {
        self::$request_queue = [];
        self::$queue_handle = curl_multi_init();
    }

    /**
     * Convenience function to load configuration for how to communicate with different services
     *
     * @param  array  $config  configuration for services
     */
    public static function loadConfig(array $config)
    {
        self::$config = $config;
    }

    /**
     * Convenience function to get the configuration information for the named configuration
     *
     * @param   string  $service_name  name/key of service to retrieve configuration for
     * @return  array                  configuration for configured service
     */
    public static function getServiceConfig($service_name)
    {
        if (isset(self::$config->$service_name)) {
            return self::$config->$service_name;
        }

        return null;
    }

    /**
     * Authorization method and options to use
     *
     * @param  string  $username   user name to use
     * @param  string  $password   password to use
     * @param  string  $auth_type  authentication method to use
     */
    public function setAuth($username, $password, $auth_type = null)
    {
        if (is_null($auth_type)) {
            $auth_type = CURLAUTH_BASIC;
        }

        $this->curl_opts[CURLOPT_HTTPAUTH] = $auth_type;
        $this->curl_opts[CURLOPT_USERPWD] = "{$username}:{$password}";
    }

    /**
     * Set referrer
     *
     * @param  string  $referer  referer string to set
     */
    public function setReferer($referer)
    {
        $this->curl_opts[CURLOPT_REFERER] = $ref;
    }

    /**
     * Set custom headers, with optional overwrite of existing
     *
     * @param   array    $headers             key/value list of headers to set
     * @param   boolean  $overwrite_existing  whether to merge or overwrite existing headers
     */
    public function setHeaders(array $headers, $overwrite_existing = false)
    {
        if ($overwrite_existing) {
            $this->curl_opts[CURLOPT_HTTPHEADER] = $headers;
        } else {
            $this->curl_opts[CURLOPT_HTTPHEADER] = array_merge(
                $this->curl_opts[CURLOPT_HTTPHEADER],
                $headers
            );
        }
    }


    /**
     * Create the request to the specified service
     * Request success/failure can be checked with
     *  $response->is_success
     * HTTP response of a successful request can be checked with
     *  $response->http_code
     *
     * @param   string  $url       URL to use for the request
     * @param   array   $options   additional CURL options to set/override for the request
     * @param   object  $callback  function to callback after request is executed
     * @return  object             Response class instance or callback response
     */
    public function makeRequest($url, array $options = [], $callback = null)
    {
        if ($url == '') {
            $this->logError('No URL passed into makeRequest method');
            return;
        }

        if (!empty($options)) {
            if (isset($options[CURLOPT_HTTPHEADER])) {
                $options[CURLOPT_HTTPHEADER] = array_merge(
                    $this->curl_opts[CURLOPT_HTTPHEADER],
                    $options[CURLOPT_HTTPHEADER]
                );
            }

            foreach ($this->curl_opts as $key => $value) {
                if (!array_key_exists($key, $options)) {
                    $options[$key] = $value;
                }
            }
        } else {
            $options = $this->curl_opts;
        }

        $curl_headers = [];
        foreach ($options[CURLOPT_HTTPHEADER] as $key => $value) {
            $curl_headers[] = "{$key}: {$value}";
        }
        $options[CURLOPT_HTTPHEADER] = $curl_headers;

        $handle = curl_init($url);
        curl_setopt_array($handle, $options);

        if ($this->queue_enabled) {
            $this->queueRequest($handle, $url, $callback);
        } else {
            $response = $this->executeRequest($handle, $options);
            if (is_object($callback)) {
                return $callback($response);
            } else {
                return $response;
            }
        }
    }

    /**
     * Execute the curl request
     *
     * @param   object  $handle   curl handle to execute
     * @param   array   $options  array of curl configs
     * @return  object            Response class instance
     */
    public function executeRequest($handle, array $options = [])
    {
        static $retries = 0;

        $result = curl_exec($handle);
        $info = curl_getinfo($handle);

        if ($result === false) {
            $info = array_merge($info, [
                'is_success'  => false,
                'errorno'     => curl_errno($handle),
                'error'       => curl_error($handle),
                'queue'       => 'off',
            ]);
            $header = '';

            $retries++;
            if ($retries < $this->retries_max) {
                self::logProfiling($info);
                if ($this->log_retries) {
                    $this->logError("retrying request - ({$info['errorno']}) {$info['error']} :: {$info['url']}");
                }
                usleep($this->retry_delay);
                return $this->makeRequest($info['url'], $options);
            } else {
                $this->logError("max retries ({$retries}) reached - ({$info['errorno']}) {$cinfo['error']} :: {$cinfo['url']}");
            }
        } else {
            $info['is_success'] = true;
            $info['queue'] = 'off';

            list($header, $result) = explode("\r\n\r\n", $result, 2);
            if ($header == 'HTTP/1.1 100 Continue') {
                list($header, $result) = explode("\r\n\r\n", $result, 2);
            }
        }

        $retries = 0;

        if (
            $this->slow_response &&
            ($this->slow_response < $info['total_time'])
        ) {
            $this->logError("SLOW SERVICE RESPONSE ({$info['total_time']}s) from {$info['url']}");
        }

        self::logProfiling($info);
        $this->response = new Response($info, $result, $header);
        return $this->response;
    }

    /**
     * Queue a request for processing when doing simultaneous requests
     *
     * @param  object  $handle    curl handle for the reqeust
     * @param  string  $url       URL to use for the request
     * @param  object  $callback  function to call after request is processed
     */
    public function queueRequest($handle, $url, $callback)
    {
        if (!is_resource(self::$queue_handle)) {
            self::initQueue();
        }

        self::$request_queue[] = [
            'url'       => $url,
            'handle'    => $handle,
            'callback'  => $callback,
            'response'  => '',
        ];
        curl_multi_add_handle(self::$queue_handle, $handle);
    }

    /**
     * Trigger to execute the curl requests
     *
     * @return  boolean  whether or not the queue was processed
     */
    public static function processQueue()
    {
        if (count(self::$request_queue) == 0) {
            return false;
        }

        do {
            $multi_handle = curl_multi_exec(self::$queue_handle, $active);
        } while ($multi_handle == CURLM_CALL_MULTI_PERFORM);

        while ($multi_handle && $multi_result == CURLM_OK) {
            if (curl_multi_select(self::$queue_handle) != -1) {
                do {
                    $multi_handle = curl_multi_exec(self::$queue_handle, $active);
                } while ($multi_handle == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach (self::$request_queue as $key => $value) {
            $error = curl_error($value['handle']);
            if (!empty($error)) {
                $info = [
                    'is_success'  => false,
                    'url'         => $value['url'],
                    'errorno'     => curl_errno($value['handle']),
                    'error'       => $error,
                    'queue'       => 'ON'
                ];

                $result = false;
                $header = '';
            } else {
                $info = curl_getinfo($value['handle']);
                $info['is_success'] = true;
                $info['queue'] = 'ON';

                $body = curl_multi_getcontent($value['handle']);
                list($header, $result) = explode("\r\n\r\n", $body, 2);
            }

            self::logProfiling($info);
            self::$request_queue[$key]['response'] = new Response($info, $result, $header);
            $callback = $value['callback'];
            $callback(self::$request_queue[$key]['response']);

            curl_multi_remove_handle(self::$queue_handle, self::$request_queue[$key]['handle']);
        }

        curl_multi_close(self::$queue_handle);
        self::$request_queue = [];
        return true;
    }

    /**
     * OPTIONS request
     *
     * @param   string  $url      URL to use for the request
     * @param   array   $options  additional CURL options to set/override for the request
     * @return  object            Response class instance
     */
    public function options($url, array $options = [])
    {
        return $this->custom('OPTIONS', $url, $options);
    }

    /**
     * HEAD request
     *
     * @param   string  $url      URL to use for the request
     * @param   array   $options  additional CURL options to set/override for the request
     * @return  object            Response class instance
     */
    public function head($url, array $options = [])
    {
        $options[CURLOPT_NOBODY] = true;
        return $this->makeRequest($url, $options);
    }

    /**
     * GET request
     *
     * @param   string  $url         URL to use for the request
     * @param   mixed   $url_params  url parameters
     * @param   object  $callback    function to call after request
     * @param   array   $options     additional CURL options to set/override for the request
     * @return  object               Response class instance
     */
    public function get($url, $url_params = null, $callback = null, array $options = [])
    {
        if (!is_null($url_params)) {
            if (is_array($url_params) && !empty($url_params)) {
                $url .= '?' . http_build_query($url_params);
            } else {
                $url .= '?' . $url_params;
            }
        }

        $options[CURLOPT_HTTPGET] = true;
        return $this->makeRequest($url, $options, $callback);
    }

    /**
     * POST request
     * To post a file, use the $data array and set the value to @/path/to/file
     *
     * @param   string  $url       URL to use for the request
     * @param   array   $data      list of values to send in the request
     * @param   object  $callback  function to call after request
     * @param   array   $options   additional CURL options to set/override for the request
     * @return  object             Response class instance
     */
    public function post($url, array $data = [], $callback = null, array $options = [])
    {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $data;
        return $this->makeRequest($url, $options, $callback);
    }

    /**
     * PUT request
     * To post a file, use the $data array and set the value to @/path/to/file
     *
     * @param   string  $url       URL to use for the request
     * @param   mixed   $data      additional data to submit with request
     * @param   object  $callback  function to call after request
     * @param   array   $options   additional CURL options to set/override for the request
     * @return  object             Response class instance
     */
    public function put($url, $data, $callback = null, array $options = [])
    {
        return $this->custom('PUT', $url, $data, $callback, $options);
    }

    /**
     * DELETE request
     *
     * @param   string  $url       URL to use for the request
     * @param   mixed   $data      additional data to submit with request
     * @param   object  $callback   function to call after request
     * @param   array   $options   additional CURL options to set/override for the request
     * @return  object             Response class instance
     */
    public function delete($url, $data, $callback = null, array $options = [])
    {
        return $this->custom('DELETE', $url, $data, $callback, $options);
    }

    /**
     * Custom request
     *
     * @param   string  $method    HTTP method to use for the request
     * @param   string  $url       URL to use for the request
     * @param   mixed   $data      data to send with request
     * @param   object  $callback  function to call after request
     * @param   array   $options   additional CURL options to set/override for the request
     * @return  object             Response class instance
     */
    public function custom($method, $url, $data = null, $callback = null, array $options = [])
    {
        $options[CURLOPT_CUSTOMREQUEST] = $method;

        if (isset($data)) {
            $options[CURLOPT_POSTFIELDS] = $data;
        }

        return $this->makeRequest($url, $options, $callback);
    }

    /**
     * Convert an array to a flattened URL parameter structure
     * This will create a URL parameter string with repeating param keys
     * If this special behavior is not needed, http_build_query should be used
     *
     * @param   array   $params     key/value list of url parameters to use
     * @param   string  $delimiter  optional alternative delimiter to use
     * @return  string              flattened URL parameters
     */
    public static function arrayToUrlParams($params, $delimiter = '&')
    {
        $url_params = array();
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subvalue) {
                    $url_params[] = urlencode($key) . '=' . urlencode($subvalue);
                }
            } else {
                $url_params[] = urlencode($key) . '=' . urlencode($value);
            }
        }
        return implode($delimiter, $url_params);
    }

    /**
     * Log an error
     *
     * @param  string  $message  error message to log
     */
    public function logError($message)
    {
        if ($this->trigger_error) {
            trigger_error("{$this->error_log_prefix}{$message}");
        } else {
            error_log("{$this->error_log_prefix}{$msg}");
        }
    }

    /**
     * Log profiling information for service requests
     *
     * @param  array  $log_data  profiling information to log
     */
    public static function logProfiling(array $log_data)
    {
        if (self::$profiling_count > self::$profiling_max) {
            return;
        }

        self::$profiling_count++;
        if (isset($log_data['errorno'])) {
            self::$profiling[] = [
                'url'       => $log_data['url'],
                'errorno'   => $log_data['errorno'],
                'error'     => $log_data['error'],
                'queue'     => $log_data['queue']
            ];
        } else {
            self::$profiling[] = [
                'url'               => $log_data['url'],
                'http_code'         => $log_data['http_code'],
                'total_time'        => $log_data['total_time'],
                'pretransfer_time'  => $log_data['pretransfer_time'],
                'queue'             => $log_data['queue']
            ];
        }
    }

    /**
     * Fetch profiling information
     *
     * @return  array  profile of all calls made during runtime
     */
    public function getProfiling()
    {
        return self::$profiling;
    }

}

