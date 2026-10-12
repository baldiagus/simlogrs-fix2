@use('App\Enums\StatusPermintaanDistribusi')
    {{-- Modal Detail Permintaan Amprah Siap Picking --}}
    <flux:modal name="modal-permintaan-picking-detail" wire:model="showPermintaanDetailModal" class="w-full max-w-[92vw] lg:max-w-5xl">
        @if ($selectedPermintaanDetail)
            <div class="space-y-4">
                <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div>
                        <flux:heading size="lg">Detail Rencana Picking: {{ $selectedPermintaanDetail->kode }}</flux:heading>
                        <flux:subheading>
                            Unit Pemohon: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->unit?->nama ?? '-' }}</span> &bull;
                            Gudang Penyedia: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->gudang?->nama ?? '-' }}</span>
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-status-badge :status="$selectedPermintaanDetail->status" />
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg">
                    <div>
                        <span class="text-zinc-500">Tanggal Pengajuan:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->tanggal_permintaan?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Tingkat Urgensi:</span>
                        <div class="mt-0.5"><x-urgency-badge :urgency="$selectedPermintaanDetail->urgensi" /></div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Pemohon / Karu:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->pengaju?->nama ?? '-' }} / {{ $selectedPermintaanDetail->penyetuju?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Total Komoditas:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->detail->count() }} Jenis Barang</div>
                    </div>
                </div>

                @if ($selectedPermintaanDetail->catatan)
                    <div class="text-xs text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800/80 p-2.5 rounded-md">
                        <span class="font-semibold">Catatan Pemohon:</span> {{ $selectedPermintaanDetail->catatan }}
                    </div>
                @endif

                {{-- Tabel Rincian Komoditas yang Mau di-Picking --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-xs">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700 text-left font-semibold">
                            <tr>
                                <th class="py-2.5 px-3 w-10 text-center">No</th>
                                <th class="py-2.5 px-3">Nama Komoditas</th>
                                <th class="py-2.5 px-3 text-center">Satuan</th>
                                <th class="py-2.5 px-3 text-right">Diminta</th>
                                <th class="py-2.5 px-3 text-right">Disetujui</th>
                                <th class="py-2.5 px-3 text-right">Terkirim</th>
                                <th class="py-2.5 px-3 text-right">Sisa Mau Dipicking</th>
                                <th class="py-2.5 px-3 text-right">Stok Fisik Bebas</th>
                                <th class="py-2.5 px-3">Catatan Item</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($selectedPermintaanDetail->detail as $idx => $det)
                                @php
                                    $info = $ringkasanPermintaanDetail[$det->id] ?? [
                                        'diminta' => (int) $det->jumlah,
                                        'disetujui' => (int) ($det->jumlah_disetujui ?: $det->jumlah),
                                        'terkirim' => 0,
                                        'sisa_picking' => (int) ($det->jumlah_disetujui ?: $det->jumlah),
                                        'stok_gudang' => 0,
                                    ];
                                @endphp
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2.5 px-3 text-center font-mono">{{ $idx + 1 }}</td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $det->barang?->nama ?? '-' }}</div>
                                        <div class="text-[11px] font-mono text-zinc-400">{{ $det->barang?->kode ?? '-' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-zinc-600 dark:text-zinc-400">{{ $det->barang?->satuan?->simbol ?? $det->barang?->satuan?->nama ?? 'Unit' }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono">{{ number_format($info['diminta'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-medium">{{ number_format($info['disetujui'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono text-zinc-500">{{ number_format($info['terkirim'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold {{ $info['sisa_picking'] > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-400' }}">
                                        {{ number_format($info['sisa_picking'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono">
                                        @if ($info['stok_gudang'] <= 0)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                                0 (Kosong)
                                            </span>
                                        @elseif ($info['stok_gudang'] < $info['sisa_picking'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                {{ number_format($info['stok_gudang'], 0, ',', '.') }} (Kurang)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                {{ number_format($info['stok_gudang'], 0, ',', '.') }} (Siap)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-zinc-500 text-[11px]">{{ $det->catatan ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Footer Modal: Tombol Aksi --}}
                <div class="flex items-center justify-between pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="closePermintaanDetailModal">
                        Tutup
                    </flux:button>
                    @canany(['create_gudang_stok::picking::distribusi'])
                        <flux:button
                            type="button"
                            variant="primary"
                            icon="cube"
                            wire:click="confirmPickingFromDetail"
                            class="bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold"
                        >
                            {{ $selectedPermintaanDetail->status === StatusPermintaanDistribusi::DikirimSebagian->value ? 'Lanjutkan Picking Sisa Hutang' : 'Lanjutkan Eksekusi Picking FEFO' }}
                        </flux:button>
                    @endcanany
                </div>
            </div>
        @endif
    </flux:modal>
