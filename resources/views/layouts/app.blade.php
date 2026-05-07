<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div x-data="{ sidebarOpen: true }" class="min-h-screen bg-gray-100">
        {{-- Sidebar --}}
        @include('layouts.sidebar')

        {{-- Overlay untuk mobile --}}
        <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity
            class="fixed inset-0 z-30 bg-black/40 lg:hidden" style="display: none;">
        </div>

        {{-- Main Content --}}
        <div class="min-h-screen transition-all duration-300" :class="sidebarOpen ? 'lg:pl-72' : 'lg:pl-0'">

            {{-- Top Header --}}
            <header class="sticky top-0 z-30 border-b border-gray-200 bg-white">
                <div class="flex h-20 items-center justify-between px-8">
                    <button type="button" @click="sidebarOpen = !sidebarOpen"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        Menu
                    </button>

                    <div x-data="{ openUserMenu: false }" class="relative">
                        <button type="button" @click="openUserMenu = !openUserMenu"
                            @click.outside="openUserMenu = false"
                            class="inline-flex items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100">
                            <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a8.25 8.25 0 0 1 15 0" />
                            </svg>

                            <span>
                                {{ Auth::user()->email ?? Auth::user()->name }}
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
                                    Logout
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
            <main class="p-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>

</html>
