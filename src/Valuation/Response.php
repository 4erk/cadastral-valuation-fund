<?php

namespace Rosreestr\Cadastral\Valuation;

use Yiisoft\Hydrator\Hydrator;

class Response
{
    public function __construct(
        /** @var Item[] $items */ private array $items = []
    )
    {
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function addItem($item): void
    {
        $this->items[] = $item;
    }

    public static function createFromArray(array $data): self
    {
        $hydrator = new Hydrator();
        $response = new self();
        foreach($data as $itemData) {
            $item = $hydrator->create(Item::class, $itemData);
            $response->addItem($item);
        }
        return $response;
    }
}
