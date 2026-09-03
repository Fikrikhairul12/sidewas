<x-app-layout>
    <div class="space-y-4">
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

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 p-3 shadow-sm">
            <iframe srcdoc="{{ $reportHtml }}" title="{{ $title }}"
                class="h-[calc(100vh-14rem)] min-h-[680px] w-full rounded-xl border-0 bg-white shadow-inner">
            </iframe>
        </div>
    </div>
</x-app-layout>
