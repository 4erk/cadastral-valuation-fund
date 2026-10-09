<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Valuation;

use DateTimeImmutable;
use UnexpectedValueException;

/**
 * Adapts the current NSPD Data Fund v3 history table to the library's model.
 * No network access: parsing can be tested against captured/synthetic fixtures.
 */
final class HistoryParser
{
    /** @param array<string, mixed> $payload */
    public function parse(array $payload, string $requestedNumber): ?Response
    {
        if (!isset($payload['data']) || !is_array($payload['data']) || !array_is_list($payload['data'])) {
            throw new UnexpectedValueException('NSPD valuation history must contain a data array.');
        }

        $items = [];
        foreach ($payload['data'] as $record) {
            if (!is_array($record) || array_is_list($record)) {
                throw new UnexpectedValueException('Invalid NSPD valuation history record.');
            }
            $items[] = $this->parseRecord($record, $requestedNumber);
        }

        if ($items === []) {
            return null;
        }

        $count = $payload['amount'] ?? count($items);
        if (!is_int($count) || $count < count($items)) {
            throw new UnexpectedValueException('Invalid NSPD valuation history record count.');
        }

        return new Response($items, $requestedNumber, $count);
    }

    /** @param array<string, mixed> $record */
    private function parseRecord(array $record, string $requestedNumber): Item
    {
        $sysName = $record['sysName'] ?? null;
        if (!is_array($sysName) || array_is_list($sysName)) {
            throw new UnexpectedValueException('NSPD record is missing sysName fields.');
        }

        $cadNumber = $record['cadNumber'] ?? $sysName['cadNumber'] ?? null;
        if (!is_string($cadNumber) || $cadNumber !== $requestedNumber) {
            throw new UnexpectedValueException('NSPD returned a record for a different cadastral number.');
        }

        $item = new Item();
        $item->cadNumber = $cadNumber;
        $item->costRecordId = $this->stringOrNull($record['costRecordId'] ?? null);
        $item->determinationDate = self::normalizeDate($sysName['determinationDate'] ?? null);
        $item->infoEntryDate = self::normalizeDate($sysName['registrationDate'] ?? null);
        $item->applicationDate = self::normalizeDate($sysName['applicationDate'] ?? null);
        $item->determinationBasis = $this->stringOrNull($sysName['determinationCouse.value'] ?? null);
        $item->determinationCode = $this->stringOrNull($sysName['determinationCouse.code'] ?? null);
        $item->documentCode = $this->stringOrNull($sysName['approvementDocRequisites.documentCode.code'] ?? null);
        $item->determinationName->text = $this->stringOrNull($sysName['approvementDocRequisites.documentCode.value'] ?? null);
        $item->approvalDetails = $this->stringOrNull($sysName['approvementDocRequisites'] ?? null);

        $rawValue = $sysName['value'] ?? null;
        $item->cadastralValue->decimal = self::normalizeMoney($rawValue);
        $item->cadastralValue->value = (float) $item->cadastralValue->decimal;

        if (isset($record['files']) && !is_array($record['files'])) {
            throw new UnexpectedValueException('NSPD record files must be an array.');
        }
        $item->files = $record['files'] ?? [];

        foreach ([
            'proceduresId', 'realEstateObjectsId',
            'linkRegistersId', 'linkObjdocId', 'hrefRegistersId',
            'hrefObjdocId', 'hrefType',
        ] as $key) {
            if (isset($record[$key]) && (is_scalar($record[$key]))) {
                $item->references[$key] = $record[$key];
            }
        }

        $item->raw = $record;
        return $item;
    }

    public static function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException('NSPD date must be a string.');
        }

        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:T.*)?$/D', $value)) {
            $date = substr($value, 0, 10);
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed !== false && $parsed->format('Y-m-d') === $date) {
                return $date;
            }
        }

        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/D', $value)) {
            $parsed = DateTimeImmutable::createFromFormat('!d.m.Y', $value);
            if ($parsed !== false && $parsed->format('d.m.Y') === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        throw new UnexpectedValueException(sprintf('Invalid NSPD date value: %s', $value));
    }

    public static function normalizeMoney(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new UnexpectedValueException('NSPD cadastral value is missing or invalid.');
        }

        $normalized = str_replace(["\u{00A0}", ' ', "\u{202F}", ','], ['', '', '', '.'], trim((string) $value));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $normalized)) {
            throw new UnexpectedValueException('NSPD cadastral value is not a decimal amount.');
        }

        return $normalized;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' || $text === '-' ? null : $text;
    }
}
