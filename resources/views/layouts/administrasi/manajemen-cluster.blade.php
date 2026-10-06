<x-app-layout>
    @php
        $openClusterId = session('open_cluster', old('cluster_id'));
        $openClusters = $clusters->filter(fn ($cluster) => !empty($filters['keyword']) || (string) $cluster->id === (string) $openClusterId)
            ->mapWithKeys(fn ($cluster) => [$cluster->id => true]);
        $clusterActions = $allClusters->mapWithKeys(fn ($cluster) => [$cluster->id => route('administrasi.manajemen-cluster.cluster.update', [$module, $cluster->id])]);
        $subClusterActions = $clusters->flatMap(fn ($cluster) => $cluster->subClusters)->mapWithKeys(fn ($subCluster) => [
            $subCluster->id => route('administrasi.manajemen-cluster.subcluster.update', [$module, $subCluster->cluster_id, $subCluster->id]),
        ]);
    @endphp
    <div x-data="{
        openClusters: @js((object) $openClusters->all()),
        clusterActions: @js((object) $clusterActions->all()),
        subClusterActions: @js((object) $subClusterActions->all()),
        createClusterId: @js(old('_form') === 'create-subcluster' ? old('cluster_id', '') : ''),
        editCluster: @js([
            'id' => old('_form') === 'edit-cluster' ? old('cluster_id', '') : '',
            'nama_cluster' => old('_form') === 'edit-cluster' ? old('nama_cluster', '') : '',
            'keterangan' => old('_form') === 'edit-cluster' ? old('keterangan', '') : '',
        ]),
        editSubCluster: @js([
            'id' => old('_form') === 'edit-subcluster' ? old('sub_cluster_id', '') : '',
            'cluster_id' => old('_form') === 'edit-subcluster' ? old('cluster_id', '') : '',
            'nama_sub_cluster' => old('_form') === 'edit-subcluster' ? old('nama_sub_cluster', '') : '',
            'keterangan' => old('_form') === 'edit-subcluster' ? old('keterangan', '') : '',
        ]),
    }" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <a href="{{ route('administrasi.manajemen-cluster.index') }}" class="text-sm font-semibold text-sky-700 hover:underline">&larr; Pilih Fitur</a>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Manajemen Cluster {{ $moduleLabel }}</h1>
                    <p class="mt-2 text-sm text-slate-500">Kelola cluster dan subcluster untuk fitur {{ $moduleLabel }}. Cluster nonaktif beserta subclusternya tidak tersedia untuk input baru; data lama tetap tersimpan.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="$dispatch('open-modal', 'create-cluster')" class="rounded-xl px-4 py-3 text-sm font-semibold shadow-sm transition hover:opacity-90"
                        style="background-color: #2377b9; border: 1px solid #2377b9; color: #fff;">Tambah Cluster</button>
                    <button type="button" @click="createClusterId = ''; $dispatch('open-modal', 'create-subcluster')" @disabled($allClusters->where('status', 'active')->isEmpty())
                        class="rounded-xl border border-sky-700 bg-white px-4 py-3 text-sm font-semibold text-sky-700 hover:bg-sky-50 disabled:opacity-50">Tambah Subcluster</button>
                </div>
            </div>
        </div>
        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any() && !in_array(old('_form'), ['create-cluster', 'edit-cluster', 'create-subcluster', 'edit-subcluster'], true))
            <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="GET" action="{{ route('administrasi.manajemen-cluster.show', $module) }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-blue-100 bg-white p-5 shadow-sm">
            <label class="min-w-52 flex-1 text-sm font-medium text-slate-700">Cari cluster atau subcluster
                <input name="keyword" value="{{ $filters['keyword'] ?? '' }}" maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm" placeholder="Nama cluster atau subcluster" />
            </label>
            <button class="rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:opacity-90" style="background-color: #2377b9; border: 1px solid #2377b9; color: #fff;">Cari</button>
            <a href="{{ route('administrasi.manajemen-cluster.show', $module) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700">Reset</a>
        </form>
        <div class="rounded-2xl border border-blue-100 bg-white shadow-sm" style="height: auto; max-height: none; overflow: visible;">
            <div class="overflow-x-auto rounded-2xl">
                <table class="min-w-full divide-y divide-slate-200 text-sm" style="border-collapse: separate; border-spacing: 0;">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th scope="col" class="px-5 py-4">Nama Cluster</th>
                            <th scope="col" class="px-5 py-4">Keterangan</th>
                            <th scope="col" class="px-5 py-4">Total Subcluster</th><th scope="col" class="px-5 py-4">Status</th>
                            <th scope="col" class="px-5 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    @forelse ($clusters as $cluster)
                        @php
                            $editClusterPayload = ['id' => $cluster->id, 'nama_cluster' => $cluster->nama_cluster, 'keterangan' => $cluster->keterangan ?? ''];
                        @endphp
                        <tbody class="divide-y divide-slate-100" style="background-color: {{ $loop->even ? '#eef5fb' : '#ffffff' }};">
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-4 font-semibold text-slate-800">{{ $cluster->nama_cluster }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $cluster->keterangan ?: '-' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $cluster->subClusters->count() }}</td><td class="px-5 py-4 text-slate-700">{{ $cluster->status === 'active' ? 'Aktif' : 'Nonaktif' }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <button type="button" @click="editCluster = @js($editClusterPayload); $dispatch('open-modal', 'edit-cluster')" aria-label="Edit cluster {{ $cluster->nama_cluster }}"
                                            class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50">Edit</button>
                                        <form method="POST" action="{{ route('administrasi.manajemen-cluster.cluster.status', [$module, $cluster->id]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $cluster->status === 'active' ? 'inactive' : 'active' }}" />
                                            <button class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700">{{ $cluster->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('administrasi.manajemen-cluster.cluster.destroy', [$module, $cluster->id]) }}" onsubmit="return confirm('Hapus cluster ini? Cluster yang memiliki subcluster atau sudah digunakan tidak dapat dihapus.')">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg border border-red-200 px-3 py-2 font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                                        </form>
                                        <button type="button" @click="openClusters[{{ $cluster->id }}] = !openClusters[{{ $cluster->id }}]"
                                            :aria-expanded="Boolean(openClusters[{{ $cluster->id }}]).toString()" aria-controls="subcluster-{{ $cluster->id }}"
                                            class="inline-flex items-center gap-2 rounded-lg border border-sky-200 px-3 py-2 font-semibold text-sky-700 hover:bg-sky-50">
                                            <span x-text="openClusters[{{ $cluster->id }}] ? 'Sembunyikan Subcluster' : 'Lihat Subcluster'">Lihat Subcluster</span>
                                            <svg class="h-4 w-4" :style="{ transform: openClusters[{{ $cluster->id }}] ? 'rotate(180deg)' : 'rotate(0deg)' }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr id="subcluster-{{ $cluster->id }}" x-show="openClusters[{{ $cluster->id }}]" style="display: none;">
                                <td colspan="5" class="bg-slate-50 px-5 py-5">
                                    <div class="rounded-xl border border-slate-200 bg-white">
                                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                                            <h2 class="font-semibold text-slate-800">Subcluster {{ $cluster->nama_cluster }}</h2>
                                            <button type="button" @click="createClusterId = '{{ $cluster->id }}'; $dispatch('open-modal', 'create-subcluster')" @disabled($cluster->status !== 'active') class="rounded-lg border border-sky-200 px-3 py-2 font-semibold text-sky-700 hover:bg-sky-50 disabled:opacity-50">Tambah Subcluster</button>
                                        </div>
                                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                <tr><th scope="col" class="px-4 py-3">Nama Subcluster</th><th scope="col" class="px-4 py-3">Keterangan</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3 text-right">Aksi</th></tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @forelse ($cluster->subClusters as $subCluster)
                                                    @php
                                                        $editSubClusterPayload = ['id' => $subCluster->id, 'cluster_id' => $cluster->id, 'nama_sub_cluster' => $subCluster->nama_sub_cluster, 'keterangan' => $subCluster->keterangan ?? ''];
                                                    @endphp
                                                    <tr style="background-color: {{ $loop->even ? '#f6f9fc' : '#ffffff' }};">
                                                        <td class="px-4 py-3 font-medium text-slate-800">{{ $subCluster->nama_sub_cluster }}</td>
                                                        <td class="px-4 py-3 text-slate-700">{{ $subCluster->keterangan ?: '-' }}</td><td class="px-4 py-3 text-slate-700">{{ $subCluster->status === 'active' ? 'Aktif' : 'Nonaktif' }}{{ $cluster->status !== 'active' ? ' (cluster nonaktif)' : '' }}</td>
                                                        <td class="px-4 py-3">
                                                            <div class="flex justify-end gap-2">
                                                                <button type="button" @click="editSubCluster = @js($editSubClusterPayload); $dispatch('open-modal', 'edit-subcluster')" aria-label="Edit subcluster {{ $subCluster->nama_sub_cluster }}" class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700">Edit</button>
                                                                <form method="POST" action="{{ route('administrasi.manajemen-cluster.subcluster.status', [$module, $cluster->id, $subCluster->id]) }}">
                                                                    @csrf @method('PATCH')
                                                                    <input type="hidden" name="status" value="{{ $subCluster->status === 'active' ? 'inactive' : 'active' }}" />
                                                                    <button class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700">{{ $subCluster->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                                                </form>
                                                                <form method="POST" action="{{ route('administrasi.manajemen-cluster.subcluster.destroy', [$module, $cluster->id, $subCluster->id]) }}" onsubmit="return confirm('Hapus subcluster ini? Subcluster yang sudah digunakan tidak dapat dihapus.')">
                                                                    @csrf @method('DELETE')
                                                                    <input type="hidden" name="cluster_id" value="{{ $cluster->id }}" />
                                                                    <button class="rounded-lg border border-red-200 px-3 py-2 font-semibold text-red-700">Hapus</button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="4" class="px-4 py-5 text-center text-slate-500">Belum ada subcluster.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">{{ empty($filters['keyword']) ? 'Belum ada cluster. Tambahkan cluster untuk mulai mengelola subcluster.' : 'Tidak ada cluster atau subcluster yang sesuai.' }}</td></tr></tbody>
                    @endforelse
                </table>
            </div>
        </div>

        @foreach (['create-cluster' => 'Tambah Cluster', 'edit-cluster' => 'Edit Cluster', 'create-subcluster' => 'Tambah Subcluster', 'edit-subcluster' => 'Edit Subcluster'] as $formName => $formTitle)
            @php
                $isSubCluster = str_contains($formName, 'subcluster');
                $isEdit = str_starts_with($formName, 'edit');
                $nameField = $isSubCluster ? 'nama_sub_cluster' : 'nama_cluster';
                $editState = $isSubCluster ? 'editSubCluster' : 'editCluster';
            @endphp
            <x-modal :name="$formName" :show="$errors->any() && old('_form') === $formName" maxWidth="lg" focusable>
                <form method="POST" class="p-6"
                    @if ($isEdit) :action="{{ $isSubCluster ? 'subClusterActions[editSubCluster.id]' : 'clusterActions[editCluster.id]' }}"
                    @else action="{{ route('administrasi.manajemen-cluster.'.($isSubCluster ? 'subcluster' : 'cluster').'.store', $module) }}" @endif>
                    @csrf
                    @if ($isEdit) @method('PATCH') @endif
                    <input type="hidden" name="_form" value="{{ $formName }}" />
                    @if ($isEdit)
                        <input type="hidden" name="cluster_id" :value="{{ $isSubCluster ? 'editSubCluster.cluster_id' : 'editCluster.id' }}" />
                        @if ($isSubCluster)<input type="hidden" name="sub_cluster_id" :value="editSubCluster.id" />@endif
                    @endif
                    <h2 class="text-xl font-bold text-slate-800">{{ $formTitle }} {{ $moduleLabel }}</h2>
                    @if ($isEdit)<x-master-name-notice />@endif
                    @if ($errors->any() && old('_form') === $formName)
                        <ul class="mt-3 list-inside list-disc text-sm text-red-700" role="alert">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    @endif
                    @if ($isSubCluster && !$isEdit)
                        <label class="mt-5 block text-sm font-medium text-slate-700">Cluster
                            <select name="cluster_id" x-model="createClusterId" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                <option value="">Pilih cluster</option>
                                @foreach ($allClusters->where('status', 'active') as $option)<option value="{{ $option->id }}">{{ $option->nama_cluster }}</option>@endforeach
                            </select>
                        </label>
                    @endif
                    <label class="mt-5 block text-sm font-medium text-slate-700">{{ $isSubCluster ? 'Nama subcluster' : 'Nama cluster' }}
                        <input name="{{ $nameField }}" @if ($isEdit) x-model="{{ $editState }}.{{ $nameField }}" @else value="{{ old('_form') === $formName ? old($nameField) : '' }}" @endif required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                    </label>
                    <label class="mt-4 block text-sm font-medium text-slate-700">Keterangan
                        <textarea name="keterangan" @if ($isEdit) x-model="{{ $editState }}.keterangan" @endif rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ !$isEdit && old('_form') === $formName ? old('keterangan') : '' }}</textarea>
                    </label>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="$dispatch('close-modal', '{{ $formName }}')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Batal</button>
                        <button @if ($isEdit) :disabled="!{{ $isSubCluster ? 'subClusterActions[editSubCluster.id]' : 'clusterActions[editCluster.id]' }}" @endif
                            class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90" style="background-color: #2377b9; border: 1px solid #2377b9; color: #fff;">{{ $isEdit ? 'Simpan Perubahan' : ($isSubCluster ? 'Simpan Subcluster' : 'Simpan Cluster') }}</button>
                    </div>
                </form>
            </x-modal>
        @endforeach
    </div>
</x-app-layout>
