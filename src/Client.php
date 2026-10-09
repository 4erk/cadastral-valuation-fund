<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use JsonException;
use Rosreestr\Cadastral\Valuation\HistoryParser;
use Rosreestr\Cadastral\Valuation\Item;
use Rosreestr\Cadastral\Valuation\Response;
use RuntimeException;
use UnexpectedValueException;

final class Client
{
    public const BASE_URL = 'https://nspd.gov.ru';
    public const HISTORY_PATH = '/api/data-fund/v3/cadastral-value-history-table';
    public const DIAGRAM_PATH = '/api/data-fund/v2/cadastral-history-diagram';
    public const CURRENT_PATH = '/api/data-fund/v1/cadastral-now';
    public const CARD_PATH = '/api/data-fund/v3/cadastral-value-card';
    public const USER_AGENT = 'Mozilla/5.0 (compatible; RosreestrCadastral/1.1; +https://github.com/4erk/cadastral-valuation-fund)';

    private readonly ClientInterface $http;
    private readonly HistoryParser $parser;

    /**
     * The HTTP client is injectable for offline tests and alternative transports.
     *
     * A CADASTRAL_PROXY environment variable can point to an SSH SOCKS5 tunnel
     * (e.g. socks5h://127.0.0.1:18749). The environment controls networking;
     * no private proxy credentials are embedded in this library.
     *
     * @param array<string, mixed> $httpOptions Additional Guzzle options.
     */
    public function __construct(
        ?ClientInterface $http = null,
        ?HistoryParser $parser = null,
        array $httpOptions = [],
    ) {
        $proxy = trim((string) getenv('CADASTRAL_PROXY'));
        $defaultOptions = [
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Referer' => self::BASE_URL . '/cadastral-price/search',
                'User-Agent' => self::USER_AGENT,
            ],
        ];
        if ($proxy !== '') {
            $defaultOptions['proxy'] = $proxy;
        }
        $this->http = $http ?? new HttpClient(array_replace($defaultOptions, $httpOptions));
        $this->parser = $parser ?? new HistoryParser();
    }

    /**
     * Fetch every historical valuation record for the cadastral number.
     * Retains distinct records even when two valuations have the same price/date.
     * Returns null only when the provider supplies an empty list.
     */
    public function searchByCadastral(string $number): ?Response
    {
        $number = self::normalizeNumber($number);
        return $this->parser->parse(
            $this->requestJson(self::HISTORY_PATH, ['cadNumber' => $number]),
            $number,
        );
    }

    /**
     * Raw graph data: data[] {applicationDate, determinationDate, cost, displayDiagram}.
     * This is a different view from the full historical record table.
     *
     * @return array<string, mixed>
     */
    public function getHistoryDiagram(string $number): array
    {
        return $this->requestJson(self::DIAGRAM_PATH, [
            'cadNumber' => self::normalizeNumber($number),
        ]);
    }

    /**
     * Current valuation and object attributes as named entries in attribsValue[].
     * Kind is NSPD's object kind (land, room, build, construction, etc.).
     *
     * @return array<string, mixed>
     */
    public function getCurrentDetails(string $number, string $kind): array
    {
        $allowed = [
            'land', 'room', 'property_complex', 'unified_real_estate',
            'object_under_construction', 'build', 'construction',
            'car_parking_space', 'right', 'restrict', 'deal',
            'zones_and_territories', 'build_part', 'land_part',
            'room_part', 'construction_part',
        ];
        if (!in_array($kind, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported cadastral object kind.');
        }

        return $this->requestJson(self::CURRENT_PATH, [
            'cadNumber' => self::normalizeNumber($number),
            'kind' => $kind,
        ]);
    }

    /**
     * Follow the provider's record reference to its detailed valuation card.
     * Available only for records that contain a link to an NSPD procedure.
     *
     * @return array<string, mixed>
     */
    public function getRecordDetails(Item $item): array
    {
        if ($item->references === [] || empty($item->references['proceduresId'])) {
            throw new \InvalidArgumentException('This valuation record has no detail-card reference.');
        }

        return $this->requestJson(self::CARD_PATH, $item->references);
    }

    private static function normalizeNumber(string $number): string
    {
        $number = trim($number);
        if (!preg_match('/^\d{2}:\d{2}:\d{6,7}:\d+$/D', $number)) {
            throw new \InvalidArgumentException('Invalid cadastral number.');
        }

        return $number;
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    private function requestJson(string $path, array $query): array
    {
        $response = $this->http->request('GET', $path, [
            RequestOptions::QUERY => $query,
            RequestOptions::HTTP_ERRORS => false,
        ]);
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('NSPD Data Fund request failed (HTTP %d).', $status), $status);
        }

        $body = (string) $response->getBody();
        if (strlen($body) > 5_000_000) {
            throw new UnexpectedValueException('NSPD response exceeds the allowed size.');
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException('Invalid NSPD JSON response.', 0, $e);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new UnexpectedValueException('Unexpected NSPD response envelope.');
        }

        return $data;
    }
}
