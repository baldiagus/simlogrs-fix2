    {{-- Modal Detail Peminjaman --}}
    <flux:modal name="modal-detail-pinjam" wire:model="showDetailModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPinjam)
            <div class="space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-700">
                    <div>
                        <flux:heading size="lg">Detail Peminjaman: {{ $selectedPinjam->kode }}</flux:heading>
                        <flux:subheading>Informasi lengkap sirkulasi aset, penanggung jawab, dan catatan operasional.</flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-status-badge :status="$selectedPinjam->status" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Tipe & Lokasi Penyedia:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            @if ($selectedPinjam->tipe_penyedia === 'RUANGAN')
                                Ruangan: {{ $selectedPinjam->unitPenyedia?->nama ?? '-' }}
                            @else
                                Gudang: {{ $selectedPinjam->gudangPenyedia?->nama ?? '-' }}
                            @endif
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Unit Peminjam:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->unitPeminjam?->nama ?? '-' }}
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Diajukan Oleh:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->pengaju?->nama ?? '-' }}
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Tanggal Pinjam:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5 tabular-nums">
                            {{ $selectedPinjam->tanggal_pinjam?->format('d/m/Y') ?? '-' }}
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Rencana Kembali:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5 tabular-nums">
                            {{ $selectedPinjam->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Disetujui Oleh:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->penyetuju?->nama ?? 'Menunggu Otorisasi' }}
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-xs space-y-1">
                    <div class="text-zinc-500 font-medium">Keperluan:</div>
                    <div class="text-zinc-800 dark:text-zinc-200 whitespace-pre-line">{{ $selectedPinjam->keperluan ?? '-' }}</div>
                </div>

                @if ($selectedPinjam->catatan)
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-xs space-y-1">
                        <div class="text-zinc-500 font-medium">Catatan & Riwayat Perpanjangan:</div>
                        <div class="text-zinc-800 dark:text-zinc-200 whitespace-pre-line font-mono text-[11px]">{{ $selectedPinjam->catatan }}</div>
                    </div>
                @endif

                {{-- Tabel Rincian Aset --}}
                <div>
                    <div class="font-semibold text-xs text-zinc-700 dark:text-zinc-300 mb-2">Daftar Aset Inventaris yang Dipinjam</div>
                    <flux:table bleed>
                        <flux:table.columns>
                            <flux:table.column class="w-32">Kode Register</flux:table.column>
                            <flux:table.column>Nama Komoditas</flux:table.column>
                            <flux:table.column class="text-center w-28">Kondisi Pinjam</flux:table.column>
                            <flux:table.column>Catatan</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($selectedPinjam->detail as $detail)
                                <flux:table.row :key="$detail->id">
                                    <flux:table.cell class="font-mono font-bold text-xs">
                                        {{ $detail->aset?->kode_aset ?? '-' }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="font-medium text-xs">{{ $detail->aset?->barang?->nama ?? '-' }}</div>
                                        @if ($detail->aset?->nomor_seri)
                                            <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">SN: {{ $detail->aset->nomor_seri }}</div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-center">
                                        <flux:badge size="sm" color="emerald">{{ $detail->kondisi_saat_pinjam }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-xs text-zinc-500">
                                        {{ $detail->catatan ?? '-' }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                {{-- Riwayat Pengembalian Fisik Jika Ada --}}
                @if ($selectedPinjam->pengembalian->isNotEmpty())
                    <div>
                        <div class="font-semibold text-xs text-zinc-700 dark:text-zinc-300 mb-2">Riwayat Pengembalian & Inspeksi Fisik</div>
                        @foreach ($selectedPinjam->pengembalian as $rtn)
                            <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30 text-xs space-y-2 mb-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono font-bold">{{ $rtn->kode }}</span>
                                    <span class="text-zinc-500">Kembali aktual: {{ $rtn->tanggal_kembali_aktual?->format('d/m/Y') }} (Pemeriksa: {{ $rtn->pemeriksa?->nama ?? '-' }})</span>
                                </div>
                                @if ($rtn->catatan)
                                    <p class="text-zinc-600 dark:text-zinc-400 text-[11px]">{{ $rtn->catatan }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @php
                    $canSerahTerimaModal = $izinSelected['serah_terima'] ?? false;
                @endphp

                @if ($selectedPinjam->status === \App\Enums\StatusPeminjaman::Disetujui->value && $canSerahTerimaModal)
                    <div class="flex items-center justify-end pt-3 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:button
                            wire:click="confirmSerahTerima({{ $selectedPinjam->id }})"
                            variant="primary"
                            icon="arrow-right-start-on-rectangle"
                            wire:loading.attr="disabled"
                        >
                            Serah Terima Fisik
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
