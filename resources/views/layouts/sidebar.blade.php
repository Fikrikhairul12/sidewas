<aside
    class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-sidewas-blue text-white shadow-lg transition-transform duration-300"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    {{-- Logo --}}
    <div class="flex h-20 items-center border-b border-white/10 px-8">
        <h1 class="text-2xl font-bold tracking-wide text-white">
            SIDEWAS
        </h1>
    </div>

    {{-- Menu --}}
    <nav class="flex-1 overflow-y-auto pb-6">
        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
            class="flex items-center gap-3 px-1 py-2 mx-4 rounded text-sm font-medium transition
                  {{ request()->routeIs('dashboard') ? 'bg-white/15 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 13h8V3H3v10Zm10 8h8V3h-8v18ZM3 21h8v-6H3v6Z" />
                </svg>
            </span>

            <span>Statistik</span>
            @if (request()->routeIs('dashboard'))
                <span class="ml-auto h-8 w-1 rounded-full bg-sidewas-yellow"></span>
            @endif
        </a>

        {{-- Section Workflow --}}
        <div class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-blue-100/80">
            Menu
        </div>

        {{-- Dropdown SNP Dewas --}}
        @php
            $authUser = \App\Models\User::find(auth()->id());
            $canAccessSnpPerekaman = $authUser?->canAccessSnpPerekaman() ?? false;
        @endphp
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
                <span class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                    </svg>

                    <span>SNP Dewas</span>
                </span>

                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-transition class="bg-slate-700/20" style="display: none;">
                @if ($canAccessSnpPerekaman)
                    <a href="{{ route('snp.perekaman') }}"
                        class="block border-b border-white/10 px-12 py-3 text-sm transition
                        {{ request()->routeIs('snp.perekaman') ? 'bg-white/15 text-white font-semibold' : 'text-blue-50 hover:bg-white/10 hover:text-white' }}">
                        Perekaman SNP
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-white/10 px-12 py-3 text-sm transition cursor-not-allowed
                        {{ request()->routeIs('snp.perekaman') ? 'bg-white/15 text-white font-semibold' : 'text-blue-50 hover:bg-white/10 hover:text-white' }}">
                        Perekaman SNP
                    </a>
                @endif

                <a href="{{ route('snp.tanggapan.index') }}"
                    class="block border-b border-white/10 px-12 py-3 text-sm transition
                    {{ request()->routeIs('snp.tanggapan.*') ? 'bg-white/15 text-white font-semibold' : 'text-blue-50 hover:bg-white/10 hover:text-white' }}">
                    Tanggapan SNP
                </a>

                <a href="{{ route('snp.tindak-lanjut.index') }}"
                    class="block border-b border-white/10 px-12 py-3 text-sm transition
                    {{ request()->routeIs('snp.tindak-lanjut.*') ? 'bg-white/15 text-white font-semibold' : 'text-blue-50 hover:bg-white/10 hover:text-white' }}">
                    Tindak Lanjut SNP
                </a>

                <a href="{{ route('snp.reviu.index') }}"
                    class="block border-b border-white/10 px-12 py-3 text-sm transition
                    {{ request()->routeIs('snp.reviu.*') ? 'bg-white/15 text-white font-semibold' : 'text-blue-50 hover:bg-white/10 hover:text-white' }}">
                    Reviu SNP
                </a>

                <a href="{{ route('snp.report.index') }}"
                    class="block border-b border-white/10 px-12 py-3 text-sm transition
                    {{ request()->routeIs('snp.report.*') ? 'bg-white/15 text-white font-semibold' : 'text-blue-50 hover:bg-white/10 hover:text-white' }}">
                    Cetak Laporan SNP
                </a>
            </div>
        </div>

        {{-- Contoh Dropdown Kedua: Ragab --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
                <span class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                    </svg>

                    <span>Ragab</span>
                </span>

                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-transition class="bg-slate-700/20" style="display: none;">
                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Perekaman Ragab
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Tindak Lanjut Ragab
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Reviu Ragab
                </a>
            </div>
        </div>

        {{-- Contoh Dropdown Ketiga: Rawas --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
                <span class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                    </svg>

                    <span>Rawas</span>
                </span>

                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-transition class="bg-slate-700/20" style="display: none;">
                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Perekaman Rawas
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Tindak Lanjut Rawas
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Reviu Rawas
                </a>
            </div>
        </div>

        {{-- Contoh Dropdown Keempat: Rekomendasi DJSN --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
                <span class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                    </svg>

                    <span>Rekomendasi DJSN</span>
                </span>

                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-transition class="bg-slate-700/20" style="display: none;">
                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Perekaman DJSN
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Tanggapan DJSN
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Tindak Lanjut DJSN
                </a>
                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Reviu DJSN
                </a>
            </div>
        </div>

        {{-- Section Workflow --}}
        @php
            $authUser = \App\Models\User::find(auth()->id());
            $canAccessPengajuan = $authUser?->canAccessPengajuan() ?? false;
        @endphp

        <div class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-blue-100/80">
            Administrasi
        </div>
        <a href="#"
            class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
            <span class="flex items-center gap-3">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                </svg>

                <span>Manajemen User</span>
            </span>
        </a>
        @if ($canAccessPengajuan)
            <a href="{{ route('administrasi.pengajuan.index') }}"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
                <span class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                    </svg>

                    <span>Pengajuan</span>
                </span>
            </a>
        @else
            <a href="#"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white cursor-not-allowed">
                <span class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                    </svg>

                    <span>Pengajuan</span>
                </span>
            </a>
        @endif
    </nav>
</aside>
