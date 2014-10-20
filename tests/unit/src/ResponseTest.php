<?php

use Shutterstock\Presto\Response;

/**
 * @covers Shutterstock\Presto\Response
 */

class ResponseTest extends PHPUnit_Framework_TestCase
{

    private $error_holder = array();

    /**
     * run on start of test
     * attaches custom error handler for this test
     */
    protected function setUp()
    {
        set_error_handler(array($this, 'errorHandler'));
    }

    /**
     * run when test is over
     * removes custom error handler used on this test
     */
    protected function tearDown()
    {
        restore_error_handler();
    }

    /**
     * custom error handler for this test
     * saves error information into local var for later comparison
     */
    public function errorHandler(
        $number,
        $string,
        $file,
        $line,
        $context
    ) {
        array_push($this->error_holder, array(
            'errno'       => $number,
            'errstr'      => $string,
            'errfile'     => $file,
            'errline'     => $line,
            'errcontext'  => $context,
        ));
    }

    /**
     * custom assertion for raw errors
     * makes sure that a defined error has been thrown during runtime
     */
    public function assertError($string, $number = E_USER_NOTICE)
    {
        foreach ($this->error_holder as $error) {
            if ($error['errstr'] == $string && $error['errno'] == $number) {
                return;
            }
        }

        $this->fail("Error with level {$number} and message {$string}");
    }

    /**
     * @covers  Shutterstock\Presto\Response::__construct
     */
    public function testObjectCanBeConstructed()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData(),
            $this->getTestHeader()
        );

        $this->assertInstanceOf('Shutterstock\\Presto\\Response', $response);
    }

    /**
     * @covers  Shutterstock\Presto\Response::__construct
     */
    public function testObjectConstructSetsMeta()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData(),
            $this->getTestHeader()
        );

        $this->assertEquals($this->getTestMeta(), $response->meta);
    }

    /**
     * @covers  Shutterstock\Presto\Response::__construct
     */
    public function testObjectConstructSetsData()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData(),
            $this->getTestHeader()
        );

        $this->assertEquals($this->getTestData(), $response->data);
    }

    /**
     * @covers  Shutterstock\Presto\Response::__construct
     */
    public function testObjectConstructParsesHeader()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData(),
            $this->getTestHeader()
        );

        $header = $this->getTestHeader();
        $header = $response->parseHeader($header);

        $this->assertEquals($header, $response->header);
    }

    /**
     * @covers  Shutterstock\Presto\Response::__construct
     */
    public function testObjectConstructHasEmptyHeader()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData()
        );

        $this->assertEquals(array(), $response->header);
    }

    /**
     * @covers  Shutterstock\Presto\Response::__get
     */
    public function testMagicMethodWithMeta()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData(),
            $this->getTestHeader()
        );

        $this->assertEquals($response->url, $this->getTestMeta()['url']);
    }

    /**
     * @covers  Shutterstock\Presto\Response::__get
     */
    public function testMagicMethodWithHeader()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData(),
            $this->getTestHeader()
        );

        $header = $this->getTestHeader();
        $header = $response->parseHeader($header);

        $this->assertEquals($response->Server, $header['Server']);
    }

    /**
     * @covers  Shutterstock\Presto\Response::__get
     */
    public function testMagicMethodWithEmpty()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData(),
            $this->getTestHeader()
        );

        $response->emptyKey;
        $this->assertError('PRESTO RESPONSE: reference to invalid meta key - emptyKey');
    }

    /**
     * @covers  Shutterstock\Presto\Response::parseHeader
     */
    public function testHeaderParse()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData(),
            $this->getTestHeader()
        );

        $header = $this->getTestHeader();
        $header = $response->parseHeader($header);

        $this->assertEquals('gunicorn/18.0', $header['Server']);
    }

    /**
     * @covers  Shutterstock\Presto\Response::parseHeader
     */
    public function testHeaderParseWithEmpty()
    {
        $response = new Response(
            $this->getTestMeta(),
            $this->getTestData(),
            $this->getTestHeader()
        );

        $header = '';
        $header = $response->parseHeader($header);

        $this->assertEquals(array(), $header);
    }

    /**
     * meta taken from a request to httpbin, slightly modified
     */
    protected function getTestMeta()
    {
        return array(
            'url'                      => 'http://httpbin.org/status/418',
            'content_type'             => NULL,
            'http_code'                => 418,
            'header_size'              => 255,
            'request_size'             => 168,
            'filetime'                 => -1,
            'ssl_verify_result'        => 0,
            'redirect_count'           => 0,
            'total_time'               => 0.12933499999999998,
            'namelookup_time'          => 0.00046999999999999999,
            'connect_time'             => 0.059146999999999998,
            'pretransfer_time'         => 0.059159000000000003,
            'size_upload'              => 0,
            'size_download'            => 135,
            'speed_download'           => 1043,
            'speed_upload'             => 0,
            'download_content_length'  => 135,
            'upload_content_length'    => 0,
            'starttransfer_time'       => 0.12923899999999999,
            'redirect_time'            => 0,
            'redirect_url'             => '',
            'primary_ip'               => '127.0.0.1',
            'certinfo'                 => array(),
            'primary_port'             => 80,
            'local_ip'                 => '127.0.0.1',
            'local_port'               => 8000,
            'is_success'               => true,
            'queue'                    => 'off',
        );
    }

    /**
     * data taken from a request to httpbin, slightly modified
     */
    protected function getTestData()
    {
        $data = '';

        $data .= "\r\n";
        $data .= "    -=[ teapot ]=-\r\n";
        $data .= "\r\n";
        $data .= "       _...._\r\n";
        $data .= "     .'  _ _ `.\r\n";
        $data .= "    | .\"` ^ `\". _,\r\n";
        $data .= "    \\_;`\"---\"`|//\r\n";
        $data .= "      |       ;/\r\n";
        $data .= "      \\_     _/\r\n";
        $data .= "        `\"\"\"`\r\n";

        return $data;
    }

    /**
     * header taken from a request to httpbin, slightly modified
     */
    protected function getTestHeader()
    {
        $header = '';

        $header .= "Access-Control-Allow-Credentials:true\r\n";
        $header .= "Access-Control-Allow-Origin: *\r\n";
        $header .= "Date: Fri, 26 Sep 2014 14:22:56 GMT\r\n";
        $header .= "Server: gunicorn/18.0\r\n";
        $header .= "X-More-Info: http://tools.ietf.org/html/rfc2324\r\n";
        $header .= "Content-Length: 135\r\n";
        $header .= "Connection: keep-alive\r\n";

        return $header;
    }

}

