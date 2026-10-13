<div wire:poll.30s class="mr-2">
    <flux:dropdown position="bottom" align="end">
        <button type="button" class="relative p-2 rounded-lg text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer" aria-label="Notifikasi" data-testid="lonceng">
            <flux:icon name="bell" class="size-5" />
            @if ($belumDibaca > 0)
                <span class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-red-600 text-white text-[10px] font-bold leading-4 text-center" data-testid="lonceng-badge">{{ $belumDibaca > 99 ? '99+' : $belumDibaca }}</span>
            @endif
        </button>

        <flux:menu class="w-80 max-w-[90vw]">
            <div class="flex items-center justify-between px-3 py-2 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-sm font-bold">Notifikasi</span>
                @if ($belumDibaca > 0)
                    <button type="button" wire:click="tandaiSemuaDibaca" class="text-xs text-blue-600 hover:underline cursor-pointer">Tandai semua dibaca</button>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto">
                @forelse ($items as $n)
                    <button type="button" wire:key="notif-{{ $n->id }}" wire:click="buka({{ $n->id }})"
                        class="block w-full text-left px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer {{ $n->dibaca ? '' : 'bg-blue-50/50 dark:bg-blue-950/20' }}">
                        <div class="text-sm {{ $n->dibaca ? 'text-zinc-700 dark:text-zinc-300' : 'font-bold text-zinc-900 dark:text-zinc-100' }}">{{ $n->judul }}</div>
                        <div class="text-xs text-zinc-500">{{ \Illuminate\Support\Str::limit($n->pesan, 80) }}</div>
                        <div class="text-[10px] text-zinc-400 mt-0.5">{{ $n->dibuat_pada?->locale('id')->diffForHumans() }}</div>
                    </button>
                @empty
                    <div class="px-3 py-6 text-center text-xs text-zinc-400">Belum ada notifikasi.</div>
                @endforelse
            </div>

            <div class="border-t border-zinc-100 dark:border-zinc-800 px-3 py-2 text-center">
                <a href="{{ $urlDaftar }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">Lihat semua</a>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
