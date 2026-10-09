<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Valuation;

final class Item implements \JsonSerializable
{
    public ?string $cadNumber = null;
    public ?string $costRecordId = null;

    /** Dates are ISO 8601 calendar dates (YYYY-MM-DD), or null if unavailable. */
    public ?string $determinationDate = null;
    public Value $cadastralValue;
    public ?string $infoEntryDate = null;
    public ?string $applicationDate = null;
    public ?string $determinationBasis = null;
    public ?string $determinationCode = null;
    public ?string $documentCode = null;
    public Link $determinationName;
    public ?string $approvalDetails = null;

    /** File descriptors returned by NSPD (not necessarily public direct URLs). */
    public array $files = [];

    /** NSPD procedure/card lookup identifiers. */
    public array $references = [];

    /** Original NSPD record, retained for forward compatibility. */
    public array $raw = [];

    public function __construct()
    {
        $this->cadastralValue = new Value();
        $this->determinationName = new Link();
    }

    public function jsonSerialize(): array
    {
        return [
            'cadNumber' => $this->cadNumber,
            'costRecordId' => $this->costRecordId,
            'determinationDate' => $this->determinationDate,
            'cadastralValue' => $this->cadastralValue,
            'infoEntryDate' => $this->infoEntryDate,
            'applicationDate' => $this->applicationDate,
            'determinationBasis' => $this->determinationBasis,
            'determinationCode' => $this->determinationCode,
            'documentCode' => $this->documentCode,
            'determinationName' => $this->determinationName,
            'approvalDetails' => $this->approvalDetails,
            'files' => $this->files,
            'references' => $this->references,
        ];
    }
}
