<aside
    class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-[#F3F4F6] text-slate-700 shadow-lg border-r border-slate-200 transition-transform duration-300"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    {{-- Logo --}}
    <div class="px-6 py-6 border-b border-slate-200">
        <img src="{{ asset('storage/images/logo-sidewas-baru.png') }}" alt="Logo SIDEWAS"
            class="mx-auto w-57 h-auto object-contain drop-shadow-md transition duration-300 hover:scale-105">
    </div>

    {{-- Menu --}}
    <nav class="flex-1 overflow-y-auto pb-6">
        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
            class="group flex items-center gap-3 px-4 py-3 mx-4 rounded-xl text-sm font-semibold transition border-b border-slate-200/70
            {{ request()->routeIs('dashboard')
                ? 'bg-white text-sidewas-blue shadow-sm border border-slate-200 is-active'
                : 'text-slate-600 hover:bg-white hover:text-sidewas-blue' }}">

            <span class="flex h-10 w-10 items-center justify-center rounded-xl shadow-sm ring-1 ring-slate-200">
                <svg class="h-10 w-10" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="bgGrad" x1="0" y1="1" x2="1" y2="0">
                            <stop offset="0%" stop-color="#1D75B9" />
                            <stop offset="55%" stop-color="#18B7A3" />
                            <stop offset="100%" stop-color="#A6CE39" />
                        </linearGradient>
                        <linearGradient id="strokeGrad" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#1D75B9" />
                            <stop offset="50%" stop-color="#18B7A3" />
                            <stop offset="100%" stop-color="#A6CE39" />
                        </linearGradient>
                    </defs>

                    <!-- Background putih (default) -->
                    <rect x="0" y="0" width="100" height="100" rx="22" ry="22" fill="white"
                        class="transition-opacity duration-300 group-hover:opacity-0 group-[.is-active]:opacity-0" />

                    <!-- Background gradient (hover / active) -->
                    <rect x="0" y="0" width="100" height="100" rx="22" ry="22" fill="url(#bgGrad)"
                        class="opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-[.is-active]:opacity-100" />

                    <!-- Y axis -->
                    <line x1="20" y1="18" x2="20" y2="76" stroke-width="2.5"
                        stroke-linecap="round"
                        class="[stroke:url(#strokeGrad)] transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />

                    <!-- X axis -->
                    <line x1="20" y1="76" x2="84" y2="76" stroke-width="2.5"
                        stroke-linecap="round"
                        class="[stroke:url(#strokeGrad)] transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />

                    <!-- Line connecting dots -->
                    <polyline points="26,62 40,48 54,57 72,34" fill="none" stroke-width="2.2" stroke-linecap="round"
                        stroke-linejoin="round"
                        class="[stroke:url(#strokeGrad)] transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />

                    <!-- Dots -->
                    <circle cx="26" cy="62" r="4"
                        class="fill-[#1D75B9] transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />
                    <circle cx="40" cy="48" r="4"
                        class="fill-[#18B7A3] transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />
                    <circle cx="54" cy="57" r="4"
                        class="fill-[#31B96E] transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />
                    <circle cx="72" cy="34" r="4"
                        class="fill-[#A6CE39] transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />
                </svg>
            </span>

            <span>Statistik</span>

            @if (request()->routeIs('dashboard'))
                <span class="ml-auto h-8 w-1 rounded-full bg-sidewas-yellow"></span>
            @endif
        </a>

        {{-- Section Workflow --}}
        <div class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Menu
        </div>

        {{-- Dropdown SNP Dewas --}}
        @php
            $authUser = \App\Models\User::find(auth()->id());
            $canAccessSnpPerekaman = $authUser?->canAccessSnpPerekaman() ?? false;
        @endphp
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70">
                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-200">
                        <svg class="h-6 w-6 text-slate-500" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9A2.5 2.5 0 0 0 19 18.5V8L14 3Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />

                            <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />

                            <path d="M8.5 12h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                            <path d="M8.5 15.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                            <path d="M14.2 17.2l3.4-3.4a1.1 1.1 0 0 1 1.6 1.6l-3.4 3.4-2 .6.4-2.2Z"
                                stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span>

                    <span>SNP Dewas</span>
                </span>

                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-transition
                class="mx-4 overflow-hidden rounded-xl bg-white border border-slate-200 shadow-sm"
                style="display: none;">
                @if ($canAccessSnpPerekaman)
                    <a href="{{ route('snp.perekaman') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('snp.perekaman') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Perekaman SNP
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed
                        {{ request()->routeIs('snp.perekaman') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Perekaman SNP
                    </a>
                @endif

                <a href="{{ route('snp.tanggapan.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('snp.tanggapan.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Tanggapan SNP
                </a>

                <a href="{{ route('snp.tindak-lanjut.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('snp.tindak-lanjut.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Tindak Lanjut SNP
                </a>

                <a href="{{ route('snp.reviu.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('snp.reviu.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Reviu Tanggapan & Tindak Lanjut SNP
                </a>

                <a href="{{ route('snp.report.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('snp.report.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Cetak Laporan SNP
                </a>
            </div>
        </div>

        {{-- Contoh Dropdown Kedua: Ragab --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70">
                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-200">
                        <svg class="h-6 w-6 text-slate-500" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9A2.5 2.5 0 0 0 19 18.5V8L14 3Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />

                            <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />

                            <path d="M8.5 12h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                            <path d="M8.5 15.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                            <path d="M14.2 17.2l3.4-3.4a1.1 1.1 0 0 1 1.6 1.6l-3.4 3.4-2 .6.4-2.2Z"
                                stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span>

                    <span>Ragab</span>
                </span>

                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-transition
                class="mx-4 overflow-hidden rounded-xl bg-white border border-slate-200 shadow-sm"
                style="display: none;">
                <a href="{{ route('ragab.perekaman') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('ragab.perekaman') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Perekaman Keputusan Ragab
                </a>

                <a href="{{ route('ragab.tindak-lanjut.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('ragab.tindak-lanjut.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Tindak Lanjut Keputusan Ragab
                </a>

                <a href="{{ route('ragab.reviu.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('ragab.reviu.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Reviu Tindak Lanjut Keputusan Ragab
                </a>

                <a href="{{ route('ragab.report.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('ragab.report.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Cetak Laporan Tindak Lanjut Keputusan Ragab
                </a>
            </div>
        </div>

        {{-- Contoh Dropdown Ketiga: Rawas --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70">
                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-200">
                        <svg class="h-6 w-6 text-slate-500" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9A2.5 2.5 0 0 0 19 18.5V8L14 3Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />

                            <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />

                            <path d="M8.5 12h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                            <path d="M8.5 15.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                            <path d="M14.2 17.2l3.4-3.4a1.1 1.1 0 0 1 1.6 1.6l-3.4 3.4-2 .6.4-2.2Z"
                                stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span>

                    <span>Rawas</span>
                </span>

                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-transition
                class="mx-4 overflow-hidden rounded-xl bg-white border border-slate-200 shadow-sm"
                style="display: none;">
                <a href="{{ route('rawas.perekaman') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('rawas.perekaman') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Perekaman Keputusan Rawas
                </a>

                <a href="{{ route('rawas.tindak-lanjut.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('rawas.tindak-lanjut.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Tindak Lanjut Keputusan Rawas
                </a>

                <a href="{{ route('rawas.reviu.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('rawas.reviu.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Reviu Tindak Lanjut Keputusan Rawas
                </a>

                <a href="{{ route('rawas.report.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('rawas.report.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Cetak Laporan Tindak Lanjut Keputusan Rawas
                </a>
            </div>
        </div>

        {{-- Contoh Dropdown Keempat: Rekomendasi DJSN --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70">
                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-200">
                        <svg class="h-6 w-6 text-slate-500" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9A2.5 2.5 0 0 0 19 18.5V8L14 3Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />

                            <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />

                            <path d="M8.5 12h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                            <path d="M8.5 15.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />

                            <path d="M14.2 17.2l3.4-3.4a1.1 1.1 0 0 1 1.6 1.6l-3.4 3.4-2 .6.4-2.2Z"
                                stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span>

                    <span>Rekomendasi DJSN</span>
                </span>

                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-transition
                class="mx-4 overflow-hidden rounded-xl bg-white border border-slate-200 shadow-sm"
                style="display: none;">
                <a href="{{ route('djsn.perekaman') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('djsn.perekaman') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Perekaman Rekomendasi DJSN
                </a>

                <a href="{{ route('djsn.tanggapan.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('djsn.tanggapan.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Tanggapan Rekomendasi DJSN
                </a>

                <a href="{{ route('djsn.tindak-lanjut.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('djsn.tindak-lanjut.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Tindak Lanjut Rekomendasi DJSN
                </a>

                <a href="{{ route('djsn.reviu.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('djsn.reviu.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Reviu Tanggapan & Tindak Lanjut Rekomendasi DJSN
                </a>

                <a href="{{ route('djsn.report.index') }}"
                    class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('djsn.report.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                    Cetak Laporan Tanggapan & Tindak Lanjut Rekomendasi DJSN
                </a>
            </div>
        </div>

        {{-- Section Workflow --}}
        @php
            $authUser = \App\Models\User::find(auth()->id());
            $canAccessPengajuan = $authUser?->canAccessPengajuan() ?? false;
        @endphp

        <div class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Administrasi
        </div>
        <a href="#"
            class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70">
            <span class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-sky-100">
                    <svg class="h-6 w-6 text-sky-500" viewBox="0 0 24 24" fill="none">
                        <path d="M10.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />

                        <path d="M3.5 20a7 7 0 0 1 10.4-6.1" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round" />

                        <path d="M17.5 14.5v1.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />

                        <path d="M17.5 20.4v1.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />

                        <path d="M14.45 16.25l.95.55" stroke="currentColor" stroke-width="1.7"
                            stroke-linecap="round" />

                        <path d="M19.6 19.2l.95.55" stroke="currentColor" stroke-width="1.7"
                            stroke-linecap="round" />

                        <path d="M14.45 19.75l.95-.55" stroke="currentColor" stroke-width="1.7"
                            stroke-linecap="round" />

                        <path d="M19.6 16.8l.95-.55" stroke="currentColor" stroke-width="1.7"
                            stroke-linecap="round" />

                        <circle cx="17.5" cy="18" r="2.1" stroke="currentColor" stroke-width="1.7" />
                    </svg>
                </span>

                <span>Manajemen User</span>
            </span>
        </a>
        @if ($canAccessPengajuan)
            <a href="{{ route('administrasi.pengajuan.index') }}"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70">
                <span class="flex items-center gap-3">
                    <span class="flex items-center gap-3">
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 ring-1 ring-indigo-100">
                            <svg class="h-6 w-6 text-blue-500" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9A2.5 2.5 0 0 0 19 18.5V8L14 3Z"
                                    stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path d="M8.5 11.5h5" stroke="currentColor" stroke-width="1.7"
                                    stroke-linecap="round" />
                                <path d="M8.5 14.5h4" stroke="currentColor" stroke-width="1.7"
                                    stroke-linecap="round" />
                                <circle cx="17.5" cy="17.5" r="4" fill="white" stroke="currentColor"
                                    stroke-width="1.7" />
                                <path d="M17.5 19.5v-4" stroke="currentColor" stroke-width="1.7"
                                    stroke-linecap="round" />
                                <path d="M15.9 17.1l1.6-1.6 1.6 1.6" stroke="currentColor" stroke-width="1.7"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>

                        <span>Pengajuan</span>
                    </span>
            </a>
        @else
            <a href="#"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-sidewas-blue cursor-not-allowed opacity-60">
                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 ring-1 ring-indigo-100">
                        <svg class="h-6 w-6 text-blue-500" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9A2.5 2.5 0 0 0 19 18.5V8L14 3Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                            <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path d="M8.5 11.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                            <path d="M8.5 14.5h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                            <circle cx="17.5" cy="17.5" r="4" fill="white" stroke="currentColor"
                                stroke-width="1.7" />
                            <path d="M17.5 19.5v-4" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round" />
                            <path d="M15.9 17.1l1.6-1.6 1.6 1.6" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>

                    <span>Pengajuan</span>
                </span>
            </a>
        @endif
    </nav>
</aside>
