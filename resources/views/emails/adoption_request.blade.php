@extends('emails.layout')

@section('content')
    <h2>Hai {{ $receiverName }},</h2>
    <p>Ada kabar gembira! 🐱</p>
    <p>Seseorang tertarik untuk mengadopsi <span class="highlight">{{ $catName }}</span>. Berikut adalah detail singkat
        pengajuannya:</p>

    <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p><strong>Nama Adopter:</strong> {{ $adopterName }}</p>
        <p><strong>Lokasi:</strong> {{ $adopterCity }}</p>
    </div>

    <p>Silakan buka dashboard MangOyen untuk melakukan interview dan memproses pengajuan ini.</p>

    <div style="text-align: center;">
        <a href="{{ $actionUrl }}" class="button">Lihat Pengajuan</a>
    </div>

    <p>Semoga {{ $catName }} segera mendapatkan rumah baru yang penuh kasih sayang! 🐾</p>
@endsection