@props(['value' => 0])

<span class="font-mono tabular-nums text-right inline-block" {{ $attributes }}>
    {{ Illuminate\Support\Number::currency((float) ($value ?? 0), 'IDR', 'id') }}
</span>
