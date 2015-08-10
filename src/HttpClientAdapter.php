<?php

namespace WyriHaximus\React\RingPHP;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Ring\Future\FutureArray;
use Psr\Http\Message\ResponseInterface;
use React\Dns\Resolver\Factory as DnsFactory;
use React\Dns\Resolver\Resolver as DnsResolver;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client as HttpClient;
use React\HttpClient\Factory as HttpClientFactory;
use WyriHaximus\React\Guzzle\HttpClient\RequestFactory;

class HttpClientAdapter
{
    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var DnsResolver
     */
    protected $dnsResolver;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @param LoopInterface $loop
     * @param HttpClient $httpClient
     * @param DnsResolver $dnsResolver
     * @param RequestFactory $requestFactory
     */
    public function __construct(
        LoopInterface $loop,
        HttpClient $httpClient = null,
        DnsResolver $dnsResolver = null,
        RequestFactory $requestFactory = null
    ) {
        $this->loop = $loop;

        $this->setDnsResolver($dnsResolver);
        $this->setHttpClient($httpClient);
        $this->setRequestFactory($requestFactory);
    }

    /**
     * @param HttpClient $httpClient
     */
    public function setHttpClient(HttpClient $httpClient = null)
    {
        if (!($httpClient instanceof HttpClient)) {
            $this->setDnsResolver($this->dnsResolver);

            $factory = new HttpClientFactory();
            $httpClient = $factory->create($this->loop, $this->dnsResolver);
        }

        $this->httpClient = $httpClient;
    }

    /**
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @param DnsResolver $dnsResolver
     */
    public function setDnsResolver(DnsResolver $dnsResolver = null)
    {
        if (!($dnsResolver instanceof DnsResolver)) {
            $dnsResolverFactory = new DnsFactory();
            $dnsResolver = $dnsResolverFactory->createCached('8.8.8.8', $this->loop);
        }

        $this->dnsResolver = $dnsResolver;
    }

    /**
     * @return DnsResolver
     */
    public function getDnsResolver()
    {
        return $this->dnsResolver;
    }

    /**
     * @param RequestFactory $requestFactory
     */
    public function setRequestFactory(RequestFactory $requestFactory = null)
    {
        if (!($requestFactory instanceof RequestFactory)) {
            $requestFactory = new RequestFactory();
        }

        $this->requestFactory = $requestFactory;
    }

    /**
     * @return RequestFactory
     */
    public function getRequestFactory()
    {
        return $this->requestFactory;
    }

    /**
     * @param array $requestArray
     * @return FutureArray
     */
    public function __invoke(array $requestArray)
    {
        $request = new Request(
            $requestArray['http_method'],
            $requestArray['url'],
            $requestArray['headers'],
            $requestArray['body'],
            $requestArray['version']
        );
        $ready = false;
        $httpRequest = $this->requestFactory->create($request, $requestArray['client'], $this->httpClient, $this->loop);
        return new FutureArray($httpRequest->then(function (ResponseInterface $response) use (&$ready, $requestArray) {
            $ready = true;
            $responseArray = [
                'effective_url' => $requestArray['url'],
                'body' => $response->getBody(),
                'headers' => $response->getHeaders(),
                'status' => $response->getStatusCode(),
                'reason' => $response->getReasonPhrase(),
                'version' => $response->getProtocolVersion(),
            ];
            return $responseArray;
        }, function ($error) use (&$ready) {
            $ready = true;
            return [
                'error' => $error,
            ];
        }), function () use (&$ready) {
            do {
                $this->loop->tick();
            } while (!$ready);
        });
    }
}
