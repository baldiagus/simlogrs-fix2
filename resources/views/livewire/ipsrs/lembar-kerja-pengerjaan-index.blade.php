<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Lembar Kerja Pengerjaan IPSRS</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Pencatatan diagnosis teknis, tindakan perbaikan fasilitas, pemakaian suku cadang FEFO, dan uji fungsi bersama unit.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Hub Servis IPSRS --}}
        <x-tabs.ipsrs-servis active="lk" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode SPK, teknisi, alat..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-48">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="AKTIF">Tugas Aktif Berjalan</option>
                    <option value="{{ \App\Enums\Ipsrs\StatusSpk::Selesai->value }}">Riwayat Selesai</option>
                    <option value="SEMUA">Semua SPK</option>
                </select>
            </div>
        </div>

        {{-- Tabel Daftar SPK --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">No. SPK</flux:table.column>
                <flux:table.column class="w-56">Fasilitas / Sarana</flux:table.column>
                <flux:table.column class="w-40">Unit Ruangan</flux:table.column>
                <flux:table.column class="w-36">Teknisi</flux:table.column>
                <flux:table.column class="text-center w-24">Log Servis</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                <flux:table.column class="text-right w-32">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarSpk as $spk)
                    <flux:table.row :key="$spk->id">
                        {{-- Kode SPK --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <div>{{ $spk->kode }}</div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-sans mt-0.5">
                                {{ $spk->tanggal_penugasan?->format('d/m/Y') }}
                            </div>
                        </flux:table.cell>

                        {{-- Fasilitas / Sarana --}}
                        <flux:table.cell style="white-space: normal;">
                            <button type="button" wire:click="showDetail({{ $spk->id }})" class="text-left font-medium text-xs text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-2">
                                {{ $spk->permintaanMaintenance?->aset?->barang?->nama ?? '-' }}
                            </button>
                            <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Kode: {{ $spk->permintaanMaintenance?->aset?->kode_aset ?? '-' }}
                            </div>
                        </flux:table.cell>
                        {{-- Unit Ruangan --}}
                        <flux:table.cell style="white-space: normal;">
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $spk->permintaanMaintenance?->unit?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Teknisi Pelaksana --}}
                        <flux:table.cell>
                            <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $spk->teknisi?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Log Servis --}}
                        <flux:table.cell class="text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                                {{ $spk->logMaintenance->count() }} log
                            </span>
                        </flux:table.cell>

                        {{-- Status SPK --}}
                        <flux:table.cell class="text-center">
                            <x-ui.status-badge :status="$spk->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $spk->id }})" icon="eye">
                                        Detail Pengerjaan
                                    </flux:menu.item>
                                    @if (in_array($spk->status, \App\Enums\Ipsrs\StatusSpk::aktif()))
                                        @if (auth()->user()?->can('create_pemeliharaan::lembar::kerja') && ((int) $spk->teknisi_id === (int) auth()->id() || auth()->user()->hasRole(['super_admin', 'admin_logistik'])))
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="openLembarKerja({{ $spk->id }})" icon="wrench-screwdriver" class="text-emerald-600 dark:text-emerald-400">
                                                Input Lembar Kerja
                                            </flux:menu.item>
                                        @endif
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-ui.empty-state message="Tidak ada SPK perbaikan yang sesuai dengan filter." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-ui.table-pagination :paginator="$daftarSpk" />
    </flux:card>

    @include('livewire.ipsrs.partials.lembar-kerja.modal-input-log')

    @include('livewire.ipsrs.partials.lembar-kerja.modal-detail-spk-log')
</div>
