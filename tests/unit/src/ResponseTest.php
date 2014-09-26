<?php

use Shutterstock\Presto\Response;

/**
 * @covers Shutterstock\Presto\Response
 */

class ResponseTest extends PHPUnit_Framework_TestCase
{

    public function testObjectCanBeConstructed()
    {
        $response = new Response([], [], '');

        $this->assertInstanceOf('Shutterstock\\Presto\\Response', $response);
    }

}

