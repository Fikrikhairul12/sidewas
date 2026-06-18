<div x-data="{ openFilter: false, selectedClusterId: @js(request('cluster_id', '')) }" class="rounded-2xl border border-blue-100 bg-white shadow-sm">
    <button type="button" @click="openFilter = !openFilter"
        class="flex w-full items-center justify-between px-6 py-4 text-left">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50" style="color: #2377b9;">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M6 12h12M10 19.5h4" />
                </svg>
            </div>

            <div>
                <p class="font-semibold text-slate-800">Filter Lanjutan</p>
                <p class="text-sm text-slate-500">
                    Isi minimal satu filter untuk mencari data.
                </p>
            </div>
        </div>

        <svg class="h-5 w-5 text-slate-500 transition-transform" :class="{ 'rotate-180': openFilter }" fill="none"
            stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
        </svg>
    </button>

    <div x-show="openFilter" x-transition class="border-t border-blue-50 px-6 py-5" style="display: none;">
        <form method="GET" action="{{ $action }}">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
                    <select name="status"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua Status</option>

                        @foreach ($statusOptions ?? [] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">
                        Direktorat
                    </label>
                    <div class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm">
                        Dewan Pengawas
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">
                        Unit PIC
                    </label>
                    <select name="unit_kerja_id"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua Unit PIC</option>
                        @foreach ($unitKerjas as $unit)
                            <option value="{{ $unit->id }}" @selected(request('unit_kerja_id', request('unit_kerja_pendukung_id')) == $unit->id)>
                                {{ $unit->kode_unit }} - {{ $unit->nama_unit }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Cluster</label>
                    <select name="cluster_id" x-model="selectedClusterId"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua Cluster</option>
                        @foreach ($clusters as $cluster)
                            <option value="{{ $cluster->id }}" @selected(request('cluster_id') == $cluster->id)>
                                {{ $cluster->nama_cluster }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Sub Cluster</label>
                    <select name="sub_cluster_id"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua Sub Cluster</option>

                        @foreach ($clusters as $cluster)
                            @foreach ($cluster->subClusters as $subCluster)
                                <option value="{{ $subCluster->id }}" data-cluster="{{ $cluster->id }}"
                                    @selected(request('sub_cluster_id') == $subCluster->id)>
                                    {{ $subCluster->nama_sub_cluster }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" value="{{ request('tanggal_mulai') }}"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" value="{{ request('tanggal_selesai') }}"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Kata Kunci</label>
                    <input type="text" name="keyword" value="{{ request('keyword') }}"
                        placeholder="{{ $keywordPlaceholder ?? 'Cari data...' }}"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-3">
                <a href="{{ $action }}"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset
                </a>

                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                    style="background-color: #2377b9;">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>
</div>
