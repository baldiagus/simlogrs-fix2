<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Permintaan Barang Ruangan (Amprah)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Kelola pengajuan permintaan barang dan komoditas dari ruangan ke gudang logistik.</flux:subheading>
            </div>

            @can('create_layanan_unit::permintaan::barang')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Ajukan Amprah
                </flux:button>
            @endcan
        </div>

        {{-- Navigasi Sub-Tab Modul Amprah Ruangan --}}
        <x-distribusi-amprah-tabs active="permintaan" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode amprah atau nama unit..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="DIAJUKAN">DIAJUKAN</option>
                    <option value="DISETUJUI_KARU">DISETUJUI KARU</option>
                    <option value="DISETUJUI_GUDANG">DISETUJUI GUDANG</option>
                    <option value="SEDANG_DIKIRIM">SEDANG DIKIRIM</option>
                    <option value="SELESAI">SELESAI</option>
                    <option value="DITOLAK">DITOLAK</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterUrgensi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Urgensi</option>
                    <option value="REGULER">REGULER</option>
                    <option value="CITO">CITO (Darurat)</option>
                </select>
            </div>
            @if ($search || $filterStatus || $filterUrgensi)
                <flux:button
                    wire:click="resetFilters"
                    size="sm"
                    variant="subtle"
                    icon="x-mark"
                >
                    Reset Filter
                </flux:button>
            @endif
        </div>

        {{-- Tabel Permintaan Amprah --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode Amprah</flux:table.column>
                <flux:table.column class="w-64">Unit Pemohon & Tujuan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')" class="w-28">Tanggal</flux:table.column>
                <flux:table.column class="w-28 text-center">Urgensi</flux:table.column>
                <flux:table.column class="text-center w-24">Jml Item</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarPermintaan as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <button type="button" wire:click="showDetail({{ $item->id }})" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                {{ $item->kode }}
                            </button>
                        </flux:table.cell>

                        <flux:table.cell style="white-space: normal;">
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Tujuan: {{ $item->gudang?->nama ?? '-' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                            {{ $item->tanggal_permintaan?->format('d/m/Y') }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <x-urgency-badge :urgency="$item->urgensi" />
                        </flux:table.cell>

                        <flux:table.cell class="text-center font-mono text-xs">
                            {{ $item->detail_count ?? $item->detail->count() }}
                        </flux:table.cell>

                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Detail Amprah
                                    </flux:menu.item>
                                    @if (in_array($item->status, ['DIAJUKAN', 'DISETUJUI_KARU']) && ($isGlobalAdmin || (int) $userUnitId === (int) $item->unit_id))
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmBatal({{ $item->id }})" icon="x-mark" variant="danger">
                                            Batalkan Permintaan
                                        </flux:menu.item>
                                    @endif
                                    @if ($item->status === 'DIKIRIM_SEBAGIAN' && ($isGlobalAdmin || (int) $userUnitId === (int) $item->unit_id))
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmTutupSisaHutang({{ $item->id }})" icon="check-circle" class="text-blue-600">
                                            Tutup Sisa Hutang
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada data permintaan amprah ruangan." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Navigasi Paginasi --}}
        <x-table-pagination :paginator="$daftarPermintaan" />
    </flux:card>

    {{-- Modal Form Tambah Amprah --}}
    <flux:modal name="modal-permintaan-form" wire:model="showModal" class="md:w-[54rem] max-w-[95vw]">
        <form wire:submit="save" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Pengajuan Amprah Ruangan</flux:heading>
                <flux:subheading>Kebutuhan operasional ruangan ke gudang logistik sentral.</flux:subheading>
            </div>

            {{-- Tahap 1: Informasi Dokumen, Lokasi Pemohon & Urgensi --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Informasi Dokumen, Lokasi Pemohon & Urgensi
                </div>

                {{-- Baris 1: Informasi Dokumen & Unit --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            No. Amprah <span class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono font-normal">(Otomatis)</span>
                        </label>
                        <input
                            type="text"
                            wire:model="kode"
                            disabled
                            readonly
                            class="w-full text-sm font-mono font-semibold rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800/80 px-3 py-2 text-zinc-600 dark:text-zinc-300 cursor-not-allowed"
                        />
                    </div>

                    @if ($isGlobalAdmin || ! auth()->user()?->unit_id)
                        <div>
                            <x-searchable-select
                                model="unit_id"
                                label="Unit Pemohon"
                                placeholder="Pilih Unit Ruangan..."
                                :options="$daftarUnit"
                                required
                            />
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Unit Pemohon</label>
                            <input
                                type="text"
                                value="{{ auth()->user()->unit?->nama ?? '-' }}"
                                disabled
                                readonly
                                class="w-full text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800/80 px-3 py-2 text-zinc-700 dark:text-zinc-300 font-medium cursor-not-allowed"
                            />
                        </div>
                    @endif

                    <div>
                        <x-searchable-select
                            model="gudang_id"
                            label="Gudang Tujuan"
                            placeholder="Pilih Gudang..."
                            :options="$daftarGudang"
                            live="true"
                            required
                        />
                    </div>
                </div>

                {{-- Schedule and notes --}}
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Tanggal Permintaan <span class="text-red-500">*</span>
                        </label>
                        <input type="date" wire:model="tanggal_permintaan" required class="w-full text-sm rounded-lg border {{ $errors->has('tanggal_permintaan') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        @error('tanggal_permintaan') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Urgensi <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="urgensi" required class="w-full text-sm rounded-lg border {{ $errors->has('urgensi') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="REGULER">REGULER</option>
                            <option value="CITO">CITO (Darurat)</option>
                        </select>
                        @error('urgensi') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-6">
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Catatan / Justifikasi <span class="text-zinc-500 dark:text-zinc-400 font-normal">(Opsional)</span>
                        </label>
                        <input
                            type="text"
                            wire:model="catatan"
                            placeholder="Nomor memo atau justifikasi kebutuhan khusus..."
                            class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                </div>
            </div>
            {{-- Requested commodities (1x3 matrix) --}}
            {{-- Tahap 2: Rincian Daftar Komoditas Barang yang Diamprah --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 flex items-center gap-2">
                        <span>Tahap 2: Daftar Komoditas Barang yang Diamprah</span>
                        <span class="text-red-500">*</span>
                    </div>
                    <flux:button type="button" wire:click="addItem" size="xs" variant="ghost" icon="plus">
                        Tambah Baris
                    </flux:button>
                </div>

                {{-- Daftar Baris Barang (Matriks 1x3 per baris) --}}
                <div class="space-y-3">
                    @foreach ($items as $index => $row)
                        <div wire:key="amprah-item-{{ $index }}" class="p-3.5 rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xs">
                            <div class="w-full grid grid-cols-12 gap-3 items-start">
                                {{-- Kolom 1 (1x3): Barang & Informasi Stok Gudang (col-span-5) --}}
                                <div class="col-span-5 w-full min-w-0">
                                    <x-searchable-select
                                        model="items.{{ $index }}.barang_id"
                                        label="Komoditas Barang"
                                        placeholder="Pilih Komoditas Barang..."
                                        :options="$daftarBarang"
                                        live="true"
                                        required
                                    />
                                    {{-- Informasi Stok Gudang Real-Time --}}
                                    @if (!empty($items[$index]['barang_id']))
                                        @php
                                            $bId = (int) $items[$index]['barang_id'];
                                            $selectedB = $daftarBarang->firstWhere('id', $bId);
                                            $stokAda = (int) ($stokGudangMap[$bId] ?? 0);
                                        @endphp
                                        <div class="mt-1.5 flex items-center gap-1.5">
                                            @if ($stokAda > 0)
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Stok Gudang: <strong>{{ number_format($stokAda, 0, ',', '.') }} {{ $selectedB?->satuan?->simbol ?? 'Unit' }}</strong>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60" title="Kebutuhan komoditas ini akan otomatis dicatat sebagai hutang gudang dan dikirim saat restok.">
                                                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                                                    Stok Gudang 0 (Otomatis Terhutang)
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                    @error("items.{$index}.barang_id") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                {{-- Kolom 2 (1x3): Jumlah Diminta (col-span-2) --}}
                                <div class="col-span-2 w-full min-w-0">
                                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                        Jumlah <span class="text-red-500">*</span>
                                    </label>
                                    @php
                                        $bIdCurrent = (int) ($items[$index]['barang_id'] ?? 0);
                                        $stokMax = $bIdCurrent ? (int) ($stokGudangMap[$bIdCurrent] ?? 0) : 0;
                                    @endphp
                                    <input
                                        type="number"
                                        wire:model="items.{{ $index }}.jumlah"
                                        min="1"
                                        required
                                        placeholder="1"
                                        class="w-full text-sm rounded-lg border {{ $errors->has("items.{$index}.jumlah") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 text-right font-mono tabular-nums focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                    @error("items.{$index}.jumlah") <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                                </div>

                                {{-- Kolom 3 (1x3): Catatan Item & Tombol Hapus (col-span-5) --}}
                                <div class="col-span-5 w-full min-w-0">
                                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                        Catatan Item <span class="text-zinc-500 dark:text-zinc-400 font-normal">(Opsional)</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="text"
                                            wire:model="items.{{ $index }}.catatan"
                                            placeholder="Keterangan peruntukan..."
                                            class="flex-1 text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        />
                                        @if (count($items) > 1)
                                            <flux:button type="button" wire:click="removeItem({{ $index }})" size="sm" variant="ghost" icon="trash" title="Hapus Baris" class="text-red-500 hover:text-red-700 shrink-0" />
                                        @endif
                                    </div>
                                    @error("items.{$index}.catatan") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @error('items') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-xs text-blue-700 dark:text-blue-300 flex items-center gap-2">
                <flux:icon name="information-circle" class="size-4 shrink-0 text-blue-600" />
                <span>Barang yang stok gudangnya kosong atau kurang dari permintaan akan otomatis dicatat sebagai hutang gudang (backorder) dan diterbitkan SPPB susulan saat barang tiba.</span>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Ajukan Amprah Barang</span>
                    <span wire:loading wire:target="save">Memproses...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

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
                        <x-urgency-badge :urgency="$selectedPermintaan->urgensi" />
                        <x-status-badge :status="$selectedPermintaan->status" />
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

                @if ($selectedPermintaan->status === 'DIKIRIM_SEBAGIAN')
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
                                        ? $selectedPermintaan->pengiriman->where('status', '!=', 'BATAL')->flatMap->detail
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
                                            @if ($selectedPermintaan->status === 'DIAJUKAN' || $detail->jumlah_disetujui === null)
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

    {{-- Modal Konfirmasi Aksi --}}
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
