<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="{{ asset('storage/images/icon-sidewas.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    @php
        $headerUser = Auth::user();
        $headerAvatar = $headerUser?->avatar;
        $headerAvatarUrl = null;

        if ($headerAvatar) {
            $headerAvatarUrl = preg_match('/^(https?:)?\/\//', $headerAvatar)
                ? $headerAvatar
                : asset(str_starts_with($headerAvatar, 'storage/') ? $headerAvatar : 'storage/' . ltrim($headerAvatar, '/'));
        }
    @endphp

    <div x-data="{ sidebarOpen: true }" class="min-h-screen bg-[#eef6fb]"
        style="
        background:
            radial-gradient(circle at 8% 45%, rgba(129, 196, 65, 0.18), transparent 32%),
            radial-gradient(circle at 82% 28%, rgba(29, 117, 185, 0.16), transparent 34%),
            linear-gradient(135deg, #f7fbf7 0%, #eef6fb 45%, #f4f8ff 100%);
    ">
        {{-- Sidebar --}}
        @include('layouts.sidebar')

        {{-- Overlay untuk mobile --}}
        <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity
            class="fixed inset-0 z-30 bg-black/40 lg:hidden" style="display: none;">
        </div>

        {{-- Main Content --}}
        <div class="flex min-h-screen flex-col transition-all duration-300"
            :class="sidebarOpen ? 'lg:pl-72' : 'lg:pl-0'">

            {{-- Top Header --}}
            <header class="sticky top-0 z-30 border-b border-gray-200 bg-white">
                <div class="flex h-20 items-center justify-between px-8">
                    <button type="button" @click="sidebarOpen = !sidebarOpen"
                        class="inline-flex items-center gap-2 rounded-md bg-sidewas-blue px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        Menu
                    </button>

                    <div x-data="{ openUserMenu: false }" class="relative">
                        <button type="button" @click="openUserMenu = !openUserMenu"
                            @click.outside="openUserMenu = false"
                            class="inline-flex items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100">
                            @if ($headerAvatarUrl)
                                <img src="{{ $headerAvatarUrl }}"
                                    alt="{{ $headerUser?->name ? 'Avatar ' . $headerUser->name : 'Avatar user' }}"
                                    class="h-10 w-10 rounded-full border border-blue-100 object-cover shadow"
                                    referrerpolicy="no-referrer">
                            @else
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-sidewas-blue text-white shadow">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a8.25 8.25 0 0 1 15 0" />
                                    </svg>
                                </div>
                            @endif
                            <span>
                                {{ $headerUser?->email ?? $headerUser?->name }}
                            </span>

                            <svg class="h-4 w-4 text-gray-500 transition-transform"
                                :class="{ 'rotate-180': openUserMenu }" fill="none" stroke="currentColor"
                                stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="openUserMenu" x-transition
                            class="absolute right-0 mt-2 w-48 overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg"
                            style="display: none;">
                            <a href="#"
                                class="block px-4 py-3 text-sm text-gray-700 transition hover:bg-gray-100">
                                Profil
                            </a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <button type="submit"
                                    class="block w-full px-4 py-3 text-left text-sm text-red-600 transition hover:bg-red-50">
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Optional Breeze Header --}}
            @isset($header)
                <div class="bg-white shadow">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </div>
            @endisset

            {{-- Page Content --}}
            <main class="flex-1 p-8">
                {{ $slot }}
            </main>

            {{-- Footer --}}
            <footer class="border-t border-gray-200 bg-white px-8 py-4">
                <div class="flex flex-col gap-2 text-sm text-gray-500 md:flex-row md:items-center md:justify-between">
                    <p>
                        &copy; {{ date('Y') }} Sekretariat Dewan Pengawas. Seluruh hak cipta dilindungi.
                    </p>

                    <a href="https://mail.google.com/mail/?view=cm&fs=1&to=pepd.sekdewas@bpjsketenagakerjaan.go.id"
                        class="font-semibold text-sidewas-blue transition hover:text-blue-700 hover:underline"
                        target="_blank">
                        Hubungi Kami
                    </a>
                </div>
            </footer>
        </div>
    </div>
</body>

</html>
