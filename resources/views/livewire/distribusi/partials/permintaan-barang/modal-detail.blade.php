@use('App\Enums\Distribusi\StatusPermintaanDistribusi')
@use('App\Enums\Distribusi\StatusPengirimanBarang')
    {{-- Modal Detail Permintaan Distribusi --}}
    <flux:modal name="modal-permintaan-detail" wire:model="showDetailModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPermintaan)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Permintaan Amprah</flux:heading>
                            <span class="font-mono text-sm font-bold text-zinc-500 dark:text-zinc-400">#{{ $selectedPermintaan->kode }}</span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal: {{ $selectedPermintaan->tanggal_permintaan?->format('d F Y') }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2" style="margin-right: 2.25rem;">
                        <x-ui.urgency-badge :urgency="$selectedPermintaan->urgensi" />
                        <x-ui.status-badge :status="$selectedPermintaan->status" />
                    </div>
                </div>

                {{-- Informasi Metadata --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Pemohon</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->unit?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Gudang Tujuan</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Diajukan Oleh</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->pengaju?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Disetujui Oleh</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->penyetuju?->nama ?? 'Belum diproses' }}</div>
                    </div>
                </div>

                @if ($selectedPermintaan->catatan)
                    <div>
                        <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Catatan Permintaan</div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg">
                            {{ $selectedPermintaan->catatan }}
                        </div>
                    </div>
                @endif

                @if ($selectedPermintaan->status === StatusPermintaanDistribusi::DikirimSebagian->value)
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-200 flex items-center gap-2">
                        <flux:icon name="clock" class="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <span>Permintaan ini telah dikirim sebagian. Sisa barang yang terhutang akan otomatis diprioritaskan saat gudang menerima restok barang dari vendor.</span>
                    </div>
                @endif

                {{-- Tabel Detail Barang Diminta, Disetujui, Terkirim & Sisa Hutang --}}
                <div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Daftar Komoditas / Barang</div>
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                                <tr>
                                    <th class="text-left px-3 py-2 w-10">#</th>
                                    <th class="text-left px-3 py-2">Barang</th>
                                    <th class="text-left px-3 py-2 w-20">Satuan</th>
                                    <th class="text-right px-3 py-2 w-20">Diminta</th>
                                    <th class="text-right px-3 py-2 w-20">Disetujui</th>
                                    <th class="text-right px-3 py-2 w-20">Terkirim</th>
                                    <th class="text-right px-3 py-2 w-28">Sisa Hutang</th>
                                    <th class="text-left px-3 py-2">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @php
                                    $shipmentDetails = $selectedPermintaan->pengiriman
                                        ? $selectedPermintaan->pengiriman->where('status', '!=', StatusPengirimanBarang::Batal->value)->flatMap->detail
                                        : collect();
                                @endphp
                                @forelse ($selectedPermintaan->detail as $detail)
                                    @php
                                        $terkirim = (int) $shipmentDetails->where('barang_id', $detail->barang_id)->sum('jumlah_dikirim');
                                        $disetujui = (int) ($detail->jumlah_disetujui !== null ? $detail->jumlah_disetujui : $detail->jumlah);
                                        $sisaHutang = max(0, $disetujui - $terkirim);
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 text-zinc-500 text-xs">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $detail->barang?->kode ?? '' }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->barang?->satuan?->simbol ?? $detail->barang?->satuan?->nama ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums text-zinc-900 dark:text-zinc-100 font-medium">
                                            {{ number_format($detail->jumlah, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                                            @if ($detail->jumlah_disetujui !== null)
                                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                                    {{ number_format($detail->jumlah_disetujui, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-zinc-500 dark:text-zinc-400 italic text-xs font-sans">Belum diproses</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums text-zinc-700 dark:text-zinc-300">
                                            {{ number_format($terkirim, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                                            @if ($selectedPermintaan->status === StatusPermintaanDistribusi::Diajukan->value || $detail->jumlah_disetujui === null)
                                                <span class="text-zinc-400 italic text-xs font-sans">-</span>
                                            @elseif ($sisaHutang > 0)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                    {{ number_format($sisaHutang, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">0 (Lengkap)</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->catatan ?: '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-3 py-4 text-center text-zinc-500 dark:text-zinc-400 text-xs italic">
                                            Tidak ada item barang dalam permintaan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @endif
    </flux:modal>
