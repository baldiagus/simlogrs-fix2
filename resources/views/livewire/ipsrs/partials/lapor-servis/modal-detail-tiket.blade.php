    {{-- Modal Detail Tiket Kerusakan --}}
    <flux:modal name="modal-detail-tiket" wire:model="showDetailModal" class="md:w-[48rem]">
        @if ($selectedTiket)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Tiket Servis: {{ $selectedTiket->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal Lapor: {{ $selectedTiket->dibuat_pada?->format('d F Y H:i') }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2" style="margin-right: 2.25rem;">
                        @php
                            $prioCol = match($selectedTiket->prioritas) {
                                'CITO' => 'rose',
                                'TINGGI' => 'amber',
                                'SEDANG' => 'blue',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$prioCol">{{ $selectedTiket->prioritas }}</flux:badge>
                        <x-status-badge :status="$selectedTiket->status" />
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

                {{-- Deskripsi Gejala Kerusakan --}}
                <div class="p-3.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 text-xs space-y-1">
                    <div class="font-semibold text-zinc-700 dark:text-zinc-300">Deskripsi Keluhan / Gejala Kerusakan:</div>
                    <div class="text-zinc-800 dark:text-zinc-200 whitespace-pre-line">{{ $selectedTiket->deskripsi_masalah }}</div>
                </div>
                {{-- Foto Bukti Kerusakan --}}
                <div class="p-3.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 text-xs space-y-2">
                    <div class="font-semibold text-zinc-700 dark:text-zinc-300">Foto Bukti Kerusakan Fisik:</div>
                    @if ($selectedTiket->foto_kerusakan)
                        <div class="flex flex-col sm:flex-row items-start gap-3">
                            <a href="{{ asset('storage/' . $selectedTiket->foto_kerusakan) }}" target="_blank" class="group relative block rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700 max-w-xs shadow-sm hover:shadow transition">
                                <img
                                    src="{{ asset('storage/' . ltrim($selectedTiket->foto_kerusakan, '/')) }}"
                                    onerror="this.onerror=null; this.src='/storage/{{ ltrim($selectedTiket->foto_kerusakan, '/') }}';"
                                    alt="Foto Bukti Kerusakan {{ $selectedTiket->kode }}"
                                    class="max-h-48 w-auto object-cover rounded-lg group-hover:scale-105 transition duration-300"
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white font-medium text-xs gap-1.5 transition">
                                    <flux:icon name="arrow-top-right-on-square" class="size-4" />
                                    <span>Buka Ukuran Penuh</span>
                                </div>
                            </a>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 space-y-1">
                                <div>Foto kerusakan telah dikompresi untuk efisiensi penyimpanan.</div>
                                <a href="{{ asset('storage/' . $selectedTiket->foto_kerusakan) }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                    <flux:icon name="magnifying-glass-plus" class="size-3.5" />
                                    Lihat resolusi penuh
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="text-zinc-400 italic">Tidak ada foto bukti kerusakan yang dilampirkan oleh pelapor.</div>
                    @endif
                </div>


                {{-- Riwayat Disposisi SPK --}}
                @if ($selectedTiket->penugasan->isNotEmpty())
                    <div class="space-y-2">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Surat Perintah Kerja (SPK) Ditugaskan</div>
                        @foreach ($selectedTiket->penugasan as $spk)
                            <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 text-xs flex items-center justify-between">
                                <div>
                                    <div class="font-mono font-semibold">{{ $spk->kode }}</div>
                                    <div class="text-zinc-500 dark:text-zinc-400 mt-0.5">
                                        Teknisi: <strong>{{ $spk->teknisi?->nama ?? '-' }}</strong> &bull; Ditugaskan: {{ $spk->tanggal_penugasan?->format('d/m/Y') }}
                                    </div>
                                    @if ($spk->instruksi_khusus)
                                        <div class="text-zinc-600 dark:text-zinc-300 mt-1 italic">{{ $spk->instruksi_khusus }}</div>
                                    @endif
                                </div>
                                <x-status-badge :status="$spk->status" />
                            </div>
                        @endforeach
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

            </div>
        @endif
    </flux:modal>
