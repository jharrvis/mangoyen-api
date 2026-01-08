@extends('emails.layout')

@section('content')
    <h2>Selamat {{ $receiverName }}! 🎉</h2>
    <p>Pengajuan adopsimu untuk <span class="highlight">{{ $catName }}</span> telah <span class="highlight">DISETUJUI</span>
        oleh shelter.</p>

    <p>Langkah selanjutnya adalah melakukan pembayaran biaya adopsi untuk mengamankan anabulmu. Uang akan ditahan di sistem
        Rekber MangOyen sampai kamu mengonfirmasi bahwa anabul sudah diterima dengan sehat.</p>

    <div style="text-align: center;">
        <a href="{{ $actionUrl }}" class="button">Bayar Sekarang</a>
    </div>

    <p>Mohon selesaikan pembayaran dalam waktu 48 jam agar pengajuan tidak otomatis dibatalkan.</p>

    <p>Meow! Kami sudah tidak sabar melihatmu bertemu dengan {{ $catName }}! 🐾</p>
@endsection