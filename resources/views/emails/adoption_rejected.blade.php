@extends('emails.layout')

@section('content')
    <h2>Halo {{ $receiverName }},</h2>
    <p>Terima kasih telah menunjukkan ketertarikan untuk mengadopsi <span class="highlight">{{ $catName }}</span>.</p>

    <p>Mohon maaf, saat ini pengajuan adopsimu <span class="highlight">BELUM BISA DISETUJUI</span> oleh shelter.</p>

    @if($reason)
        <p><strong>Alasan dari shelter:</strong><br>
            <em>"{{ $reason }}"</em>
        </p>
    @endif

    <p>Jangan patah semangat ya! Masih banyak anabul lucu lainnya di MangOyen yang menanti rumah baru. Mungkin jodohmu ada
        di anabul yang lain. 🐱</p>

    <div style="text-align: center;">
        <a href="{{ $actionUrl }}" class="button">Cari Anabul Lain</a>
    </div>
@endsection