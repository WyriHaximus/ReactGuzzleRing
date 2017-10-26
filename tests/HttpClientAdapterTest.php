<?php

/**
 * This file is part of ReactGuzzle.
 *
 ** (c) 2014 Cees-Jan Kiewiet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace WyriHaximus\React\Tests\RingPHP;

use Phake;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\EventLoop\Factory;
use React\Promise\FulfilledPromise;
use React\Promise\RejectedPromise;
use WyriHaximus\React\RingPHP\HttpClientAdapter;

/**
 * Class HttpClientAdapterTest
 *
 * @package WyriHaximus\React\Tests\Guzzle
 */
class HttpClientAdapterTest extends \PHPUnit_Framework_TestCase
{

    protected $requestArray;
    protected $loop;
    protected $requestFactory;
    protected $dnsResolver;
    protected $httpClient;
    protected $adapter;

    public function setUp()
    {
        parent::setUp();

        $this->requestArray = [
            'http_method' => 'GET',
            'url' => 'http://example.com/',
            'headers' => [],
            'body' => 'abc',
            'version' => '1.1',
            'client' => [],
        ];
        $this->loop = Factory::create();
        $this->requestFactory = Phake::mock('WyriHaximus\React\Guzzle\HttpClient\RequestFactory');
        $this->dnsResolver = (new ResolverFactory())->createCached('8.8.8.8', $this->loop);
        $this->httpClient = Phake::partialMock(
            'React\HttpClient\Client',
            Phake::mock('React\EventLoop\LoopInterface'),
            Phake::mock('React\Socket\ConnectorInterface')
        );

        $this->adapter = new HttpClientAdapter($this->loop, $this->httpClient, null, $this->requestFactory);
    }

    public function tearDown()
    {
        parent::tearDown();

        unset($this->adapter, $this->request, $this->httpClient, $this->requestFactory, $this->dnsResolver, $this->loop);
    }

    public function testSend()
    {
        Phake::when($this->requestFactory)->create($this->isInstanceOf('GuzzleHttp\Psr7\Request'), [], $this->dnsResolver, $this->httpClient, $this->loop)->thenReturn(
            new FulfilledPromise(Phake::mock('Psr\Http\Message\ResponseInterface'))
        );

        $adapter = $this->adapter;
        $futureArray = $adapter($this->requestArray);
        $this->assertInstanceOf('GuzzleHttp\Ring\Future\FutureArray', $futureArray);
        $callbackFired = false;
        $futureArray->then(function () use (&$callbackFired) {
            $callbackFired = true;
        });
        $futureArray->wait();

        Phake::inOrder(
            Phake::verify($this->requestFactory, Phake::times(1))->create(
                $this->isInstanceOf('GuzzleHttp\Psr7\Request'),
                [],
                $this->dnsResolver,
                $this->httpClient,
                $this->loop
            )
        );

        $this->assertTrue($callbackFired);
    }

    public function testSendFailed()
    {
        Phake::when($this->requestFactory)->create($this->isInstanceOf('GuzzleHttp\Psr7\Request'), [], $this->dnsResolver, $this->httpClient, $this->loop)->thenReturn(
            new RejectedPromise(123)
        );

        $adapter = $this->adapter;
        $futureArray = $adapter($this->requestArray);
        $this->assertInstanceOf('GuzzleHttp\Ring\Future\FutureArray', $futureArray);
        $callbackFired = false;
        $futureArray->then(function ($error) use (&$callbackFired) {
            $this->assertEquals([
                'error' => 123,
            ], $error);
            $callbackFired = true;
        });

        Phake::inOrder(
            Phake::verify($this->requestFactory, Phake::times(1))->create(
                $this->isInstanceOf('GuzzleHttp\Psr7\Request'),
                [],
                $this->dnsResolver,
                $this->httpClient,
                $this->loop
            )
        );

        $this->assertTrue($callbackFired);
    }

    public function testSetDnsResolver()
    {
        $this->adapter->setDnsResolver();
        $this->assertInstanceOf('React\Dns\Resolver\Resolver', $this->adapter->getDnsResolver());

        $mock = Phake::partialMock(
            'React\Dns\Resolver\Resolver',
            Phake::mock('React\Dns\Query\ExecutorInterface'),
            Phake::mock('React\Dns\Query\ExecutorInterface')
        );
        $this->adapter->setDnsResolver($mock);
        $this->assertSame($mock, $this->adapter->getDnsResolver());
    }

    public function testSetHttpClient()
    {
        $this->adapter->setHttpClient();
        $this->assertInstanceOf('React\HttpClient\Client', $this->adapter->getHttpClient());

        $mock = Phake::partialMock(
            'React\HttpClient\Client',
            Phake::mock('React\EventLoop\LoopInterface'),
            Phake::mock('React\Socket\ConnectorInterface')
        );
        $this->adapter->setHttpClient($mock);
        $this->assertSame($mock, $this->adapter->getHttpClient());
    }

    public function testSetRequestFactory()
    {
        $this->adapter->setRequestFactory();
        $this->assertInstanceOf(
            'WyriHaximus\React\Guzzle\HttpClient\RequestFactory',
            $this->adapter->getRequestFactory()
        );

        $mock = Phake::mock('WyriHaximus\React\Guzzle\HttpClient\RequestFactory');
        $this->adapter->setRequestFactory($mock);
        $this->assertSame($mock, $this->adapter->getRequestFactory());
    }
}
