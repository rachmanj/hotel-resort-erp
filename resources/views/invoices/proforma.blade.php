<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Proforma Invoice {{ $proforma['number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #262626; }
        .header { text-align: center; margin-bottom: 18px; }
        .header .company { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .header .title { font-size: 14px; margin-top: 4px; }
        .meta { width: 100%; margin-bottom: 14px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 90px; color: #595959; }
        .meta .right { text-align: right; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #d9d9d9; padding: 6px; }
        table.lines th { background: #fafafa; text-align: left; }
        table.lines td.num, table.lines th.num { text-align: right; }
        .note { font-style: italic; color: #595959; border-top: none; }
        .totals { width: 45%; margin-left: auto; margin-top: 12px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; }
        .totals .label { color: #595959; }
        .totals .value { text-align: right; }
        .totals .grand td { font-weight: bold; border-top: 1px solid #d9d9d9; }
        .payment { margin-top: 24px; }
        .payment h3 { font-size: 12px; margin: 0 0 6px; }
        .payment table { border-collapse: collapse; margin-bottom: 8px; }
        .payment table td { padding: 2px 10px 2px 0; }
        .payment ul { margin: 0; padding-left: 16px; }
        .payment li { margin-bottom: 2px; }
        .prepared { margin-top: 36px; }
        .prepared .name { margin-top: 42px; border-top: 1px solid #d9d9d9; display: inline-block; padding-top: 4px; min-width: 180px; }
        .draft { margin-top: 10px; text-align: center; color: #d46b08; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $company['name'] }}</div>
        <div class="title">{{ $company['title'] }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Customer ID</td>
            <td>: {{ $customer['id'] ?? '-' }}</td>
            <td class="label right">No.</td>
            <td class="right">{{ $proforma['number'] }}</td>
        </tr>
        <tr>
            <td class="label">Customer Name</td>
            <td>: {{ $customer['name'] ?? '-' }}</td>
            <td class="label right">Date</td>
            <td class="right">{{ $proforma['date'] }}</td>
        </tr>
        <tr>
            <td class="label">Contact</td>
            <td>: {{ $customer['contact'] ?? '-' }}</td>
            <td class="label right">Revision</td>
            <td class="right">{{ $proforma['revision'] }}</td>
        </tr>
        <tr>
            <td class="label">Date of Stay</td>
            <td>: {{ $proforma['date_of_stay'] }}</td>
            <td class="label right">Reservation</td>
            <td class="right">{{ $reservation['reservation_code'] }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Rate</th>
                <th class="num">Qty</th>
                <th class="num">Ns</th>
                <th class="num">Price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($proforma['lines'] as $line)
            <tr>
                <td>{{ $line['description'] }}</td>
                <td class="num">{{ number_format($line['unit_price'], 0, ',', '.') }}</td>
                <td class="num">{{ $line['quantity'] }}</td>
                <td class="num">{{ $line['nights'] }}</td>
                <td class="num">{{ number_format($line['stay_price'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($line['amount'], 0, ',', '.') }}</td>
            </tr>
            @if (! empty($line['note']))
            <tr>
                <td class="note" colspan="6">{{ $line['note'] }}</td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Total</td>
            <td class="value">Rp {{ number_format($proforma['subtotal'], 0, ',', '.') }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Purchase Total</td>
            <td class="value">Rp {{ number_format($proforma['total'], 0, ',', '.') }}</td>
        </tr>
        @if ($proforma['received_total'] > 0)
        <tr>
            <td class="label">Received</td>
            <td class="value">Rp {{ number_format($proforma['received_total'], 0, ',', '.') }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Outstanding</td>
            <td class="value">Rp {{ number_format($proforma['outstanding_total'], 0, ',', '.') }}</td>
        </tr>
        @endif
    </table>

    <div class="payment">
        <h3>Payment Details</h3>
        <table>
            @foreach ($payment_details['bank_accounts'] as $account)
            <tr>
                <td>{{ $account['bank_name'] }}</td>
                <td>{{ $account['account_no'] }}</td>
                <td>{{ $account['account_name'] }}</td>
            </tr>
            @endforeach
        </table>
        <ul>
            @foreach ($payment_details['terms'] as $term)
            <li>{{ $term }}</li>
            @endforeach
        </ul>
    </div>

    <div class="prepared">
        Prepared by,
        <div class="name">{{ $proforma['prepared_by'] ?? '-' }}</div>
    </div>

    @if ($proforma['status'] !== 'released')
    <div class="draft">DRAFT, NOT YET RELEASED BY FINANCE</div>
    @endif
</body>
</html>
