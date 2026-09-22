<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice['number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #262626; }
        .header { text-align: center; margin-bottom: 18px; }
        .header .company { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .header .title { font-size: 15px; font-weight: bold; letter-spacing: 3px; margin-top: 4px; }
        .meta { width: 100%; margin-bottom: 16px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 110px; color: #595959; }
        .terms { margin-bottom: 16px; border: 1px solid #d9d9d9; padding: 8px 10px; }
        .terms h3 { font-size: 11px; margin: 0 0 6px; }
        .terms table { border-collapse: collapse; margin-bottom: 6px; }
        .terms table td { padding: 1px 12px 1px 0; }
        .terms ol { margin: 0; padding-left: 16px; }
        .terms li { margin-bottom: 2px; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #d9d9d9; padding: 6px; }
        table.lines th { background: #fafafa; text-align: left; }
        table.lines td.num, table.lines th.num { text-align: right; }
        table.lines tr.grand td { font-weight: bold; background: #fafafa; }
        .signatures { width: 100%; margin-top: 48px; border-collapse: collapse; }
        .signatures td { width: 33%; text-align: center; vertical-align: bottom; }
        .signatures .line { margin-top: 56px; border-top: 1px solid #595959; padding-top: 4px; }
        .draft { margin-top: 14px; text-align: center; color: #d46b08; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $company['name'] }}</div>
        <div class="title">{{ $company['title'] }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Date</td>
            <td>: {{ $invoice['date'] }}</td>
        </tr>
        <tr>
            <td class="label">Invoice Number</td>
            <td>: {{ $invoice['number'] }}@if ($invoice['revision'] > 1) (Revision {{ $invoice['revision'] }})@endif</td>
        </tr>
        <tr>
            <td class="label">Customer ID</td>
            <td>: {{ $customer['id'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Customer Name</td>
            <td>: {{ $customer['name'] ?? '-' }}</td>
        </tr>
    </table>

    <div class="terms">
        <h3>{{ $terms['title'] }}</h3>
        <table>
            @foreach ($terms['bank_accounts'] as $account)
            <tr>
                <td>{{ $account['bank_name'] }}</td>
                <td>{{ $account['account_no'] }}</td>
                <td>{{ $account['account_name'] }}</td>
            </tr>
            @endforeach
        </table>
        <ol>
            @foreach ($terms['items'] as $term)
            <li>{{ $term }}</li>
            @endforeach
        </ol>
    </div>

    @php
        $columnCount = $show_tax_columns ? 7 : 5;
    @endphp

    <table class="lines">
        <thead>
            <tr>
                <th>Rincian</th>
                <th class="num">QTY</th>
                <th class="num">Ns</th>
                <th class="num">Harga</th>
                @if ($show_tax_columns)
                <th class="num">SC</th>
                <th class="num">Tax</th>
                @endif
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice['lines'] as $line)
            <tr>
                <td>{{ $line['description'] }}</td>
                <td class="num">{{ number_format($line['quantity'], 0) }}</td>
                <td class="num">{{ $line['nights'] ?? '' }}</td>
                <td class="num">{{ number_format($line['unit_price'], 0, ',', '.') }}</td>
                @if ($show_tax_columns)
                <td class="num">{{ number_format($line['service_charge_amount'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($line['tax_amount'], 0, ',', '.') }}</td>
                @endif
                <td class="num">{{ number_format($line['line_total'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ $columnCount }}">No charges recorded yet.</td>
            </tr>
            @endforelse
            <tr class="grand">
                <td colspan="{{ $columnCount - 1 }}">Grand Total</td>
                <td class="num">Rp {{ number_format($invoice['total'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                {{ $signatures['prepared_by'] }},
                <div class="line">{{ $invoice['prepared_by'] ?? '-' }}</div>
            </td>
            <td>
                {{ $signatures['approved_by'] }},
                <div class="line">{{ $invoice['approved_by'] ?? '-' }}</div>
            </td>
            <td>
                {{ $signatures['received_by'] }},
                <div class="line">{{ $customer['name'] ?? '-' }}</div>
            </td>
        </tr>
    </table>

    @if ($invoice['status'] !== 'released')
    <div class="draft">DRAFT, NOT YET RELEASED BY FINANCE</div>
    @endif
</body>
</html>
