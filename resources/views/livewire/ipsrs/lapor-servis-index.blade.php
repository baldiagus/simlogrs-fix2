<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Lapor Kerusakan & Servis IPSRS</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Pengajuan tiket perbaikan fasilitas, sarana, prasarana, dan alat kerja ruangan ke teknisi IPSRS.</flux:subheading>
            </div>

            @can('create_layanan_unit::lapor::servis')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Lapor Kerusakan
                </flux:button>
            @endcan
        </div>

        {{-- Navigasi Sub-Tab Hub Servis IPSRS --}}
        <x-ipsrs-servis-tabs active="lapor" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode tiket, nama alat, keluhan..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-52">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="{{ \App\Enums\StatusMaintenance::Dilaporkan->value }}">DILAPORKAN</option>
                    <option value="{{ \App\Enums\StatusMaintenance::Ditugaskan->value }}">DITUGASKAN (SPK)</option>
                    <option value="{{ \App\Enums\StatusMaintenance::SedangDikerjakan->value }}">SEDANG DIKERJAKAN</option>
                    <option value="{{ \App\Enums\StatusMaintenance::MenungguSparepart->value }}">MENUNGGU SPAREPART</option>
                    <option value="{{ \App\Enums\StatusMaintenance::UjiFungsi->value }}">UJI FUNGSI</option>
                    <option value="{{ \App\Enums\StatusMaintenance::Selesai->value }}">SELESAI</option>
                    <option value="{{ \App\Enums\StatusMaintenance::Dibatalkan->value }}">DIBATALKAN</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterPrioritas" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Prioritas</option>
                    <option value="CITO">CITO (Darurat)</option>
                    <option value="TINGGI">TINGGI</option>
                    <option value="SEDANG">SEDANG</option>
                    <option value="RENDAH">RENDAH</option>
                </select>
            </div>
        </div>

        {{-- Tabel Daftar Tiket Kerusakan --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">No. Tiket</flux:table.column>
                <flux:table.column class="w-64">Sarana / Alat</flux:table.column>
                <flux:table.column class="w-44">Unit Pelapor</flux:table.column>
                <flux:table.column class="text-center w-24">Prioritas</flux:table.column>
                <flux:table.column class="w-36">Teknisi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                <flux:table.column class="text-right w-20">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarTiket as $item)
                    @php
                        $prioritasColor = match($item->prioritas) {
                            'CITO' => 'rose',
                            'TINGGI' => 'amber',
                            'SEDANG' => 'blue',
                            'RENDAH' => 'zinc',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:table.row :key="$item->id">
                        {{-- Kode Tiket --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <div>{{ $item->kode }}</div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-sans mt-0.5">
                                {{ $item->dibuat_pada?->format('d/m/Y H:i') }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell style="white-space: normal;">
                            <button type="button" wire:click="showDetail({{ $item->id }})" class="text-left font-medium text-xs text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-2">
                                {{ $item->aset?->barang?->nama ?? '-' }}
                            </button>
                            <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Kode: {{ $item->aset?->kode_aset ?? '-' }}
                                @if ($item->aset?->nomor_seri)
                                    &bull; SN: {{ $item->aset->nomor_seri }}
                                @endif
                            </div>
                            <div class="text-[11px] text-zinc-600 dark:text-zinc-300 mt-1 line-clamp-1">
                                {{ $item->deskripsi_masalah }}
                            </div>
                            @if ($item->foto_kerusakan)
                                <div class="mt-1.5 flex items-center gap-1.5">
                                    <a href="{{ asset('storage/' . $item->foto_kerusakan) }}" target="_blank" class="block rounded border border-zinc-200 dark:border-zinc-700 overflow-hidden hover:opacity-80">
                                        <img src="{{ asset('storage/' . $item->foto_kerusakan) }}" alt="Foto" class="h-7 w-7 object-cover" />
                                    </a>
                                    <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-medium">Ada Foto Bukti</span>
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell style="white-space: normal;">
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $item->unit?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                Pelapor: {{ $item->pelapor?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Prioritas --}}
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$prioritasColor">
                                {{ $item->prioritas }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Teknisi Ditugaskan --}}
                        <flux:table.cell>
                            @if ($item->penugasanAktif && $item->penugasanAktif->teknisi)
                                <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $item->penugasanAktif->teknisi->nama }}
                                </div>
                                <div class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400">
                                    SPK: {{ $item->penugasanAktif->kode }}
                                </div>
                            @else
                                <span class="text-zinc-400 text-xs italic">Belum Ditugaskan</span>
                            @endif
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Lihat Tiket Kerusakan
                                    </flux:menu.item>
                                    @if ($item->status === \App\Enums\StatusMaintenance::Dilaporkan->value && $this->canBatal($item))
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmBatal({{ $item->id }})" icon="x-mark" variant="danger">
                                            Batalkan Laporan
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada tiket laporan kerusakan sarana yang terdaftar." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$daftarTiket" />
    </flux:card>

    @include('livewire.ipsrs.partials.lapor-servis.modal-form-lapor')

    @include('livewire.ipsrs.partials.lapor-servis.modal-detail-tiket')

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
