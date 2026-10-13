@props(['urgency' => 'REGULER'])

@php
$isCito = strtoupper($urgency ?? '') === 'CITO';
$color = match (strtoupper($urgency ?? '')) {
    'CITO' => 'red',
    'MENDESAK', 'TINGGI' => 'amber',
    'SEDANG' => 'blue',
    default => 'zinc',
};
@endphp

<flux:badge size="sm" :color="$color" :class="$isCito ? 'animate-pulse font-bold tracking-wide' : ''" {{ $attributes }}>
    {{ strtoupper($urgency ?? 'REGULER') }}
</flux:badge>
