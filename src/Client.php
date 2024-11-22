<?php

namespace Rosreestr\Cadastral;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Rosreestr\Cadastral\Valuation\Parser;
use Rosreestr\Cadastral\Valuation\Response;

class Client
{
    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome';
    public const BASE_URL = 'https://rosreestr.gov.ru';
    public const SEARCH_CADASTRAL_URL = '/wps/portal/p/cc_ib_portal_services/cc_ib_ais_fdgko/!ut/p/z1/04_Sj9CPykssy0xPLMnMz0vMAfIjo8zi3QNNXA2dTQy93UOdzAwcPQO8nMI8nQ0MDMz1w9EUBBqaAxU4ehsaG7obGPgb6keRph9DAUi_AQ7gaADUH4VmBaoLnI0IKAA5kZAlBbmhEQaZnooANTW-bQ!!/p0/IZ7_GQ4E1C41KGUB60AIPJBVIC0080=CZ6_GQ4E1C41KGUB60AIPJBVIC0007=MEcontroller!searchObjects==/';

    private HttpClient $client;

    public function __construct()
    {
        $this->client = new HttpClient(['base_uri' => self::BASE_URL]);
    }


    /**
     * @throws GuzzleException
     */
    public function searchByCadastral(string $number): ?Response
    {
        $formData = [
            'sorting.order'           => 'asc',
            'sorting.name'            => 'default',
            'paging.pageNumber'       => '1',
            'search.type'             => 'object',
            'search.fullCadNumSearch' => 'true',
            'search.reportType'       => 'ALL',
            'search.searchString'     => $number
        ];
        $response = $this->client->post(self::SEARCH_CADASTRAL_URL, [
            RequestOptions::HEADERS     => ['User-Agent' => self::USER_AGENT],
            RequestOptions::VERIFY      => false,
            RequestOptions::FORM_PARAMS => $formData,
        ]);
        $html = $response->getBody()->getContents();
        $parser = new Parser($html);
        $data =  $parser->parse();
        if ($data === false) {
            return null;
        }
        return Response::createFromArray($data);
    }
}
