@props([
    'model' => '',
    'label' => '',
    'prefix' => null,
    'placeholder' => '',
    'required' => false,
])

<div x-data="{
    raw: @entangle($model),
    display: '',
    format(v) {
        if (v === null || v === '') return '';
        let s = String(v).replace(/[^\d]/g, '');
        if (s === '' || s === '0') return s === '0' && v !== null ? '0' : '';
        s = s.replace(/^0+/, '') || '0';
        return s.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    },
    init() {
        this.display = this.format(this.raw);
        this.$watch('raw', v => { this.display = this.format(v); });
    },
    onInput(e) {
        let pos = e.target.selectionStart;
        let before = this.display;
        let clean = e.target.value.replace(/[^\d]/g, '');
        this.raw = clean === '' ? null : parseInt(clean, 10);
        this.display = clean === '' ? '' : this.format(this.raw);
        this.$nextTick(() => {
            let diff = this.display.length - before.length;
            e.target.setSelectionRange(pos + diff, pos + diff);
        });
    }
}">
    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
        {{ $label }}@if($required)<span class="text-red-500 ml-0.5">*</span>@endif
    </label>
    <div class="relative">
        @if($prefix)
            <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-xs font-semibold text-zinc-400 dark:text-zinc-500 pointer-events-none">{{ $prefix }}</span>
        @endif
        <input
            type="text"
            inputmode="numeric"
            x-model="display"
            x-on:input="onInput($event)"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            @php
                $isInvalid = $errors->has($model);
            @endphp
            {{ $attributes->class([
                'w-full text-sm rounded-lg border bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 text-right font-mono tabular-nums placeholder:text-zinc-400 placeholder:font-sans placeholder:text-xs placeholder:not-italic shadow-xs outline-none',
                $isInvalid
                    ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30 focus:border-red-500 focus:ring-red-500/30'
                    : 'border-zinc-200 dark:border-zinc-700 focus:border-zinc-400 dark:focus:border-zinc-500 focus:ring-1 focus:ring-zinc-400/30 dark:focus:ring-zinc-500/30',
                'ps-10' => $prefix,
            ]) }}
        />
    </div>
    @error($model)
        <p class="mt-1.5 text-xs text-red-500 dark:text-red-400 flex items-center gap-1 font-medium">
            <svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm.75-10.25a.75.75 0 0 0-1.5 0v4.5a.75.75 0 0 0 1.5 0v-4.5Zm-.75 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
            </svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
