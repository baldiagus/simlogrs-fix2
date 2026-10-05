<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'SIMLOG RS Non-Medis v2' }}</title>
    
    <!-- Google Fonts: Inter & JetBrains Mono (Non-blocking) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    </noscript>

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
        $portalKey = \App\Support\Portal\PortalResolver::resolve($portal ?? null);
        $user = auth()->user();
        $user?->loadMissing(['unit', 'gudang']);
        $userUnitLabel = match (true) {
            filled($user?->unit?->nama) => 'Unit: ' . $user->unit->nama,
            filled($user?->gudang?->nama) => 'Fasilitas: ' . $user->gudang->nama,
            default => 'Unit: Lintas Unit (Pusat)',
        };

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

        $userRole = $user?->roles?->where('name', '!=', 'panel_user')->first();
        $userRoleLabel = match($userRole?->name) {
            'kepala_teknisi' => 'Kepala / Koordinator Teknisi IPSRS',
            'teknisi'        => 'Teknisi Sarana & IPSRS',
            'super_admin'    => 'Super Administrator',
            'admin_logistik' => 'Admin Logistik',
            'kepala_gudang'  => 'Kepala Gudang',
            'petugas_gudang' => 'Petugas Gudang',
            'pengadaan'      => 'Bagian Pengadaan',
            'kepala_unit'    => 'Kepala Ruangan / Unit',
            'petugas_unit'   => 'Petugas / Staf Ruangan',
            'manajemen'      => 'Manajemen Direksi',
            'auditor'        => 'Auditor Internal (SPI)',
            default          => $currentPortal['label'],
        };
        // Evaluasi grup sidebar aktif (tertutup default, terbuka otomatis di rute aktif)
        $isMasterActive = request()->is(
            '*/barang', '*/barang/*',
            '*/aset', '*/aset/*',
            '*/unit', '*/unit/*',
            '*/gudang', '*/gudang/*',
            '*/lokasi-gudang', '*/lokasi-gudang/*',
            '*/kategori', '*/kategori/*',
            '*/satuan', '*/satuan/*',
            '*/merek', '*/merek/*',
            '*/pemasok', '*/pemasok/*'
        );

        $isPengadaanActive = request()->is(
            '*/permintaan-pengadaan*',
            '*/pesanan-pembelian*',
            '*/penerimaan-po*',
            '*/realisasi-anggaran*',
            '*/master-rekanan*'
        );

        $isDistribusiActive = request()->is(
            '*/permintaan-barang*',
            '*/approval-permintaan*',
            '*/distribusi-approval*',
            '*/picking-distribusi*',
            '*/penerimaan-barang*',
            '*/stok-ruangan*'
        );

        $isSirkulasiActive = request()->is(
            '*/peminjaman-aset*',
            '*/approval-peminjaman*',
            '*/pengembalian-aset*'
        );

        $isIpsrsActive = request()->is(
            '*/lapor-servis*',
            '*/spk*',
            '*/lembar-kerja*',
            '*/rekam-medis-aset*',
            '*/jadwal-preventif*'
        );

        $isPersediaanActive = request()->is(
            '*/monitoring-fefo*',
            '*/buku-mutasi*',
            '*/stok-opname*',
            '*/otorisasi-penyesuaian*'
        );

        $isAuditActive = request()->is(
            '*/audit-trail*',
            '*/kepatuhan-pengadaan*',
            '*/selisih-opname*',
            '*/temuan-capa*'
        );
    @endphp

    {{-- Official Flux Sidebar (Full Height di Kolom Kiri) --}}
    <flux:sidebar sticky collapsible class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="/{{ $portalKey }}/dashboard"
                wire:navigate
                name="SIMLOG RS"
                class="font-extrabold tracking-tight"
            >
                <div class="size-6 rounded bg-blue-600 text-white flex items-center justify-center font-bold text-xs shadow-xs">
                    RS
                </div>
            </flux:sidebar.brand>

            <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.search placeholder="Cari modul..." />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="/{{ $portalKey }}/dashboard" wire:navigate :current="request()->is('*/dashboard')">
                Dashboard
            </flux:sidebar.item>

            {{-- Modul Manajemen Sistem & RBAC (Eksklusif Portal Super Administrator) --}}
            @if ($portalKey === 'super-admin')
                @can('view_any_user::pengguna')
                    <flux:sidebar.item icon="users" href="/super-admin/pengguna" wire:navigate :current="request()->is('*/pengguna')">
                        Akun Pengguna
                    </flux:sidebar.item>
                @endcan

                @can('view_any_user::role')
                    <flux:sidebar.item icon="shield-check" href="/super-admin/roles" wire:navigate :current="request()->is('*/roles')">
                        Peran & Hak Akses
                    </flux:sidebar.item>
                @endcan

                @can('page_SchedulerNotifikasiIndex')
                    <flux:sidebar.item icon="clock" href="/super-admin/scheduler" wire:navigate :current="request()->is('*/scheduler')">
                        Task Scheduler
                    </flux:sidebar.item>
                @endcan
            @endif

            @can('view_any_master::barang')
                <flux:sidebar.group expandable :expanded="$isMasterActive" heading="Master & Logistik" class="grid">
                    <flux:sidebar.item icon="cube" href="/{{ $portalKey }}/barang" wire:navigate :current="request()->is('*/barang')">
                        Katalog Barang
                    </flux:sidebar.item>
                    @can('view_any_master::aset')
                        <flux:sidebar.item icon="archive-box" href="/{{ $portalKey }}/aset" wire:navigate :current="request()->is('*/aset')">
                            Register Aset
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::unit')
                        <flux:sidebar.item icon="building-office" href="/{{ $portalKey }}/unit" wire:navigate :current="request()->is('*/unit')">
                            Unit & Ruangan
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::gudang')
                        <flux:sidebar.item icon="building-storefront" href="/{{ $portalKey }}/gudang" wire:navigate :current="request()->is('*/gudang')">
                            Gudang Logistik
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::lokasi::gudang')
                        <flux:sidebar.item icon="table-cells" href="/{{ $portalKey }}/lokasi-gudang" wire:navigate :current="request()->is('*/lokasi-gudang')">
                            Lokasi Rak / Bin
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::kategori::barang')
                        <flux:sidebar.item icon="tag" href="/{{ $portalKey }}/kategori" wire:navigate :current="request()->is('*/kategori')">
                            Kategori Barang
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::satuan::barang')
                        <flux:sidebar.item icon="scale" href="/{{ $portalKey }}/satuan" wire:navigate :current="request()->is('*/satuan')">
                            Satuan Kemasan
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::merek::barang')
                        <flux:sidebar.item icon="bookmark" href="/{{ $portalKey }}/merek" wire:navigate :current="request()->is('*/merek')">
                            Merek Barang
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_master::pemasok')
                        <flux:sidebar.item icon="truck" href="/{{ $portalKey }}/pemasok" wire:navigate :current="request()->is('*/pemasok')">
                            Pemasok / Vendor
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcan

            @if ($user->can('view_any_pengadaan::permintaan::pengadaan') || $user->can('view_any_pengadaan::pesanan::pembelian') || $user->can('view_any_gudang_stok::penerimaan::po') || $user->can('view_any_pengadaan::realisasi::anggaran') || $user->can('view_any_pengadaan::master::rekanan'))
                <flux:sidebar.group expandable :expanded="$isPengadaanActive" heading="Pengadaan" class="grid">
                    @if ($user->can('view_any_pengadaan::permintaan::pengadaan') || $user->can('view_any_pengadaan::pesanan::pembelian') || $user->can('view_any_gudang_stok::penerimaan::po'))
                        @php
                            $pengadaanHref = match(true) {
                                $user->can('view_any_pengadaan::permintaan::pengadaan') => "/{$portalKey}/permintaan-pengadaan",
                                $user->can('view_any_pengadaan::pesanan::pembelian') => "/{$portalKey}/pesanan-pembelian",
                                default => "/{$portalKey}/penerimaan-po",
                            };
                            $isPengadaanCurrent = request()->is('*/permintaan-pengadaan*') || request()->is('*/pesanan-pembelian*') || request()->is('*/penerimaan-po*');
                        @endphp
                        <flux:sidebar.item icon="document-text" :href="$pengadaanHref" wire:navigate :current="$isPengadaanCurrent">
                            Transaksi Pengadaan
                        </flux:sidebar.item>
                    @endif

                    @if ($user->can('view_any_pengadaan::realisasi::anggaran') || $user->can('view_any_pengadaan::master::rekanan'))
                        @php
                            $evaluasiHref = $user->can('view_any_pengadaan::realisasi::anggaran')
                                ? "/{$portalKey}/realisasi-anggaran"
                                : "/{$portalKey}/master-rekanan";
                            $isEvaluasiCurrent = request()->is('*/realisasi-anggaran*') || request()->is('*/master-rekanan*');
                        @endphp
                        <flux:sidebar.item icon="banknotes" :href="$evaluasiHref" wire:navigate :current="$isEvaluasiCurrent">
                            Pagu & Rekanan
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif

            @if ($user->can('view_any_layanan_unit::permintaan::barang') || $user->can('view_any_layanan_unit::approval::karu') || $user->can('view_any_gudang_stok::distribusi::approval') || $user->can('view_any_gudang_stok::picking::distribusi') || $user->can('view_any_layanan_unit::penerimaan::barang') || $user->can('view_any_layanan_unit::stok::ruangan'))
                <flux:sidebar.group expandable :expanded="$isDistribusiActive" heading="Distribusi & Amprah" class="grid">
                    {{-- 1. Hub Amprah Ruangan (Permintaan & Otorisasi) --}}
                    @if ($user->can('view_any_layanan_unit::permintaan::barang') || $user->can('view_any_layanan_unit::approval::karu') || $user->can('view_any_gudang_stok::distribusi::approval'))
                        @php
                            $amprahHref = match(true) {
                                $user->can('view_any_layanan_unit::permintaan::barang') => "/{$portalKey}/permintaan-barang",
                                $user->can('view_any_layanan_unit::approval::karu') => "/{$portalKey}/approval-permintaan",
                                default => "/{$portalKey}/distribusi-approval",
                            };
                            $isAmprahCurrent = request()->is('*/permintaan-barang*') || request()->is('*/approval-permintaan*') || request()->is('*/distribusi-approval*');
                        @endphp
                        <flux:sidebar.item icon="arrow-up-tray" :href="$amprahHref" wire:navigate :current="$isAmprahCurrent">
                            Amprah Ruangan
                        </flux:sidebar.item>
                    @endif

                    {{-- 2. Hub Penyaluran SPPB (Picking FEFO & Terima di Ruangan) --}}
                    @if ($user->can('view_any_gudang_stok::picking::distribusi') || $user->can('view_any_layanan_unit::penerimaan::barang'))
                        @php
                            $sppbHref = $user->can('view_any_gudang_stok::picking::distribusi')
                                ? "/{$portalKey}/picking-distribusi"
                                : "/{$portalKey}/penerimaan-barang";
                            $isSppbCurrent = request()->is('*/picking-distribusi*') || request()->is('*/penerimaan-barang*');
                        @endphp
                        <flux:sidebar.item icon="truck" :href="$sppbHref" wire:navigate :current="$isSppbCurrent">
                            Penyaluran SPPB
                        </flux:sidebar.item>
                    @endif

                    {{-- 3. Persediaan Ruangan (Stok Lemari & Aset Lokal) --}}
                    @can('view_any_layanan_unit::stok::ruangan')
                        <flux:sidebar.item icon="cube" href="/{{ $portalKey }}/stok-ruangan" wire:navigate :current="request()->is('*/stok-ruangan*')">
                            Persediaan Ruangan
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

            @if ($user->can('view_any_layanan_unit::peminjaman::aset') || $user->can('view_any_layanan_unit::approval::peminjaman') || $user->can('view_any_layanan_unit::pengembalian::aset'))
                <flux:sidebar.group expandable :expanded="$isSirkulasiActive" heading="Sirkulasi Aset" class="grid">
                    @if ($user->can('view_any_layanan_unit::peminjaman::aset') || $user->can('view_any_layanan_unit::approval::peminjaman'))
                        @php
                            $pinjamHref = match(true) {
                                $user->can('view_any_layanan_unit::peminjaman::aset') => "/{$portalKey}/peminjaman-aset",
                                default => "/{$portalKey}/approval-peminjaman",
                            };
                            $isPinjamCurrent = request()->is('*/peminjaman-aset*') || request()->is('*/approval-peminjaman*');
                        @endphp
                        <flux:sidebar.item icon="arrow-path" :href="$pinjamHref" wire:navigate :current="$isPinjamCurrent">
                            Peminjaman & Otorisasi
                        </flux:sidebar.item>
                    @endif

                    @can('view_any_layanan_unit::pengembalian::aset')
                        <flux:sidebar.item icon="arrow-down-tray" href="/{{ $portalKey }}/pengembalian-aset" wire:navigate :current="request()->is('*/pengembalian-aset*')">
                            Pengembalian & Inspeksi
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

            @if ($user->can('view_any_layanan_unit::lapor::servis') || $user->can('view_any_pemeliharaan::spk::perbaikan') || $user->can('view_any_pemeliharaan::lembar::kerja') || $user->can('view_any_pemeliharaan::rekam::medis::aset') || $user->can('view_any_pemeliharaan::pemeliharaan::berkala'))
                <flux:sidebar.group expandable :expanded="$isIpsrsActive" heading="Pemeliharaan & IPSRS" class="grid">
                    @if ($user->can('view_any_layanan_unit::lapor::servis') || $user->can('view_any_pemeliharaan::spk::perbaikan') || $user->can('view_any_pemeliharaan::lembar::kerja'))
                        @php
                            $servisHref = match(true) {
                                $user->can('view_any_layanan_unit::lapor::servis') => "/{$portalKey}/lapor-servis",
                                $user->can('view_any_pemeliharaan::spk::perbaikan') => "/{$portalKey}/spk",
                                default => "/{$portalKey}/lembar-kerja",
                            };
                            $isServisCurrent = request()->is('*/lapor-servis*') || request()->is('*/spk*') || request()->is('*/lembar-kerja*');
                        @endphp
                        <flux:sidebar.item icon="wrench-screwdriver" :href="$servisHref" wire:navigate :current="$isServisCurrent">
                            Servis & Perbaikan
                        </flux:sidebar.item>
                    @endif

                    @if ($user->can('view_any_pemeliharaan::pemeliharaan::berkala') || $user->can('view_any_pemeliharaan::rekam::medis::aset'))
                        @php
                            $preventifHref = $user->can('view_any_pemeliharaan::pemeliharaan::berkala')
                                ? "/{$portalKey}/jadwal-preventif"
                                : "/{$portalKey}/rekam-medis-aset";
                            $isPreventifCurrent = request()->is('*/jadwal-preventif*') || request()->is('*/rekam-medis-aset*');
                        @endphp
                        <flux:sidebar.item icon="calendar-days" :href="$preventifHref" wire:navigate :current="$isPreventifCurrent">
                            Preventif & Rekam Medis
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif

            @if ($user->can('view_any_gudang_stok::monitoring::fefo') || $user->can('view_any_gudang_stok::buku::mutasi') || $user->can('view_any_gudang_stok::stok::opname') || $user->can('view_any_gudang_stok::otorisasi::penyesuaian'))
                <flux:sidebar.group expandable :expanded="$isPersediaanActive" heading="Persediaan & Opname" class="grid">
                    @if ($user->can('view_any_gudang_stok::monitoring::fefo') || $user->can('view_any_gudang_stok::buku::mutasi'))
                        @php
                            $monitoringHref = $user->can('view_any_gudang_stok::monitoring::fefo')
                                ? "/{$portalKey}/monitoring-fefo"
                                : "/{$portalKey}/buku-mutasi";
                            $isMonitoringCurrent = request()->is('*/monitoring-fefo*') || request()->is('*/buku-mutasi*');
                        @endphp
                        <flux:sidebar.item icon="clock" :href="$monitoringHref" wire:navigate :current="$isMonitoringCurrent">
                            Monitoring & Kartu Stok
                        </flux:sidebar.item>
                    @endif

                    @if ($user->can('view_any_gudang_stok::stok::opname') || $user->can('view_any_gudang_stok::otorisasi::penyesuaian'))
                        @php
                            $opnameHref = $user->can('view_any_gudang_stok::stok::opname')
                                ? "/{$portalKey}/stok-opname"
                                : "/{$portalKey}/otorisasi-penyesuaian";
                            $isOpnameCurrent = request()->is('*/stok-opname*') || request()->is('*/otorisasi-penyesuaian*');
                        @endphp
                        <flux:sidebar.item icon="clipboard-document-check" :href="$opnameHref" wire:navigate :current="$isOpnameCurrent">
                            Stock Opname & Otorisasi
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif

            @if ($user->can('view_any_audit_manajemen::audit::trail') || $user->can('view_any_audit_manajemen::kepatuhan::pengadaan') || $user->can('view_any_audit_manajemen::selisih::opname') || $user->can('view_any_audit_manajemen::temuan::capa'))
                <flux:sidebar.group expandable :expanded="$isAuditActive" heading="Audit Internal (SPI)" class="grid">
                    @if ($user->can('view_any_audit_manajemen::audit::trail') || $user->can('view_any_audit_manajemen::kepatuhan::pengadaan') || $user->can('view_any_audit_manajemen::selisih::opname'))
                        @php
                            $auditHref = match(true) {
                                $user->can('view_any_audit_manajemen::audit::trail') => "/{$portalKey}/audit-trail",
                                $user->can('view_any_audit_manajemen::kepatuhan::pengadaan') => "/{$portalKey}/kepatuhan-pengadaan",
                                default => "/{$portalKey}/selisih-opname",
                            };
                            $isAuditCurrent = request()->is('*/audit-trail*') || request()->is('*/kepatuhan-pengadaan*') || request()->is('*/selisih-opname*');
                        @endphp
                        <flux:sidebar.item icon="shield-check" :href="$auditHref" wire:navigate :current="$isAuditCurrent">
                            Pengawasan Kepatuhan
                        </flux:sidebar.item>
                    @endif

                    @can('view_any_audit_manajemen::temuan::capa')
                        <flux:sidebar.item icon="clipboard-document-list" href="/{{ $portalKey }}/temuan-capa" wire:navigate :current="request()->is('*/temuan-capa*')">
                            Temuan & CAPA
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

            @if ($user->can('view_any_layanan_unit::lapor::servis') || $user->can('view_any_pemeliharaan::spk::perbaikan') || $user->can('view_any_pemeliharaan::lembar::kerja') || $user->can('view_any_pemeliharaan::rekam::medis::aset') || $user->can('view_any_pemeliharaan::pemeliharaan::berkala'))
                <flux:sidebar.group expandable :expanded="$isIpsrsActive" heading="Pemeliharaan & IPSRS" class="grid">
                    @can('view_any_layanan_unit::lapor::servis')
                        <flux:sidebar.item icon="wrench" href="/{{ $portalKey }}/lapor-servis" wire:navigate :current="request()->is('*/lapor-servis')">
                            Lapor Kerusakan
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_pemeliharaan::spk::perbaikan')
                        <flux:sidebar.item icon="clipboard-document-check" href="/{{ $portalKey }}/spk" wire:navigate :current="request()->is('*/spk')">
                            Perintah Kerja (SPK)
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_pemeliharaan::lembar::kerja')
                        <flux:sidebar.item icon="wrench-screwdriver" href="/{{ $portalKey }}/lembar-kerja" wire:navigate :current="request()->is('*/lembar-kerja')">
                            Lembar Kerja Servis
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_pemeliharaan::rekam::medis::aset')
                        <flux:sidebar.item icon="book-open" href="/{{ $portalKey }}/rekam-medis-aset" wire:navigate :current="request()->is('*/rekam-medis-aset')">
                            Rekam Medis Aset
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_pemeliharaan::pemeliharaan::berkala')
                        <flux:sidebar.item icon="calendar-days" href="/{{ $portalKey }}/jadwal-preventif" wire:navigate :current="request()->is('*/jadwal-preventif')">
                            Jadwal Preventif
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

            @if ($user->can('view_any_gudang_stok::monitoring::fefo') || $user->can('view_any_gudang_stok::buku::mutasi') || $user->can('view_any_gudang_stok::stok::opname') || $user->can('view_any_gudang_stok::otorisasi::penyesuaian'))
                <flux:sidebar.group expandable :expanded="$isPersediaanActive" heading="Persediaan & Opname" class="grid">
                    @can('view_any_gudang_stok::monitoring::fefo')
                        <flux:sidebar.item icon="clock" href="/{{ $portalKey }}/monitoring-fefo" wire:navigate :current="request()->is('*/monitoring-fefo')">
                            Monitoring FEFO
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_gudang_stok::buku::mutasi')
                        <flux:sidebar.item icon="document-magnifying-glass" href="/{{ $portalKey }}/buku-mutasi" wire:navigate :current="request()->is('*/buku-mutasi')">
                            Kartu Mutasi Stok
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_gudang_stok::stok::opname')
                        <flux:sidebar.item icon="clipboard-document-check" href="/{{ $portalKey }}/stok-opname" wire:navigate :current="request()->is('*/stok-opname')">
                            Stock Opname
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_gudang_stok::otorisasi::penyesuaian')
                        <flux:sidebar.item icon="check-badge" href="/{{ $portalKey }}/otorisasi-penyesuaian" wire:navigate :current="request()->is('*/otorisasi-penyesuaian')">
                            Otorisasi Penyesuaian
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

            @if ($user->can('view_any_audit_manajemen::audit::trail') || $user->can('view_any_audit_manajemen::kepatuhan::pengadaan') || $user->can('view_any_audit_manajemen::selisih::opname') || $user->can('view_any_audit_manajemen::temuan::capa'))
                <flux:sidebar.group expandable :expanded="$isAuditActive" heading="Audit Internal (SPI)" class="grid">
                    @can('view_any_audit_manajemen::audit::trail')
                        <flux:sidebar.item icon="document-magnifying-glass" href="/{{ $portalKey }}/audit-trail" wire:navigate :current="request()->is('*/audit-trail*')">
                            Audit Trail (Forensik)
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_audit_manajemen::kepatuhan::pengadaan')
                        <flux:sidebar.item icon="shield-exclamation" href="/{{ $portalKey }}/kepatuhan-pengadaan" wire:navigate :current="request()->is('*/kepatuhan-pengadaan*')">
                            Kepatuhan Pengadaan
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_audit_manajemen::selisih::opname')
                        <flux:sidebar.item icon="scale" href="/{{ $portalKey }}/selisih-opname" wire:navigate :current="request()->is('*/selisih-opname*')">
                            Selisih Opname (Audit)
                        </flux:sidebar.item>
                    @endcan
                    @can('view_any_audit_manajemen::temuan::capa')
                        <flux:sidebar.item icon="clipboard-document-list" href="/{{ $portalKey }}/temuan-capa" wire:navigate :current="request()->is('*/temuan-capa*')">
                            Temuan Audit & CAPA
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <div class="p-4 border-t border-zinc-200 dark:border-zinc-700 text-[11px] text-zinc-400 space-y-1">
            <div class="font-semibold text-zinc-600 dark:text-zinc-400">SIMLOG RS Non-Medis v2</div>
            <div class="font-mono text-[10px] text-zinc-400">Livewire 3 • Flux UI Free</div>
        </div>
    </flux:sidebar>

    {{-- Header di dalam Konten (Sebelah Kanan Sidebar) --}}
    <flux:header class="bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700 px-6 lg:px-8 flex items-center">
        <flux:sidebar.toggle icon="bars-2" inset="left" class="mr-3" />

        <div class="flex items-center gap-2">
            <span class="text-xs text-zinc-400 font-medium hidden sm:inline">Ruang Kerja:</span>
            <flux:badge size="sm" :color="($user?->hasRole('kepala_teknisi') && $portalKey === 'teknisi') ? 'violet' : $currentPortal['badge']" class="font-semibold uppercase tracking-wider text-[10px]">
                {{ ($user?->hasRole('kepala_teknisi') && $portalKey === 'teknisi') ? 'Kepala Teknisi' : $currentPortal['label'] }}
            </flux:badge>
        </div>

        <flux:spacer />


        {{-- Dropdown Profile Pengguna di ujung kanan --}}
        <flux:dropdown position="top" align="start">
            <flux:profile :name="$user?->nama ?? 'Pengguna'" :initials="substr($user?->nama ?? 'P', 0, 2)" />

            <flux:menu>
                <div class="px-3 py-2 text-xs border-b border-zinc-100 dark:border-zinc-800">
                    <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $user?->nama }}</div>
                    <div class="font-mono text-[11px] text-zinc-400">{{ $user?->email }}</div>
                    <div class="text-[10px] text-zinc-500 mt-1 uppercase font-semibold">Peran: {{ $userRoleLabel }}</div>
                    <div class="text-[11px] text-zinc-600 dark:text-zinc-300 mt-1 flex items-center gap-1.5 truncate">
                        <flux:icon name="building-office" class="size-3.5 text-zinc-400 shrink-0" />
                        <span class="truncate font-medium">{{ $userUnitLabel }}</span>
                    </div>
                </div>

                <flux:menu.separator />

                <form method="POST" action="/logout">
                    @csrf
                    <flux:menu.item icon="arrow-right-start-on-rectangle" variant="danger" type="submit">
                        Logout
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{-- Main Container Langsung di Bawah Header Konten --}}
    <flux:main container>
        <x-context-banner />
        {{ $slot }}
    </flux:main>

    <flux:toast position="top end" />

    @livewireScripts
    @fluxScripts
</body>
</html>
