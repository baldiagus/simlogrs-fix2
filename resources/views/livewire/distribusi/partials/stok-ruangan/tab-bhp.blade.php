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
