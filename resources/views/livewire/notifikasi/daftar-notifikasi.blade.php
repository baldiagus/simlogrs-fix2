<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Notifikasi</flux:heading>
            <flux:subheading class="text-xs text-zinc-500 mt-1">{{ $belumDibaca }} belum dibaca.</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:select wire:model.live="filter" size="sm" class="w-40">
                <flux:select.option value="semua">Semua</flux:select.option>
                <flux:select.option value="belum">Belum dibaca</flux:select.option>
            </flux:select>
            <flux:button size="sm" icon="check" wire:click="tandaiSemuaDibaca" :disabled="$belumDibaca === 0">Tandai semua dibaca</flux:button>
        </div>
    </div>

    <flux:card class="p-0 divide-y divide-zinc-100 dark:divide-zinc-800">
        @forelse ($items as $n)
            <div wire:key="notif-{{ $n->id }}" class="flex items-start gap-3 px-4 py-3 {{ $n->dibaca ? '' : 'bg-blue-50/50 dark:bg-blue-950/20' }}">
                <button type="button" wire:click="buka({{ $n->id }})" class="flex-1 text-left cursor-pointer">
                    <div class="text-sm {{ $n->dibaca ? 'text-zinc-700 dark:text-zinc-300' : 'font-bold text-zinc-900 dark:text-zinc-100' }}">{{ $n->judul }}</div>
                    <div class="text-xs text-zinc-500">{{ $n->pesan }}</div>
                    <div class="text-[10px] text-zinc-400 mt-0.5">{{ $n->dibuat_pada?->locale('id')->diffForHumans() }}</div>
                </button>
                @unless ($n->dibaca)
                    <flux:button size="xs" variant="ghost" wire:click="tandaiDibaca({{ $n->id }})">Tandai dibaca</flux:button>
                @endunless
            </div>
        @empty
            <div class="px-4 py-10 text-center text-sm text-zinc-400">
                {{ $filter === 'belum' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' }}
            </div>
        @endforelse
    </flux:card>

    <flux:pagination :paginator="$items" />
</div>
