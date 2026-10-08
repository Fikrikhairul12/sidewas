<?php

namespace App\Http\Controllers\ProdukHukum;

use App\Exports\ProdukHukumReportExport;
use App\Http\Controllers\Controller;
use App\Models\ProdukHukum;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportProdukHukumController extends Controller
{
    private const StatusLabels = [
        'semua' => 'Semua status',
        'berlaku' => 'Berlaku',
        'tidak_berlaku' => 'Tidak Berlaku',
        'draft' => 'Draf',
    ];

    public function options(Request $request): JsonResponse
    {
        $this->authorizeReport($request);
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = $this->summaryQuery();
        $keyword = trim($validated['keyword'] ?? '');

        if ($keyword !== '') {
            $query->where(function (Builder $query) use ($keyword): void {
                foreach (['judul', 'kode_produk_hukum', 'nomor_peraturan_keputusan', 'tahun_peraturan', 'jenis_bentuk_peraturan', 'bidang_pengaturan'] as $field) {
                    $query->orWhere($field, 'like', '%'.$keyword.'%');
                }
            });
        }

        $products = $query->paginate(20);

        return response()->json([
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
        ]);
    }

    public function download(Request $request): Response|BinaryFileResponse
    {
        $this->authorizeReport($request);
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['status', 'manual'])],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'status' => ['exclude_unless:mode,status', 'required', Rule::in(array_keys(self::StatusLabels))],
            'product_ids' => ['exclude_unless:mode,manual', 'required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ], [
            'product_ids.required' => 'Pilih minimal satu peraturan untuk diunduh.',
            'product_ids.min' => 'Pilih minimal satu peraturan untuk diunduh.',
        ]);

        $query = $this->summaryQuery();
        if ($validated['mode'] === 'manual') {
            $query->whereIn('id', $validated['product_ids']);
            $scope = 'Peraturan pilihan';
        } else {
            if ($validated['status'] !== 'semua') {
                $query->where('status_peraturan', $validated['status']);
            }
            $scope = 'Status: '.self::StatusLabels[$validated['status']];
        }

        $products = $query->get();
        if ($validated['mode'] === 'manual' && $products->count() !== count($validated['product_ids'])) {
            throw ValidationException::withMessages(['product_ids' => 'Sebagian peraturan sudah tidak tersedia. Perbarui pilihan Anda.']);
        }
        if ($products->isEmpty()) {
            throw ValidationException::withMessages(['status' => 'Tidak ada peraturan untuk pilihan ini.']);
        }

        $printedAt = now()->timezone('Asia/Jakarta');
        $printedBy = $request->user()->name ?? $request->user()->email;
        $filename = 'Rekap_Produk_Hukum_'.($validated['mode'] === 'manual' ? 'pilihan' : $validated['status']).'_'.$printedAt->format('Ymd_His');

        if ($validated['format'] === 'xlsx') {
            return Excel::download(new ProdukHukumReportExport($products, $scope, $printedBy, $printedAt->format('d/m/Y H:i').' WIB'), $filename.'.xlsx');
        }

        $pdf = Pdf::loadView('layouts.produk-hukum.report-pdf', [
            'products' => $products,
            'scope' => $scope,
            'printedBy' => $printedBy,
            'printedAt' => $printedAt->format('d/m/Y H:i').' WIB',
            'statusLabels' => self::StatusLabels,
        ])->setPaper('a4', 'landscape');
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(710, 567, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.35, 0.4, 0.45]);

        return $pdf->download($filename.'.pdf');
    }

    private function authorizeReport(Request $request): void
    {
        abort_unless($request->user()?->canAccessProdukHukum(), 403, 'Anda tidak memiliki akses ke rekap Produk Hukum.');
    }

    private function summaryQuery(): Builder
    {
        return ProdukHukum::query()->select([
            'id', 'kode_produk_hukum', 'judul', 'nomor_peraturan_keputusan', 'tahun_peraturan',
            'jenis_bentuk_peraturan', 'bidang_pengaturan', 'sifat_dokumen', 'status_peraturan',
        ])->orderByDesc('tahun_peraturan')->orderBy('id');
    }
}
