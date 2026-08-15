@props(['status'])

@php
    use App\Enums\BookingStatus;

    // Copy Indonesia + warna semantik hidup di layer presentasi; enum-nya
    // tetap satu sumber kebenaran status dan dipakai apa adanya oleh Filament.
    [$label, $classes, $dot] = match ($status) {
        BookingStatus::Confirmed => ['Terkonfirmasi', 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300', 'bg-emerald-400'],
        BookingStatus::Pending => ['Menunggu pembayaran', 'border-amber-400/30 bg-amber-500/10 text-amber-300', 'bg-amber-400 animate-pulse'],
        BookingStatus::Cancelled => ['Dibatalkan', 'border-rose-400/30 bg-rose-500/10 text-rose-300', 'bg-rose-400'],
        BookingStatus::Completed => ['Selesai', 'border-white/15 bg-white/5 text-ink-200', 'bg-ink-300'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.12em] ' . $classes]) }}>
    <span class="h-1.5 w-1.5 shrink-0 {{ $dot }}" aria-hidden="true"></span>
    {{ $label }}
</span>
