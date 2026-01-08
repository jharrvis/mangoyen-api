@extends('emails.layout')

@section('content')
    <h2>Yeay! Adopsi Selesai! 🎉</h2>
    <p>Selamat kepada {{ $adopterName }} yang resmi menjadi babu baru bagi <span class="highlight">{{ $catName }}</span>!
    </p>

    <p>Dan terima kasih kepada {{ $shelterName }} yang telah merawat anabul ini dengan baik sebelumnya.</p>

    <p>Karena konfirmasi penerimaan sudah dilakukan, dana adopsi telah kami teruskan ke pihak shelter. Kami sangat senang
        bisa menjadi perantara kebahagiaan ini.</p>

    <p>Jangan lupa bagikan momen bahagiamu bersama {{ $catName }} di sosial media dan tag MangOyen ya! 🐾✨</p>

    <div style="text-align: center;">
        <a href="{{ $actionUrl }}" class="button">Tulis Testimoni</a>
    </div>
@endsection