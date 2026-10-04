<div class="space-y-6">
    {{-- Card 1: Peminjaman Aktif yang Berstatus DIPINJAM --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $pageTitle }}</flux:heading>
                <flux:subheading>Inspeksi fisik dan serah terima pengembalian aset inventaris rumah sakit yang sedang dipinjam.</flux:subheading>
            </div>
        </div>

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

        {{-- Tabel Transaksi Peminjaman Aktif --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode PINJ</flux:table.column>
                <flux:table.column>Peminjam</flux:table.column>
                <flux:table.column>Penyedia</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_pinjam'" :direction="$sortDirection" wire:click="sort('tanggal_pinjam')" class="w-28">Tgl Pinjam</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_kembali_rencana'" :direction="$sortDirection" wire:click="sort('tanggal_kembali_rencana')" class="w-36">Batas Kembali</flux:table.column>
                <flux:table.column class="text-center w-24">Jml Aset</flux:table.column>
                <flux:table.column class="text-right w-44">Aksi</flux:table.column>
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
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100 text-xs">{{ $pinjam->unitPeminjam?->nama ?? '-' }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Pengaju: {{ $pinjam->pengaju?->nama ?? '-' }}</div>
                        </flux:table.cell>

                        {{-- Unit / Gudang Penyedia --}}
                        <flux:table.cell>
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
                        <flux:table.cell class="text-right">
                            <flux:button
                                size="sm"
                                class="bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold shadow-xs inline-flex items-center gap-1.5"
                                wire:click="openPengembalianModal({{ $pinjam->id }})"
                                wire:loading.attr="disabled"
                            >
                                <flux:icon name="arrow-uturn-left" class="size-3.5" />
                                <span>Proses Pengembalian</span>
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Tidak ada transaksi peminjaman aktif yang memerlukan pengembalian." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Navigasi Paginasi Peminjaman Aktif --}}
        <x-table-pagination :paginator="$daftarPeminjaman" />
    </flux:card>

    {{-- Card 2: Riwayat Pengembalian Selesai (PengembalianPeminjaman) --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div>
            <flux:heading size="lg">Riwayat Pengembalian & Hasil Inspeksi Selesai</flux:heading>
            <flux:subheading>Daftar transaksi pengembalian aset beserta catatan inspeksi fisik kelayakan dan tiket perbaikan IPSRS.</flux:subheading>
        </div>

        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-36">Kode RTN</flux:table.column>
                <flux:table.column class="w-36">Ref PINJ</flux:table.column>
                <flux:table.column class="w-28">Tgl Aktual</flux:table.column>
                <flux:table.column>Unit Peminjam</flux:table.column>
                <flux:table.column>Diperiksa Oleh</flux:table.column>
                <flux:table.column class="text-center w-36">Hasil Fisik</flux:table.column>
                <flux:table.column>Catatan</flux:table.column>
                <flux:table.column class="text-right w-20">Aksi</flux:table.column>
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
                        <flux:table.cell class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $kembali->permintaanPeminjaman?->unitPeminjam?->nama ?? '-' }}
                        </flux:table.cell>

                        {{-- Diperiksa Oleh --}}
                        <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
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

                        {{-- Tombol Detail --}}
                        <flux:table.cell class="text-right">
                            <flux:button
                                size="xs"
                                variant="ghost"
                                icon="eye"
                                wire:click="openDetailPengembalianModal({{ $kembali->id }})"
                                title="Lihat Rincian Inspeksi"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada riwayat transaksi pengembalian aset yang tercatat." cols="8" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($riwayatPengembalian->hasPages())
            <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:pagination :paginator="$riwayatPengembalian" />
            </div>
        @endif
    </flux:card>

    {{-- Modal Inspeksi & Terima Kembali Aset --}}
    <flux:modal name="modal-inspeksi-pengembalian" wire:model="showPengembalianModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPinjam)
            <div class="space-y-5">
                {{-- Header Modal --}}
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Inspeksi & Terima Kembali Aset</flux:heading>
                            <span class="font-mono text-sm font-bold text-zinc-500 dark:text-zinc-400">#{{ $selectedPinjam->kode }}</span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Pemeriksaan kondisi fisik aset inventaris saat diserahterimakan kembali ke penyedia.
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <flux:badge size="sm" color="amber" inset="top bottom">DIPINJAM</flux:badge>
                    </div>
                </div>

                {{-- Ringkasan Metadata Transaksi --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Peminjam</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPinjam->unitPeminjam?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Penyedia Aset</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">
                            @if ($selectedPinjam->tipe_penyedia === 'GUDANG')
                                {{ $selectedPinjam->gudangPenyedia?->nama ?? '-' }} (Gudang)
                            @else
                                {{ $selectedPinjam->unitPenyedia?->nama ?? '-' }} (Ruangan)
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Tanggal Pinjam</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPinjam->tanggal_pinjam?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Batas Kembali</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPinjam->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                </div>

                {{-- Form Tabel Inspeksi Fisik Aset --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                            Pemeriksaan Kondisi Aset Fisik
                        </div>
                        <span class="text-xs text-zinc-500 font-medium">Total: {{ count($inspeksiRows) }} unit aset</span>
                    </div>

                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-2xs">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-100 dark:bg-zinc-800/80 font-semibold text-zinc-700 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3.5 py-2.5 w-10">#</th>
                                    <th class="px-3.5 py-2.5">Aset & Register</th>
                                    <th class="px-3.5 py-2.5 w-52">Kondisi Saat Kembali</th>
                                    <th class="px-3.5 py-2.5">Catatan Fisik / Kerusakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                                @foreach ($inspeksiRows as $detailId => $row)
                                    <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                        <td class="px-3.5 py-3 text-zinc-400 font-mono">{{ $loop->iteration }}</td>
                                        <td class="px-3.5 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100 text-sm">{{ $row['nama_aset'] }}</div>
                                            <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">Kode: {{ $row['kode_aset'] }}</div>
                                            <div class="text-[11px] text-zinc-500 mt-0.5">Kondisi Awal: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $row['kondisi_saat_pinjam'] ?? 'BAIK' }}</span></div>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <select
                                                wire:model.live="inspeksiRows.{{ $detailId }}.kondisi_saat_kembali"
                                                class="w-full text-xs font-semibold rounded-lg border px-2.5 py-1.5 transition-colors {{ ($row['kondisi_saat_kembali'] ?? 'BAIK') === 'BAIK' ? 'border-emerald-300 bg-emerald-50/50 text-emerald-800 dark:bg-emerald-950/30 dark:border-emerald-700 dark:text-emerald-300' : (($row['kondisi_saat_kembali'] ?? '') === 'RUSAK_RINGAN' ? 'border-amber-300 bg-amber-50/50 text-amber-800 dark:bg-amber-950/30 dark:border-amber-700 dark:text-amber-300' : 'border-rose-300 bg-rose-50/50 text-rose-800 dark:bg-rose-950/30 dark:border-rose-700 dark:text-rose-300') }}"
                                            >
                                                <option value="BAIK">BAIK (Normal / Siap Pakai)</option>
                                                <option value="RUSAK_RINGAN">RUSAK RINGAN (Tiket IPSRS)</option>
                                                <option value="RUSAK_BERAT">RUSAK BERAT (Tiket CITO)</option>
                                            </select>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <flux:input
                                                wire:model="inspeksiRows.{{ $detailId }}.catatan"
                                                placeholder="Catatan kondisi fisik / detail kerusakan..."
                                                size="sm"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Alert Box jika terdeteksi aset rusak --}}
                @php
                    $anyDamaged = collect($inspeksiRows)->contains(fn ($r) => in_array($r['kondisi_saat_kembali'] ?? 'BAIK', ['RUSAK_RINGAN', 'RUSAK_BERAT']));
                @endphp
                @if ($anyDamaged)
                    <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
                        <flux:icon name="exclamation-triangle" class="size-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                        <div>
                            <div class="font-bold">Peringatan: Kerusakan Fisik Terdeteksi</div>
                            <div class="text-[11px] mt-0.5 text-amber-700 dark:text-amber-300">
                                Aset dengan kondisi <strong>RUSAK RINGAN</strong> atau <strong>RUSAK BERAT</strong> akan otomatis dialihkan ke status <strong>DALAM_MAINTENANCE</strong> dan sistem langsung menerbitkan tiket perbaikan ke antrean teknisi IPSRS.
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Catatan Pengembalian Umum --}}
                <div>
                    <flux:textarea
                        wire:model="catatanUmum"
                        label="Catatan Pengembalian (Opsional)"
                        placeholder="Tambahkan catatan serah terima fisik atau keterangan lainnya..."
                        rows="2"
                    />
                </div>

                {{-- Tombol Batal & Submit --}}
                <div class="flex items-center justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button
                        type="button"
                        variant="primary"
                        class="!bg-emerald-600 hover:!bg-emerald-700 !text-white font-medium shadow-xs"
                        wire:click="confirmSubmitPengembalian"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="confirmSubmitPengembalian">Inspeksi & Terima Kembali</span>
                        <span wire:loading wire:target="confirmSubmitPengembalian">Memvalidasi...</span>
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Detail Riwayat Pengembalian --}}
    <flux:modal name="modal-detail-pengembalian" wire:model="showDetailPengembalianModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPengembalian)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Riwayat Pengembalian Aset</flux:heading>
                            <span class="font-mono text-sm font-bold text-zinc-500 dark:text-zinc-400">#{{ $selectedPengembalian->kode }}</span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal Kembali: {{ $selectedPengembalian->tanggal_kembali_aktual?->format('d F Y') }}
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <flux:badge size="sm" color="emerald" inset="top bottom">SELESAI</flux:badge>
                    </div>
                </div>

                {{-- Metadata --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Ref Peminjaman</div>
                        <div class="font-mono font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPengembalian->permintaanPeminjaman?->kode ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Peminjam</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPengembalian->permintaanPeminjaman?->unitPeminjam?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Diperiksa Oleh</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPengembalian->pemeriksa?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Item Aset</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPengembalian->detail->count() }} unit</div>
                    </div>
                </div>

                @if ($selectedPengembalian->catatan)
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Catatan Pengembalian:</span>
                        <p class="text-zinc-600 dark:text-zinc-400 mt-1">{{ $selectedPengembalian->catatan }}</p>
                    </div>
                @endif

                {{-- Tabel Rincian Kondisi Aset --}}
                <div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-2">
                        Rincian Hasil Inspeksi Fisik
                    </div>
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-100 dark:bg-zinc-800/80 font-semibold text-zinc-700 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3.5 py-2.5 w-10">#</th>
                                    <th class="px-3.5 py-2.5">Aset</th>
                                    <th class="px-3.5 py-2.5 w-36">Kondisi Akhir</th>
                                    <th class="px-3.5 py-2.5">Catatan Inspeksi</th>
                                    <th class="px-3.5 py-2.5 w-44">Tiket IPSRS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                                @foreach ($selectedPengembalian->detail as $detail)
                                    <tr>
                                        <td class="px-3.5 py-2.5 text-zinc-400 font-mono">{{ $loop->iteration }}</td>
                                        <td class="px-3.5 py-2.5">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->aset?->barang?->nama ?? '-' }}</div>
                                            <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">Kode: {{ $detail->aset?->kode_aset ?? '-' }}</div>
                                        </td>
                                        <td class="px-3.5 py-2.5">
                                            @if ($detail->kondisi_saat_kembali === 'BAIK')
                                                <flux:badge size="sm" color="emerald" inset="top bottom">BAIK</flux:badge>
                                            @elseif ($detail->kondisi_saat_kembali === 'RUSAK_RINGAN')
                                                <flux:badge size="sm" color="amber" inset="top bottom">RUSAK RINGAN</flux:badge>
                                            @else
                                                <flux:badge size="sm" color="red" inset="top bottom">RUSAK BERAT</flux:badge>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-2.5 text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->catatan ?: '-' }}
                                        </td>
                                        <td class="px-3.5 py-2.5">
                                            @if ($detail->maintenance->isNotEmpty())
                                                @foreach ($detail->maintenance as $mnt)
                                                    <span class="font-mono text-[11px] px-2 py-0.5 rounded bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 font-bold">
                                                        {{ $mnt->kode }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-zinc-400 text-[11px]">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @endif
    </flux:modal>

    {{-- Modal Konfirmasi Tindakan --}}
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
