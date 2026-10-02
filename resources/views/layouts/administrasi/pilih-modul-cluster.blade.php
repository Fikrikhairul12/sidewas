<x-app-layout>
    <div class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">Administrasi</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-800">Manajemen Cluster</h1>
            <p class="mt-2 text-sm text-slate-500">Pilih fitur untuk mengelola cluster dan subclusternya. Perubahan hanya berlaku pada fitur yang dipilih.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($modules as $module => $config)
                <a href="{{ route('administrasi.manajemen-cluster.show', $module) }}"
                    class="group flex flex-col rounded-2xl border border-blue-100 bg-white p-6 shadow-sm transition hover:border-sky-300 hover:shadow-md">
                    <span class="text-xs font-semibold uppercase tracking-wide text-sky-700">Kelola Fitur</span>
                    <h2 class="mt-3 text-2xl font-bold text-slate-800">{{ $config['label'] }}</h2>
                    <p class="mt-2 text-sm text-slate-500">Daftar cluster dan subcluster {{ $config['label'] }}.</p>
                    <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-sky-700">
                        Kelola {{ $config['label'] }}
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</x-app-layout>
