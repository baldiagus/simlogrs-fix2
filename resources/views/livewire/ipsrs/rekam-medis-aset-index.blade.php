<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Buku Rekam Medis Sarana & Aset (IPSRS)</flux:heading>
                <flux:subheading>Rekam jejak digital siklus hidup pemeliharaan, histori penanganan teknisi, dan akumulasi biaya suku cadang per unit fasilitas.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Hub Rutin & Rekam Medis --}}
        <x-ipsrs-rutin-tabs active="rekam-medis" />

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode aset, nomor seri, nama sarana..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-48">
                <select wire:model.live="filterKondisi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Kondisi Fisik</option>
                    <option value="BAIK">Kondisi BAIK</option>
                    <option value="RUSAK_RINGAN">RUSAK RINGAN</option>
                    <option value="RUSAK_BERAT">RUSAK BERAT</option>
                </select>
            </div>
        </div>

        {{-- Tabel Rekam Medis Aset --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode_aset'" :direction="$sortDirection" wire:click="sort('kode_aset')" class="w-32">Kode Register</flux:table.column>
                <flux:table.column class="w-64">Sarana / Alat</flux:table.column>
                <flux:table.column class="w-48">Penempatan Lokasi</flux:table.column>
                <flux:table.column class="text-center w-24">Kondisi Fisik</flux:table.column>
                <flux:table.column class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-center w-24">Histori Servis</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarAset as $aset)
                    @php
                        $kondisiCol = match($aset->kondisi) {
                            'BAIK' => 'emerald',
                            'RUSAK_RINGAN' => 'amber',
                            'RUSAK_BERAT' => 'rose',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:table.row :key="$aset->id">
                        {{-- Kode Register --}}
                        <flux:table.cell class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $aset->kode_aset }}
                        </flux:table.cell>

                        {{-- Sarana / Alat --}}
                        <flux:table.cell style="white-space: normal;">
                            <button type="button" wire:click="openRekamMedis({{ $aset->id }})" class="text-left font-medium text-xs text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-2">
                                {{ $aset->barang?->nama ?? '-' }}
                            </button>
                            @if ($aset->nomor_seri)
                                <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    SN: {{ $aset->nomor_seri }}
                                </div>
                            @endif
                        </flux:table.cell>

                        {{-- Lokasi Penempatan --}}
                        <flux:table.cell style="white-space: normal;">
                            @if ($aset->unit)
                                <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                    Ruangan: {{ $aset->unit->nama }}
                                </div>
                            @elseif ($aset->gudang)
                                <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                    Gudang: {{ $aset->gudang->nama }}
                                </div>
                            @else
                                <span class="text-zinc-400 text-xs">-</span>
                            @endif
                        </flux:table.cell>

                        {{-- Kondisi Fisik --}}
                        <flux:table.cell class="text-center">
                            <flux:badge size="sm" :color="$kondisiCol">
                                {{ $aset->kondisi }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Status Operasional --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$aset->status" />
                        </flux:table.cell>

                        {{-- Total Histori Servis --}}
                        <flux:table.cell class="text-center">
                            @if ($aset->permintaan_maintenance_count > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                    {{ $aset->permintaan_maintenance_count }} kali
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    0 kali
                                </span>
                            @endif
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showBukuRiwayat({{ $aset->id }})" icon="book-open">
                                        Rekam Medis Aset
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada unit inventaris aset yang terdaftar." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$daftarAset" />
    </flux:card>

    {{-- Modal Buku Riwayat Rekam Medis Aset --}}
    <flux:modal name="modal-rekam-medis" wire:model="showDetailModal" class="w-full max-w-5xl">
        @if ($selectedAset)
            <div class="space-y-5">
                {{-- Header Modal --}}
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Rekam Medis: {{ $selectedAset->barang?->nama }}</flux:heading>
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                                {{ $selectedAset->kode_aset }}
                            </span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Nomor Seri: {{ $selectedAset->nomor_seri ?? '-' }} &bull;
                            Penempatan: {{ $selectedAset->unit?->nama ?? $selectedAset->gudang?->nama ?? 'Sentral' }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2" style="margin-right: 2.25rem;">
                        <x-status-badge :status="$selectedAset->status" />
                    </div>
                </div>

                {{-- Ringkasan Biaya & Histori Pemeliharaan --}}
                @php
                    $allSukuCadang = $selectedAset->permintaanMaintenance->flatMap->sukuCadang;
                    $totalBiayaSparepart = $allSukuCadang->sum('subtotal');
                    $totalServis = $selectedAset->permintaanMaintenance->count();
                @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Servis Dilakukan</div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $totalServis }} Kali</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Suku Cadang Terganti</div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $allSukuCadang->count() }} Komponen</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Akumulasi Biaya Perbaikan</div>
                        <div class="font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400 mt-0.5">
                            Rp {{ number_format($totalBiayaSparepart, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nilai Awal Perolehan</div>
                        <div class="font-mono font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">
                            Rp {{ number_format($selectedAset->nilai_perolehan, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                {{-- Timeline Riwayat Servis & SPK --}}
                <div class="space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Kronologi Rekam Medis & Servis Fisik</div>

                    @forelse ($selectedAset->permintaanMaintenance as $pm)
                        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs space-y-2">
                            <div class="flex items-center justify-between pb-1.5 border-b border-zinc-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $pm->kode }}</span>
                                    <span class="text-zinc-400">&bull;</span>
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ $pm->dibuat_pada?->format('d F Y H:i') }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <flux:badge size="xs" color="blue">{{ $pm->prioritas }}</flux:badge>
                                    <x-status-badge :status="$pm->status" />
                                </div>
                            </div>

                            <div class="text-zinc-700 dark:text-zinc-300">
                                <span class="font-semibold text-zinc-500 dark:text-zinc-400">Keluhan:</span> {{ $pm->deskripsi_masalah }}
                            </div>

                            {{-- Pengerjaan Teknisi --}}
                            @if ($pm->logMaintenance->isNotEmpty())
                                <div class="pt-1.5 space-y-1.5 border-t border-dashed border-zinc-200 dark:border-zinc-800">
                                    @foreach ($pm->logMaintenance as $lm)
                                        <div class="p-2 rounded bg-zinc-50 dark:bg-zinc-800/40 text-[11px] space-y-1">
                                            <div class="flex items-center justify-between">
                                                <span class="font-semibold text-zinc-800 dark:text-zinc-200">Teknisi: {{ $lm->teknisi?->nama }}</span>
                                                <span class="font-bold text-emerald-600 dark:text-emerald-400 uppercase text-[10px]">{{ $lm->hasil }}</span>
                                            </div>
                                            <div><span class="text-zinc-400">Tindakan:</span> {{ $lm->tindakan }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Suku Cadang Terganti --}}
                            @if ($pm->sukuCadang->isNotEmpty())
                                <div class="pt-1 text-[11px]">
                                    <span class="font-semibold text-zinc-600 dark:text-zinc-400">Suku Cadang:</span>
                                    @foreach ($pm->sukuCadang as $sc)
                                        <span class="inline-block font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-[10px] mr-1 mt-1">
                                            {{ $sc->barang?->nama }} ({{ $sc->jumlah_digunakan }} {{ $sc->barang?->satuan?->simbol ?? 'unit' }}) - Rp {{ number_format($sc->subtotal, 0, ',', '.') }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-zinc-400 italic border rounded-lg">
                            Belum ada riwayat perbaikan atau servis untuk sarana ini (kondisi orisinal operasional).
                        </div>
                    @endforelse
                </div>

                {{-- Riwayat Mutasi & Lokasi Penempatan Aset Fisik --}}
                <div class="space-y-3 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 flex items-center justify-between">
                        <span>Riwayat Mutasi & Lokasi Penempatan</span>
                        <span class="text-[11px] font-normal text-zinc-500">Histori mutasi permanen antar-unit/gudang</span>
                    </div>

                    @forelse ($selectedAset->pengalihanDetail->filter(fn($pd) => $pd->pengalihanAset && $pd->pengalihanAset->status === 'SELESAI') as $pd)
                        @php $tf = $pd->pengalihanAset; @endphp
                        <div class="p-3 rounded-xl border border-blue-100 dark:border-blue-900/60 bg-blue-50/30 dark:bg-blue-950/20 text-xs space-y-1.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-bold text-blue-600 dark:text-blue-400">{{ $tf->kode }}</span>
                                    <span class="text-zinc-400">&bull;</span>
                                    <span class="text-zinc-500">{{ $tf->tanggal_serah_terima?->format('d M Y H:i') }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    MUTASI PERMANEN
                                </span>
                            </div>

                            <div class="flex items-center gap-2 text-xs font-medium">
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $tf->nama_lokasi_asal }}</span>
                                <flux:icon name="arrow-right" class="size-3 text-zinc-400" />
                                <span class="text-blue-700 dark:text-blue-300 font-bold">{{ $tf->nama_lokasi_tujuan }}</span>
                            </div>

                            <div class="text-[11px] text-zinc-500">
                                Dasar: {{ $tf->alasan }}
                                @if ($tf->penerima)
                                    &bull; Diterima oleh: <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $tf->penerima->nama }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-zinc-400 italic border rounded-lg">
                            Belum pernah mengalami mutasi atau pengalihan (berada di lokasi perolehan awal).
                        </div>
                    @endforelse
                </div>

            </div>
        @endif
    </flux:modal>
</div>
