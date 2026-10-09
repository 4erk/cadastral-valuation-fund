<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Tests;

use PHPUnit\Framework\TestCase;
use Rosreestr\Cadastral\Valuation\Parser;
use Rosreestr\Cadastral\Valuation\Response;
use UnexpectedValueException;

final class LegacyParserTest extends TestCase
{
    public function testParsesOldHtmlTableWithoutDamagingDatesOrLinks(): void
    {
        $html = <<<'HTML'
<table><tbody>
<tr>
<td>01.01.2025</td>
<td><a href="/history/valuation">23 690 357,06</a></td>
<td>17.12.2025</td>
<td>01.01.2026</td>
<td>Акт бюджетного учреждения</td>
<td><a href="/document/approval">Акт об утверждении стоимости</a></td>
<td>-</td>
</tr>
</tbody></table>
HTML;
        $records = (new Parser($html))->parse();
        self::assertIsArray($records);
        self::assertCount(1, $records);
        self::assertSame('2025-01-01', $records[0]['determinationDate']);
        self::assertSame('2025-12-17', $records[0]['infoEntryDate']);
        self::assertSame('2026-01-01', $records[0]['applicationDate']);
        self::assertSame(23690357.06, $records[0]['cadastralValue']['value']);
        self::assertSame('https://rosreestr.gov.ru/history/valuation', $records[0]['cadastralValue']['link']);
        self::assertSame('https://rosreestr.gov.ru/document/approval', $records[0]['determinationName']['url']);
        self::assertNull($records[0]['approvalDetails']);

        $response = Response::createFromArray($records);
        self::assertCount(1, $response->getItems());
        self::assertSame(23690357.06, $response->getItems()[0]->cadastralValue->value);
    }

    public function testNoTableReturnsFalse(): void
    {
        self::assertFalse((new Parser('<div>nothing to parse</div>'))->parse());
    }

    public function testIncompleteRowIsRejected(): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new Parser('<table><tbody><tr><td>wrong</td></tr></tbody></table>'))->parse();
    }

    public function testEmptyLinkDoesNotInventUrl(): void
    {
        $html = '<table><tbody><tr><td>-</td><td>12,34</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr></tbody></table>';
        $row = (new Parser($html))->parse()[0];

        self::assertSame(12.34, $row['cadastralValue']['value']);
        self::assertNull($row['cadastralValue']['link']);
        self::assertNull($row['determinationName']['url']);
        self::assertNull($row['determinationDate']);
    }
}
