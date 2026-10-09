<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Valuation;

use Yiisoft\Hydrator\Hydrator;

final class Response implements \JsonSerializable
{
    /** @param Item[] $items */
    public function __construct(
        private array $items = [],
        private readonly ?string $cadastralNumber = null,
        private readonly ?int $totalCount = null,
    ) {
    }

    /** @return Item[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getCadastralNumber(): ?string
    {
        return $this->cadastralNumber;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount ?? count($this->items);
    }

    /** Returns the most recently applicable entry, preserving original records. */
    public function getCurrent(): ?Item
    {
        $latest = null;
        $latestDate = '';
        foreach ($this->items as $item) {
            $candidate = $item->applicationDate
                ?? $item->infoEntryDate
                ?? $item->determinationDate
                ?? '';
            if ($latest === null || $candidate > $latestDate) {
                $latest = $item;
                $latestDate = $candidate;
            }
        }

        return $latest;
    }

    public function addItem(Item $item): void
    {
        $this->items[] = $item;
    }

    /**
     * Legacy helper used by older consumers of the HTML parser.
     * New NSPD data should be mapped with HistoryParser instead.
     *
     * @param array<int, array<string, mixed>> $data
     */
    public static function createFromArray(array $data): self
    {
        $hydrator = new Hydrator();
        $response = new self();
        foreach ($data as $itemData) {
            $response->addItem($hydrator->create(Item::class, $itemData));
        }
        return $response;
    }

    public function jsonSerialize(): array
    {
        return [
            'cadNumber' => $this->cadastralNumber,
            'totalCount' => $this->getTotalCount(),
            'items' => $this->items,
        ];
    }
}
