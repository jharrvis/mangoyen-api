@extends('emails.layout')

@section('content')
    <h2>Halo {{ $receiverName }},</h2>
    <p>Kami telah menerima pembayaran untuk adopsi <span class="highlight">{{ $catName }}</span>! 💰</p>

    <p>Dana saat ini <span class="highlight">AMAN</span> di sistem Rekber MangOyen. Shelter akan segera memproses pengiriman
        anabul dalam waktu maksimal 3 hari kerja.</p>

    <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p><strong>ID Transaksi:</strong> {{ $transactionId }}</p>
        <p><strong>Jumlah:</strong> Rp {{ number_format($amount, 0, ',', '.') }}</p>
    </div>

    <p>Tetap pantau chat di MangOyen untuk update pengiriman dari shelter.</p>

    <div style="text-align: center;">
        <a href="{{ $actionUrl }}" class="button">Lihat Status Adopsi</a>
    </div>
@endsection