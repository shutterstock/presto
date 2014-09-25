<?php

namespace Shutterstock\Presto;

/**
 * Response meta data:
 *  url, content_type, http_code, filetime, redirect_count,
 *  total_time, namelookup_time, connect_time, pretransfer_time, starttransfer_time, redirect_time,
 *  header_size, request_size, size_upload, size_download,
 *  download_content_length, upload_content_length,
 *  speed_download, speed_upload,
 *  certinfo, ssl_verify_result
 *
 *
 * Response class for a service request
 * Meta data information can be accessed directly for simplicity
 */

class Response
{

    /**
     * @var array
     */
    public $meta = [];

    /**
     * @var array
     */
    public $header = [];

    /**
     * @var string
     */
    public $data = null;

    /**
     * Constructor
     *
     * @param  array   $meta    curl_getinfo information
     * @param  string  $data    response content from service request
     * @param  string  $header  optional header from the response
     */
    public function __construct(array $meta, $data, $header = null)
    {
        $this->meta = $meta;
        $this->data = $data;

        if (!is_null($header)) {
            $this->parseHeader($header);
        }
    }

    /**
     * Convenience magic method for accessing meta data
     *
     * @param   string  $meta_key  curl_getinfo meta data key
     * @return  string             value from meta (or header) field
     */
    public function __get($meta_key)
    {
        if (array_key_exists($meta_key, $this->meta)) {
            return $this->meta[$meta_key];
        }

        if (array_key_exists($meta_key, $this->header)) {
            return $this->header[$meta_key];
        }

        trigger_error("PRESTO RESPONSE: reference to invalid meta key - {$meta_key}");
        return;
    }

    /**
     * Parse response headers into an array
     *
     * @param  string  $header  headers from request
     */
    public function parseHeader($header)
    {
        $header_lines = explode("\r\n", $header);
        if (count($header_lines) > 0) {
            foreach ($header_lines as $line) {
                $header = explode(':', $line);
                if (count($header) > 1) {
                    $label = array_shift($header);
                    $this->header[$label] = implode(':', $header);
                }
            }
        }
    }

}

