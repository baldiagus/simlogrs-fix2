<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Surat Perintah Kerja (SPK) Perbaikan</flux:heading>
                <flux:subheading>Disposisi penugasan teknisi IPSRS, penerbitan dokumen resmi SPK, dan pemantauan perbaikan fisik sarana.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Tab --}}
        <flux:tabs class="pt-2">
            <flux:tab
                wire:click="setTab('antrean')"
                :current="$activeTab === 'antrean'"
                icon="inbox-arrow-down"
                :badge="$countAntrean"
            >
                Antrean Tiket Masuk
            </flux:tab>
            <flux:tab
                wire:click="setTab('spk')"
                :current="$activeTab === 'spk'"
                icon="document-check"
                :badge="$countSpkAktif"
            >
                Daftar SPK Terbit & Riwayat
            </flux:tab>
        </flux:tabs>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ $activeTab === 'antrean' ? 'Cari kode tiket, nama alat, keluhan...' : 'Cari kode SPK, teknisi, kode aset...' }}"
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            @if ($activeTab === 'spk')
                <div class="w-full sm:w-48">
                    <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Status SPK</option>
                        <option value="DITUGASKAN">DITUGASKAN</option>
                        <option value="DITERIMA">DITERIMA</option>
                        <option value="SELESAI">SELESAI</option>
                        <option value="DIBATALKAN">DIBATALKAN</option>
                    </select>
                </div>
            @endif
        </div>

        {{-- TAB 1: Antrean Tiket Masuk Menunggu SPK --}}
        @if ($activeTab === 'antrean')
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column class="w-36">No. Tiket</flux:table.column>
                    <flux:table.column>Sarana / Alat Rusak</flux:table.column>
                    <flux:table.column>Unit Pelapor</flux:table.column>
                    <flux:table.column class="text-center w-28">Urgensi</flux:table.column>
                    <flux:table.column>Keluhan Ruangan</flux:table.column>
                    <flux:table.column class="text-right w-40">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarData as $tiket)
                        @php
                            $prioCol = match($tiket->prioritas) {
                                'CITO' => 'rose',
                                'TINGGI' => 'amber',
                                'SEDANG' => 'blue',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:table.row :key="$tiket->id">
                            {{-- Kode Tiket --}}
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                <div>{{ $tiket->kode }}</div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-sans mt-0.5">
                                    {{ $tiket->dibuat_pada?->format('d/m/Y H:i') }}
                                </div>
                            </flux:table.cell>

                            {{-- Sarana / Alat --}}
                            <flux:table.cell>
                                <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                    {{ $tiket->aset?->barang?->nama ?? '-' }}
                                </div>
                                <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Kode: {{ $tiket->aset?->kode_aset ?? '-' }}
                                    @if ($tiket->aset?->nomor_seri)
                                        &bull; SN: {{ $tiket->aset->nomor_seri }}
                                    @endif
                                </div>
                            </flux:table.cell>

                            {{-- Unit Pelapor --}}
                            <flux:table.cell>
                                <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ $tiket->unit?->nama ?? '-' }}
                                </div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                    Pelapor: {{ $tiket->pelapor?->nama ?? '-' }}
                                </div>
                            </flux:table.cell>

                            {{-- Urgensi / Prioritas --}}
                            <flux:table.cell class="text-center">
                                <flux:badge size="sm" :color="$prioCol">{{ $tiket->prioritas }}</flux:badge>
                            </flux:table.cell>

                            {{-- Keluhan --}}
                            <flux:table.cell>
                                <div class="text-xs text-zinc-700 dark:text-zinc-300 line-clamp-2 max-w-xs">
                                    {{ $tiket->deskripsi_masalah }}
                                </div>
                            </flux:table.cell>

                            {{-- Aksi: Terbitkan SPK --}}
                            <flux:table.cell class="text-right">
                                @can('create_pemeliharaan::spk::perbaikan')
                                    <flux:button
                                        wire:click="openTerbitkanSpk({{ $tiket->id }})"
                                        size="xs"
                                        variant="primary"
                                        icon="clipboard-document-check"
                                    >
                                        Disposisi SPK
                                    </flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Tidak ada antrean tiket kerusakan baru yang membutuhkan disposisi SPK." cols="6" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            {{-- TAB 2: Daftar SPK Terbit & Riwayat --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">No. SPK</flux:table.column>
                    <flux:table.column>Ref Tiket & Alat</flux:table.column>
                    <flux:table.column>Teknisi Pelaksana</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_penugasan'" :direction="$sortDirection" wire:click="sort('tanggal_penugasan')" class="w-28">Tgl Tugas</flux:table.column>
                    <flux:table.column class="w-28">Target</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-32">Status</flux:table.column>
                    <flux:table.column class="text-right w-44">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarData as $spk)
                        <flux:table.row :key="$spk->id">
                            {{-- Kode SPK --}}
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                <div>{{ $spk->kode }}</div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-sans mt-0.5">
                                    Oleh: {{ $spk->penugast?->nama ?? '-' }}
                                </div>
                            </flux:table.cell>

                            {{-- Ref Tiket & Alat --}}
                            <flux:table.cell>
                                <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                    {{ $spk->permintaanMaintenance?->aset?->barang?->nama ?? '-' }}
                                </div>
                                <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Ref: {{ $spk->permintaanMaintenance?->kode ?? '-' }} &bull; {{ $spk->permintaanMaintenance?->unit?->nama ?? '-' }}
                                </div>
                            </flux:table.cell>

                            {{-- Teknisi Pelaksana --}}
                            <flux:table.cell>
                                <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $spk->teknisi?->nama ?? '-' }}
                                </div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">
                                    IPSRS RS
                                </div>
                            </flux:table.cell>

                            {{-- Tanggal Penugasan --}}
                            <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ $spk->tanggal_penugasan?->format('d/m/Y') }}
                            </flux:table.cell>

                            {{-- Target Selesai --}}
                            <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ $spk->target_selesai?->format('d/m/Y') ?? '-' }}
                            </flux:table.cell>

                            {{-- Status SPK --}}
                            <flux:table.cell class="text-center">
                                <x-status-badge :status="$spk->status" />
                            </flux:table.cell>

                            {{-- Aksi: Cetak PDF & Detail --}}
                            <flux:table.cell class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button
                                        as="a"
                                        href="/{{ request()->segment(1) ?? 'teknisi' }}/spk/{{ $spk->id }}/cetak-pdf"
                                        target="_blank"
                                        size="xs"
                                        variant="ghost"
                                        icon="printer"
                                        title="Cetak Dokumen Fisik SPK (PDF)"
                                    />

                                    <flux:button
                                        wire:click="showDetailSpk({{ $spk->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="eye"
                                        title="Lihat Detail SPK"
                                    />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Belum ada Surat Perintah Kerja (SPK) yang diterbitkan." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif

        <x-table-pagination :paginator="$daftarData" />
    </flux:card>

    {{-- Modal Disposisi / Terbitkan SPK --}}
    <flux:modal name="modal-terbitkan-spk" wire:model="showSpkModal" class="md:w-[44rem]">
        @if ($selectedTiketForSpk)
            <form wire:submit="saveSpk" class="space-y-4">
                <div>
                    <flux:heading size="lg">Disposisi Surat Perintah Kerja (SPK)</flux:heading>
                    <flux:subheading>Tugaskan teknisi pelaksana dan target penyelesaian perbaikan fisik sarana.</flux:subheading>
                </div>

                {{-- Ringkasan Tiket Terpilih --}}
                <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">No. Tiket</div>
                        <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiketForSpk->kode }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Sarana / Alat</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiketForSpk->aset?->barang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Pelapor</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiketForSpk->unit?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Urgensi</div>
                        <div class="font-bold text-rose-600 dark:text-rose-400 mt-0.5">{{ $selectedTiketForSpk->prioritas }}</div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Teknisi Pelaksana Ditugaskan <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="teknisi_id"
                        required
                        class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                        <option value="">-- Pilih Teknisi IPSRS --</option>
                        @foreach ($daftarTeknisi as $tek)
                            <option value="{{ $tek->id }}">{{ $tek->nama }} ({{ $tek->email }})</option>
                        @endforeach
                    </select>
                    @error('teknisi_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Tanggal Penugasan <span class="text-red-500">*</span></flux:label>
                        <flux:input type="date" wire:model="tanggal_penugasan" required />
                        <flux:error name="tanggal_penugasan" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Target Selesai</flux:label>
                        <flux:input type="date" wire:model="target_selesai" />
                        <flux:error name="target_selesai" />
                    </flux:field>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Instruksi Khusus Koordinator (Opsional)
                    </label>
                    <textarea
                        wire:model="instruksi_khusus"
                        rows="2"
                        placeholder="Contoh: Periksa kelistrikan panel MCB terlebih dahulu, koordinasikan dengan perawat jaga..."
                        class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    ></textarea>
                    @error('instruksi_khusus') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showSpkModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveSpk">Terbitkan SPK</span>
                        <span wire:loading wire:target="saveSpk">Menerbitkan...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

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
                    <x-status-badge :status="$selectedSpk->status" />
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

                @if ($selectedSpk->instruksi_khusus)
                    <div class="p-3 rounded-lg bg-blue-50/50 dark:bg-blue-950/30 border border-blue-200/60 dark:border-blue-800/40 text-xs space-y-1">
                        <div class="font-semibold text-blue-900 dark:text-blue-300">Instruksi Khusus Koordinator:</div>
                        <div class="text-blue-800 dark:text-blue-200">{{ $selectedSpk->instruksi_khusus }}</div>
                    </div>
                @endif

                <div class="flex items-center justify-between pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </flux:button>

                    <flux:button
                        as="a"
                        href="/{{ request()->segment(1) ?? 'teknisi' }}/spk/{{ $selectedSpk->id }}/cetak-pdf"
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
</div>
