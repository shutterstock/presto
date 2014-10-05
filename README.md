presto
======

## PHP REST Orchestration

A REST client to access any services that support RESTful access or any HTTP access method. Supports all HTTP methods including: GET, POST, DELETE, PUT, OPTIONS, HEAD, AUTH, and custom. Simultaneous requests are supported through the use of a queue activation setting. Use of the queue will allow multiple requests to be processed in parallel, which can significantly speed up calls to multiple services or API end points.

### Example
```php
$request = new Shutterstock\Presto\Presto();
$r = $request->get('http://www.google.com');
```

### Features

1. Built-in support for all common REST call types
2. Support for adding AUTH headers
3. Supports queueing and parallel requests
4. Support for passing a callback function for processing response
5. Logging and profiling built-in
6. Customizable timeouts and retries on a per request basis
7. Support for setting user agent and referer

### More Details

Presto relies on curl to make all requests. Many customization options are available including custom header additions, referrer, and user agent. Profiling is built-in and all the curl meta data and timing information are logged to assist with troubleshooting and optimization. Failed requests are logged as errors with optional exception throwing. A slow request configuration option allows logging of slow requests to the error log. Failed requests can automatically be retried a configureable number of times after a specified delay.

All request results are stored in a standardized Response object that includes meta data, header and request response data blocks. A meta data `is_success` entry is always present to indicate if the network request was successful or not. In the event of failure, there will be error information in the meta section.

### Testing and Coverage

Presto has both unit and integration tests. To view coverage...
```
phpunit -c /tests/unit
phpunit -c /tests/integration
```

### Queueing Example
```php
Shutterstock\Presto\Presto::initQueue();

// callback function to process response
$rest_response = function($r) {
    if ($r->is_success) {
        echo "{$r->http_code} - {$r->url} " . round($r->total_time, 2) . "s\n";
    } else {
        echo "0 - {$r->url} {$r->error}\n";
    }
};

// URLs to hit, include one that will fail and another that will return a 404
$urls = [
    'http://www.google.com',
    'http://www.yahoo.com',
    'http://www.hfjkhfwekjhfkj.info',
    'http://www.bigstockphoto.com/qwerty',
];

// Create an array to hold Presto request objects
$requests = array();

foreach ($urls as $url) {
    $requests[$url] = new Shutterstock\Presto\Presto();
    $requests[$url]->queue_enabled = true;
    $requests[$url]->get($url, null, $rest_response);
}

// Process all queued requests
Shutterstock\Presto\Presto::processQueue();
```

