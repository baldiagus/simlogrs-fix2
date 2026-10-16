<div class="space-y-6">
    {{-- Header Halaman --}}
    <div class="space-y-1">
        <flux:heading size="xl" level="1" class="font-bold tracking-tight">{{ $pageTitle }}</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400">Inspeksi fisik dan serah terima pengembalian aset inventaris rumah sakit yang sedang dipinjam.</flux:subheading>
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Navigasi Sub-Tab Status --}}
        <flux:tabs class="pt-2">
            <flux:tab
                wire:click="setTab('menunggu')"
                :current="$activeTab === 'menunggu'"
                icon="clock"
                :badge="$daftarPeminjaman->total()"
            >
                Menunggu Pengembalian
            </flux:tab>
            <flux:tab
                wire:click="setTab('riwayat')"
                :current="$activeTab === 'riwayat'"
                icon="check-circle"
                :badge="$riwayatPengembalian->total()"
            >
                Riwayat Pengembalian & Inspeksi Selesai
            </flux:tab>
        </flux:tabs>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-96">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode transaksi, unit peminjam, penyedia, atau aset..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>
        </div>

        @if ($activeTab === 'menunggu')
            {{-- Tabel Transaksi Peminjaman Aktif --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode PINJ</flux:table.column>
                    <flux:table.column class="w-44">Peminjam</flux:table.column>
                    <flux:table.column class="w-44">Penyedia</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_pinjam'" :direction="$sortDirection" wire:click="sort('tanggal_pinjam')" class="w-28">Tgl Pinjam</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_kembali_rencana'" :direction="$sortDirection" wire:click="sort('tanggal_kembali_rencana')" class="w-32">Batas Kembali</flux:table.column>
                    <flux:table.column class="text-center w-24">Jml Aset</flux:table.column>
                    <flux:table.column class="text-right w-40">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarPeminjaman as $pinjam)
                        @php
                            $isOverdue = $pinjam->tanggal_kembali_rencana && \Carbon\Carbon::parse($pinjam->tanggal_kembali_rencana)->isPast() && ! \Carbon\Carbon::parse($pinjam->tanggal_kembali_rencana)->isToday();
                        @endphp
                        <flux:table.row :key="$pinjam->id">
                            {{-- Kode PINJ --}}
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                <div>{{ $pinjam->kode }}</div>
                                <div class="mt-0.5">
                                    <flux:badge size="sm" color="amber" inset="top bottom">DIPINJAM</flux:badge>
                                </div>
                            </flux:table.cell>

                            {{-- Unit Peminjam --}}
                            <flux:table.cell style="white-space: normal;">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100 text-xs">{{ $pinjam->unitPeminjam?->nama ?? '-' }}</div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Pengaju: {{ $pinjam->pengaju?->nama ?? '-' }}</div>
                            </flux:table.cell>

                            {{-- Unit / Gudang Penyedia --}}
                            <flux:table.cell style="white-space: normal;">
                                @if ($pinjam->tipe_penyedia === 'GUDANG')
                                    <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $pinjam->gudangPenyedia?->nama ?? '-' }}</div>
                                    <span class="text-[10px] text-zinc-500 font-medium">(Gudang Sentral)</span>
                                @else
                                    <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $pinjam->unitPenyedia?->nama ?? '-' }}</div>
                                    <span class="text-[10px] text-zinc-500 font-medium">(Ruangan Asal)</span>
                                @endif
                            </flux:table.cell>

                            {{-- Tanggal Pinjam --}}
                            <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ $pinjam->tanggal_pinjam?->format('d/m/Y') ?? '-' }}
                            </flux:table.cell>

                            {{-- Batas Kembali & Indikator Overdue --}}
                            <flux:table.cell>
                                <div class="space-y-1">
                                    <div class="text-xs tabular-nums {{ $isOverdue ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                        {{ $pinjam->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}
                                    </div>
                                    @if ($isOverdue)
                                        <flux:badge size="sm" color="red" inset="top bottom">OVERDUE</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>

                            {{-- Jumlah Aset --}}
                            <flux:table.cell class="text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                                    {{ $pinjam->detail_count ?? $pinjam->detail->count() }} unit
                                </span>
                            </flux:table.cell>

                            {{-- Tombol Aksi: Proses Pengembalian --}}
                            @php
                                $user = auth()->user();
                                $isProvider = $isGlobalAdmin || ($user?->canAny(['create_layanan_unit::pengembalian::aset', 'update_layanan_unit::pengembalian::aset']) && (
                                    ($pinjam->tipe_penyedia === 'RUANGAN' && $user?->unit_id && (int) $user->unit_id === (int) $pinjam->unit_penyedia_id) ||
                                    ($pinjam->tipe_penyedia === 'GUDANG' && $user?->gudang_id && (int) $user->gudang_id === (int) $pinjam->gudang_penyedia_id)));
                            @endphp
                            {{-- Aksi: Proses Pengembalian --}}
                            <flux:table.cell class="text-right w-16">
                                @if ($isProvider)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                        <flux:menu class="min-w-44">
                                            <flux:menu.item wire:click="openPengembalianModal({{ $pinjam->id }})" icon="arrow-uturn-left" class="text-emerald-600 dark:text-emerald-400">
                                                Proses Pengembalian
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <span class="text-[11px] text-zinc-400 italic">Serahkan ke Penyedia</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-ui.empty-state message="Tidak ada transaksi peminjaman aktif yang memerlukan pengembalian." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>

            {{-- Navigasi Paginasi Peminjaman Aktif --}}
            <x-ui.table-pagination :paginator="$daftarPeminjaman" />
        @else
            {{-- Tabel Riwayat Pengembalian Selesai --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column class="w-32">Kode RTN</flux:table.column>
                    <flux:table.column class="w-28">Ref PINJ</flux:table.column>
                    <flux:table.column class="w-28">Tgl Aktual</flux:table.column>
                    <flux:table.column class="w-44">Unit Peminjam</flux:table.column>
                    <flux:table.column class="w-36">Diperiksa Oleh</flux:table.column>
                    <flux:table.column class="text-center w-32">Hasil Fisik</flux:table.column>
                    <flux:table.column>Catatan</flux:table.column>
                    <flux:table.column class="text-right w-16">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($riwayatPengembalian as $kembali)
                        @php
                            $totalItem = $kembali->detail->count();
                            $damagedCount = $kembali->detail->filter(fn ($d) => in_array($d->kondisi_saat_kembali, ['RUSAK_RINGAN', 'RUSAK_BERAT']))->count();
                        @endphp
                        <flux:table.row :key="$kembali->id">
                            {{-- Kode RTN --}}
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $kembali->kode ?? '-' }}
                            </flux:table.cell>

                            {{-- Ref Kode PINJ --}}
                            <flux:table.cell class="font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                {{ $kembali->permintaanPeminjaman?->kode ?? '-' }}
                            </flux:table.cell>

                            {{-- Tanggal Kembali Aktual --}}
                            <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ $kembali->tanggal_kembali_aktual?->format('d/m/Y') ?? '-' }}
                            </flux:table.cell>

                            {{-- Unit Peminjam --}}
                            <flux:table.cell class="text-xs font-medium text-zinc-800 dark:text-zinc-200" style="white-space: normal;">
                                {{ $kembali->permintaanPeminjaman?->unitPeminjam?->nama ?? '-' }}
                            </flux:table.cell>

                            {{-- Diperiksa Oleh --}}
                            <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400" style="white-space: normal;">
                                {{ $kembali->pemeriksa?->nama ?? '-' }}
                            </flux:table.cell>

                            {{-- Ringkasan Kondisi --}}
                            <flux:table.cell class="text-center">
                                @if ($damagedCount > 0)
                                    <flux:badge size="sm" color="amber" inset="top bottom">
                                        {{ $damagedCount }} Rusak (Tiket IPSRS)
                                    </flux:badge>
                                @else
                                    <flux:badge size="sm" color="emerald" inset="top bottom">
                                        {{ $totalItem }} Baik
                                    </flux:badge>
                                @endif
                            </flux:table.cell>

                            {{-- Catatan Pengembalian --}}
                            <flux:table.cell class="text-xs text-zinc-500 max-w-xs truncate">
                                {{ $kembali->catatan ?: '-' }}
                            </flux:table.cell>

                            {{-- Aksi --}}
                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="openDetailPengembalianModal({{ $kembali->id }})" icon="eye">
                                            Rincian Inspeksi
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-ui.empty-state message="Belum ada riwayat transaksi pengembalian aset yang tercatat." cols="8" />
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <x-ui.table-pagination :paginator="$riwayatPengembalian" />
        @endif
    </flux:card>

    @include('livewire.aset.partials.pengembalian.modal-inspeksi')

    @include('livewire.aset.partials.pengembalian.modal-detail')

    {{-- Modal Konfirmasi Tindakan --}}
    <x-ui.confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
