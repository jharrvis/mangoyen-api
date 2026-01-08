<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            padding: 40px;
            color: #333;
            font-size: 12px;
        }

        .invoice-box {
            max-width: 600px;
            margin: auto;
            border: 1px solid #eee;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 3px solid #FF7A45;
            padding-bottom: 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #FF7A45;
        }

        .logo-subtitle {
            color: #666;
            font-size: 10px;
            margin-top: 5px;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h2 {
            color: #333;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .invoice-title p {
            color: #666;
            font-size: 10px;
        }

        .invoice-number {
            font-family: monospace;
            font-size: 11px;
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
            margin: 15px 0;
        }

        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .info-grid {
            margin-bottom: 30px;
        }

        .info-grid table {
            width: 100%;
        }

        .info-grid td {
            width: 50%;
            vertical-align: top;
            padding: 10px;
        }

        .info-box {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 8px;
        }

        .info-box h4 {
            color: #666;
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .info-box p {
            font-size: 12px;
            line-height: 1.6;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background: #f8f8f8;
            padding: 12px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 2px solid #eee;
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .total-row {
            font-weight: bold;
            font-size: 16px;
        }

        .total-row td {
            border-top: 2px solid #333;
            padding-top: 15px;
        }

        .payment-info {
            background: #e0f2fe;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .payment-info h4 {
            color: #0369a1;
            font-size: 11px;
            margin-bottom: 10px;
        }

        .payment-info table {
            width: 100%;
            font-size: 11px;
        }

        .payment-info td {
            padding: 3px 0;
        }

        .payment-info .label {
            color: #0284c7;
        }

        .payment-info .value {
            text-align: right;
            font-weight: bold;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        .text-orange {
            color: #FF7A45;
        }
    </style>
</head>

<body>
    <div class="invoice-box">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="logo">🐱 MangOyen</div>
                <div class="logo-subtitle">Platform Adopsi Kucing Indonesia</div>
            </div>
            <div class="invoice-title">
                <h2>INVOICE</h2>
                <p class="invoice-number">{{ $invoiceNumber }}</p>
                <p>{{ $invoiceDate }}</p>
            </div>
        </div>

        <!-- Status Badge -->
        <div>
            @if($isPaid)
                <span class="status-badge status-paid">✓ LUNAS</span>
            @else
                <span class="status-badge status-pending">BELUM LUNAS</span>
            @endif
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <table>
                <tr>
                    <td>
                        <div class="info-box">
                            <h4>Adopter</h4>
                            <p><strong>{{ $adopterName }}</strong></p>
                            <p>{{ $adopterPhone }}</p>
                            <p>{{ $adopterEmail }}</p>
                        </div>
                    </td>
                    <td>
                        <div class="info-box">
                            <h4>Shelter</h4>
                            <p><strong>{{ $shelterName }}</strong></p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Biaya Adopsi: {{ $catName }}</strong><br>
                        <span style="color: #666; font-size: 11px;">{{ $catBreed }} • {{ $catAge }}</span>
                    </td>
                    <td class="text-right">{{ $amount }}</td>
                </tr>
                <tr class="total-row">
                    <td><strong>TOTAL</strong></td>
                    <td class="text-right text-orange"><strong>{{ $amount }}</strong></td>
                </tr>
            </tbody>
        </table>

        <!-- Payment Info -->
        @if($isPaid)
            <div class="payment-info">
                <h4>Informasi Pembayaran</h4>
                <table>
                    @if($paymentMethod)
                        <tr>
                            <td class="label">Metode Pembayaran</td>
                            <td class="value">{{ $paymentMethod }}</td>
                        </tr>
                    @endif
                    @if($paidAt)
                        <tr>
                            <td class="label">Tanggal Pembayaran</td>
                            <td class="value">{{ $paidAt }}</td>
                        </tr>
                    @endif
                    @if($transactionId)
                        <tr>
                            <td class="label">ID Transaksi</td>
                            <td class="value" style="font-family: monospace; font-size: 10px;">{{ $transactionId }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>Terima kasih telah mengadopsi melalui MangOyen! 🐱</p>
            <p style="margin-top: 5px;">mangoyen.com • support@mangoyen.com</p>
            <p style="margin-top: 10px; font-size: 9px; color: #bbb;">
                Invoice ini dibuat secara otomatis dan sah tanpa tanda tangan.
            </p>
        </div>
    </div>
</body>

</html>