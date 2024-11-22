<?php

namespace Rosreestr\Cadastral\Valuation;

use DOMDocument;
use DOMElement;
use DOMXPath;

class Parser
{
    private DOMDocument $dom;

    public function __construct(private readonly string $html)
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $this->dom->loadHTML('<?xml encoding="UTF-8">' . $this->html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
    }

    public function parse(): array|false
    {
        $xpath = new DOMXPath($this->dom);
        $tbody = $xpath->query('//tbody')->item(0);

        if (!$tbody) {
            return false;
        }

        $rows = $tbody->getElementsByTagName('tr');
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->parseRow($row);
        }


        return $items;
    }

    private function parseRow(DOMElement $row): array
    {
        $cells = $row->getElementsByTagName('td');
        return [
            'determinationDate'  => $this->parseDate($cells->item(0)->textContent),
            'cadastralValue'     => $this->parseCadastralValue($cells->item(1)),
            'infoEntryDate'      => $this->parseDate($cells->item(2)->textContent),
            'applicationDate'    => $this->parseDate($cells->item(3)->textContent),
            'determinationBasis' => $this->parseText($cells->item(4)),
            'determinationName'  => $this->parseLink($cells->item(5)),
            'approvalDetails'    => $this->parseText($cells->item(6)),
        ];
    }

    private function parseDate(string $data): ?string
    {
        $date = trim($data);
        return $date === '-' ? null : date('Y-m-d', strtotime($date));
    }

    private function parseCadastralValue(DOMElement $item): array
    {
        return [
            'value' => (float) trim(str_replace(',', '.', $item->textContent)),
            'link'  => $item->baseURI . $item->getElementsByTagName('a')->item(0)?->getAttribute('href'),
        ];
    }

    private function parseLink(DOMElement $item): array
    {
        return [
            'url'  => $item->baseURI . $item->getElementsByTagName('a')->item(0)?->getAttribute('href'),
            'text' => trim($item->textContent),
        ];
    }

    private function parseText(DOMElement $item): ?string
    {
        $text = trim($item->textContent);
        return $text === '-' ? null : $text;
    }
}
