<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'SIMLOG RS Non-Medis v2' }}</title>
    
    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 antialiased font-sans">
    {{-- Banner Jaringan Offline (Basement / Putus Koneksi) --}}
    <div wire:offline class="bg-red-600 text-white text-center py-2 px-4 text-xs font-semibold sticky top-0 z-50 shadow-md">
        Koneksi internet terputus. Mohon periksa jaringan Anda sebelum melanjutkan transaksi.
    </div>

    @php
        $portalKey = $portal ?? request()->segment(1) ?? 'super-admin';
        $user = auth()->user();

        $portalMeta = [
            'super-admin'    => ['label' => 'Super Administrator', 'badge' => 'blue'],
            'admin-logistik' => ['label' => 'Admin Logistik', 'badge' => 'sky'],
            'kepala-gudang'  => ['label' => 'Kepala Gudang', 'badge' => 'amber'],
            'petugas-gudang' => ['label' => 'Petugas Gudang', 'badge' => 'emerald'],
            'pengadaan'      => ['label' => 'Pengadaan', 'badge' => 'indigo'],
            'kepala-unit'    => ['label' => 'Kepala Unit', 'badge' => 'cyan'],
            'petugas-unit'   => ['label' => 'Petugas Unit', 'badge' => 'teal'],
            'teknisi'        => ['label' => 'Teknisi IPSRS', 'badge' => 'violet'],
            'manajemen'      => ['label' => 'Manajemen Direksi', 'badge' => 'slate'],
            'auditor'        => ['label' => 'Auditor Internal', 'badge' => 'rose'],
        ];

        $currentPortal = $portalMeta[$portalKey] ?? $portalMeta['super-admin'];
    @endphp

    {{-- Official Flux Sidebar Layout --}}
    <flux:sidebar sticky collapsible="mobile" class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="/{{ $portalKey }}/dashboard"
                name="SIMLOG RS"
            >
                <div class="size-6 rounded bg-blue-600 text-white flex items-center justify-center font-bold text-xs shadow-xs">
                    RS
                </div>
            </flux:sidebar.brand>

            <flux:badge size="sm" :color="$currentPortal['badge']" class="ml-auto in-data-flux-sidebar-collapsed-desktop:hidden font-semibold text-[10px] uppercase">
                {{ $currentPortal['label'] }}
            </flux:badge>

            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.search placeholder="Cari modul..." />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="/{{ $portalKey }}/dashboard" :current="request()->is('*/dashboard')">
                Dashboard
            </flux:sidebar.item>

            @can('view_any_user::role')
                <flux:sidebar.item icon="shield-check" href="/super-admin/roles" :current="request()->is('*/roles')">
                    Peran & Hak Akses
                </flux:sidebar.item>
            @endcan

            @can('view_any_master::barang')
                <flux:sidebar.group expandable heading="Master & Logistik" class="grid">
                    <flux:sidebar.item icon="cube" href="/{{ $portalKey }}/barang" :current="request()->is('*/barang')">
                        Katalog Barang
                    </flux:sidebar.item>
                    @can('view_any_master::aset')
                        <flux:sidebar.item icon="archive-box" href="/{{ $portalKey }}/aset" :current="request()->is('*/aset')">
                            Register Aset
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::unit')
                        <flux:sidebar.item icon="building-office" href="/{{ $portalKey }}/unit" :current="request()->is('*/unit')">
                            Unit & Ruangan
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::gudang')
                        <flux:sidebar.item icon="building-storefront" href="/{{ $portalKey }}/gudang" :current="request()->is('*/gudang')">
                            Gudang Logistik
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::lokasi::gudang')
                        <flux:sidebar.item icon="table-cells" href="/{{ $portalKey }}/lokasi-gudang" :current="request()->is('*/lokasi-gudang')">
                            Lokasi Rak / Bin
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::kategori::barang')
                        <flux:sidebar.item icon="tag" href="/{{ $portalKey }}/kategori" :current="request()->is('*/kategori')">
                            Kategori Barang
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::satuan::barang')
                        <flux:sidebar.item icon="scale" href="/{{ $portalKey }}/satuan" :current="request()->is('*/satuan')">
                            Satuan Kemasan
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::merek::barang')
                        <flux:sidebar.item icon="bookmark" href="/{{ $portalKey }}/merek" :current="request()->is('*/merek')">
                            Merek Barang
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::pemasok')
                        <flux:sidebar.item icon="truck" href="/{{ $portalKey }}/pemasok" :current="request()->is('*/pemasok')">
                            Pemasok / Vendor
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcan

            {{-- Switcher Portal untuk Super Admin --}}
            @if ($user && $user->can('page_Dashboard'))
                <flux:sidebar.group expandable heading="Ganti Ruang Kerja (10 Peran)" class="grid">
                    @foreach ($portalMeta as $pKey => $pData)
                        <flux:sidebar.item href="/{{ $pKey }}/dashboard" :current="$portalKey === $pKey">
                            <flux:badge size="sm" :color="$pData['badge']" class="mr-1.5"></flux:badge>
                            <span>{{ $pData['label'] }}</span>
                        </flux:sidebar.item>
                    @endforeach
                </flux:sidebar.group>
            @endif
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        {{-- Profil Pengguna di Bagian Bawah Sidebar --}}
        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:sidebar.profile :name="$user?->nama ?? 'Pengguna'" :initials="substr($user?->nama ?? 'P', 0, 2)" />

            <flux:menu>
                <div class="px-3 py-2 text-xs border-b border-zinc-100 dark:border-zinc-800">
                    <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $user?->nama }}</div>
                    <div class="font-mono text-zinc-400 text-[11px]">{{ $user?->email }}</div>
                    <div class="text-[10px] text-zinc-500 mt-1 uppercase font-semibold">Peran: {{ $currentPortal['label'] }}</div>
                </div>

                <form method="POST" action="/logout">
                    @csrf
                    <flux:menu.item icon="arrow-right-start-on-rectangle" type="submit" class="text-rose-600 dark:text-rose-400">
                        Keluar dari Sistem
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    {{-- Mobile Header --}}
    <flux:header class="lg:hidden bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <div class="flex items-center gap-2">
            <span class="font-bold text-sm tracking-tight">SIMLOG RS</span>
            <flux:badge size="sm" :color="$currentPortal['badge']">{{ $currentPortal['label'] }}</flux:badge>
        </div>

        <flux:spacer />

        <flux:dropdown position="top" align="start">
            <flux:profile :name="$user?->nama ?? 'Pengguna'" :initials="substr($user?->nama ?? 'P', 0, 2)" />

            <flux:menu>
                <div class="px-3 py-2 text-xs border-b border-zinc-100 dark:border-zinc-800">
                    <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $user?->nama }}</div>
                    <div class="font-mono text-zinc-400 text-[11px]">{{ $user?->email }}</div>
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

    {{-- Konten Utama --}}
    <flux:main class="p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <x-context-banner />
        {{ $slot }}
    </flux:main>

    {{-- Flux Toast --}}
    <flux:toast />

    @livewireScripts
    @fluxScripts
</body>
</html>
