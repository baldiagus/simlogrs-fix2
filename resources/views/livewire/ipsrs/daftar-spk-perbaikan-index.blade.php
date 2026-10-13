<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Surat Perintah Kerja (SPK) Perbaikan</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Disposisi penugasan teknisi IPSRS, penerbitan dokumen resmi SPK, dan pemantauan perbaikan fisik sarana.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Hub Servis IPSRS --}}
        <x-tabs.ipsrs-servis active="spk" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
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
                        <option value="{{ \App\Enums\Ipsrs\StatusSpk::Ditugaskan->value }}">DITUGASKAN</option>
                        <option value="{{ \App\Enums\Ipsrs\StatusSpk::Diterima->value }}">DITERIMA</option>
                        <option value="{{ \App\Enums\Ipsrs\StatusSpk::Selesai->value }}">SELESAI</option>
                        <option value="{{ \App\Enums\Ipsrs\StatusSpk::Dibatalkan->value }}">DIBATALKAN</option>
                    </select>
                </div>
            @endif
        </div>

        {{-- TAB 1: Antrean Tiket Masuk Menunggu SPK --}}
        @if ($activeTab === 'antrean')
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column class="w-32">No. Tiket</flux:table.column>
                    <flux:table.column class="w-56">Sarana / Alat Rusak</flux:table.column>
                    <flux:table.column class="w-40">Unit Pelapor</flux:table.column>
                    <flux:table.column class="text-center w-24">Urgensi</flux:table.column>
                    <flux:table.column class="w-56">Keluhan Ruangan</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
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

                            <flux:table.cell style="white-space: normal;">
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
                            <flux:table.cell style="white-space: normal;">
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
                                @if ($tiket->foto_kerusakan)
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <a href="{{ asset('storage/' . $tiket->foto_kerusakan) }}" target="_blank" class="block rounded border border-zinc-200 dark:border-zinc-700 overflow-hidden hover:opacity-80">
                                            <img src="{{ asset('storage/' . $tiket->foto_kerusakan) }}" alt="Foto" class="h-6 w-6 object-cover" />
                                        </a>
                                        <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-medium">Ada Foto Bukti</span>
                                    </div>
                                @endif
                            </flux:table.cell>
                            {{-- Aksi: Terbitkan SPK --}}
                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        @if ($this->canTerbitkanSpk())
                                            <flux:menu.item wire:click="openTerbitkanSpk({{ $tiket->id }})" icon="clipboard-document-check" class="text-emerald-600 dark:text-emerald-400">
                                                Disposisi SPK
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-ui.empty-state message="Tidak ada antrean tiket kerusakan baru yang membutuhkan disposisi SPK." cols="6" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            {{-- TAB 2: Daftar SPK Terbit & Riwayat --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">No. SPK</flux:table.column>
                    <flux:table.column class="w-56">Ref Tiket & Alat</flux:table.column>
                    <flux:table.column class="w-36">Teknisi</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_penugasan'" :direction="$sortDirection" wire:click="sort('tanggal_penugasan')" class="w-28">Tgl Tugas</flux:table.column>
                    <flux:table.column class="w-28">Target</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
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
                            <flux:table.cell style="white-space: normal;">
                                <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                    {{ $spk->permintaanMaintenance?->aset?->barang?->nama ?? '-' }}
                                </div>
                                <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Ref: {{ $spk->permintaanMaintenance?->kode ?? '-' }} &bull; {{ $spk->permintaanMaintenance?->unit?->nama ?? '-' }}
                                </div>
                                @if ($spk->permintaanMaintenance?->foto_kerusakan)
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <a href="{{ asset('storage/' . $spk->permintaanMaintenance->foto_kerusakan) }}" target="_blank" class="block rounded border border-zinc-200 dark:border-zinc-700 overflow-hidden hover:opacity-80">
                                            <img src="{{ asset('storage/' . $spk->permintaanMaintenance->foto_kerusakan) }}" alt="Foto" class="h-6 w-6 object-cover" />
                                        </a>
                                        <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-medium">Ada Foto Bukti</span>
                                    </div>
                                @endif
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
                                <x-ui.status-badge :status="$spk->status" />
                            </flux:table.cell>

                            {{-- Aksi: Cetak PDF & Detail --}}
                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="showDetailSpk({{ $spk->id }})" icon="eye">
                                            Detail SPK
                                        </flux:menu.item>
                                        <flux:menu.item as="a" href="/{{ \App\Support\Portal\PortalResolver::resolve() }}/spk/{{ $spk->id }}/cetak-pdf" target="_blank" icon="printer">
                                            Cetak PDF SPK
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-ui.empty-state message="Belum ada Surat Perintah Kerja (SPK) yang diterbitkan." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif

        <x-ui.table-pagination :paginator="$daftarData" />
    </flux:card>

    @include('livewire.ipsrs.partials.daftar-spk.modal-terbitkan-spk')

    @include('livewire.ipsrs.partials.daftar-spk.modal-detail-spk')
</div>
