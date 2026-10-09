<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Valuation;

use DOMDocument;
use DOMElement;
use DOMXPath;
use UnexpectedValueException;

/**
 * @deprecated Old Rosreestr HTML table parser. New clients use HistoryParser
 *             and the NSPD Data Fund JSON API. Kept for backwards compatibility.
 */
class Parser
{
    private DOMDocument $dom;

    public function __construct(
        private readonly string $html,
        private readonly string $baseUrl = 'https://rosreestr.gov.ru',
    ) {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $this->dom->loadHTML(
                '<?xml encoding="UTF-8">' . $this->html,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array<int, array<string, mixed>>|false */
    public function parse(): array|false
    {
        $tbody = (new DOMXPath($this->dom))->query('//tbody')->item(0);
        if (!$tbody instanceof DOMElement) {
            return false;
        }

        $items = [];
        foreach ($tbody->getElementsByTagName('tr') as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length === 0) {
                continue;
            }
            if ($cells->length < 7) {
                throw new UnexpectedValueException('Legacy valuation table contains an incomplete row.');
            }
            $items[] = $this->parseRow($row);
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function parseRow(DOMElement $row): array
    {
        $cells = $row->getElementsByTagName('td');

        return [
            'determinationDate' => HistoryParser::normalizeDate($cells->item(0)->textContent),
            'cadastralValue' => $this->parseCadastralValue($cells->item(1)),
            'infoEntryDate' => HistoryParser::normalizeDate($cells->item(2)->textContent),
            'applicationDate' => HistoryParser::normalizeDate($cells->item(3)->textContent),
            'determinationBasis' => $this->parseText($cells->item(4)),
            'determinationName' => $this->parseLink($cells->item(5)),
            'approvalDetails' => $this->parseText($cells->item(6)),
        ];
    }

    /** @return array{value:float,link:?string} */
    private function parseCadastralValue(DOMElement $cell): array
    {
        $anchor = $cell->getElementsByTagName('a')->item(0);
        return [
            'value' => (float) HistoryParser::normalizeMoney($cell->textContent),
            'link' => $this->resolveLink($anchor instanceof DOMElement ? $anchor->getAttribute('href') : null),
        ];
    }

    /** @return array{url:?string,text:?string} */
    private function parseLink(DOMElement $cell): array
    {
        $anchor = $cell->getElementsByTagName('a')->item(0);
        return [
            'url' => $this->resolveLink($anchor instanceof DOMElement ? $anchor->getAttribute('href') : null),
            'text' => $this->parseText($cell),
        ];
    }

    private function parseText(DOMElement $cell): ?string
    {
        $text = trim($cell->textContent);
        return $text === '' || $text === '-' ? null : $text;
    }

    private function resolveLink(?string $href): ?string
    {
        if ($href === null || trim($href) === '') {
            return null;
        }
        $href = trim($href);
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '/')) {
            return rtrim($this->baseUrl, '/') . $href;
        }
        return null;
    }
}
