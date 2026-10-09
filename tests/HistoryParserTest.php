<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Tests;

use PHPUnit\Framework\TestCase;
use Rosreestr\Cadastral\Valuation\HistoryParser;
use UnexpectedValueException;

final class HistoryParserTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fixture(string $file = 'history-six-records.json'): array
    {
        return json_decode(
            (string) file_get_contents(__DIR__ . '/Fixtures/' . $file),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function testParsesFullHistoryWithoutLosingDuplicateValuations(): void
    {
        $history = (new HistoryParser())->parse(
            $this->fixture(),
            '78:11:0006113:5691',
        );

        self::assertNotNull($history);
        self::assertSame(6, $history->getTotalCount());
        self::assertCount(6, $history->getItems());
        self::assertSame('78:11:0006113:5691', $history->getCadastralNumber());

        $items = $history->getItems();
        $latest = $history->getCurrent();

        self::assertSame($items[0], $latest);
        self::assertSame('2025-01-01', $latest->determinationDate);
        self::assertSame('2026-01-01', $latest->applicationDate);
        self::assertSame('2025-12-17', $latest->infoEntryDate);
        self::assertSame(23690357.06, $latest->cadastralValue->value);
        self::assertSame('23690357.06', $latest->cadastralValue->decimal);
        self::assertNotEmpty($latest->costRecordId);
        self::assertNotEmpty($latest->references['proceduresId']);
        self::assertCount(1, $latest->files);
        self::assertStringContainsString('Акт об утверждении', (string) $latest->determinationBasis);
        self::assertStringContainsString('Акт об утверждении', (string) $latest->determinationName->text);

        self::assertSame('2023-01-01', $items[1]->determinationDate);
        self::assertSame('2023-01-01', $items[2]->determinationDate);
        self::assertSame($items[1]->cadastralValue->decimal, $items[2]->cadastralValue->decimal);
        self::assertNotSame($items[1]->costRecordId, $items[2]->costRecordId);
        self::assertSame('6193600.42', $items[3]->cadastralValue->decimal);
        self::assertSame('14723539.81', $items[4]->cadastralValue->decimal);
        self::assertNull($items[4]->applicationDate);
        self::assertNull($items[5]->determinationDate);
        self::assertSame('12332049.99', $items[5]->cadastralValue->decimal);

        $encoded = json_decode(json_encode($history, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(6, $encoded['totalCount']);
        self::assertCount(6, $encoded['items']);
    }

    public function testEmptyHistoryReturnsNull(): void
    {
        self::assertNull((new HistoryParser())->parse(
            ['amount' => 0, 'data' => []],
            '78:11:0006113:5691',
        ));
    }

    public function testRejectsMismatchedCadastralNumber(): void
    {
        $payload = $this->fixture();
        $payload['data'][0]['cadNumber'] = '77:01:0000001:123';
        $this->expectException(UnexpectedValueException::class);
        (new HistoryParser())->parse($payload, '78:11:0006113:5691');
    }

    public function testRejectsMalformedResponseEnvelope(): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new HistoryParser())->parse(['amount' => 5], '78:11:0006113:5691');
    }

    public function testRejectsNonNumericValuation(): void
    {
        $payload = $this->fixture();
        $payload['data'][0]['sysName']['value'] = 'not a number';
        $this->expectException(UnexpectedValueException::class);
        (new HistoryParser())->parse($payload, '78:11:0006113:5691');
    }

    public function testRejectsInvalidCalendarDate(): void
    {
        $payload = $this->fixture();
        $payload['data'][0]['sysName']['determinationDate'] = '2025-02-31T00:00:00Z';
        $this->expectException(UnexpectedValueException::class);
        (new HistoryParser())->parse($payload, '78:11:0006113:5691');
    }

    public function testNormalizesDatesAndMoney(): void
    {
        self::assertSame('2026-01-01', HistoryParser::normalizeDate('01.01.2026'));
        self::assertSame('2025-01-01', HistoryParser::normalizeDate('2025-01-01T00:00:00Z'));
        self::assertNull(HistoryParser::normalizeDate('-'));
        self::assertSame('23690357.06', HistoryParser::normalizeMoney("23 690 357,06"));
        self::assertSame('23690357.06', HistoryParser::normalizeMoney("23\u{00A0}690\u{202F}357,06"));
        self::assertSame('0', HistoryParser::normalizeMoney(0));
    }
}
