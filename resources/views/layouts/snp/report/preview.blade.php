<x-app-layout>
    <div class="space-y-4" x-data="{
        zoom: 125,
        applyZoom() {
            const previewDocument = this.$refs.reportPreview.contentDocument;

            if (previewDocument) {
                previewDocument.documentElement.style.zoom = this.zoom + '%';
            }
        },
    }">
        <div class="flex flex-col gap-4 rounded-2xl border border-blue-100 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                    Review sebelum download
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-800">
                    {{ $title }}
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Periksa isi report di bawah ini. PDF baru akan didownload setelah Anda menekan tombol download.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('snp.report.index') }}"
                    class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600 hover:bg-slate-50">
                    Kembali
                </a>

                <form method="POST" action="{{ $downloadRoute }}">
                    @csrf

                    @foreach ($downloadParameters as $parameterName => $parameterValues)
                        @foreach ((array) $parameterValues as $parameterValue)
                            <input type="hidden" name="{{ $parameterName }}[]" value="{{ $parameterValue }}">
                        @endforeach
                    @endforeach

                    <button type="submit"
                        class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                        style="background-color: #2377b9;">
                        Download {{ $filename }}
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-200 p-3 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3">
                <p class="text-sm text-slate-500">
                    Perbesar tampilan untuk membaca isi. Zoom tidak mengubah hasil PDF.
                </p>

                <div class="flex items-center gap-2">
                    <button type="button" @click="zoom = Math.max(75, zoom - 25); applyZoom()"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50"
                        aria-label="Perkecil preview">
                        −
                    </button>

                    <span class="min-w-16 text-center text-sm font-bold text-slate-700" x-text="zoom + '%'">
                        125%
                    </span>

                    <button type="button" @click="zoom = Math.min(200, zoom + 25); applyZoom()"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50"
                        aria-label="Perbesar preview">
                        +
                    </button>

                    <button type="button" @click="$refs.reportPreview.requestFullscreen()"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-white hover:opacity-90"
                        style="background-color: #2377b9;">
                        Layar Penuh
                    </button>
                </div>
            </div>

            <iframe x-ref="reportPreview" srcdoc="{{ $reportHtml }}" title="{{ $title }}" @load="applyZoom()"
                class="w-full rounded-xl border-0 bg-white shadow-inner" style="height: 75vh; min-height: 680px;">
            </iframe>
        </div>
    </div>
</x-app-layout>
