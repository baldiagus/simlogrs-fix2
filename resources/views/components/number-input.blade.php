@props([
    'model' => '',
    'label' => '',
    'prefix' => null,
    'required' => false,
])

<div x-data="{
    raw: @entangle($model),
    display: '',
    format(v) {
        if (v === null || v === '' || v === 0) return '0';
        let s = String(v).replace(/[^\d]/g, '');
        if (s === '') return '0';
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
        this.raw = clean === '' ? 0 : parseInt(clean, 10);
        this.display = this.format(this.raw);
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
            {{ $required ? 'required' : '' }}
            {{ $attributes->class([
                'w-full text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 text-right font-mono tabular-nums',
                'shadow-xs outline-none focus:border-zinc-400 dark:focus:border-zinc-500 focus:ring-1 focus:ring-zinc-400/30 dark:focus:ring-zinc-500/30',
                'ps-10' => $prefix,
            ]) }}
        />
    </div>
    @error($model)
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
    @enderror
</div>
