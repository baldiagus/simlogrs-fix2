    {{-- Modal Inspeksi & Terima Kembali Aset --}}
    <flux:modal name="modal-inspeksi-pengembalian" wire:model="showPengembalianModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPinjam)
            <div class="space-y-5">
                {{-- Header Modal --}}
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Inspeksi & Terima Kembali Aset</flux:heading>
                            <span class="font-mono text-sm font-bold text-zinc-500 dark:text-zinc-400">#{{ $selectedPinjam->kode }}</span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Pemeriksaan kondisi fisik aset inventaris saat diserahterimakan kembali ke penyedia.
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <flux:badge size="sm" color="amber" inset="top bottom">DIPINJAM</flux:badge>
                    </div>
                </div>

                {{-- Tahap 1: Riwayat Peminjaman & Batas Waktu --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 1: Verifikasi Riwayat & Batas Waktu Peminjaman
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div>
                            <div class="text-zinc-500 dark:text-zinc-400">Unit Peminjam</div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPinjam->unitPeminjam?->nama ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-500 dark:text-zinc-400">Penyedia Aset</div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">
                                @if ($selectedPinjam->tipe_penyedia === 'GUDANG')
                                    {{ $selectedPinjam->gudangPenyedia?->nama ?? '-' }} (Gudang)
                                @else
                                    {{ $selectedPinjam->unitPenyedia?->nama ?? '-' }} (Ruangan)
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-zinc-500 dark:text-zinc-400">Tanggal Pinjam</div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPinjam->tanggal_pinjam?->format('d/m/Y') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-500 dark:text-zinc-400">Batas Kembali</div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPinjam->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                {{-- Tahap 2: Hasil Inspeksi Fisik Aset & Catatan Kerusakan --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                            Tahap 2: Pemeriksaan Fisik & Penetapan Kondisi Kembali
                        </div>
                        <span class="text-xs text-zinc-500 font-medium">Total: {{ count($inspeksiRows) }} unit aset</span>
                    </div>

                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-2xs">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-100 dark:bg-zinc-800/80 font-semibold text-zinc-700 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3.5 py-2.5 w-10">#</th>
                                    <th class="px-3.5 py-2.5">Aset & Register</th>
                                    <th class="px-3.5 py-2.5 w-52">Kondisi Saat Kembali</th>
                                    <th class="px-3.5 py-2.5">Catatan Fisik / Kerusakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                                @foreach ($inspeksiRows as $detailId => $row)
                                    <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                        <td class="px-3.5 py-3 text-zinc-400 font-mono">{{ $loop->iteration }}</td>
                                        <td class="px-3.5 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100 text-sm">{{ $row['nama_aset'] }}</div>
                                            <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">Kode: {{ $row['kode_aset'] }}</div>
                                            <div class="text-[11px] text-zinc-500 mt-0.5">Kondisi Awal: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $row['kondisi_saat_pinjam'] ?? 'BAIK' }}</span></div>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <select
                                                wire:model.live="inspeksiRows.{{ $detailId }}.kondisi_saat_kembali"
                                                class="w-full text-xs font-semibold rounded-lg border px-2.5 py-1.5 transition-colors {{ ($row['kondisi_saat_kembali'] ?? 'BAIK') === 'BAIK' ? 'border-emerald-300 bg-emerald-50/50 text-emerald-800 dark:bg-emerald-950/30 dark:border-emerald-700 dark:text-emerald-300' : (($row['kondisi_saat_kembali'] ?? '') === 'RUSAK_RINGAN' ? 'border-amber-300 bg-amber-50/50 text-amber-800 dark:bg-amber-950/30 dark:border-amber-700 dark:text-amber-300' : 'border-rose-300 bg-rose-50/50 text-rose-800 dark:bg-rose-950/30 dark:border-rose-700 dark:text-rose-300') }}"
                                            >
                                                <option value="BAIK">BAIK (Normal / Siap Pakai)</option>
                                                <option value="RUSAK_RINGAN">RUSAK RINGAN (Tiket IPSRS)</option>
                                                <option value="RUSAK_BERAT">RUSAK BERAT (Tiket CITO)</option>
                                            </select>
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <flux:input
                                                wire:model="inspeksiRows.{{ $detailId }}.catatan"
                                                placeholder="Catatan kondisi fisik / detail kerusakan..."
                                                size="sm"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Alert Box jika terdeteksi aset rusak --}}
                    @php
                        $anyDamaged = collect($inspeksiRows)->contains(fn ($r) => in_array($r['kondisi_saat_kembali'] ?? 'BAIK', ['RUSAK_RINGAN', 'RUSAK_BERAT']));
                    @endphp
                    @if ($anyDamaged)
                        <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
                            <flux:icon name="exclamation-triangle" class="size-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                            <div>
                                <div class="font-bold">Peringatan: Kerusakan Fisik Terdeteksi</div>
                                <div class="text-[11px] mt-0.5 text-amber-700 dark:text-amber-300">
                                    Aset dengan kondisi <strong>RUSAK RINGAN</strong> atau <strong>RUSAK BERAT</strong> akan otomatis dialihkan ke status <strong>DALAM_MAINTENANCE</strong> dan sistem langsung menerbitkan tiket perbaikan ke antrean teknisi IPSRS.
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Catatan Pengembalian Umum --}}
                    <div>
                        <flux:textarea
                            wire:model="catatanUmum"
                            label="Catatan Pengembalian (Opsional)"
                            placeholder="Tambahkan catatan serah terima fisik atau keterangan lainnya..."
                            rows="2"
                        />
                    </div>
                </div>
                {{-- Tombol Batal & Submit --}}
                <div class="flex items-center justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button
                        type="button"
                        variant="primary"
                        class="!bg-emerald-600 hover:!bg-emerald-700 !text-white font-medium shadow-xs"
                        wire:click="confirmSubmitPengembalian"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="confirmSubmitPengembalian">Inspeksi & Terima Kembali</span>
                        <span wire:loading wire:target="confirmSubmitPengembalian">Memvalidasi...</span>
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
