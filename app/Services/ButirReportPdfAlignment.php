<?php

namespace App\Services;

use DOMElement;
use Dompdf\Frame;
use Dompdf\FrameDecorator\Block;
use Dompdf\FrameDecorator\Text;

class ButirReportPdfAlignment
{
    /** @return list<array{event: string, f: callable}> */
    public function callbacks(): array
    {
        return [['event' => 'begin_frame', 'f' => function (Frame $frame): void {
            $node = $frame->get_node();
            if (! $frame instanceof Block || ! $node instanceof DOMElement || $node->getAttribute('data-report-justify') !== '1') {
                return;
            }

            $this->justify($frame);
        }]];
    }

    /**
     * Measured lines are separate blocks for table pagination. Expand only
     * soft wraps after reflow, using the actual column width without rewrapping.
     */
    private function justify(Block $block): void
    {
        foreach ($block->get_line_boxes() as $line) {
            if (! $line->inline || $line->br) {
                continue;
            }
            $line->trim_trailing_ws();
            $frames = $line->get_frames();
            $spaces = 0;
            foreach ($frames as $frame) {
                if ($frame instanceof Text) {
                    $spaces += mb_substr_count($frame->get_text(), ' ');
                }
            }
            $remaining = $block->get_content_box()['w'] - $line->get_width();
            if ($spaces === 0 || $remaining <= 0) {
                continue;
            }

            $spacing = $remaining / $spaces;
            $offset = 0;
            foreach ($frames as $frame) {
                $frame->move($offset, 0);
                if ($frame instanceof Text) {
                    $frame->set_text_spacing($frame->get_text_spacing() + $spacing);
                    $offset += mb_substr_count($frame->get_text(), ' ') * $spacing;
                }
            }
            $line->recalculate_width();
        }
    }
}
