    {{-- Modal Detail PO (Tracking Detail) --}}
    <flux:modal name="modal-tracking-detail" wire:model="showDetailPoModal" class="md:w-[48rem]">
        @if ($selectedPo)
            <div class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Detail PO: {{ $selectedPo->kode }}</flux:heading>
                    <flux:subheading>Informasi lengkap pesanan pembelian dan status pengiriman vendor.</flux:subheading>
                </div>

                {{-- Info Ringkas --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400 font-medium">Vendor</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPo->pemasok?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400 font-medium">Tanggal PO</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPo->tanggal_po?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400 font-medium">Est. Kirim</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPo->estimasi_tanggal_kirim?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400 font-medium">Status PO</div>
                        <div><x-status-badge :status="$selectedPo->status" /></div>
                    </div>
                </div>

                @php
                    $isLate = $selectedPo->estimasi_tanggal_kirim
                        && $selectedPo->estimasi_tanggal_kirim->isPast()
                        && $selectedPo->status !== \App\Enums\StatusPesananPembelian::DiterimaLengkap->value;
                @endphp
                @if ($isLate)
                    <div class="flex items-center gap-2 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
                        <flux:icon.exclamation-triangle class="size-5" />
                        <span class="font-semibold">Pengiriman terlambat: estimasi kirim {{ $selectedPo->estimasi_tanggal_kirim->format('d/m/Y') }} sudah terlewat.</span>
                    </div>
                @endif

                {{-- Daftar Item --}}
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Daftar Item Pesanan</div>
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="py-2.5 px-3 font-semibold">Barang</th>
                                <th class="py-2.5 px-3 font-semibold text-right">Qty Pesan</th>
                                <th class="py-2.5 px-3 font-semibold text-right">Qty Diterima</th>
                                <th class="py-2.5 px-3 font-semibold text-right">Harga Satuan</th>
                                <th class="py-2.5 px-3 font-semibold text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($selectedPo->detail as $detail)
                                <tr>
                                    <td class="py-2 px-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $detail->barang?->kode ?? '' }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono text-xs tabular-nums">{{ number_format($detail->jumlah_dipesan, 0, ',', '.') }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-xs tabular-nums">
                                        @if ($detail->jumlah_diterima >= $detail->jumlah_dipesan)
                                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ number_format($detail->jumlah_diterima, 0, ',', '.') }}</span>
                                        @else
                                            <span class="{{ $detail->jumlah_diterima > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500 dark:text-zinc-400' }}">{{ number_format($detail->jumlah_diterima, 0, ',', '.') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono text-xs tabular-nums">
                                        <x-rupiah :value="$detail->harga_satuan" />
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono text-xs tabular-nums">
                                        <x-rupiah :value="$detail->subtotal" class="font-semibold" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-zinc-500 italic">Tidak ada item dalam PO ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Total Biaya --}}
                <div class="flex justify-end p-3 rounded-lg bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800">
                    <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                        Total: <x-rupiah :value="$selectedPo->total_biaya" class="text-blue-600 dark:text-blue-400" />
                    </div>
                </div>

                {{-- Timeline / Riwayat Penerimaan Barang --}}
                @if ($selectedPo->penerimaanBarang && $selectedPo->penerimaanBarang->isNotEmpty())
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Riwayat Penerimaan Fisik</div>
                    <div class="space-y-2">
                        @foreach ($selectedPo->penerimaanBarang as $penerimaan)
                            <div class="flex items-start gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 text-xs">
                                <flux:icon.inbox-arrow-down class="size-4 text-emerald-500 mt-0.5 shrink-0" />
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $penerimaan->kode ?? 'Penerimaan' }}</div>
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ $penerimaan->tanggal_terima?->format('d/m/Y') ?? '-' }} &bull; Surat Jalan: {{ $penerimaan->nomor_surat_jalan }}</div>
                                    <div class="text-zinc-500 mt-0.5"><x-status-badge :status="$penerimaan->status" /></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @can('create_gudang_stok::penerimaan::po')
                    @if (in_array($selectedPo->status, \App\Enums\StatusPesananPembelian::bisaDiterima()))
                        <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                            <flux:button
                                type="button"
                                variant="primary"
                                icon="inbox-arrow-down"
                                wire:click="terima({{ $selectedPo->id }})"
                                x-on:click="$flux.modal('modal-tracking-detail').close()"
                            >
                                Terima PO Ini
                            </flux:button>
                        </div>
                    @endif
                @endcan
            </div>
        @endif
    </flux:modal>
