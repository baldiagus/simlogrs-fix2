@props(['paginator'])

<div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 mt-2 border-t border-zinc-200 dark:border-zinc-800">
    {{-- Ujung Kiri: Showing info --}}
    <div class="text-zinc-500 dark:text-zinc-400 text-xs font-medium whitespace-nowrap">
        @if ($paginator->total() > 0)
            {!! __('Showing') !!} {{ $paginator->firstItem() }} {!! __('to') !!} {{ $paginator->lastItem() }} {!! __('of') !!} {{ $paginator->total() }} {!! __('results') !!}
        @else
            {!! __('Showing') !!} 0 {!! __('results') !!}
        @endif
    </div>

    {{-- Di Tengah: Baris per halaman --}}
    <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <span>Baris per halaman:</span>
        <select wire:model.live="perPage" class="border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 rounded-md px-2.5 py-1 text-xs font-medium focus:ring-2 focus:ring-blue-500">
            <option value="10">10</option>
            <option value="15">15</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </select>
    </div>

    {{-- Ujung Kanan: Tombol Pagination Flux --}}
    <div class="flex justify-end min-w-[120px]">
        @if ($paginator->hasPages())
            <flux:pagination :paginator="$paginator" class="border-t-0! pt-0! [&>div:first-child]:hidden!" />
        @endif
    </div>
</div>
