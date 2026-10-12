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
            @include('livewire.distribusi.partials.stok-ruangan.tab-bhp')
        @else
            @include('livewire.distribusi.partials.stok-ruangan.tab-aset')
        @endif
    </flux:card>

    @include('livewire.distribusi.partials.stok-ruangan.modal-ubah-status-aset')

    @include('livewire.distribusi.partials.stok-ruangan.modal-catat-pemakaian')

    @include('livewire.distribusi.partials.stok-ruangan.modal-lapor-servis-aset')
</div>
