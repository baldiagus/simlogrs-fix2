    {{-- Modal Detail Aset Inventaris --}}
    <flux:modal wire:model="showDetailModal" class="w-full max-w-5xl">
        @if ($selectedAset)
            <div x-data="{ activeTab: 'info' }" class="space-y-4">
                {{-- Header Modal --}}
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800" style="padding-right: 2.5rem;">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Aset: {{ $selectedAset->barang?->nama }}</flux:heading>
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                                {{ $selectedAset->kode_aset }}
                            </span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Nomor Seri: {{ $selectedAset->nomor_seri ?: '-' }} &bull;
                            Lokasi: {{ $selectedAset->unit?->nama ?? $selectedAset->gudang?->nama ?? '-' }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.status-badge :status="$selectedAset->status" />
                    </div>
                </div>

                {{-- Navigasi Tab Header --}}
                <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
                    <button
                        type="button"
                        @click="activeTab = 'info'"
                        :class="activeTab === 'info' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'"
                        class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5"
                    >
                        <flux:icon name="information-circle" class="size-3.5" />
                        <span>Informasi Umum</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'servis'"
                        :class="activeTab === 'servis' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'"
                        class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5"
                    >
                        <flux:icon name="wrench-screwdriver" class="size-3.5" />
                        <span>Rekam Medis &amp; Servis</span>
                        @if ($selectedAset->permintaanMaintenance->isNotEmpty())
                            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                {{ $selectedAset->permintaanMaintenance->count() }}
                            </span>
                        @endif
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'pinjam'"
                        :class="activeTab === 'pinjam' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'"
                        class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5"
                    >
                        <flux:icon name="arrow-path" class="size-3.5" />
                        <span>Riwayat Peminjaman</span>
                        @if ($selectedAset->peminjamanDetail->isNotEmpty())
                            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                {{ $selectedAset->peminjamanDetail->count() }}
                            </span>
                        @endif
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'pengalihan'"
                        :class="activeTab === 'pengalihan' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'"
                        class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5"
                    >
                        <flux:icon name="arrows-right-left" class="size-3.5" />
                        <span>Riwayat Pengalihan &amp; Mutasi</span>
                        @php
                            $selesaiTfCount = $selectedAset->pengalihanDetail->filter(fn($pd) => $pd->pengalihanAset && $pd->pengalihanAset->status === 'SELESAI')->count();
                        @endphp
                        @if ($selesaiTfCount > 0)
                            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300">
                                {{ $selesaiTfCount }}
                            </span>
                        @endif
                    </button>
                </div>

                <div class="max-h-[62vh] overflow-y-auto pr-1">
                    {{-- TAB 1: INFORMASI UMUM --}}
                    <div x-show="activeTab === 'info'" class="space-y-4">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Kode Register Aset</div>
                                <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->kode_aset }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Nama Komoditas</div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->barang?->nama ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Nomor Seri</div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedAset->nomor_seri ?: '-' }}</div>
                            </div>

                            <div class="col-span-2 sm:col-span-3">
                                <div class="text-zinc-500 dark:text-zinc-400">Penempatan Lokasi</div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    @if ($selectedAset->unit_id)
                                        Ruangan: {{ $selectedAset->unit?->nama }}
                                    @elseif ($selectedAset->gudang_id)
                                        Gudang: {{ $selectedAset->gudang?->nama }}
                                        @if ($selectedAset->lokasiGudang)
                                            &middot; Rak: {{ $selectedAset->lokasiGudang->nama }}
                                        @endif
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Kondisi Fisik</div>
                                <div class="mt-0.5">
                                    @php
                                    $kondisiColor = match($selectedAset->kondisi) {
                                        'BAIK' => 'emerald',
                                        'RUSAK_RINGAN' => 'amber',
                                        'RUSAK_BERAT' => 'rose',
                                        default => 'zinc',
                                    };
                                    @endphp
                                    <flux:badge size="sm" :color="$kondisiColor">{{ str_replace('_', ' ', $selectedAset->kondisi) }}</flux:badge>
                                </div>
                            </div>
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Status Operasional</div>
                                <div class="mt-0.5">
                                    <x-ui.status-badge :status="$selectedAset->status" />
                                </div>
                            </div>
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Kategori Barang</div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->barang?->kategori?->nama ?? '-' }}</div>
                            </div>

                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Nilai Perolehan</div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">
                                    <x-ui.rupiah :value="$selectedAset->nilai_perolehan" />
                                </div>
                            </div>
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Tanggal Perolehan</div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->tanggal_perolehan?->format('d/m/Y') ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Satuan Unit</div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->barang?->satuan?->nama ?? 'Unit' }}</div>
                            </div>
                        </div>

                        {{-- Ringkasan Metrik Sirkulasi & Servis --}}
                        @php
                            $allSc = $selectedAset->permintaanMaintenance->flatMap->sukuCadang;
                            $biayaSc = $allSc->sum('subtotal');
                        @endphp
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Total Servis</div>
                                <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedAset->permintaanMaintenance->count() }} Kali</div>
                            </div>
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Total Peminjaman</div>
                                <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedAset->peminjamanDetail->count() }} Kali</div>
                            </div>
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Total Pengalihan</div>
                                <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selesaiTfCount }} Kali</div>
                            </div>
                            <div>
                                <div class="text-zinc-500 dark:text-zinc-400">Biaya Sparepart</div>
                                <div class="font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400 mt-0.5">
                                    <x-ui.rupiah :value="$biayaSc" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 2: REKAM MEDIS & SERVIS --}}
                    <div x-show="activeTab === 'servis'" class="space-y-3">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Kronologi Rekam Medis Servis & Pemeliharaan IPSRS</div>

                        @forelse ($selectedAset->permintaanMaintenance->sortByDesc('id') as $pm)
                            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs space-y-2.5">
                                <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $pm->kode }}</span>
                                        <span class="text-zinc-400">&bull;</span>
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ $pm->dibuat_pada?->format('d/m/Y H:i') }}</span>
                                        @if ($pm->pelapor)
                                            <span class="text-zinc-400">&bull; Pelapor: {{ $pm->pelapor->nama }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="xs" color="blue">{{ $pm->prioritas }}</flux:badge>
                                        <x-ui.status-badge :status="$pm->status" />
                                    </div>
                                </div>

                                <div class="text-zinc-700 dark:text-zinc-300">
                                    <span class="font-semibold text-zinc-500 dark:text-zinc-400">Deskripsi Keluhan:</span> {{ $pm->deskripsi_masalah }}
                                </div>

                                {{-- Pengerjaan Teknisi (Log Servis) --}}
                                @if ($pm->logMaintenance->isNotEmpty())
                                    <div class="pt-1.5 space-y-1.5 border-t border-dashed border-zinc-200 dark:border-zinc-800">
                                        @foreach ($pm->logMaintenance as $lm)
                                            <div class="p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/40 text-[11px] space-y-1">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                                                        Teknisi: {{ $lm->teknisi?->nama ?? '-' }}
                                                        @if ($lm->tanggal_pengerjaan)
                                                            <span class="text-zinc-400 font-normal">({{ $lm->tanggal_pengerjaan->format('d/m/Y') }} {{ $lm->jam_mulai ? $lm->jam_mulai . '-' . $lm->jam_selesai : '' }})</span>
                                                        @endif
                                                    </span>
                                                    <span class="font-bold uppercase text-[10px] {{ $lm->hasil === 'BERHASIL' ? 'text-emerald-600 dark:text-emerald-400' : ($lm->hasil === 'PENDING' ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }}">
                                                        {{ $lm->hasil }}
                                                    </span>
                                                </div>
                                                @if ($lm->diagnosa)
                                                    <div><span class="text-zinc-400">Diagnosa:</span> {{ $lm->diagnosa }}</div>
                                                @endif
                                                <div><span class="text-zinc-400">Tindakan:</span> {{ $lm->tindakan }}</div>
                                                @if ($lm->catatan)
                                                    <div class="text-zinc-500 italic">"{{ $lm->catatan }}"</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Suku Cadang Terganti --}}
                                @if ($pm->sukuCadang->isNotEmpty())
                                    <div class="pt-1.5 border-t border-zinc-100 dark:border-zinc-800 text-[11px]">
                                        <span class="font-semibold text-zinc-600 dark:text-zinc-400">Suku Cadang Terganti:</span>
                                        <div class="flex flex-wrap gap-1.5 mt-1">
                                            @foreach ($pm->sukuCadang as $sc)
                                                <span class="inline-flex items-center gap-1 font-mono bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded text-[10px]">
                                                    <span class="font-semibold">{{ $sc->barang?->nama ?? 'Sparepart' }}</span>
                                                    <span>({{ $sc->jumlah_digunakan }} {{ $sc->barang?->satuan?->simbol ?? 'unit' }})</span>
                                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold">&bull; Rp {{ number_format($sc->subtotal, 0, ',', '.') }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-zinc-400 italic bg-zinc-50/50 dark:bg-zinc-800/20 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
                                Belum ada riwayat perbaikan atau servis untuk aset ini (kondisi orisinal operasional).
                            </div>
                        @endforelse
                    </div>

                    {{-- TAB 3: RIWAYAT PEMINJAMAN --}}
                    <div x-show="activeTab === 'pinjam'" class="space-y-3">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Linimasa Riwayat Peminjaman & Pengembalian Fisik</div>

                        @forelse ($selectedAset->peminjamanDetail->sortByDesc('id') as $d)
                            @php
                                $pinjam = $d->permintaanPeminjaman;
                                $kembali = $d->pengembalianDetail->first();
                                $pengembalianHeader = $kembali?->pengembalianPeminjaman;
                            @endphp
                            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs space-y-2">
                                <div class="flex items-center justify-between pb-1.5 border-b border-zinc-100 dark:border-zinc-800">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $pinjam?->kode ?? '-' }}</span>
                                        <span class="text-zinc-400">&bull;</span>
                                        <span class="text-zinc-500">{{ $pinjam?->unitPeminjam?->nama ?? 'Ruangan' }} ({{ $pinjam?->pengaju?->nama ?? 'Staf' }})</span>
                                    </div>
                                    <x-ui.status-badge :status="$pinjam?->status ?? \App\Enums\Aset\StatusPeminjaman::Selesai->value" />
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] text-zinc-600 dark:text-zinc-400">
                                    <div>
                                        <span class="text-zinc-400">Masa Pinjam:</span>
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $pinjam?->tanggal_pinjam?->format('d/m/Y') ?? '-' }} s/d {{ $pinjam?->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-zinc-400">Keperluan:</span>
                                        <span class="text-zinc-800 dark:text-zinc-200">{{ $pinjam?->keperluan ?: '-' }}</span>
                                    </div>
                                </div>

                                {{-- Hasil Cek Fisik Pengembalian --}}
                                <div class="pt-1.5 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between text-[11px]">
                                    @if ($kembali)
                                        <div class="flex items-center gap-2">
                                            <span class="text-zinc-400">Serah Terima Kembali:</span>
                                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ str_replace('_', ' ', $kembali->kondisi_saat_kembali) }}</span>
                                            <span class="text-zinc-400">&bull; {{ $pengembalianHeader?->tanggal_kembali?->format('d/m/Y') ?? '-' }}</span>
                                            @if ($kembali->catatan)
                                                <span class="text-zinc-500 italic">"{{ $kembali->catatan }}"</span>
                                            @endif
                                        </div>
                                        @if ($pengembalianHeader?->pemeriksa)
                                            <span class="text-zinc-400">Pemeriksa: {{ $pengembalianHeader->pemeriksa->nama }}</span>
                                        @endif
                                    @elseif ($pinjam?->status === \App\Enums\Aset\StatusPeminjaman::Dipinjam->value)
                                        <span class="font-medium text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                            <flux:icon name="clock" class="size-3.5" />
                                            Sedang aktif dipinjam oleh ruangan
                                        </span>
                                    @else
                                        <span class="text-zinc-400">Pengembalian belum tercatat</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-zinc-400 italic bg-zinc-50/50 dark:bg-zinc-800/20 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
                                Belum pernah ada riwayat sirkulasi peminjaman untuk unit alat ini.
                            </div>
                        @endforelse
                    </div>

                    {{-- TAB 4: RIWAYAT PENGALIHAN & MUTASI --}}
                    <div x-show="activeTab === 'pengalihan'" class="space-y-3">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Histori Mutasi & Pengalihan Lokasi Fisik Permanen</div>

                        @forelse ($selectedAset->pengalihanDetail->sortByDesc('id') as $pd)
                            @php
                                $tf = $pd->pengalihanAset;
                            @endphp
                            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs space-y-2">
                                <div class="flex items-center justify-between pb-1.5 border-b border-zinc-100 dark:border-zinc-800">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $tf?->kode ?? '-' }}</span>
                                        <span class="text-zinc-400">&bull;</span>
                                        <span class="text-zinc-500">{{ $tf?->tanggal_pengalihan?->format('d/m/Y') ?? '-' }}</span>
                                    </div>
                                    <x-ui.status-badge :status="$tf?->status ?? 'SELESAI'" />
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                                    <div>
                                        <span class="text-zinc-400">Rute Pengalihan:</span>
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">
                                            {{ $tf?->nama_lokasi_asal ?? '-' }} &rarr; {{ $tf?->nama_lokasi_tujuan ?? '-' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-zinc-400">Alasan:</span>
                                        <span class="text-zinc-800 dark:text-zinc-200">{{ $tf?->alasan ?: '-' }}</span>
                                    </div>
                                </div>

                                <div class="pt-1.5 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400">
                                    <div>
                                        <span>Kondisi Fisik: <strong>{{ str_replace('_', ' ', $pd->kondisi_sebelum ?? 'BAIK') }}</strong> &rarr; <strong>{{ str_replace('_', ' ', $pd->kondisi_saat_diterima ?? 'BAIK') }}</strong></span>
                                        @if ($pd->catatan)
                                            <span class="italic ml-1">"{{ $pd->catatan }}"</span>
                                        @endif
                                    </div>
                                    <div>
                                        <span>Serah Terima: {{ $tf?->tanggal_serah_terima?->format('d/m/Y H:i') ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-zinc-400 italic bg-zinc-50/50 dark:bg-zinc-800/20 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
                                Aset ini belum pernah mengalami pengalihan atau mutasi antar-unit/gudang (masih di lokasi awal perolehan).
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Footer Tombol Tutup --}}
                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="ghost" size="sm">Tutup</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
