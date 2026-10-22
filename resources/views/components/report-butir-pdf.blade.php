@props(['records', 'module', 'prepared' => false])
{!! app(\App\Services\ButirReportPdf::class)->render($slot->toHtml(), $records, $module, $prepared) !!}
