@extends('emails.layout')

@section('content')
    <h2 style="color: #1a1a1a; font-size: 20px; margin-bottom: 20px;">
        Halo {{ $recipientName }}! 👋
    </h2>

    <p style="color: #666; font-size: 14px; line-height: 1.6;">
        @if($recipientType === 'adopter')
            Terima kasih telah melakukan pembayaran untuk adopsi <strong>{{ $catName }}</strong>.
            Berikut terlampir invoice/tanda terima pembayaran Anda.
        @else
            Pembayaran untuk adopsi <strong>{{ $catName }}</strong> telah berhasil dikonfirmasi.
            Berikut terlampir invoice/bukti pembayaran dari adopter.
        @endif
    </p>

    <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 20px 0;">
        <table width="100%" style="font-size: 14px;">
            <tr>
                <td style="padding: 8px 0; color: #666;">Nomor Invoice</td>
                <td style="padding: 8px 0; font-weight: bold; font-family: monospace;">{{ $invoiceNumber }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #666;">Kucing</td>
                <td style="padding: 8px 0; font-weight: bold;">{{ $catName }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #666;">Total</td>
                <td style="padding: 8px 0; font-weight: bold; color: #FF7A45;">{{ $amount }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #666;">Status</td>
                <td style="padding: 8px 0;">
                    @if($isPaid)
                        <span
                            style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 12px;">
                            ✓ LUNAS
                        </span>
                    @else
                        <span
                            style="background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 12px;">
                            BELUM LUNAS
                        </span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <p style="color: #666; font-size: 14px; line-height: 1.6;">
        @if($recipientType === 'adopter')
            Silakan simpan invoice ini sebagai bukti pembayaran. Anda juga bisa mengunduh invoice melalui dashboard MangOyen.
        @else
            Silakan proses pengiriman kucing ke adopter. Dana pembayaran saat ini aman di Rekber MangOyen dan akan dilepas
            setelah adopter mengkonfirmasi penerimaan.
        @endif
    </p>

    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ config('app.frontend_url') }}/dashboard"
            style="background: #FF7A45; color: white; padding: 14px 30px; border-radius: 12px; text-decoration: none; font-weight: bold; display: inline-block;">
            Buka Dashboard
        </a>
    </div>

    <p style="color: #999; font-size: 12px; margin-top: 30px; text-align: center;">
        📎 Invoice PDF terlampir dalam email ini.
    </p>
@endsection