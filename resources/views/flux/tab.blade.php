@props([
    'name' => null,
    'badge' => null,
    'icon' => null,
    'current' => false,
])

@php
$classes = $current
    ? 'border-blue-600 text-blue-600 dark:border-blue-500 dark:text-blue-400 font-semibold'
    : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-200 dark:hover:border-zinc-600 font-medium';
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => "flex items-center gap-2 px-3.5 py-2.5 text-xs border-b-2 -mb-px transition-colors whitespace-nowrap $classes"]) }}
>
    @if ($icon)
        <flux:icon :name="$icon" class="size-4" />
    @endif

    <span>{{ $slot }}</span>

    @if ($badge !== null)
        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold {{ $current ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' }}">
            {{ $badge }}
        </span>
    @endif
</button>
