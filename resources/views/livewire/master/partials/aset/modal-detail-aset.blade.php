    {{-- Modal Detail Aset Inventaris --}}
    <flux:modal wire:model="showDetailModal" class="md:w-[44rem]">
        @if ($selectedAset)
            <div class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Detail Aset Inventaris: {{ $selectedAset->barang?->nama }}</flux:heading>
                    <flux:subheading>Informasi lengkap unit fisik aset yang terdaftar.</flux:subheading>
                </div>

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
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedAset->nomor_seri ?? '-' }}</div>
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
                            <x-status-badge :status="$selectedAset->status" />
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Histori Servis</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->permintaan_maintenance_count }} kali</div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nilai Perolehan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                            <x-rupiah :value="$selectedAset->nilai_perolehan" />
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Tanggal Perolehan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->tanggal_perolehan->format('d/m/Y') }}</div>
                    </div>
                </div>

                {{-- Riwayat Sirkulasi Peminjaman Aset --}}
                <div class="pt-2">
                    <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2 flex items-center justify-between">
                        <span>Buku Rekam Sirkulasi & Peminjaman Unit</span>
                        <span class="text-zinc-400 font-normal">{{ $selectedAset->peminjamanDetail->count() }} transaksi tercatat</span>
                    </div>

                    @if ($selectedAset->peminjamanDetail->isEmpty())
                        <div class="p-3 text-xs rounded-lg bg-zinc-50 dark:bg-zinc-800/40 text-zinc-500 italic text-center">
                            Belum pernah ada riwayat sirkulasi peminjaman untuk unit alat ini.
                        </div>
                    @else
                        @php
                            $latestD = $selectedAset->peminjamanDetail->sortByDesc('id')->first();
                            $latestPinjam = $latestD?->permintaanPeminjaman;
                            $latestKembali = $latestD?->pengembalianDetail->first();
                        @endphp
                        <div class="p-3 border border-zinc-200 dark:border-zinc-700/60 rounded-xl bg-zinc-50/60 dark:bg-zinc-800/30 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $latestPinjam?->kode ?? '-' }}</span>
                                    <span class="text-[11px] text-zinc-400">&bull; Peminjaman Terakhir</span>
                                </div>
                                <x-status-badge :status="$latestPinjam?->status ?? \App\Enums\StatusPeminjaman::Selesai->value" />
                            </div>
                            <div class="text-xs text-zinc-700 dark:text-zinc-300">
                                <span class="font-semibold">{{ $latestPinjam?->unitPeminjam?->nama ?? 'Ruangan' }}</span>
                                <span class="text-zinc-400 font-normal">({{ $latestPinjam?->pengaju?->name ?? 'Staf' }})</span>
                            <div class="flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400 pt-1 border-t border-zinc-200/60 dark:border-zinc-700/40">
                                <span>Masa: {{ $latestPinjam?->tanggal_pinjam?->format('d/m/Y') ?? '-' }} s/d {{ $latestPinjam?->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}</span>
                                @if ($latestKembali)
                                    <span class="font-medium text-emerald-600 dark:text-emerald-400">Kembali: {{ str_replace('_', ' ', $latestKembali->kondisi_saat_kembali) }}</span>
                                @elseif ($latestPinjam?->status === \App\Enums\StatusPeminjaman::Dipinjam->value)
                                    <span class="font-medium text-amber-600 dark:text-amber-400">Sedang Dipinjam</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <flux:button size="xs" variant="primary" icon="arrow-top-right-on-square" wire:click="openSirkulasiModal({{ $selectedAset->id }})">
                                Lihat Semua Riwayat Sirkulasi ({{ $selectedAset->peminjamanDetail->count() }})
                            </flux:button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
