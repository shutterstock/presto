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
        // todo
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
            [
                'foo'   => 'bar',
                'test'  => 1,
                'test'  => 2,
            ],
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['args'];
            }
        );

        $this->assertEquals([
            'foo'   => 'bar',
            'test'  => 1,
            'test'  => 2,
        ], $response);

        $response = $this->presto->get(
            self::$test_endpoint . 'get',
            Presto::arrayToUrlParams([
                'foo'   => 'bar',
                'test'  => [
                    1,
                    2,
                ],
            ]),
            function(Response $response) {
                $data = $response->data;
                $data = json_decode($data, true);
                return $data['args'];
            }
        );

        $this->assertEquals([
            'foo'   => 'bar',
            'test'  => [
                1,
                2,
            ],
        ], $response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::post
     */
    public function testPostRequest()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::put
     */
    public function testPutRequest()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::delete
     */
    public function testDeleteRequest()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::custom
     */
    public function testCustomRequest()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::head
     */
    public function testHeadRequest()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::options
     */
    public function testOptionRequest()
    {
        // todo
    }

    /**
     * @covers  Shutterstock\Presto\Presto::processQueue
     */
    public function testQueueProcess()
    {
        // todo
    }

}

