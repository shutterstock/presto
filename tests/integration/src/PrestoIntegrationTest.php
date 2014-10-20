<?php

use Shutterstock\Presto\Presto;
use Shutterstock\Presto\Response;

class PrestoIntegrationTest extends PHPUnit_Framework_TestCase
{

    protected static $TEST_ENDPOINT = 'http://httpbin.org/';

    /**
     * @covers  Shutterstock\Presto\Presto::executeRequest
     */
    public function testExecuteRequest()
    {
        $presto = new Presto();

        $handle = curl_init(self::$TEST_ENDPOINT . 'get');
        curl_setopt_array($handle, $presto->curl_opts);
        $response = $presto->executeRequest($handle);

        $this->assertEquals(200, $response->http_code);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::makeRequest
     */
    public function testMakeRequest()
    {
        $response = (new Presto())->makeRequest(
            self::$TEST_ENDPOINT . 'get',
            array(),
            function(Response $response) {
                return $response->http_code;
            }
        );

        $this->assertEquals(200, $response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::__construct
     */
    public function testUserAgent()
    {
        $presto = new Presto();
        $response = $presto->makeRequest(
            self::$TEST_ENDPOINT . 'user-agent',
            array(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['user-agent'];
            }
        );

        $this->assertEquals($presto->curl_opts[CURLOPT_USERAGENT], $response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setHeaders
     */
    public function testHeaders()
    {
        $presto = new Presto();
        $presto->setHeaders(array(
            'X-Powered-By' => 'Awesomeness',
        ));

        $response = $presto->makeRequest(
            self::$TEST_ENDPOINT . 'headers',
            array(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['headers'];
            }
        );

        $this->assertArrayHasKey('X-Powered-By', $response);
        $this->assertEquals('Awesomeness', $response['X-Powered-By']);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::makeRequest
     */
    public function testStatusCode()
    {
        $response = (new Presto())->makeRequest(
            self::$TEST_ENDPOINT . 'status/418',
            array(),
            function(Response $response) {
                return $response->http_code;
            }
        );

        $this->assertEquals(418, $response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setAuth
     */
    public function testAuth()
    {
        $presto = new Presto();
        $presto->setAuth('username', 'password');

        $response = $presto->makeRequest(
            self::$TEST_ENDPOINT . 'basic-auth/username/password',
            array(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data;
            }
        );

        $this->assertEquals(true, $response['authenticated']);
        $this->assertEquals('username', $response['user']);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::get
     */
    public function testGetRequest()
    {
        $response = (new Presto())->get(
            self::$TEST_ENDPOINT . 'get',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['args'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = (new Presto())->get(
            self::$TEST_ENDPOINT . 'get',
            Presto::arrayToUrlParams($this->getGenericData()),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['args'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::post
     */
    public function testPostRequest()
    {
        $response = (new Presto())->post(
            self::$TEST_ENDPOINT . 'post',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = (new Presto())->post(
            self::$TEST_ENDPOINT . 'post',
            Presto::arrayToUrlParams($this->getGenericData()),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::put
     */
    public function testPutRequest()
    {
        $response = (new Presto())->put(
            self::$TEST_ENDPOINT . 'put',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = (new Presto())->put(
            self::$TEST_ENDPOINT . 'put',
            Presto::arrayToUrlParams($this->getGenericData()),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::delete
     */
    public function testDeleteRequest()
    {
        $response = (new Presto())->delete(
            self::$TEST_ENDPOINT . 'delete',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = (new Presto())->delete(
            self::$TEST_ENDPOINT . 'delete',
            Presto::arrayToUrlParams($this->getGenericData()),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::custom
     */
    public function testCustomRequest()
    {
        $response = (new Presto())->custom(
            'PATCH',
            self::$TEST_ENDPOINT . 'patch',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = (new Presto())->custom(
            'PATCH',
            self::$TEST_ENDPOINT . 'patch',
            Presto::arrayToUrlParams($this->getGenericData()),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::head
     */
    public function testHeadRequest()
    {
        $response = (new Presto())->head(self::$TEST_ENDPOINT . 'headers');
        $this->assertEquals(200, $response->http_code);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::options
     */
    public function testOptionRequest()
    {
        $response = (new Presto())->options(self::$TEST_ENDPOINT . 'headers');
        $this->assertEquals(200, $response->http_code);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::processQueue
     */
    public function testQueueProcess()
    {
        Presto::$profiling_count = 0;
        Presto::$profiling = array();

        Presto::initQueue();
        $status_codes = array(
            401,
            404,
            418,
        );

        $requests = array();
        foreach ($status_codes as $code) {
            $requests[$code] = new Presto();
            $requests[$code]->queue_enabled = true;
            $requests[$code]->get(
                self::$TEST_ENDPOINT . "status/{$code}",
                array(),
                function(Response $response) {
                    return $response->http_code;
                }
            );
        }

        $queue_result = Presto::processQueue();
        $this->assertEquals(true, $queue_result);

        $profiling = Presto::getProfiling();
        $this->assertEquals($status_codes[0], $profiling[0]['http_code']);
        $this->assertEquals($status_codes[1], $profiling[1]['http_code']);
        $this->assertEquals($status_codes[2], $profiling[2]['http_code']);
    }

    protected function getGenericData()
    {
        return array(
            'foo'   => 'bar',
            'test'  => array(
                1,
                2,
            ),
        );
    }

}

