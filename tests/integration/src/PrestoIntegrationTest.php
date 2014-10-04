<?php

use Shutterstock\Presto\Presto;
use Shutterstock\Presto\Response;

class PrestoIntegrationTest extends PHPUnit_Framework_TestCase
{

    private static $test_endpoint = 'http://httpbin.org/';

    private $presto;

    /**
     * run on start of test
     * set up presto client for all requests
     */
    protected function setUp()
    {
        $this->presto = new Presto();
    }

    /**
     * @covers  Shutterstock\Presto\Presto::makeRequest
     */
    public function testMakeRequest()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::executeRequest
     */
    public function testExecuteRequest()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::__construct
     */
    public function testUserAgent()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setHeaders
     */
    public function testHeaders()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::makeRequest
     */
    public function testStatusCode()
    {
        $response = $this->presto->get(
            self::$test_endpoint . 'status/418',
            [],
            function(Response $response) {
                return $response->http_code;
            }
        );

        $this->assertEquals(418, $response);
    }

    /**
     * @covers  Shutterstock\Presto\Response::parseHeaders
     */
    public function testHeaderParse()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setAuth
     */
    public function testAuth()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::get
     */
    public function testGetRequest()
    {
        $response = $this->presto->get(
            self::$test_endpoint . 'get',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['args'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = $this->presto->get(
            self::$test_endpoint . 'get',
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
        $response = $this->presto->post(
            self::$test_endpoint . 'post',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = $this->presto->post(
            self::$test_endpoint . 'post',
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
        $response = $this->presto->put(
            self::$test_endpoint . 'put',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = $this->presto->put(
            self::$test_endpoint . 'put',
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
        $response = $this->presto->delete(
            self::$test_endpoint . 'delete',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = $this->presto->delete(
            self::$test_endpoint . 'delete',
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
        $response = $this->presto->custom(
            'PATCH',
            self::$test_endpoint . 'patch',
            $this->getGenericData(),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['form'];
            }
        );
        $this->assertEquals($this->getGenericData(), $response);

        $response = $this->presto->custom(
            'PATCH',
            self::$test_endpoint . 'patch',
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
        $response = $this->presto->head(self::$test_endpoint . 'headers');
        $this->assertEquals(200, $response->http_code);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::options
     */
    public function testOptionRequest()
    {
        $response = $this->presto->options(self::$test_endpoint . 'headers');
        $this->assertEquals(200, $response->http_code);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::processQueue
     */
    public function testQueueProcess()
    {
        Presto::initQueue();
        $status_codes = [
            418,
            418,
        ];

        $requests = [];
        foreach ($status_codes as $code) {
            $requests[$code] = new Presto();
            $requests[$code]->queue_enabled = true;
            $requests[$code]->get(
                self::$test_endpoint . "status/{$code}",
                [],
                function(Response $response) {
                    return $response->http_code;
                }
            );
        }

        Presto::processQueue();

        var_dump($responses);
    }

    protected function getGenericData()
    {
        return [
            'foo'   => 'bar',
            'test'  => [
                1,
                2,
            ],
        ];
    }

}

