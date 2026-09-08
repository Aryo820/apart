@props(['status'])

@php
    use App\Enums\BookingStatus;

    // Copy Indonesia + warna semantik hidup di layer presentasi; enum-nya
    // tetap satu sumber kebenaran status dan dipakai apa adanya oleh Filament.
    // Warna = tinta stempel di atas kertas.
    [$label, $classes, $dot] = match ($status) {
        BookingStatus::Confirmed => ['Terkonfirmasi', 'border-emerald-700 bg-paper-50 text-emerald-800', 'bg-emerald-600'],
        BookingStatus::Pending => ['Menunggu pembayaran', 'border-amber-700 bg-paper-50 text-amber-800', 'bg-amber-500 animate-pulse'],
        BookingStatus::Cancelled => ['Dibatalkan', 'border-dimension-600 bg-dimension-50 text-dimension-700', 'bg-dimension-500'],
        // Expired bukan kegagalan tamu maupun pembatalan — netral, bukan merah.
        BookingStatus::Expired => ['Kedaluwarsa', 'border-ink-500 bg-paper-50 text-ink-500', 'bg-ink-400'],
        BookingStatus::Completed => ['Selesai', 'border-ink-400 bg-paper-50 text-ink-600', 'bg-ink-300'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'stamp ' . $classes]) }}>
    <span class="h-1.5 w-1.5 shrink-0 {{ $dot }}" aria-hidden="true"></span>
    {{ $label }}
</span>
