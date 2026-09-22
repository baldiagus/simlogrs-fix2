@props([
    'variant' => 'default',
])

<div {{ $attributes->class('flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-700') }}>
    {{ $slot }}
</div>
