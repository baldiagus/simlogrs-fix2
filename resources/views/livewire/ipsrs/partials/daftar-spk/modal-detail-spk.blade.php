    {{-- Modal Detail SPK --}}
    <flux:modal name="modal-detail-spk" wire:model="showDetailModal" class="md:w-[48rem]">
        @if ($selectedSpk)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail SPK: {{ $selectedSpk->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal Tugas: {{ $selectedSpk->tanggal_penugasan?->format('d F Y') }}
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-ui.status-badge :status="$selectedSpk->status" />
                    </div>
                </div>

                {{-- Metadata SPK --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Teknisi Ditugaskan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedSpk->teknisi?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Pemberi Tugas</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedSpk->penugast?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Sarana / Fasilitas</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedSpk->permintaanMaintenance?->aset?->barang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Ruangan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedSpk->permintaanMaintenance?->unit?->nama ?? '-' }}</div>
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
                                <a href="{{ asset('storage/' . ltrim($selectedSpk->permintaanMaintenance->foto_kerusakan, '/')) }}" target="_blank" class="group relative block rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 max-w-xs shadow-sm hover:shadow transition">
                                    <img
                                        src="{{ asset('storage/' . ltrim($selectedSpk->permintaanMaintenance->foto_kerusakan, '/')) }}"
                                        onerror="this.onerror=null; this.src='/storage/{{ ltrim($selectedSpk->permintaanMaintenance->foto_kerusakan, '/') }}';"
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


                @if ($selectedSpk->instruksi_khusus)
                    <div class="p-3 rounded-lg bg-blue-50/50 dark:bg-blue-950/30 border border-blue-200/60 dark:border-blue-800/40 text-xs space-y-1">
                        <div class="font-semibold text-blue-900 dark:text-blue-300">Instruksi Khusus Koordinator:</div>
                        <div class="text-blue-800 dark:text-blue-200">{{ $selectedSpk->instruksi_khusus }}</div>
                    </div>
                @endif

                <div class="flex items-center justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button
                        as="a"
                        href="/{{ \App\Support\Portal\PortalResolver::resolve() }}/spk/{{ $selectedSpk->id }}/cetak-pdf"
                        target="_blank"
                        variant="primary"
                        icon="printer"
                    >
                        Cetak PDF SPK
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
