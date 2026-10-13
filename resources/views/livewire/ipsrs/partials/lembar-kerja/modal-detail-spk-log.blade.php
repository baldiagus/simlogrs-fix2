    {{-- Modal Detail SPK & Riwayat Lembar Kerja --}}
    <flux:modal name="modal-detail-spk-log" wire:model="showDetailModal" class="w-full max-w-4xl">
        @if ($selectedSpk)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail SPK: {{ $selectedSpk->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Fasilitas: {{ $selectedSpk->permintaanMaintenance?->aset?->barang?->nama }} &bull;
                            Unit: {{ $selectedSpk->permintaanMaintenance?->unit?->nama }}
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-ui.status-badge :status="$selectedSpk->status" />
                    </div>
                </div>
                {{-- Keluhan & Foto Bukti Fisik Kerusakan dari Ruangan --}}
                <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/40 text-xs space-y-2">
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 flex items-center justify-between">
                        <span>Laporan Keluhan Kerusakan:</span>
                        @if ($selectedSpk->permintaanMaintenance?->prioritas)
                            <flux:badge size="sm" :color="match($selectedSpk->permintaanMaintenance->prioritas) {
                                'CITO' => 'rose',
                                'TINGGI' => 'amber',
                                'SEDANG' => 'blue',
                                default => 'zinc',
                            }">
                                {{ $selectedSpk->permintaanMaintenance->prioritas }}
                            </flux:badge>
                        @endif
                    </div>
                    <div class="text-zinc-700 dark:text-zinc-300 whitespace-pre-line">
                        {{ $selectedSpk->permintaanMaintenance?->deskripsi_masalah ?? '-' }}
                    </div>

                    @if ($selectedSpk->permintaanMaintenance?->foto_kerusakan)
                        <div class="pt-2 border-t border-zinc-200/60 dark:border-zinc-700">
                            <div class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1.5">Foto Bukti Kerusakan Fisik dari Pelapor:</div>
                            <div class="flex flex-col sm:flex-row items-start gap-3">
                                <a href="{{ asset('storage/' . $selectedSpk->permintaanMaintenance->foto_kerusakan) }}" target="_blank" class="group relative block rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 max-w-xs shadow-sm hover:shadow transition">
                                    <img
                                        src="{{ asset('storage/' . $selectedSpk->permintaanMaintenance->foto_kerusakan) }}"
                                        alt="Foto Kerusakan {{ $selectedSpk->permintaanMaintenance->kode }}"
                                        class="max-h-40 w-auto object-cover rounded group-hover:scale-105 transition duration-300"
                                    />
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white font-medium text-[11px] gap-1 transition">
                                        <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                        <span>Buka Foto</span>
                                    </div>
                                </a>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 space-y-1">
                                    <div>Foto kondisi fisik saat dilaporkan oleh unit.</div>
                                    <a href="{{ asset('storage/' . $selectedSpk->permintaanMaintenance->foto_kerusakan) }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                        <flux:icon name="magnifying-glass-plus" class="size-3" />
                                        Lihat resolusi penuh
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>


                {{-- Riwayat Lembar Kerja Pengerjaan --}}
                <div class="space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Catatan Pengerjaan & Penggantian Komponen</div>
                    @forelse ($selectedSpk->logMaintenance as $log)
                        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900 text-xs space-y-2">
                            <div class="flex items-center justify-between pb-1.5 border-b border-zinc-200/60 dark:border-zinc-800">
                                <div>
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">Teknisi: {{ $log->teknisi?->nama }}</span>
                                    <span class="text-zinc-500 dark:text-zinc-400 ml-2">({{ $log->tanggal_pengerjaan?->format('d/m/Y H:i') }})</span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $log->hasil === 'BERHASIL' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                    {{ $log->hasil }}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div><span class="text-zinc-500 dark:text-zinc-400">Diagnosa:</span> {{ $log->diagnosa }}</div>
                                <div><span class="text-zinc-500 dark:text-zinc-400">Tindakan:</span> {{ $log->tindakan }}</div>
                            </div>

                            @if ($log->sukuCadang->isNotEmpty())
                                <div class="pt-2 border-t border-zinc-200/60 dark:border-zinc-800">
                                    <div class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Suku Cadang Digunakan:</div>
                                    <div class="space-y-1 font-mono text-[11px]">
                                        @foreach ($log->sukuCadang as $sc)
                                            <div class="flex items-center justify-between text-zinc-700 dark:text-zinc-300">
                                                <span>&bull; {{ $sc->barang?->nama }} (Batch: {{ $sc->stokBatch?->nomor_batch ?? '-' }})</span>
                                                <span class="font-semibold">{{ $sc->jumlah_digunakan }} {{ $sc->barang?->satuan?->simbol ?? 'Unit' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-zinc-500 dark:text-zinc-400 italic border rounded-lg">
                            Belum ada catatan lembar kerja untuk SPK ini.
                        </div>
                    @endforelse
                </div>

            </div>
        @endif
    </flux:modal>
