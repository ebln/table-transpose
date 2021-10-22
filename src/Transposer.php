<?php

declare(strict_types=1);

namespace Brnc\Html\TableTranspose;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class Transposer
{
    private const SWAP_SCOPE = ['row' => 'col', 'col' => 'row'];
    private const TABLE_CELL = ['td', 'th'];

    public function transpose(?string $htmlTable): string
    {
        $htmlOutput  = null;
        $reportError = null;
        try {
            if (!empty($htmlTable)) {
                $dom    = $this->createDom($htmlTable);
                $tables = $dom->getElementsByTagName('table');
                if ($tables->count() > 0) {
                    $table = $tables->item(0);
                    $this->transposeTable($table);
                    $htmlOutput = $dom->saveHTML($table);
                }
            }
        } catch (\Throwable $t) {
            if (preg_match('/^(?:Warning|Error):\s*DOM\w+::\w+\(\):\s*(.*)/s', $t->getMessage(), $match)) {
                $reportError = $match[1] . "\n\n";
            }
        }

        return $htmlOutput;
    }

    // TODO colgroup
    private function transposeTable(DOMNode $node): void
    {
        $cellCounter = 0;
        $spanCounter = 0;

        // gather table
        $xpath   = new DOMXPath($node->ownerDocument);
        $rowList = $xpath->query('.//tr', $node);
        /** @var DOMElement[] $rowsToDelete */
        $rowsToDelete = [];
        $table        = [];
        foreach ($rowList as $rowNumber => $row) {
            $rowsToDelete[] = $row;
            $cells          = $xpath->query('.//th|.//td', $row);
            $xCoordinate    = 0;
            foreach ($cells as $cell) {
                $rowSpan = $cell->hasAttribute('rowspan') ? (int)$cell->getAttribute('rowspan') : 1;
                $colspan = $cell->hasAttribute('colspan') ? (int)$cell->getAttribute('colspan') : 1;
                while (isset($table[$rowNumber]) && array_key_exists($xCoordinate, $table[$rowNumber])) {
                    $xCoordinate++;
                }
                for ($i = 0; $i < $rowSpan; $i++) {
                    for ($j = 0; $j < $colspan; $j++) {
                        $table[$rowNumber + $i][$xCoordinate + $j] = null;
                    }
                }
                $cellCounter++;
                $table[$rowNumber][$xCoordinate] = $cell;
                $cell->removeAttribute('rowspan');
                $cell->removeAttribute('colspan');
                if ($colspan > 1) {
                    $cell->setAttribute('rowspan', (string)$colspan);
                    $spanCounter++;
                }
                if ($rowSpan > 1) {
                    $cell->setAttribute('colspan', (string)$rowSpan);
                    $spanCounter++;
                }
                if ($cell->hasAttribute('scope')) {
                    $swapScope = self::SWAP_SCOPE[strtolower($cell->getAttribute('scope'))] ?? null;
                    if ($swapScope) {
                        $cell->setAttribute('scope', $swapScope);
                    }
                }
            }
        }

        // flip table
        $transposedTable = [];
        foreach ($table as $x => $column) {
            foreach ($column as $y => $cell) {
                $transposedTable[$y][$x] = $cell;
            }
        }

        // determine pure header rows
        $doFlushBody = false;
        $isBodyMap   = [];
        foreach ($transposedTable as $rowNumber => $newRow) {
            if ($doFlushBody) {
                $isBodyMap[$rowNumber] = true;
                continue;
            }
            foreach ($newRow as $cell) {
                if (!array_key_exists($rowNumber, $isBodyMap) || false === $isBodyMap[$rowNumber]) {
                    $isBodyMap[$rowNumber] = null !== $cell && ($cell instanceof DOMElement && $cell->nodeName !== 'th');
                }
            }
            if ($isBodyMap[$rowNumber] ?? false) {
                $doFlushBody = true;
            }
        }

        // remove rows, collect junknodes, collect and delete thead, tbody & tfoot
        $firstHead = null;
        $firstBody = null;
        $junkNodes = [];
        foreach ($rowsToDelete as $rowNumber => $row) {
            $parent = $row->parentNode;
            /** @var DOMNode $childNode */
            foreach ($row->childNodes as $childNode) {
                if (!in_array(strtolower($childNode->nodeName), self::TABLE_CELL)) {
                    if (trim($childNode->textContent) !== '') {
                        $junkNodes[] = $childNode;
                    }
                }
            }
            $parent->removeChild($row);

            if ($parent && strtolower($parent->nodeName) === 'thead') {
                if (null === $firstHead) {
                    $firstHead = $parent;
                } elseif ($parent->parentNode && $parent->parentNode->isSameNode($firstHead)) {
                    $parent->parentNode->removeChild($parent);
                }
            }

            if ($parent && strtolower($parent->nodeName) === 'tbody') {
                if (null === $firstBody) {
                    $firstBody = $parent;
                } elseif ($parent->parentNode && $parent->parentNode->isSameNode($firstBody)) {
                    $parent->parentNode->removeChild($parent);
                }
            }

            if ($parent && strtolower($parent->nodeName) === 'tfoot') {
                $parent->parentNode->removeChild($parent);
            }
        }

        // create thead if not present yet tbody
        if (null !== $firstBody && null === $firstHead && !($isBodyMap[0] ?? true)) {
            $firstHead = $node->ownerDocument->createElement('thead');
            $node->insertBefore($firstHead, $firstBody);
        }

        // inject new rows for transposed cells and link them
        $useBody = false;
        foreach ($transposedTable as $rowNumber => $newRow) {
            $tr = $node->ownerDocument->createElement('tr');
            foreach ($newRow as $cell) {
                $maxRowSpan = 1;
                if ($cell instanceof DOMNode) {
                    $tr->appendChild($cell);
                    // lookahead for rowspan number (or reverse
                    $maxRowSpan = max($maxRowSpan, $cell->hasAttribute('rowspan') ? (int)$cell->getAttribute('rowspan') : 1);
                }

                $useBody = $useBody | $isBodyMap[$rowNumber + $maxRowSpan - 1];
            }
            $pivot = $node;
            if (!$useBody && $firstHead && !$isBodyMap[$rowNumber]) {
                $pivot = $firstHead;
            } elseif ($firstBody) {
                $pivot = $firstBody;
            }
            $pivot->appendChild($tr);
        }
        // clean up if left empty
        if ($firstHead && !$firstHead->hasChildNodes()) {
            $firstHead->parentNode->removeChild($firstHead);
        }
        if ($firstBody && !$firstBody->hasChildNodes()) {
            $firstBody->parentNode->removeChild($firstBody);
        }
        // add junknotes ~likely to be stray (tr) comments
        if (!empty($junkNodes)) {
            $disclaimer = $node->ownerDocument->createComment(' *** collected junk nodes *** ');
            $node->appendChild($disclaimer);
            foreach ($junkNodes as $junkNode) {
                $node->appendChild($junkNode);
            }
        }
    }

    private function createDom(string $htmlTable): DOMDocument
    {
        $dom                     = new DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals   = false;
        $dom->validateOnParse    = true;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = true;
        $dom->encoding           = 'UTF-8';
        $content                 = '<?xml version="1.0" encoding="UTF-8"?><html>' . $htmlTable . '</html>';
        $dom->loadHTML($content, LIBXML_NOBLANKS | LIBXML_NOCDATA);

        return $dom;
    }
}
