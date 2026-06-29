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
            $canAccessSnpTanggapan = $authUser?->canAccessSnpTanggapan() ?? false;
            $canAccessSnpTindakLanjut = $authUser?->canAccessSnpTindakLanjut() ?? false;
            $canAccessSnpKompilasi = $authUser?->canAccessSnpKompilasi() ?? false;
            $canAccessSnpReview = $authUser?->canAccessSnpReview() ?? false;
            $canAccessSnpReport = $authUser?->canAccessSnpReport() ?? false;
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
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50
                        Perekaman SNP
                    </a>
@endif

                @if ($canAccessSnpTanggapan)
<a href="{{ route('snp.tanggapan.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('snp.tanggapan.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Tanggapan SNP
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Tanggapan SNP
                    </a>
                @endif

                @if ($canAccessSnpTindakLanjut)
                    <a href="{{ route('snp.tindak-lanjut.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('snp.tindak-lanjut.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Tindak Lanjut SNP
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Tindak Lanjut SNP
                    </a>
                @endif

                @if ($canAccessSnpKompilasi)
                    <a href="{{ route('snp.kompilasi.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('snp.kompilasi.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Kompilasi SNP
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Kompilasi SNP
                    </a>
                @endif

                @if ($canAccessSnpReview)
                    <a href="{{ route('snp.reviu.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('snp.reviu.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Reviu Tanggapan & Tindak Lanjut SNP
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Reviu Tanggapan & Tindak Lanjut SNP
                    </a>
                @endif

                @if ($canAccessSnpReport)
                    <a href="{{ route('snp.report.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('snp.report.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Cetak Laporan SNP
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Cetak Laporan SNP
                    </a>
                @endif
            </div>
        </div>

        {{-- Contoh Dropdown Kedua: Ragab --}}
        @php
            $authUser = \App\Models\User::find(auth()->id());
            $canAccessRagabPerekaman = $authUser?->canAccessRagabPerekaman() ?? false;
            $canAccessRagabTindakLanjut = $authUser?->canAccessRagabTindakLanjut() ?? false;
            $canAccessRagabReview = $authUser?->canAccessRagabReview() ?? false;
            $canAccessRagabReport = $authUser?->canAccessRagabReport() ?? false;
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
                @if ($canAccessRagabPerekaman)
                    <a href="{{ route('ragab.perekaman') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('ragab.perekaman') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Perekaman Keputusan Ragab
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Perekaman Keputusan Ragab
                    </a>
                @endif

                @if ($canAccessRagabTindakLanjut)
                    <a href="{{ route('ragab.tindak-lanjut.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('ragab.tindak-lanjut.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Tindak Lanjut Keputusan Ragab
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Tindak Lanjut Keputusan Ragab
                    </a>
                @endif

                @if ($canAccessRagabReview)
                    <a href="{{ route('ragab.reviu.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('ragab.reviu.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Reviu Tindak Lanjut Keputusan Ragab
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Reviu Tindak Lanjut Keputusan Ragab
                    </a>
                @endif

                @if ($canAccessRagabReport)
                    <a href="{{ route('ragab.report.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('ragab.report.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Cetak Laporan Tindak Lanjut Keputusan Ragab
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Cetak Laporan Tindak Lanjut Keputusan Ragab
                    </a>
                @endif
            </div>
        </div>



        {{-- Contoh Dropdown Ketiga: Rawas --}}
        @php
            $authUser = \App\Models\User::find(auth()->id());
            $canAccessRawasPerekaman = $authUser?->canAccessRawasPerekaman() ?? false;
            $canAccessRawasTindakLanjut = $authUser?->canAccessRawasTindakLanjut() ?? false;
            $canAccessRawasReview = $authUser?->canAccessRawasReview() ?? false;
            $canAccessRawasReport = $authUser?->canAccessRawasReport() ?? false;
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
                @if ($canAccessRawasPerekaman)
                    <a href="{{ route('rawas.perekaman') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('rawas.perekaman') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Perekaman Keputusan Rawas
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Perekaman Keputusan Rawas
                    </a>
                @endif

                @if ($canAccessRawasTindakLanjut)
                    <a href="{{ route('rawas.tindak-lanjut.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('rawas.tindak-lanjut.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Tindak Lanjut Keputusan Rawas
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Tindak Lanjut Keputusan Rawas
                    </a>
                @endif

                @if ($canAccessRawasReview)
                    <a href="{{ route('rawas.reviu.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('rawas.reviu.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Reviu Tindak Lanjut Keputusan Rawas
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Reviu Tindak Lanjut Keputusan Rawas
                    </a>
                @endif

                @if ($canAccessRawasReport)
                    <a href="{{ route('rawas.report.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('rawas.report.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Cetak Laporan Tindak Lanjut Keputusan Rawas
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Cetak Laporan Tindak Lanjut Keputusan Rawas
                    </a>
                @endif
            </div>
        </div>

        {{-- Contoh Dropdown Keempat: Rekomendasi DJSN --}}
        @php
            $authUser = \App\Models\User::find(auth()->id());
            $canAccessDjsnPerekaman = $authUser?->canAccessDjsnPerekaman() ?? false;
            $canAccessDjsnTanggapan = $authUser?->canAccessDjsnTanggapan() ?? false;
            $canAccessDjsnTindakLanjut = $authUser?->canAccessDjsnTindakLanjut() ?? false;
            $canAccessDjsnReview = $authUser?->canAccessDjsnReview() ?? false;
            $canAccessDjsnReport = $authUser?->canAccessDjsnReport() ?? false;
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
                @if ($canAccessDjsnPerekaman)
                    <a href="{{ route('djsn.perekaman') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('djsn.perekaman') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Perekaman Rekomendasi DJSN
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Perekaman Rekomendasi DJSN
                    </a>
                @endif

                @if ($canAccessDjsnTanggapan)
                    <a href="{{ route('djsn.tanggapan.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('djsn.tanggapan.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Tanggapan Rekomendasi DJSN
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Tanggapan Rekomendasi DJSN
                    </a>
                @endif

                @if ($canAccessDjsnTindakLanjut)
                    <a href="{{ route('djsn.tindak-lanjut.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('djsn.tindak-lanjut.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Tindak Lanjut Rekomendasi DJSN
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Tindak Lanjut Rekomendasi DJSN
                    </a>
                @endif

                @if ($canAccessDjsnReview)
                    <a href="{{ route('djsn.reviu.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('djsn.reviu.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Reviu Tanggapan & Tindak Lanjut Rekomendasi DJSN
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Reviu Tanggapan & Tindak Lanjut Rekomendasi DJSN
                    </a>
                @endif

                @if ($canAccessDjsnReport)
                    <a href="{{ route('djsn.report.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('djsn.report.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Cetak Laporan Tanggapan & Tindak Lanjut Rekomendasi DJSN
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Cetak Laporan Tanggapan & Tindak Lanjut Rekomendasi DJSN
                    </a>
                @endif
            </div>
        </div>

        {{-- Dropdown Rapat Eksternal --}}
        @php
            $authUser = \App\Models\User::find(auth()->id());
            $canAccessEksternalPerekaman = $authUser?->canAccessEksternalPerekaman() ?? false;
            $canAccessEksternalTindakLanjut = $authUser?->canAccessEksternalTindakLanjut() ?? false;
            $canAccessEksternalReview = $authUser?->canAccessEksternalReview() ?? false;
            $canAccessEksternalReport = $authUser?->canAccessEksternalReport() ?? false;
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

                    <span>Rapat Eksternal</span>
                </span>

                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="open" x-transition
                class="mx-4 overflow-hidden rounded-xl bg-white border border-slate-200 shadow-sm"
                style="display: none;">
                @if ($canAccessEksternalPerekaman)
                    <a href="{{ route('eksternal.perekaman') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('eksternal.perekaman') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Perekaman Rapat Eksternal
                    </a>
                @else
                    <a href="#"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Perekaman Rapat Eksternal
                    </a>
                @endif

                @if ($canAccessEksternalTindakLanjut)
                    <a href="{{ route('eksternal.tindak-lanjut.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                        {{ request()->routeIs('eksternal.tindak-lanjut.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Tindak Lanjut Rapat Eksternal
                    </a>
                @else
                    <a href="{{ route('eksternal.tindak-lanjut.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Tindak Lanjut Rapat Eksternal
                    </a>
                @endif

                @if ($canAccessEksternalReview)
                    <a href="{{ route('eksternal.reviu.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('eksternal.reviu.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Reviu Tindak Lanjut Rapat Eksternal
                    </a>
                @else
                    <a href="{{ route('eksternal.reviu.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Reviu Tindak Lanjut Rapat Eksternal
                    </a>
                @endif

                @if ($canAccessEksternalReport)
                    <a href="{{ route('eksternal.report.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition
                    {{ request()->routeIs('eksternal.report.*') ? 'bg-blue-50 text-sidewas-blue font-semibold' : 'text-slate-600 hover:bg-blue-50 hover:text-sidewas-blue' }}">
                        Cetak Laporan Tindak Lanjut Rapat Eksternal
                    </a>
                @else
                    <a href="{{ route('eksternal.report.index') }}"
                        class="block border-b border-slate-200 px-10 py-3 text-sm transition cursor-not-allowed opacity-60 text-slate-600 hover:bg-blue-50">
                        Cetak Laporan Tindak Lanjut Rapat Eksternal
                    </a>
                @endif
            </div>
        </div>

        {{-- Produk Hukum --}}
        @php
            $canAccessProdukHukum = $authUser?->canAccessProdukHukum() ?? false;
        @endphp

        @if ($canAccessProdukHukum)
            <a href="{{ route('produk-hukum.index') }}"
                class="group flex w-full items-center justify-between px-6 py-4 text-sm font-medium transition hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70
                    {{ request()->routeIs('produk-hukum.*')
                        ? 'bg-white text-sidewas-blue font-semibold is-active'
                        : 'text-slate-600' }}">

                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-200">
                        <svg class="h-6 w-6 text-slate-500" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9A2.5 2.5 0 0 0 19 18.5V8L14 3Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                            <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path d="M8.5 11.5h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M8.5 15h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M15.3 18.2h3.2" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" />
                            <path d="M16.9 16.6v3.2" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" />
                        </svg>
                    </span>

                    <span>Produk Hukum</span>
                </span>
            </a>
        @else
            <a href="#"
                class="group flex w-full items-center justify-between px-6 py-4 text-sm font-medium transition cursor-not-allowed hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70
                    {{ request()->routeIs('produk-hukum.*')
                        ? 'bg-white text-sidewas-blue font-semibold is-active'
                        : 'text-slate-600' }}">

                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-200">
                        <svg class="h-6 w-6 text-slate-500" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9A2.5 2.5 0 0 0 19 18.5V8L14 3Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                            <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path d="M8.5 11.5h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M8.5 15h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M15.3 18.2h3.2" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" />
                            <path d="M16.9 16.6v3.2" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" />
                        </svg>
                    </span>

                    <span>Produk Hukum</span>
                </span>
            </a>
        @endif

        {{-- Section Workflow --}}
        @php
            $authUser = \App\Models\User::find(auth()->id());
            $canAccessManajemenUser = $authUser?->canAccessManajemenUser() ?? false;
            $canAccessPengajuan = $authUser?->canAccessPengajuan() ?? false;
        @endphp

        <div class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Administrasi
        </div>
        @if ($canAccessManajemenUser)
            <a href="{{ route('administrasi.manajemen-user.index') }}"
                class="group flex w-full items-center justify-between px-6 py-4 text-sm font-medium transition hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70
                {{ request()->routeIs('administrasi.manajemen-user.*')
                    ? 'bg-white text-sidewas-blue font-semibold is-active'
                    : 'text-slate-600' }}">

                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl shadow-sm ring-1 ring-slate-200">
                        <svg class="h-10 w-10" viewBox="0 0 100 100" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="mgGrad" x1="0" y1="1" x2="1"
                                    y2="0">
                                    <stop offset="0%" stop-color="#38BDF8" />
                                    <stop offset="100%" stop-color="#0EA5E9" />
                                </linearGradient>
                            </defs>

                            <!-- Background putih (default) -->
                            <rect x="0" y="0" width="100" height="100" rx="22" fill="white"
                                class="transition-opacity duration-300 group-hover:opacity-0 group-[.is-active]:opacity-0" />

                            <!-- Background gradient (hover/active) -->
                            <rect x="0" y="0" width="100" height="100" rx="22" fill="url(#mgGrad)"
                                class="opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-[.is-active]:opacity-100" />

                            <!-- Gear 8 gigi simetris -->
                            <path
                                d="M 50.0 14.0 A 27 27 0 0 1 55.8 14.6 L 60.2 21.8 A 36 36 0 0 1 65.5 24.5 L 73.8 22.0 A 27 27 0 0 1 78.0 26.2 L 75.5 34.5 A 36 36 0 0 1 78.2 39.8 L 85.4 44.2 A 27 27 0 0 1 86.0 50.0 A 27 27 0 0 1 85.4 55.8 L 78.2 60.2 A 36 36 0 0 1 75.5 65.5 L 78.0 73.8 A 27 27 0 0 1 73.8 78.0 L 65.5 75.5 A 36 36 0 0 1 60.2 78.2 L 55.8 85.4 A 27 27 0 0 1 50.0 86.0 A 27 27 0 0 1 44.2 85.4 L 39.8 78.2 A 36 36 0 0 1 34.5 75.5 L 26.2 78.0 A 27 27 0 0 1 22.0 73.8 L 24.5 65.5 A 36 36 0 0 1 21.8 60.2 L 14.6 55.8 A 27 27 0 0 1 14.0 50.0 A 27 27 0 0 1 14.6 44.2 L 21.8 39.8 A 36 36 0 0 1 24.5 34.5 L 22.0 26.2 A 27 27 0 0 1 26.2 22.0 L 34.5 24.5 A 36 36 0 0 1 39.8 21.8 L 44.2 14.6 A 27 27 0 0 1 50.0 14.0 Z"
                                class="fill-sky-400 transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />

                            <!-- Lubang tengah gear -->
                            <circle cx="50" cy="50" r="16"
                                class="fill-white transition-all duration-300 group-hover:fill-[#1ab0f0] group-[.is-active]:fill-[#1ab0f0]" />

                            <!-- Kepala user -->
                            <circle cx="50" cy="44" r="5.5"
                                class="fill-sky-400 transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />

                            <!-- Badan user -->
                            <path d="M40,59 Q40,49 50,49 Q60,49 60,59 Z"
                                class="fill-sky-400 transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />
                        </svg>
                    </span>

                    <span>Manajemen User</span>
                </span>
            </a>
        @else
            <a href="#"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-sidewas-blue cursor-not-allowed opacity-60">
                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl shadow-sm ring-1 ring-slate-200">
                        <svg class="h-10 w-10" viewBox="0 0 100 100" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="mgGrad" x1="0" y1="1" x2="1"
                                    y2="0">
                                    <stop offset="0%" stop-color="#38BDF8" />
                                    <stop offset="100%" stop-color="#0EA5E9" />
                                </linearGradient>
                            </defs>

                            <!-- Background putih (default) -->
                            <rect x="0" y="0" width="100" height="100" rx="22" fill="white"
                                class="transition-opacity duration-300 group-hover:opacity-0 group-[.is-active]:opacity-0" />

                            <!-- Background gradient (hover/active) -->
                            <rect x="0" y="0" width="100" height="100" rx="22" fill="url(#mgGrad)"
                                class="opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-[.is-active]:opacity-100" />

                            <!-- Gear 8 gigi simetris -->
                            <path
                                d="M 50.0 14.0 A 27 27 0 0 1 55.8 14.6 L 60.2 21.8 A 36 36 0 0 1 65.5 24.5 L 73.8 22.0 A 27 27 0 0 1 78.0 26.2 L 75.5 34.5 A 36 36 0 0 1 78.2 39.8 L 85.4 44.2 A 27 27 0 0 1 86.0 50.0 A 27 27 0 0 1 85.4 55.8 L 78.2 60.2 A 36 36 0 0 1 75.5 65.5 L 78.0 73.8 A 27 27 0 0 1 73.8 78.0 L 65.5 75.5 A 36 36 0 0 1 60.2 78.2 L 55.8 85.4 A 27 27 0 0 1 50.0 86.0 A 27 27 0 0 1 44.2 85.4 L 39.8 78.2 A 36 36 0 0 1 34.5 75.5 L 26.2 78.0 A 27 27 0 0 1 22.0 73.8 L 24.5 65.5 A 36 36 0 0 1 21.8 60.2 L 14.6 55.8 A 27 27 0 0 1 14.0 50.0 A 27 27 0 0 1 14.6 44.2 L 21.8 39.8 A 36 36 0 0 1 24.5 34.5 L 22.0 26.2 A 27 27 0 0 1 26.2 22.0 L 34.5 24.5 A 36 36 0 0 1 39.8 21.8 L 44.2 14.6 A 27 27 0 0 1 50.0 14.0 Z"
                                class="fill-sky-400 transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />

                            <!-- Lubang tengah gear -->
                            <circle cx="50" cy="50" r="16"
                                class="fill-white transition-all duration-300 group-hover:fill-[#1ab0f0] group-[.is-active]:fill-[#1ab0f0]" />

                            <!-- Kepala user -->
                            <circle cx="50" cy="44" r="5.5"
                                class="fill-sky-400 transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />

                            <!-- Badan user -->
                            <path d="M40,59 Q40,49 50,49 Q60,49 60,59 Z"
                                class="fill-sky-400 transition-all duration-300 group-hover:fill-white group-[.is-active]:fill-white" />
                        </svg>
                    </span>

                    <span>Manajemen User</span>
                </span>
            </a>
        @endif
        @if ($canAccessPengajuan)
            <a href="{{ route('administrasi.pengajuan.index') }}"
                class="group flex w-full items-center justify-between px-6 py-4 text-sm font-medium transition hover:bg-white hover:text-sidewas-blue border-b border-slate-300/70
                    {{ request()->routeIs('administrasi.pengajuan.*')
                        ? 'bg-white text-sidewas-blue font-semibold is-active'
                        : 'text-slate-600' }}">

                <span class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl shadow-sm ring-1 ring-slate-200">
                        <svg class="h-10 w-10" viewBox="0 0 100 100" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="pjGrad" x1="0" y1="1" x2="1"
                                    y2="0">
                                    <stop offset="0%" stop-color="#818CF8" />
                                    <stop offset="100%" stop-color="#38BDF8" />
                                </linearGradient>
                            </defs>

                            <!-- Background putih (default) -->
                            <rect x="0" y="0" width="100" height="100" rx="22" fill="white"
                                class="transition-opacity duration-300 group-hover:opacity-0 group-[.is-active]:opacity-0" />

                            <!-- Background gradient (hover/active) -->
                            <rect x="0" y="0" width="100" height="100" rx="22" fill="url(#pjGrad)"
                                class="opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-[.is-active]:opacity-100" />

                            <!-- Dokumen belakang -->
                            <rect x="14" y="20" width="44" height="54" rx="4" fill="none"
                                stroke-width="2.2"
                                class="stroke-indigo-300 transition-all duration-300 group-hover:stroke-white group-hover:stroke-opacity-60 group-[.is-active]:stroke-white" />

                            <!-- Dokumen depan body -->
                            <path d="M24 14 H54 L66 26 V72 Q66 76 62 76 H24 Q20 76 20 72 V18 Q20 14 24 14 Z"
                                stroke-width="2.2" stroke-linejoin="round"
                                class="fill-white stroke-indigo-400 transition-all duration-300 group-hover:fill-white/20 group-hover:stroke-white group-[.is-active]:fill-white/20 group-[.is-active]:stroke-white" />

                            <!-- Lipatan pojok -->
                            <path d="M54 14 L54 26 L66 26" fill="none" stroke-width="2.2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="stroke-indigo-400 transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />

                            <!-- Garis header dokumen (kotak kecil) -->
                            <rect x="28" y="33" width="18" height="4" rx="2" fill="none"
                                stroke-width="1.8"
                                class="stroke-indigo-400 transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />

                            <!-- Garis isi dokumen -->
                            <line x1="28" y1="44" x2="58" y2="44" stroke-width="1.8"
                                stroke-linecap="round"
                                class="stroke-indigo-400 transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />
                            <line x1="28" y1="51" x2="58" y2="51" stroke-width="1.8"
                                stroke-linecap="round"
                                class="stroke-indigo-400 transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />
                            <line x1="28" y1="58" x2="52" y2="58" stroke-width="1.8"
                                stroke-linecap="round"
                                class="stroke-indigo-400 transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />

                            <!-- Lingkaran upload: fill putih saat default, transparan saat hover -->
                            <circle cx="68" cy="70" r="18" stroke-width="2.2"
                                class="fill-white stroke-indigo-400 transition-all duration-300 group-hover:fill-white/20 group-hover:stroke-white group-[.is-active]:fill-white/20 group-[.is-active]:stroke-white" />

                            <!-- Panah upload -->
                            <line x1="68" y1="76" x2="68" y2="62" stroke-width="2.2"
                                stroke-linecap="round"
                                class="stroke-indigo-400 transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />
                            <path d="M62 68 L68 62 L74 68" fill="none" stroke-width="2.2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="stroke-indigo-400 transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />
                            <line x1="63" y1="77" x2="73" y2="77" stroke-width="2.2"
                                stroke-linecap="round"
                                class="stroke-indigo-400 transition-all duration-300 group-hover:stroke-white group-[.is-active]:stroke-white" />
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
