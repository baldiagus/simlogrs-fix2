<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'SIMLOG RS Non-Medis' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxStyles
</head>
<body class="bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 min-h-screen antialiased flex flex-col font-sans">
    {{-- Banner Deteksi Jaringan Offline (Basement / Putus Koneksi) --}}
    <div wire:offline class="bg-red-600 text-white text-center py-2 px-4 text-xs sm:text-sm font-semibold sticky top-0 z-50 shadow-md">
        Koneksi internet terputus. Mohon periksa jaringan Anda sebelum melanjutkan transaksi.
    </div>

    @php
        $portalKey = $portal ?? request()->segment(1) ?? 'super-admin';
        $user = auth()->user();

        $portalMeta = [
            'super-admin'    => ['label' => 'Super Administrator', 'color' => 'bg-blue-600', 'border' => 'border-blue-600', 'text' => 'text-blue-600', 'accent' => 'blue'],
            'admin-logistik' => ['label' => 'Admin Logistik Sentral', 'color' => 'bg-sky-600', 'border' => 'border-sky-600', 'text' => 'text-sky-600', 'accent' => 'sky'],
            'kepala-gudang'  => ['label' => 'Kepala Gudang', 'color' => 'bg-amber-600', 'border' => 'border-amber-600', 'text' => 'text-amber-600', 'accent' => 'amber'],
            'petugas-gudang' => ['label' => 'Petugas Gudang', 'color' => 'bg-emerald-600', 'border' => 'border-emerald-600', 'text' => 'text-emerald-600', 'accent' => 'emerald'],
            'pengadaan'      => ['label' => 'Bagian Pengadaan', 'color' => 'bg-indigo-600', 'border' => 'border-indigo-600', 'text' => 'text-indigo-600', 'accent' => 'indigo'],
            'kepala-unit'    => ['label' => 'Kepala Unit / Karu', 'color' => 'bg-cyan-600', 'border' => 'border-cyan-600', 'text' => 'text-cyan-600', 'accent' => 'cyan'],
            'petugas-unit'   => ['label' => 'Petugas Ruangan', 'color' => 'bg-teal-600', 'border' => 'border-teal-600', 'text' => 'text-teal-600', 'accent' => 'teal'],
            'teknisi'        => ['label' => 'Teknisi IPSRS', 'color' => 'bg-violet-600', 'border' => 'border-violet-600', 'text' => 'text-violet-600', 'accent' => 'violet'],
            'manajemen'      => ['label' => 'Manajemen Direksi', 'color' => 'bg-slate-700', 'border' => 'border-slate-700', 'text' => 'text-slate-700', 'accent' => 'slate'],
            'auditor'        => ['label' => 'Auditor Internal (SPI)', 'color' => 'bg-rose-600', 'border' => 'border-rose-600', 'text' => 'text-rose-600', 'accent' => 'rose'],
        ];

        $currentPortal = $portalMeta[$portalKey] ?? $portalMeta['super-admin'];
    @endphp

    {{-- Banner Mode Emulasi Context --}}
    <x-context-banner />

    {{-- Topbar / Header --}}
    <header class="h-14 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 sticky top-0 z-40 px-4 sm:px-6 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <span class="w-2.5 h-6 rounded-full {{ $currentPortal['color'] }}"></span>
            <div class="flex items-center gap-2">
                <span class="font-bold text-sm sm:text-base tracking-tight text-zinc-900 dark:text-zinc-100">SIMLOG RS</span>
                <span class="text-zinc-400 text-xs">/</span>
                <span class="text-xs sm:text-sm font-semibold {{ $currentPortal['text'] }}">{{ $currentPortal['label'] }}</span>
            </div>
        </div>

        <div class="flex items-center gap-4">
            {{-- Switcher Portal untuk Super Admin --}}
            @if ($user && $user->can('page_Dashboard'))
                <div x-data="{ open: false }" class="relative">
                    <button
                        @click="open = !open"
                        type="button"
                        class="text-xs bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 px-3 py-1.5 rounded-lg font-medium flex items-center gap-1.5 transition-colors"
                    >
                        <span>Ganti Portal</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        @click.outside="open = false"
                        x-transition
                        class="absolute right-0 mt-2 w-56 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg p-1.5 z-50 text-xs space-y-0.5"
                        style="display: none;"
                    >
                        <div class="px-2.5 py-1 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">10 Portal Peran</div>
                        @foreach ($portalMeta as $pKey => $pData)
                            <a
                                href="/{{ $pKey }}/dashboard"
                                class="flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 {{ $portalKey === $pKey ? 'font-bold bg-zinc-50 dark:bg-zinc-800/50' : 'text-zinc-700 dark:text-zinc-300' }}"
                            >
                                <span class="size-2 rounded-full {{ $pData['color'] }}"></span>
                                <span>{{ $pData['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Profil & Logout --}}
            <div class="flex items-center gap-3 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                <div class="text-right hidden sm:block">
                    <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $user?->nama ?? 'Pengguna' }}</div>
                    <div class="text-[11px] font-mono text-zinc-400">{{ $user?->nip ?? $user?->email }}</div>
                </div>

                <form method="POST" action="/logout" class="inline">
                    @csrf
                    <button
                        type="submit"
                        title="Keluar dari Sistem"
                        class="text-xs text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400 p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Body Wrapper: Sidebar + Konten Utama --}}
    <div class="flex-1 flex">
        {{-- Sidebar Navigasi --}}
        <aside class="w-64 bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-800 flex flex-col justify-between shrink-0 hidden md:flex">
            <div class="p-4 space-y-6 overflow-y-auto">
                {{-- Modul Navigasi Portal --}}
                <div class="space-y-1">
                    <div class="px-3 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider mb-2">Navigasi Portal</div>

                    <a
                        href="/{{ $portalKey }}/dashboard"
                        class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg transition-colors {{ request()->is('*/dashboard') ? $currentPortal['color'] . ' text-white' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    @can('view_any_user::role')
                        <a
                            href="/super-admin/roles"
                            class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg transition-colors {{ request()->is('*/roles') ? $currentPortal['color'] . ' text-white' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                            <span>Peran & Hak Akses</span>
                        </a>
                    @endcan

                    @can('view_any_master::barang')
                        <a
                            href="/{{ $portalKey }}/barang"
                            class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg transition-colors {{ request()->is('*/barang') ? $currentPortal['color'] . ' text-white' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                            <span>Katalog Barang</span>
                        </a>
                    @endcan
                </div>
            </div>

            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 text-[11px] text-zinc-400 space-y-1">
                <div>SIMLOG RS Non-Medis v2</div>
                <div class="font-mono text-[10px]">Laravel 13 • Livewire 3 • Flux UI</div>
            </div>
        </aside>

        {{-- Konten Utama Halaman --}}
        <main class="flex-1 overflow-x-hidden">
            <div class="max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- Flux Toast Notification Container --}}
    <flux:toast />

    @livewireScripts
    @fluxScripts
</body>
</html>
