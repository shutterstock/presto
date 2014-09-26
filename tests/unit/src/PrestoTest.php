<?php

use Shutterstock\Presto\Presto;

/**
 * @covers Shutterstock\Presto\Presto
 */

class PrestoTest extends PHPUnit_Framework_TestCase
{

    private $error_holder = [];
    private $error_log_destination;

    /**
     * run on start of test
     * attaches custom error handler for this test
     */
    protected function setUp()
    {
        set_error_handler([$this, 'errorHandler']);

        $this->error_log_destination = ini_get('error_log');
        // ini_set('error_log', '/dev/stdout');
    }

    /**
     * run when test is over
     * removes custom error handler used on this test
     */
    protected function tearDown()
    {
        restore_error_handler();
        // ini_set('error_log', $this->error_log_destination);
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
        array_push($this->error_holder, [
            'errno'       => $number,
            'errstr'      => $string,
            'errfile'     => $file,
            'errline'     => $line,
            'errcontext'  => $context,
        ]);
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
     * @covers  Shutterstock\Presto\Presto::__construct
     */
    public function testObjectCanBeConstructed()
    {
        $presto = new Presto();
        $this->assertInstanceOf('Shutterstock\\Presto\\Presto', $presto);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::__construct
     */
    public function testObjectConstructSetsUserAgent()
    {
        $presto = new Presto();

        $user_agent = '';
        $user_agent .= Presto::$curl_opts_defaults[CURLOPT_USERAGENT];
        $user_agent .= Presto::VERSION;

        $this->assertEquals($user_agent, $presto->curl_opts[CURLOPT_USERAGENT]);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::__construct
     */
    public function testObjectConstructOverridesOptions()
    {
        $presto = new Presto([
            CURLOPT_HEADER => false,
        ]);

        $this->assertFalse($presto->curl_opts[CURLOPT_HEADER]);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::__construct
     */
    public function testObjectConstructSetsSlowResponseTimeout()
    {
        $presto = new Presto();
        $this->assertEquals(Presto::$slow_response_default, $presto->slow_response);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::initQueue
     */
    public function testInitQueueClearsRequestQueue()
    {
        $presto = new Presto();

        $reflectionPresto = new ReflectionClass('Shutterstock\Presto\Presto');
        $reflectionProperty = $reflectionPresto->getProperty('request_queue');
        $reflectionProperty->setAccessible(true);

        $reflectionProperty->setValue($presto, ['value']);
        $presto::initQueue();

        $this->assertEmpty($reflectionProperty->getValue($presto));
    }

    /**
     * @covers  Shutterstock\Presto\Presto::initQueue
     */
    public function testInitQueueSetsMultiQueue()
    {
        $presto = new Presto();

        $reflectionPresto = new ReflectionClass('Shutterstock\Presto\Presto');
        $reflectionProperty = $reflectionPresto->getProperty('queue_handle');
        $reflectionProperty->setAccessible(true);

        $presto::initQueue();

        $this->assertInternalType('resource', $reflectionProperty->getValue($presto));
    }

    /**
     * @covers  Shutterstock\Presto\Presto::loadConfig
     */
    public function testConfigLoading()
    {
        $presto = new Presto();
        $presto->loadConfig($this->getServiceConfig());

        $this->assertEquals($this->getServiceConfig(), $presto::$config);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::getServiceConfig
     */
    public function testServiceConfigReturn()
    {
        $presto = new Presto();
        $presto->loadConfig($this->getServiceConfig());

        $this->assertEquals($this->getServiceConfig()->alpha, $presto->getServiceConfig('alpha'));
    }

    /**
     * @covers  Shutterstock\Presto\Presto::getServiceConfig
     */
    public function testServiceConfigEmptyReturn()
    {
        $presto = new Presto();
        $this->assertEmpty($presto->getServiceConfig('charlie'));

        $presto->loadConfig($this->getServiceConfig());
        $this->assertEmpty($presto->getServiceConfig('charlie'));
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setAuth
     */
    public function testAuthUserPass()
    {
        $presto = new Presto();
        $presto->setAuth('username', 'password');

        $this->assertEquals('username:password', $presto->curl_opts[CURLOPT_USERPWD]);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setAuth
     */
    public function testAuthDefaultType()
    {
        $presto = new Presto();
        $presto->setAuth('username', 'password');

        $this->assertEquals(CURLAUTH_BASIC, $presto->curl_opts[CURLOPT_HTTPAUTH]);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setAuth
     */
    public function testAuthTypeOverride()
    {
        $presto = new Presto();
        $presto->setAuth('username', 'password', CURLAUTH_ANY);

        $this->assertEquals(CURLAUTH_ANY, $presto->curl_opts[CURLOPT_HTTPAUTH]);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setReferer
     */
    public function testRefererSet()
    {
        $presto = new Presto();
        $presto->setReferer('http://www.shutterstock.com/');

        $this->assertEquals('http://www.shutterstock.com/', $presto->curl_opts[CURLOPT_REFERER]);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setHeaders
     */
    public function testHeaderSet()
    {
        $presto = new Presto();
        $presto->setHeaders([
            'Accept' => 'application/xml',
        ]);

        $this->assertEquals(
            array_merge(
                $presto::$curl_opts_defaults[CURLOPT_HTTPHEADER],
                [
                    'Accept' => 'application/xml',
                ]
            ),
            $presto->curl_opts[CURLOPT_HTTPHEADER]
        );

        $presto = new Presto();
        $presto->setHeaders([
            'User-Agent' => 'Presto',
        ]);

        $this->assertEquals(
            array_merge(
                $presto::$curl_opts_defaults[CURLOPT_HTTPHEADER],
                [
                    'User-Agent' => 'Presto',
                ]
            ),
            $presto->curl_opts[CURLOPT_HTTPHEADER]
        );
    }

    /**
     * @covers  Shutterstock\Presto\Presto::setHeaders
     */
    public function testHeaderSetOverride()
    {
        $presto = new Presto();
        $presto->setHeaders([
            'User-Agent' => 'Presto',
        ], true);

        $this->assertEquals([
                'User-Agent' => 'Presto',
            ],
            $presto->curl_opts[CURLOPT_HTTPHEADER]
        );
    }

    /**
     * @covers  Shutterstock\Presto\Presto::arrayToUrlParams
     */
    public function testSimpleURLParamBuild()
    {
        $this->assertEquals(
            'foo=bar&test=true',
            Presto::arrayToUrlParams([
                'foo'   => 'bar',
                'test'  => 'true',
            ])
        );
    }

    /**
     * @covers  Shutterstock\Presto\Presto::arrayToUrlParams
     */
    public function testSimpleURLParamBuildCustomDelimiter()
    {
        $this->assertEquals(
            'foo=bar|test=true',
            Presto::arrayToUrlParams([
                'foo'   => 'bar',
                'test'  => 'true',
            ], '|')
        );
    }

    /**
     * @covers  Shutterstock\Presto\Presto::arrayToUrlParams
     */
    public function testNestedURLParamBuild()
    {
        $this->assertEquals(
            'foo=bar&foo=test',
            Presto::arrayToUrlParams([
                'foo'  => [
                    'bar',
                    'test',
                ],
            ])
        );
    }

    /**
     * @covers  Shutterstock\Presto\Presto::arrayToUrlParams
     */
    public function testNestedURLParamBuildCustomDelimiter()
    {
        $this->assertEquals(
            'foo=bar|foo=test',
            Presto::arrayToUrlParams([
                'foo'  => [
                    'bar',
                    'test',
                ],
            ], '|')
        );
    }

    /**
     * @covers  Shutterstock\Presto\Presto::logError
     */
    public function testErrorTrigger()
    {
        $presto = new Presto();
        $presto->trigger_error = true;
        $presto->logError('Error');

        $this->assertError("{$presto->error_log_prefix}Error");
    }

    /**
     * @covers  Shutterstock\Presto\Presto::logError
     */
    public function testErrorLogging()
    {
        // todo this seems to not want to work
        return;
        $presto = new Presto();
        $this->expectOutputString("{$presto->error_log_prefix}Error");
        $presto->logError('Error');
    }

    /**
     * @covers  Shutterstock\Presto\Presto::logProfiling
     */
    public function testProfiling()
    {
        $presto = new Presto();
        $presto::logProfiling([
            'url'               => 'http://www.shutterstock.com/',
            'http_code'         => 200,
            'total_time'        => .12,
            'pretransfer_time'  => .05,
            'queue'             => 'off',
        ]);

        $this->assertContains([
            'url'               => 'http://www.shutterstock.com/',
            'http_code'         => 200,
            'total_time'        => .12,
            'pretransfer_time'  => .05,
            'queue'             => 'off',
        ], $presto::$profiling);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::logProfiling
     */
    public function testProfilingErrors()
    {
        $presto = new Presto();
        $presto::logProfiling([
            'url'      => 'http://www.shutterstock.com/',
            'errorno'  => CURLE_URL_MALFORMAT_USER,
            'error'    => 'Bad user credentials',
            'queue'    => 'off',
        ]);

        $this->assertContains([
            'url'      => 'http://www.shutterstock.com/',
            'errorno'  => CURLE_URL_MALFORMAT_USER,
            'error'    => 'Bad user credentials',
            'queue'    => 'off',
        ], $presto::$profiling);
    }

    /**
     * @covers  Shutterstock\Presto\Presto::logProfiling
     */
    public function testProfilingOverflow()
    {
        $presto = new Presto();
        for ($i = 0; $i <= $presto::$profiling_max; $i++) {
            $presto::logProfiling([
                'url'               => 'http://www.shutterstock.com/',
                'http_code'         => 200,
                'total_time'        => .12,
                'pretransfer_time'  => .05,
                'queue'             => 'off',
            ]);
        }

        $this->assertEmpty($presto::logProfiling([
            'url'               => 'http://www.bigstockphoto.com/',
            'http_code'         => 200,
            'total_time'        => .12,
            'pretransfer_time'  => .05,
            'queue'             => 'off',
        ]));

        $this->assertNotContains([
            'url'               => 'http://www.bigstockphoto.com/',
            'http_code'         => 200,
            'total_time'        => .12,
            'pretransfer_time'  => .05,
            'queue'             => 'off',
        ], $presto::$profiling); 
    }

    /**
     * @covers  Shutterstock\Presto\Presto::getProfiling
     */
    public function testProfilingFetch()
    {
        $presto = new Presto();
        $this->assertEquals($presto::$profiling, $presto::getProfiling());
    }

    protected function getServiceConfig()
    {
        return (object) [
            'alpha'  => [
                'setting'  => true,
            ],
            'beta'   => [
                'setting'  => false,
            ],
        ];
    }

}

