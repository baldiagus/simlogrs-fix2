@use('App\Enums\StatusPermintaanDistribusi')
@use('App\Enums\StatusPengirimanBarang')
    {{-- Modal Detail Permintaan Distribusi --}}
    <flux:modal name="modal-detail-distribusi" wire:model="showDetailModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPermintaan)
            <div class="space-y-5">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Detail Permintaan Distribusi</flux:heading>
                    <flux:subheading class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        Informasi lengkap permohonan amprah barang ruangan.
                    </flux:subheading>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Kode Amprah</span>
                        <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->kode }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Unit Pemohon</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->unit?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Gudang Penyedia</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->gudang?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Tanggal Permintaan</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->tanggal_permintaan?->format('d/m/Y') }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Diajukan Oleh</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->pengaju?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Otorisator</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->penyetuju?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Tingkat Urgensi</span>
                        <div class="mt-0.5">
                            <x-urgency-badge :urgency="$selectedPermintaan->urgensi" />
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Status Terakhir</span>
                        <div class="mt-0.5">
                            <x-status-badge :status="$selectedPermintaan->status" />
                        </div>
                    </div>
                </div>

                @if ($selectedPermintaan->catatan)
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 rounded-lg text-xs">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Catatan:</span>
                        <span class="text-zinc-600 dark:text-zinc-400 ml-1">{{ $selectedPermintaan->catatan }}</span>
                    </div>
                @endif

                @if ($selectedPermintaan->status === StatusPermintaanDistribusi::DikirimSebagian->value)
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-200 flex items-center gap-2">
                        <flux:icon name="clock" class="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <span>Permintaan ini telah dikirim sebagian. Sisa barang yang terhutang akan otomatis diprioritaskan saat gudang menerima restok barang dari vendor.</span>
                    </div>
                @endif

                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 font-semibold">
                                <th class="py-2.5 px-3">Nama Barang</th>
                                <th class="py-2.5 px-3 text-center">Satuan</th>
                                <th class="py-2.5 px-3 text-right">Diminta</th>
                                <th class="py-2.5 px-3 text-right">Disetujui</th>
                                <th class="py-2.5 px-3 text-right">Terkirim</th>
                                <th class="py-2.5 px-3 text-right">Sisa Hutang</th>
                                <th class="py-2.5 px-3">Catatan Item</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @php
                                $shipmentDetails = $selectedPermintaan->pengiriman
                                    ? $selectedPermintaan->pengiriman->where('status', '!=', StatusPengirimanBarang::Batal->value)->flatMap->detail
                                    : collect();
                            @endphp
                            @foreach ($selectedPermintaan->detail as $detail)
                                @php
                                    $terkirim = (int) $shipmentDetails->where('barang_id', $detail->barang_id)->sum('jumlah_dikirim');
                                    $disetujui = (int) ($detail->jumlah_disetujui !== null ? $detail->jumlah_disetujui : $detail->jumlah);
                                    $sisaHutang = max(0, $disetujui - $terkirim);
                                @endphp
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                        @if ($detail->barang?->kode)
                                            <div class="text-[11px] font-mono text-zinc-500">{{ $detail->barang->kode }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-zinc-600 dark:text-zinc-400">
                                        {{ $detail->barang?->satuan?->nama ?? '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($detail->jumlah, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold">
                                        @if ($detail->jumlah_disetujui !== null)
                                            <span class="text-blue-600 dark:text-blue-400">{{ number_format($detail->jumlah_disetujui, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-zinc-500 dark:text-zinc-400 italic">-</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono tabular-nums text-zinc-700 dark:text-zinc-300">
                                        {{ number_format($terkirim, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono tabular-nums">
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
                                    <td class="py-2.5 px-3 text-zinc-600 dark:text-zinc-400">
                                        {{ $detail->catatan ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        @endif
    </flux:modal>
