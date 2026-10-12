<div class="space-y-6">
    {{-- Ringkasan Metrik Persediaan Ruangan --}}
    {{-- Ringkasan Metrik Persediaan Ruangan & Aset --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400">
                <flux:icon name="cube" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Jenis BHP</div>
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ $countTotalItem }} Item</div>
            </div>
        </flux:card>

        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="check-circle" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">BHP Tersedia (&gt; 0)</div>
                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $countTersedia }} Item</div>
            </div>
        </flux:card>

        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
                <flux:icon name="computer-desktop" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Aset Ruangan</div>
                <div class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ $countTotalAset }} Unit</div>
            </div>
        </flux:card>

        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-teal-50 dark:bg-teal-950/40 text-teal-600 dark:text-teal-400">
                <flux:icon name="check-badge" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Aset Kondisi Baik</div>
                <div class="text-xl font-bold text-teal-600 dark:text-teal-400">{{ $countAsetBaik }} Unit</div>
            </div>
        </flux:card>
    </div>

    {{-- Kartu Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $pageTitle }}</flux:heading>
                <flux:subheading>{{ $pageSubtitle }}</flux:subheading>
            </div>
            <div class="flex items-center gap-2">
                @php
                    $portalSlug = \App\Support\Portal\PortalResolver::resolve();
                @endphp
                @if ($activeTab === 'bhp')
                    @if ($isWarehouseUser)
                        <flux:button as="a" href="/{{ $portalSlug }}/pengalihan-barang" variant="primary" icon="arrows-right-left">
                            Pengalihan Barang
                        </flux:button>
                    @else
                        <flux:button as="a" href="/{{ $portalSlug }}/permintaan-barang" variant="primary" icon="plus">
                            Ajukan Amprah Baru
                        </flux:button>
                    @endif
                @else
                    @if ($isWarehouseUser)
                        <flux:button as="a" href="/{{ $portalSlug }}/pengalihan-aset" variant="primary" icon="arrows-right-left">
                            Pengalihan Aset
                        </flux:button>
                    @else
                        <flux:button as="a" href="/{{ $portalSlug }}/peminjaman-aset" variant="primary" icon="plus">
                            Pinjam Aset Baru
                        </flux:button>
                    @endif
                @endif
            </div>
        </div>

        {{-- Navigasi Tab --}}
        <flux:tabs class="pt-1">
            <flux:tab
                wire:click="setTab('bhp')"
                :current="$activeTab === 'bhp'"
                icon="cube"
                :badge="$countTotalItem"
            >
                {{ $isWarehouseUser ? 'Barang Persediaan' : 'Barang Habis Pakai (BHP)' }}
            </flux:tab>
            <flux:tab
                wire:click="setTab('aset')"
                :current="$activeTab === 'aset'"
                icon="computer-desktop"
                :badge="$countTotalAset"
            >
                {{ $isWarehouseUser ? 'Aset & Sarana Gudang' : 'Aset & Inventaris Ruangan' }}
            </flux:tab>
        </flux:tabs>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ $activeTab === 'bhp' ? 'Cari kode barang, nama, unit...' : 'Cari kode register AST, nama alat, nomor seri...' }}"
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            @if ($activeTab === 'bhp')
                <div class="w-full sm:w-48">
                    <select wire:model.live="filterKondisi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Kondisi Stok</option>
                        <option value="TERSEDIA">Tersedia (&gt; 0)</option>
                        <option value="HABIS">Habis (0)</option>
                    </select>
                </div>
            @else
                <div class="w-full sm:w-44">
                    <select wire:model.live="filterKondisiAset" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Kondisi Fisik</option>
                        <option value="BAIK">Kondisi: BAIK</option>
                        <option value="RUSAK_RINGAN">Kondisi: RUSAK RINGAN</option>
                        <option value="RUSAK_BERAT">Kondisi: RUSAK BERAT</option>
                    </select>
                </div>
                <div class="w-full sm:w-44">
                    <select wire:model.live="filterStatusAset" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Status Aset</option>
                        <option value="DIGUNAKAN">Status: DIGUNAKAN</option>
                        <option value="TERSEDIA">Status: TERSEDIA</option>
                        <option value="DIPINJAM">Status: DIPINJAM</option>
                    </select>
                </div>
            @endif

            @if ($canPickUnit && $daftarUnit->isNotEmpty())
                <div class="w-full sm:w-64">
                    <select wire:model.live="filterUnitId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 font-medium">
                        @foreach ($daftarUnit as $u)
                            <option value="{{ $u->id }}">{{ $u->nama }} ({{ $u->kode }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        @if ($activeTab === 'bhp')
            {{-- Tabel Persediaan Lokal Ruangan (BHP) --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'barang_id'" :direction="$sortDirection" wire:click="sort('barang_id')" class="w-36">Kode</flux:table.column>
                    <flux:table.column>Nama Komoditas & Kategori</flux:table.column>
                    @if ($canPickUnit)
                        <flux:table.column>Unit / Ruangan</flux:table.column>
                    @endif
                    <flux:table.column sortable :sorted="$sortBy === 'jumlah'" :direction="$sortDirection" wire:click="sort('jumlah')" class="text-right w-36">Sisa Fisik</flux:table.column>
                    <flux:table.column class="text-center w-32">Status</flux:table.column>
                    <flux:table.column class="text-right w-44">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarStok as $stok)
                        @php
                            $satuan = $stok->barang?->satuan?->simbol ?? 'Pcs';
                            $isHabis = $stok->jumlah <= 0;
                        @endphp
                        <flux:table.row :key="$stok->id">
                            {{-- Kode Barang --}}
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $stok->barang?->kode ?? '-' }}
                            </flux:table.cell>

                            {{-- Nama & Kategori --}}
                            <flux:table.cell>
                                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $stok->barang?->nama ?? 'Komoditas' }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Kategori: {{ $stok->barang?->kategori?->nama ?? 'Umum' }}
                                </div>
                            </flux:table.cell>

                            {{-- Unit (Jika Admin) --}}
                            @if ($canPickUnit)
                                <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ $stok->unit?->nama ?? $stok->gudang?->nama ?? '-' }}
                                </flux:table.cell>
                            @endif

                            {{-- Sisa Fisik --}}
                            <flux:table.cell class="text-right">
                                <span class="font-bold text-sm {{ $isHabis ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                    {{ number_format($stok->jumlah, 0, ',', '.') }}
                                </span>
                                <span class="text-xs text-zinc-500 ml-1">{{ $satuan }}</span>
                            </flux:table.cell>

                            {{-- Status Badge --}}
                            <flux:table.cell class="text-center">
                                @if ($isHabis)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-800">
                                        HABIS
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                        TERSEDIA
                                    </span>
                                @endif
                            </flux:table.cell>

                            {{-- Aksi --}}
                            <flux:table.cell class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if (! $isWarehouseUser && $canCatatPakai)
                                        @if (! $isHabis)
                                            <flux:button
                                                wire:click="openCatatPemakaian({{ $stok->id }})"
                                                size="xs"
                                                variant="primary"
                                                icon="minus-circle"
                                                title="Catat Pemakaian"
                                            >
                                                Catat Pakai
                                            </flux:button>
                                        @else
                                            <flux:button
                                                as="a"
                                                href="/{{ \App\Support\Portal\PortalResolver::resolve() }}/permintaan-barang"
                                                size="xs"
                                                variant="danger"
                                                icon="arrow-up-tray"
                                                title="Stok Habis - Ajukan Amprah"
                                            >
                                                Amprah
                                            </flux:button>
                                        @endif
                                    @else
                                        <flux:button
                                            as="a"
                                            href="/{{ \App\Support\Portal\PortalResolver::resolve() }}/pengalihan-barang"
                                            size="xs"
                                            variant="ghost"
                                            icon="arrows-right-left"
                                            title="Pengalihan Stok Gudang"
                                        >
                                            Alihkan
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Belum ada persediaan barang yang tercatat di lemari ruangan ini. Lakukan amprah dan konfirmasi terima barang terlebih dahulu." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>

            {{-- Pagination BHP --}}
            <x-table-pagination :paginator="$daftarStok" />
        @else
            {{-- Tabel Aset & Inventaris Ruangan --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode_aset'" :direction="$sortDirection" wire:click="sort('kode_aset')" class="w-36">Kode Aset</flux:table.column>
                    <flux:table.column>Nama Sarana & Kategori</flux:table.column>
                    <flux:table.column class="w-36">Nomor Seri</flux:table.column>
                    @if ($canPickUnit)
                        <flux:table.column>Unit / Ruangan</flux:table.column>
                    @endif
                    <flux:table.column class="text-center w-32">Kondisi</flux:table.column>
                    <flux:table.column class="text-center w-32">Status</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarAset as $ast)
                        <flux:table.row :key="$ast->id">
                            {{-- Kode Aset --}}
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $ast->kode_aset }}
                            </flux:table.cell>

                            {{-- Nama & Kategori --}}
                            <flux:table.cell>
                                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $ast->barang?->nama ?? 'Aset Fisik' }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Kategori: {{ $ast->barang?->kategori?->nama ?? 'Umum' }}
                                </div>
                            </flux:table.cell>

                            {{-- Nomor Seri --}}
                            <flux:table.cell class="text-xs font-mono text-zinc-600 dark:text-zinc-300">
                                {{ $ast->nomor_seri ?? '-' }}
                            </flux:table.cell>

                            {{-- Unit (Jika Admin) --}}
                            @if ($canPickUnit)
                                <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ $ast->unit?->nama ?? '-' }}
                                </flux:table.cell>
                            @endif

                            {{-- Kondisi Fisik --}}
                            <flux:table.cell class="text-center">
                                @if ($ast->kondisi === 'BAIK')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                        BAIK
                                    </span>
                                @elseif ($ast->kondisi === 'RUSAK_RINGAN')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                                        RUSAK RINGAN
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-800">
                                        {{ $ast->kondisi }}
                                    </span>
                                @endif
                            </flux:table.cell>

                            {{-- Status Operasional --}}
                            <flux:table.cell class="text-center">
                                @if ($ast->status === 'DIGUNAKAN')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-300 dark:border-blue-800">
                                        DIGUNAKAN
                                    </span>
                                @elseif ($ast->status === 'TERSEDIA')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                        TERSEDIA
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-300 dark:border-purple-800">
                                        {{ $ast->status }}
                                    </span>
                                @endif
                            </flux:table.cell>

                            {{-- Aksi --}}
                            <flux:table.cell class="text-right w-16">
                                @php
                                    $user = auth()->user();
                                    $ownsThisAset = $isGlobalAdmin
                                        || ($user?->unit_id && $ast->unit_id && (int) $user->unit_id === (int) $ast->unit_id)
                                        || ($user?->gudang_id && $ast->gudang_id && (int) $user->gudang_id === (int) $ast->gudang_id);
                                    $canUbahStatus = $canUpdateStok && $ownsThisAset;
                                    $canLaporThis = $canLaporServis && $ownsThisAset;
                                @endphp
                                @if ($ast->status === 'DALAM_MAINTENANCE')
                                    <flux:badge size="xs" color="amber" class="font-medium" title="Aset sedang dalam penanganan perbaikan oleh teknisi IPSRS">
                                        Dalam Servis IPSRS
                                    </flux:badge>
                                @else
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                        <flux:menu class="min-w-44">
                                            @if ($canUbahStatus && ! in_array($ast->status, ['DIPINJAM', 'DALAM_MAINTENANCE', 'DIHAPUSKAN']))
                                                <flux:menu.item wire:click="openUbahStatusAset({{ $ast->id }})" icon="pencil-square">
                                                    Ubah Status Operasional
                                                </flux:menu.item>
                                            @endif
                                            @if ($canLaporThis && ! in_array($ast->status, ['DIPINJAM', 'DALAM_MAINTENANCE', 'DIHAPUSKAN']))
                                                <flux:menu.item wire:click="openLaporServis({{ $ast->id }})" icon="wrench">
                                                    Lapor Servis ke IPSRS
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Belum ada aset fisik atau sarana inventaris yang terdaftar pada unit ruangan ini." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>

            {{-- Pagination Aset --}}
            <x-table-pagination :paginator="$daftarAset" />
        @endif
    </flux:card>

    @include('livewire.distribusi.partials.stok-ruangan.modal-ubah-status-aset')

    @include('livewire.distribusi.partials.stok-ruangan.modal-catat-pemakaian')

    @include('livewire.distribusi.partials.stok-ruangan.modal-lapor-servis-aset')
</div>
