<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $folio['folio_no'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .meta { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .text-right { text-align: right; }
        .totals { margin-top: 16px; width: 40%; margin-left: auto; }
        .totals td { border: none; padding: 4px 8px; }
        .totals .label { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Invoice</h1>
    <div class="meta">
        <strong>Folio:</strong> {{ $folio['folio_no'] }}<br>
        <strong>Guest:</strong> {{ $folio['guest']['full_name'] ?? '—' }}<br>
        <strong>Reservation:</strong> {{ $folio['reservation']['reservation_code'] ?? '—' }}<br>
        <strong>Date:</strong> {{ $folio['opened_at'] }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Amount</th>
                <th class="text-right">SC</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($folio['items'] as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td class="text-right">{{ number_format($item['quantity'], 0) }}</td>
                <td class="text-right">{{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item['amount'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item['service_charge_amount'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item['tax_amount'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item['line_total'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Charges Total</td>
            <td class="text-right">Rp {{ number_format($charges_total, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Balance Due</td>
            <td class="text-right">Rp {{ number_format($balance, 0, ',', '.') }}</td>
        </tr>
    </table>
</body>
</html>
