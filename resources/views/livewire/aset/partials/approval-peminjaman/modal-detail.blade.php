    {{-- Modal detail peminjaman aset --}}
    <flux:modal wire:model="showDetailModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPinjam)
            <div class="space-y-6">
                <div>
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">Rincian Permintaan Peminjaman</flux:heading>
                        <div style="margin-right: 2.25rem;">
                            <x-ui.status-badge :status="$selectedPinjam->status" />
                        </div>
                    </div>
                    <flux:subheading class="mt-1 font-mono text-xs text-zinc-500">
                        {{ $selectedPinjam->kode }}
                    </flux:subheading>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Peminjam</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->unitPeminjam?->nama ?? '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Penyedia Aset</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            @if ($selectedPinjam->tipe_penyedia === 'GUDANG')
                                Gudang: {{ $selectedPinjam->gudangPenyedia?->nama ?? '-' }}
                            @else
                                Ruangan: {{ $selectedPinjam->unitPenyedia?->nama ?? '-' }}
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Diajukan Oleh</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->pengaju?->nama ?? '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Tanggal Pinjam</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->tanggal_pinjam?->format('d/m/Y') ?? '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Rencana Kembali</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Diverifikasi Oleh</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->penyetuju?->nama ?? '-' }}
                        </div>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <div class="text-zinc-500 dark:text-zinc-400">Keperluan</div>
                        <div class="text-zinc-800 dark:text-zinc-200 mt-0.5">
                            {{ $selectedPinjam->keperluan ?: '-' }}
                        </div>
                    </div>

                    @if ($selectedPinjam->catatan)
                        <div class="sm:col-span-2 lg:col-span-3">
                            <div class="text-zinc-500 dark:text-zinc-400">Catatan Tambahan</div>
                            <div class="text-zinc-800 dark:text-zinc-200 mt-0.5">
                                {{ $selectedPinjam->catatan }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Tabel daftar aset yang dipinjam --}}
                <div class="space-y-2">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Daftar Aset yang Diminta
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800 text-left text-xs">
                            <thead class="bg-zinc-50 dark:bg-zinc-900 text-zinc-500">
                                <tr>
                                    <th class="px-3 py-2.5 font-medium">No</th>
                                    <th class="px-3 py-2.5 font-medium">Kode Register Aset</th>
                                    <th class="px-3 py-2.5 font-medium">Nama Barang / Aset</th>
                                    <th class="px-3 py-2.5 font-medium">Nomor Seri</th>
                                    <th class="px-3 py-2.5 font-medium">Kondisi Saat Pinjam</th>
                                    <th class="px-3 py-2.5 font-medium">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-950">
                                @forelse ($selectedPinjam->detail as $idx => $detail)
                                    <tr>
                                        <td class="px-3 py-2.5 text-zinc-400">{{ $idx + 1 }}</td>
                                        <td class="px-3 py-2.5 font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $detail->aset?->kode_aset ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2.5 text-zinc-800 dark:text-zinc-200 font-medium">
                                            {{ $detail->aset?->barang?->nama ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-zinc-500 dark:text-zinc-400">
                                            {{ $detail->aset?->nomor_seri ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                                {{ $detail->kondisi_saat_pinjam ?? $detail->aset?->kondisi ?? 'BAIK' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->catatan ?: '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-4 text-center text-zinc-400">
                                            Tidak ada aset terdaftar pada permintaan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @php
                    $canApproveSelected = $izinSelected['approve'] ?? false;
                    $canHandoverSelected = $izinSelected['serah_terima'] ?? false;
                @endphp

                @if ($canApproveSelected || $canHandoverSelected)
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        @if ($selectedPinjam->status === \App\Enums\Aset\StatusPeminjaman::Diajukan->value && $canApproveSelected)
                            <flux:button
                                wire:click="confirmReject({{ $selectedPinjam->id }})"
                                variant="danger"
                                icon="x-circle"
                                wire:loading.attr="disabled"
                            >
                                Tolak Permintaan
                            </flux:button>
                            <flux:button
                                wire:click="confirmApprove({{ $selectedPinjam->id }})"
                                variant="primary"
                                icon="check-circle"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white"
                                wire:loading.attr="disabled"
                            >
                                Setujui Permintaan
                            </flux:button>
                        @elseif ($selectedPinjam->status === \App\Enums\Aset\StatusPeminjaman::Disetujui->value && $canHandoverSelected)
                            <flux:button
                                wire:click="confirmSerahTerima({{ $selectedPinjam->id }})"
                                variant="primary"
                                icon="arrow-right-start-on-rectangle"
                                class="bg-blue-600 hover:bg-blue-700 text-white"
                                wire:loading.attr="disabled"
                            >
                                Serah Terima Fisik
                            </flux:button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
