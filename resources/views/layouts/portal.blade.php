<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'SIMLOG RS Non-Medis v2' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxStyles
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 antialiased font-sans flex flex-col">
    {{-- Banner Jaringan Offline (Basement) --}}
    <div wire:offline class="bg-red-600 text-white text-center py-2 px-4 text-xs font-semibold sticky top-0 z-50 shadow-md">
        Koneksi internet terputus. Mohon periksa jaringan Anda sebelum melanjutkan transaksi.
    </div>

    @php
        $portalKey = $portal ?? request()->segment(1) ?? 'super-admin';
        $user = auth()->user();

        $portalMeta = [
            'super-admin'    => ['label' => 'Super Administrator', 'badge' => 'blue', 'accent' => 'blue'],
            'admin-logistik' => ['label' => 'Admin Logistik Sentral', 'badge' => 'sky', 'accent' => 'sky'],
            'kepala-gudang'  => ['label' => 'Kepala Gudang', 'badge' => 'amber', 'accent' => 'amber'],
            'petugas-gudang' => ['label' => 'Petugas Gudang', 'badge' => 'emerald', 'accent' => 'emerald'],
            'pengadaan'      => ['label' => 'Bagian Pengadaan', 'badge' => 'indigo', 'accent' => 'indigo'],
            'kepala-unit'    => ['label' => 'Kepala Unit / Karu', 'badge' => 'cyan', 'accent' => 'cyan'],
            'petugas-unit'   => ['label' => 'Petugas Ruangan', 'badge' => 'teal', 'accent' => 'teal'],
            'teknisi'        => ['label' => 'Teknisi IPSRS', 'badge' => 'violet', 'accent' => 'violet'],
            'manajemen'      => ['label' => 'Manajemen Direksi', 'badge' => 'slate', 'accent' => 'slate'],
            'auditor'        => ['label' => 'Auditor Internal (SPI)', 'badge' => 'rose', 'accent' => 'rose'],
        ];

        $currentPortal = $portalMeta[$portalKey] ?? $portalMeta['super-admin'];
    @endphp

    {{-- Banner Mode Emulasi Context Super Admin --}}
    <x-context-banner />

    {{-- Flux Header --}}
    <flux:header container class="bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:brand href="/{{ $portalKey }}/dashboard" name="SIMLOG RS" class="font-bold tracking-tight">
            <span class="text-xs px-2 py-0.5 rounded-full font-semibold uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                {{ $currentPortal['label'] }}
            </span>
        </flux:brand>

        <flux:spacer />

        {{-- Switcher Portal untuk Super Admin --}}
        @if ($user && $user->can('page_Dashboard'))
            <flux:dropdown class="mr-2">
                <flux:button variant="subtle" size="sm" icon:trailing="chevron-down">
                    Ganti Portal
                </flux:button>

                <flux:menu>
                    @foreach ($portalMeta as $pKey => $pData)
                        <flux:menu.item href="/{{ $pKey }}/dashboard" :current="$portalKey === $pKey">
                            <flux:badge size="sm" :color="$pData['badge']" class="mr-2"></flux:badge>
                            <span>{{ $pData['label'] }}</span>
                        </flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        @endif

        {{-- Profil Pengguna & Logout --}}
        <flux:dropdown position="top" align="start">
            <flux:button variant="ghost" class="flex items-center gap-2">
                <span class="text-xs font-semibold">{{ $user?->nama ?? 'Pengguna' }}</span>
                <flux:icon icon="chevron-down" size="xs" />
            </flux:button>

            <flux:menu>
                <div class="px-3 py-2 text-xs border-b border-zinc-100 dark:border-zinc-800">
                    <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $user?->nama }}</div>
                    <div class="font-mono text-zinc-400">{{ $user?->email }}</div>
                </div>

                <form method="POST" action="/logout">
                    @csrf
                    <flux:menu.item icon="arrow-right-start-on-rectangle" type="submit" class="text-rose-600 dark:text-rose-400">
                        Keluar dari Sistem
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{-- Layout Wrapper: Sidebar + Konten Utama --}}
    <div class="flex-1 flex">
        {{-- Sidebar Flux --}}
        <flux:sidebar sticky collapsible="mobile" class="bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-800 w-64 shrink-0">
            <div class="p-3">
                <flux:navlist>
                    <flux:navlist.item icon="home" href="/{{ $portalKey }}/dashboard" :current="request()->is('*/dashboard')">
                        Dashboard
                    </flux:navlist.item>

                    @can('view_any_user::role')
                        <flux:navlist.item icon="shield-check" href="/super-admin/roles" :current="request()->is('*/roles')">
                            Peran & Hak Akses
                        </flux:navlist.item>
                    @endcan

                    @can('view_any_master::barang')
                        <flux:navlist.item icon="cube" href="/{{ $portalKey }}/barang" :current="request()->is('*/barang')">
                            Katalog Barang
                        </flux:navlist.item>
                    @endcan
                </flux:navlist>
            </div>

            <flux:sidebar.spacer />

            <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 text-[11px] text-zinc-400">
                <div class="font-semibold text-zinc-500">SIMLOG RS Non-Medis v2</div>
                <div class="font-mono text-[10px]">Livewire 3 • Flux UI Free</div>
            </div>
        </flux:sidebar>

        {{-- Main Content --}}
        <flux:main container class="flex-1 max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </flux:main>
    </div>

    {{-- Flux Toast --}}
    <flux:toast />

    @livewireScripts
    @fluxScripts
</body>
</html>
