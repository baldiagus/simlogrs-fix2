@props(['status'])

@php
$color = match (strtoupper($status ?? '')) {
    'AKTIF', 'DISETUJUI', 'TERSEDIA', 'SELESAI', 'NORMAL', 'LENGKAP', 'CLOSED' => 'emerald',
    'DIAJUKAN', 'MENUNGGU_APPROVAL', 'HAMPIR_KEDALUWARSA', 'DIPINJAM', 'IN PROGRESS' => 'amber',
    'KEDALUWARSA', 'DITOLAK', 'RUSAK_BERAT', 'OVERDUE', 'GAGAL' => 'rose',
    'DRAFT', 'DALAM_PENGIRIMAN', 'UJI_FUNGSI', 'SEDANG_DIKERJAKAN', 'IN REVIEW' => 'sky',
    default => 'zinc',
};
@endphp

<flux:badge size="sm" :color="$color" {{ $attributes }}>
    {{ str_replace('_', ' ', strtoupper($status ?? '')) }}
</flux:badge>
