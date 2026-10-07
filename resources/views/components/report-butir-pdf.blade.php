@props(['records', 'module'])
{!! app(\App\Services\ButirReportPdf::class)->render($slot->toHtml(), $records, $module) !!}
