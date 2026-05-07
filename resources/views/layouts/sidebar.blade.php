<aside
    class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-slate-800 text-slate-100 shadow-lg transition-transform duration-300"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    {{-- Logo --}}
    <div class="flex h-20 items-center border-b border-slate-700 px-8">
        <h1 class="text-2xl font-bold tracking-wide text-white">
            SIDEWAS
        </h1>
    </div>

    {{-- Menu --}}
    <nav class="flex-1 overflow-y-auto pb-6">
        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
            class="flex items-center gap-3 px-6 py-4 text-sm font-medium transition
                  {{ request()->routeIs('dashboard') ? 'bg-slate-900 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 13h8V3H3v10Zm10 8h8V3h-8v18ZM3 21h8v-6H3v6Z" />
            </svg>

            <span>Dashboard</span>
        </a>

        {{-- Section Workflow --}}
        <div class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Workflow
        </div>

        {{-- Dropdown SNP Dewas --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-slate-700 hover:text-white">
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

            <div x-show="open" x-transition class="bg-slate-700/60" style="display: none;">
                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar SNP Dewas
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Rekomendasi
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Tindak Lanjut
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Surat Kompilasi
                </a>
            </div>
        </div>

        {{-- Contoh Dropdown Kedua: Ragab --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-slate-700 hover:text-white">
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

            <div x-show="open" x-transition class="bg-slate-700/60" style="display: none;">
                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Ragab
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Keputusan Ragab
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Tindak Lanjut Ragab
                </a>
            </div>
        </div>

        {{-- Contoh Dropdown Ketiga: Rawas --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-slate-700 hover:text-white">
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

            <div x-show="open" x-transition class="bg-slate-700/60" style="display: none;">
                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Rawas
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Keputusan Rawas
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Tindak Lanjut Rawas
                </a>
            </div>
        </div>

        {{-- Contoh Dropdown Keempat: Rekomendasi DJSN --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex w-full items-center justify-between px-6 py-4 text-sm font-medium text-slate-300 transition hover:bg-slate-700 hover:text-white">
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

            <div x-show="open" x-transition class="bg-slate-700/60" style="display: none;">
                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Surat
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Rekomendasi DJSN
                </a>

                <a href="#"
                    class="block border-t border-slate-600 px-12 py-3 text-sm text-slate-300 transition hover:bg-slate-600 hover:text-white">
                    Daftar Tindak Lanjut DJSN
                </a>
            </div>
        </div>
    </nav>
</aside>
