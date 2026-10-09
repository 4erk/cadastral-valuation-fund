<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Valuation;

final class Link implements \JsonSerializable
{
    public ?string $url = null;
    public ?string $text = null;

    public function jsonSerialize(): array
    {
        return ['url' => $this->url, 'text' => $this->text];
    }
}
