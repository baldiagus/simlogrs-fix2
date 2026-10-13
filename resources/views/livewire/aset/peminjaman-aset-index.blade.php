<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">{{ $pageTitle }}</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">{{ $pageSubtitle }}</flux:subheading>
            </div>

            @can('create_layanan_unit::peminjaman::aset')
                <div class="flex items-center gap-2">
                    <flux:button wire:click="createPinjam" variant="primary" icon="plus">
                        Ajukan Peminjaman
                    </flux:button>
                </div>
            @endcan
        </div>

        {{-- Navigasi Sub-Tab Hub Peminjaman --}}
        <x-tabs.sirkulasi-peminjaman active="pinjam" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Navigasi tab --}}
        <flux:tabs class="pt-2">
            <flux:tab
                wire:click="setTab('aktif')"
                :current="$activeTab === 'aktif'"
                icon="arrow-path"
                :badge="$countAktif"
            >
                Peminjaman Aktif & Riwayat
            </flux:tab>
            <flux:tab
                wire:click="setTab('katalog')"
                :current="$activeTab === 'katalog'"
                icon="archive-box"
                :badge="$countKatalog"
            >
                Katalog Aset Tersedia
            </flux:tab>
        </flux:tabs>

        {{-- Toolbar filter dan pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-4 pt-1">
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <div class="w-full sm:w-80">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ $activeTab === 'aktif' ? 'Cari kode PINJ, aset, unit, keperluan...' : 'Cari kode register aset, nama barang, nomor seri...' }}"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                @if ($activeTab === 'aktif')
                    <div class="w-full sm:w-44">
                        <select
                            wire:model.live="filterStatus"
                            class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100"
                        >
                            <option value="">Semua Status</option>
                            @foreach (\App\Enums\Aset\StatusPeminjaman::cases() as $statusPinjam)
                                <option value="{{ $statusPinjam->value }}">{{ $statusPinjam->value }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($userUnitId)
                        <div class="px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-xs text-blue-700 dark:text-blue-300 font-medium flex items-center gap-1.5">
                            <flux:icon name="arrow-right-start-on-rectangle" class="size-3.5 text-blue-600" />
                            <span>Permohonan Pinjam Unit ke Luar</span>
                        </div>
                    @endif
                @else
                    @if ($isGlobalAdmin)
                        <div class="w-full sm:w-44">
                            <select
                                wire:model.live="filterKatalogLokasi"
                                class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                            >
                                <option value="">Semua Lokasi</option>
                                <option value="RUANGAN">Ruangan Unit</option>
                                <option value="GUDANG">Gudang Logistik</option>
                            </select>
                        </div>

                        @if ($filterKatalogLokasi === 'RUANGAN')
                            <div class="w-full sm:w-48">
                                <select
                                    wire:model.live="filterKatalogUnitId"
                                    class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                                >
                                    <option value="">Semua Ruangan</option>
                                    @foreach ($daftarUnit as $unitOpt)
                                        <option value="{{ $unitOpt->id }}">{{ $unitOpt->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif ($filterKatalogLokasi === 'GUDANG')
                            <div class="w-full sm:w-48">
                                <select
                                    wire:model.live="filterKatalogGudangId"
                                    class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                                >
                                    <option value="">Semua Gudang</option>
                                    @foreach ($daftarGudang as $gdgOpt)
                                        <option value="{{ $gdgOpt->id }}">{{ $gdgOpt->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    @else
                        <div class="px-3 py-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-600 dark:text-zinc-300 flex items-center gap-1.5 font-medium">
                            <flux:icon icon="map-pin" class="size-3.5 text-blue-600" />
                            <span>Lokasi Terdaftar: {{ $userUnitId ? (auth()->user()->unit?->nama ?? 'Ruangan Anda') : (auth()->user()->gudang?->nama ?? 'Gudang Anda') }}</span>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Tampilan Tab 1: Peminjaman Aktif & Riwayat --}}
        @if ($activeTab === 'aktif')
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode PINJ</flux:table.column>
                    <flux:table.column class="w-44">Penyedia Aset</flux:table.column>
                    <flux:table.column class="w-36">Unit Pemohon</flux:table.column>
                    <flux:table.column class="w-44">Periode Pinjam</flux:table.column>
                    <flux:table.column class="w-48">Aset Dipinjam</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                    <flux:table.column class="text-right w-16">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarPeminjaman as $item)
                        @php
                            $isOverdue = ($item->status === \App\Enums\Aset\StatusPeminjaman::Dipinjam->value && now()->startOfDay()->gt($item->tanggal_kembali_rencana));
                        @endphp
                        <flux:table.row :key="$item->id">
                            {{-- Kode PINJ --}}
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->kode }}
                            </flux:table.cell>

                            {{-- Penyedia --}}
                            <flux:table.cell style="white-space: normal;">
                                @if ($item->tipe_penyedia === 'RUANGAN')
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="xs" color="cyan">RUANGAN</flux:badge>
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $item->unitPenyedia?->nama ?? '-' }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="xs" color="emerald">GUDANG</flux:badge>
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $item->gudangPenyedia?->nama ?? '-' }}</span>
                                    </div>
                                @endif
                            </flux:table.cell>

                            {{-- Unit Peminjam --}}
                            <flux:table.cell style="white-space: normal;">
                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $item->unitPeminjam?->nama ?? '-' }}
                                </span>
                            </flux:table.cell>

                            {{-- Periode Pinjam (Gabungan Tgl Pinjam & Rencana Kembali) --}}
                            <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                                <div>{{ $item->tanggal_pinjam?->format('d/m/Y') }} &rarr; <span class="{{ $isOverdue ? 'font-bold text-rose-600 dark:text-rose-400' : '' }}">{{ $item->tanggal_kembali_rencana?->format('d/m/Y') }}</span></div>
                                @if ($isOverdue)
                                    <div class="mt-0.5"><flux:badge size="xs" color="rose">OVERDUE</flux:badge></div>
                                @endif
                            </flux:table.cell>

                            {{-- Aset Dipinjam --}}
                            <flux:table.cell style="white-space: normal;">
                                @forelse ($item->detail as $detail)
                                    <div class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $detail->aset?->barang?->nama ?? 'Aset #' . $detail->aset_id }}
                                    </div>
                                    <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">
                                        {{ $detail->aset?->kode_aset ?? '-' }}
                                    </div>
                                @empty
                                    <span class="text-zinc-400 text-xs">-</span>
                                @endforelse
                            </flux:table.cell>

                            {{-- Status --}}
                            <flux:table.cell class="text-center">
                                <x-ui.status-badge :status="$item->status" />
                            </flux:table.cell>

                            {{-- Aksi --}}
                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="openDetail({{ $item->id }})" icon="eye">
                                            Detail Pinjam
                                        </flux:menu.item>
                                        @php
                                            $canSerahTerima = $izin[$item->id]['serah_terima'] ?? false;
                                        @endphp
                                        @if ($item->status === \App\Enums\Aset\StatusPeminjaman::Disetujui->value && $canSerahTerima)
                                            <flux:menu.item wire:click="confirmSerahTerima({{ $item->id }})" icon="arrow-right-start-on-rectangle">
                                                Serah Terima
                                            </flux:menu.item>
                                        @endif

                                        @if (in_array($item->status, [\App\Enums\Aset\StatusPeminjaman::Dipinjam->value], true))
                                            @can('update_layanan_unit::peminjaman::aset')
                                                <flux:menu.item wire:click="openPerpanjang({{ $item->id }})" icon="calendar-days">
                                                    Perpanjang
                                                </flux:menu.item>
                                            @endcan
                                        @endif

                                        @if ($item->status === \App\Enums\Aset\StatusPeminjaman::Diajukan->value)
                                            @can('create_layanan_unit::peminjaman::aset')
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="confirmBatal({{ $item->id }})" icon="x-mark" variant="danger">
                                                    Batalkan Pengajuan
                                                </flux:menu.item>
                                            @endcan
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-ui.empty-state message="Belum ada transaksi peminjaman aset yang terdaftar." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <x-ui.table-pagination :paginator="$daftarPeminjaman" />
        @else
            {{-- Tampilan Tab 2: Katalog Aset Tersedia --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode_aset'" :direction="$sortDirection" wire:click="sort('kode_aset')" class="w-32">Kode Register</flux:table.column>
                    <flux:table.column class="w-64">Komoditas & Nomor Seri</flux:table.column>
                    <flux:table.column class="w-56">Lokasi Penyedia</flux:table.column>
                    <flux:table.column class="text-center w-24">Kondisi</flux:table.column>
                    <flux:table.column class="text-center w-24">Status</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarKatalogAset as $aset)
                        <flux:table.row :key="$aset->id">
                            {{-- Kode Register --}}
                            <flux:table.cell class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $aset->kode_aset }}
                            </flux:table.cell>

                            {{-- Komoditas & Seri --}}
                            <flux:table.cell style="white-space: normal;">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $aset->barang?->nama ?? '-' }}</div>
                                @if ($aset->nomor_seri)
                                    <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">SN: {{ $aset->nomor_seri }}</div>
                                @endif
                            </flux:table.cell>

                            {{-- Lokasi Penyedia --}}
                            <flux:table.cell style="white-space: normal;">
                                @if ($aset->unit_id)
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="xs" color="cyan">RUANGAN</flux:badge>
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $aset->unit?->nama }}</span>
                                    </div>
                                @elseif ($aset->gudang_id)
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="xs" color="emerald">GUDANG</flux:badge>
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $aset->gudang?->nama }}</span>
                                    </div>
                                    @if ($aset->lokasiGudang)
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono mt-0.5">Bin/Rak: {{ $aset->lokasiGudang->nama }}</div>
                                    @endif
                                @else
                                    <span class="text-zinc-400 text-xs">-</span>
                                @endif
                            </flux:table.cell>

                            {{-- Kondisi --}}
                            <flux:table.cell class="text-center">
                                <flux:badge size="sm" color="emerald">BAIK</flux:badge>
                            </flux:table.cell>

                            {{-- Status --}}
                            <flux:table.cell class="text-center">
                                <flux:badge size="sm" color="emerald">TERSEDIA</flux:badge>
                            </flux:table.cell>

                            {{-- Aksi --}}
                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        @can('create_layanan_unit::peminjaman::aset')
                                            <flux:menu.item wire:click="pinjamAset({{ $aset->id }})" icon="arrow-path" class="text-emerald-600 dark:text-emerald-400">
                                                Ajukan Pinjam
                                            </flux:menu.item>
                                        @endcan
                                        <flux:menu.item wire:click="showQr({{ $aset->id }})" icon="qr-code">
                                            Stiker QR Code
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-ui.empty-state message="Tidak ada aset inventaris yang berstatus TERSEDIA dan kondisi BAIK sesuai filter." cols="6" />
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <x-ui.table-pagination :paginator="$daftarKatalogAset" />
        @endif
    </flux:card>

    @include('livewire.aset.partials.peminjaman-aset.modal-pinjam')

    @include('livewire.aset.partials.peminjaman-aset.modal-perpanjang')

    @include('livewire.aset.partials.peminjaman-aset.modal-qr-code')

    @include('livewire.aset.partials.peminjaman-aset.modal-detail-pinjam')

    {{-- Modal Konfirmasi Tindakan --}}
    <x-ui.confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
