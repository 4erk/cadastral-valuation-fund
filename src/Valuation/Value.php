<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Valuation;

class Value implements \JsonSerializable
{
    /** Amount in rubles. This float is kept for backwards compatibility. */
    public ?float $value = null;

    /** Provider's exact decimal representation; preferred for monetary arithmetic. */
    public ?string $decimal = null;

    /** Legacy property; the new NSPD endpoint does not provide a URL here. */
    public ?string $link = null;

    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'decimal' => $this->decimal, 'link' => $this->link];
    }
}
