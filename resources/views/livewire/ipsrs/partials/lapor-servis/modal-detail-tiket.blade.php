    {{-- Modal Detail Tiket Kerusakan --}}
    <flux:modal name="modal-detail-tiket" wire:model="showDetailModal" :dismissible="false" :closable="false" class="w-full max-w-4xl">
        @if ($selectedTiket)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Tiket Servis: {{ $selectedTiket->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal Lapor: {{ $selectedTiket->dibuat_pada?->format('d F Y H:i') }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2">
                        @php
                            $prioCol = match($selectedTiket->prioritas) {
                                'CITO' => 'rose',
                                'TINGGI' => 'amber',
                                'SEDANG' => 'blue',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$prioCol">{{ $selectedTiket->prioritas }}</flux:badge>
                        <x-ui.status-badge :status="$selectedTiket->status" />
                    </div>
                </div>

                {{-- Informasi Aset & Pelapor --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nama Fasilitas / Alat</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiket->aset?->barang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Kode Register Aset</div>
                        <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiket->aset?->kode_aset ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Ruangan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiket->unit?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Pelapor</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiket->pelapor?->nama ?? '-' }}</div>
                    </div>
                </div>

                {{-- Grid 2 Kolom: Deskripsi Keluhan & Foto Bukti Fisik --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 items-start">
                    {{-- Kolom Kiri: Deskripsi Gejala Kerusakan --}}
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs space-y-1.5 border border-zinc-200/80 dark:border-zinc-700/60 h-full">
                        <div class="font-semibold text-zinc-700 dark:text-zinc-300">Deskripsi Keluhan / Gejala Kerusakan:</div>
                        <div class="text-zinc-800 dark:text-zinc-200 whitespace-pre-line leading-relaxed">{{ $selectedTiket->deskripsi_masalah }}</div>
                    </div>

                    {{-- Kolom Kanan: Foto Bukti Kerusakan --}}
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs space-y-2 border border-zinc-200/80 dark:border-zinc-700/60">
                        <div class="font-semibold text-zinc-700 dark:text-zinc-300">Foto Bukti Kerusakan Fisik:</div>
                        @if ($selectedTiket->foto_kerusakan)
                            <div class="flex flex-col sm:flex-row items-start gap-3">
                                <a href="{{ asset('storage/' . $selectedTiket->foto_kerusakan) }}" target="_blank" class="group relative block rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700 max-w-xs shadow-sm hover:shadow transition">
                                    <img
                                        src="{{ asset('storage/' . ltrim($selectedTiket->foto_kerusakan, '/')) }}"
                                        onerror="this.onerror=null; this.src='/storage/{{ ltrim($selectedTiket->foto_kerusakan, '/') }}';"
                                        alt="Foto Bukti Kerusakan {{ $selectedTiket->kode }}"
                                        class="max-h-36 w-auto object-cover rounded-lg group-hover:scale-105 transition duration-300"
                                    />
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white font-medium text-xs gap-1.5 transition">
                                        <flux:icon name="arrow-top-right-on-square" class="size-4" />
                                        <span>Buka Penuh</span>
                                    </div>
                                </a>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 space-y-1">
                                    <div>Foto terkompresi otomatis.</div>
                                    <a href="{{ asset('storage/' . $selectedTiket->foto_kerusakan) }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                        <flux:icon name="magnifying-glass-plus" class="size-3.5" />
                                        Resolusi penuh
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="text-zinc-400 italic py-2">Tidak ada foto bukti kerusakan yang dilampirkan oleh pelapor.</div>
                        @endif
                    </div>
                </div>


                {{-- Riwayat Disposisi SPK (Scroll terisolasi HANYA jika banyak SPK) --}}
                @if ($selectedTiket->penugasan->isNotEmpty())
                    <div class="space-y-2">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 flex items-center justify-between">
                            <span>Surat Perintah Kerja (SPK) Ditugaskan</span>
                            <span class="text-[11px] font-normal text-zinc-400">{{ $selectedTiket->penugasan->count() }} Penugasan</span>
                        </div>
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            @foreach ($selectedTiket->penugasan as $spk)
                                <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 text-xs flex items-center justify-between bg-white dark:bg-zinc-900">
                                    <div>
                                        <div class="font-mono font-semibold">{{ $spk->kode }}</div>
                                        <div class="text-zinc-500 dark:text-zinc-400 mt-0.5">
                                            Teknisi: <strong>{{ $spk->teknisi?->nama ?? '-' }}</strong> &bull; Ditugaskan: {{ $spk->tanggal_penugasan?->format('d/m/Y') }}
                                        </div>
                                        @if ($spk->instruksi_khusus)
                                            <div class="text-zinc-600 dark:text-zinc-300 mt-1 italic">{{ $spk->instruksi_khusus }}</div>
                                        @endif
                                    </div>
                                    <x-ui.status-badge :status="$spk->status" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Riwayat Lembar Kerja Teknisi --}}
                @if ($selectedTiket->logMaintenance->isNotEmpty())
                    <div class="space-y-2">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Catatan Pengerjaan & Tindakan Teknisi</div>
                        @foreach ($selectedTiket->logMaintenance as $log)
                            <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900 text-xs space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">Teknisi: {{ $log->teknisi?->nama }} ({{ $log->tanggal_pengerjaan?->format('d/m/Y H:i') }})</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $log->hasil === 'BERHASIL' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($log->hasil === 'PENDING' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300') }}">
                                        {{ $log->hasil }}
                                    </span>
                                </div>
                                @if ($log->diagnosa)
                                    <div><span class="text-zinc-500">Diagnosa:</span> {{ $log->diagnosa }}</div>
                                @endif
                                <div><span class="text-zinc-500">Tindakan:</span> {{ $log->tindakan }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
                {{-- Footer Tombol Tutup --}}
                <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">
                        Tutup Lembar Rincian
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
