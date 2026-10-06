<?php

namespace App\Services;

use DOMElement;
use Dompdf\Canvas;
use Dompdf\Frame;

class SnpReportPdfLabels
{
    /**
     * @return list<array{event: string, f: callable}>
     */
    public function callbacks(): array
    {
        $lastPages = [];

        return [['event' => 'begin_frame', 'f' => function (Frame $frame, Canvas $canvas) use (&$lastPages): void {
            $node = $frame->get_node();
            if (! $node instanceof DOMElement || ! $node->hasAttribute('data-snp-butir-label')) {
                return;
            }

            $key = $node->getAttribute('data-snp-butir-label');
            $page = $canvas->get_page_number();
            $previousPage = $lastPages[$key] ?? null;
            $isContinuation = $previousPage !== null && $previousPage !== $page;
            $showLabel = $isContinuation || ($previousPage === null && $node->getAttribute('data-snp-show-first') === '1');
            $lastPages[$key] = $page;

            $this->setVisibility($frame, $showLabel, $isContinuation);
        }]];
    }

    private function setVisibility(Frame $frame, bool $visible, bool $isContinuation): void
    {
        $node = $frame->get_node();
        if ($node instanceof DOMElement && $node->hasAttribute('data-snp-continuation')) {
            $visible = $visible && $isContinuation;
        }

        $frame->get_style()->visibility = $visible ? 'visible' : 'hidden';
        foreach ($frame->get_children() as $child) {
            $this->setVisibility($child, $visible, $isContinuation);
        }
    }
}
