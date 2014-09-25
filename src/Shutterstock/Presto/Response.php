<?php

namespace Shutterstock\Presto;

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
