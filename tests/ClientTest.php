<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as HttpResponse;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rosreestr\Cadastral\Client;
use RuntimeException;
use UnexpectedValueException;

final class ClientTest extends TestCase
{
    private array $requests = [];

    private function mockClient(HttpResponse ...$responses): Client
    {
        $this->requests = [];
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->requests));
        $http = new HttpClient(['handler' => $handler, 'base_uri' => Client::BASE_URL]);
        return new Client($http);
    }

    private function jsonFixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/Fixtures/' . $name);
    }

    public function testSearchUsesCurrentV3HistoryEndpoint(): void
    {
        $client = $this->mockClient(new HttpResponse(
            200,
            ['Content-Type' => 'application/json'],
            $this->jsonFixture('history-six-records.json'),
        ));

        $history = $client->searchByCadastral(' 78:11:0006113:5691 ');
        self::assertNotNull($history);
        self::assertCount(6, $history->getItems());
        self::assertCount(1, $this->requests);
        self::assertSame('GET', $this->requests[0]['request']->getMethod());
        self::assertSame(Client::HISTORY_PATH, $this->requests[0]['request']->getUri()->getPath());
        parse_str($this->requests[0]['request']->getUri()->getQuery(), $query);
        self::assertSame('78:11:0006113:5691', $query['cadNumber']);
    }

    public function testGraphAndCurrentDetailsAreSeparateFromHistory(): void
    {
        $client = $this->mockClient(
            new HttpResponse(200, [], $this->jsonFixture('history-diagram.json')),
            new HttpResponse(200, [], $this->jsonFixture('current-room.json')),
        );

        $graph = $client->getHistoryDiagram('78:11:0006113:5691');
        self::assertCount(4, $graph['data']);
        self::assertFalse($graph['data'][2]['displayDiagram']);

        $current = $client->getCurrentDetails('78:11:0006113:5691', 'room');
        self::assertNotEmpty($current['attribsValue']);
        self::assertSame(Client::DIAGRAM_PATH, $this->requests[0]['request']->getUri()->getPath());
        self::assertSame(Client::CURRENT_PATH, $this->requests[1]['request']->getUri()->getPath());
        parse_str($this->requests[1]['request']->getUri()->getQuery(), $query);
        self::assertSame('room', $query['kind']);
    }

    public function testRecordCardUsesOnlyValidatedProviderReferenceFields(): void
    {
        $client = $this->mockClient(
            new HttpResponse(200, [], $this->jsonFixture('history-six-records.json')),
            new HttpResponse(200, [], '{"name":"valuation card"}'),
        );

        $history = $client->searchByCadastral('78:11:0006113:5691');
        $card = $client->getRecordDetails($history->getItems()[0]);

        self::assertSame('valuation card', $card['name']);
        self::assertSame(Client::CARD_PATH, $this->requests[1]['request']->getUri()->getPath());
        parse_str($this->requests[1]['request']->getUri()->getQuery(), $query);
        self::assertArrayHasKey('proceduresId', $query);
    }

    public function testEmptyResultsRemainNullForLegacyApiCompatibility(): void
    {
        $client = $this->mockClient(new HttpResponse(200, [], '{"amount":0,"data":[]}'));
        self::assertNull($client->searchByCadastral('78:11:0006113:5691'));
    }

    public function testInvalidNumberIsRejectedBeforeNetworkRequest(): void
    {
        $client = $this->mockClient();
        $this->expectException(InvalidArgumentException::class);
        $client->searchByCadastral('78:11:0006113:5691?admin=true');
    }

    public function testInvalidKindIsRejected(): void
    {
        $client = $this->mockClient();
        $this->expectException(InvalidArgumentException::class);
        $client->getCurrentDetails('78:11:0006113:5691', '../admin');
    }

    public function testHttpErrorIsNotMisrepresentedAsNoResults(): void
    {
        $client = $this->mockClient(new HttpResponse(503, [], '<h1>maintenance</h1>'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(503);
        $client->searchByCadastral('78:11:0006113:5691');
    }

    public function testInvalidJsonIsNotSilentlyAccepted(): void
    {
        $client = $this->mockClient(new HttpResponse(200, [], '<html>blocked</html>'));
        $this->expectException(UnexpectedValueException::class);
        $client->searchByCadastral('78:11:0006113:5691');
    }
}
