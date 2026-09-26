<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bank Reconciliation {{ $header['account_no'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #262626; }
        .header { text-align: center; margin-bottom: 18px; }
        .header .company { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .header .title { font-size: 14px; font-weight: bold; letter-spacing: 2px; margin-top: 4px; }
        .meta { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta .label { width: 140px; color: #595959; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th, table.lines td { border: 1px solid #d9d9d9; padding: 6px; }
        table.lines th { background: #fafafa; text-align: left; }
        table.lines td.num, table.lines th.num { text-align: right; }
        h3 { font-size: 12px; margin: 16px 0 6px; }
        .signatures { width: 100%; margin-top: 36px; border-collapse: collapse; }
        .signatures td { width: 33%; vertical-align: top; padding-right: 8px; }
        .signatures .line { margin-top: 48px; border-top: 1px solid #595959; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $company['name'] }}</div>
        <div class="title">{{ $company['title'] }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Bank account</td>
            <td>: {{ $header['bank_name'] }} — {{ $header['account_no'] }}</td>
        </tr>
        <tr>
            <td class="label">Period end</td>
            <td>: {{ $header['period_end_date'] }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td>: {{ $header['status'] }}@if($header['validation_status']) / {{ $header['validation_status'] }}@endif</td>
        </tr>
        <tr>
            <td class="label">Validator</td>
            <td>: {{ $header['validator_name'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Generated</td>
            <td>: {{ $header['generated_at'] }}</td>
        </tr>
    </table>

    <h3>Balance proof</h3>
    <table class="meta">
        <tr><td class="label">Statement closing</td><td>: Rp {{ number_format($balance_proof['statement_closing'], 2, ',', '.') }}</td></tr>
        <tr><td class="label">Deposits in transit</td><td>: Rp {{ number_format($balance_proof['deposits_in_transit'], 2, ',', '.') }}</td></tr>
        <tr><td class="label">Outstanding checks</td><td>: Rp {{ number_format($balance_proof['outstanding_checks'], 2, ',', '.') }}</td></tr>
        <tr><td class="label">Adjusted statement balance</td><td>: Rp {{ number_format($balance_proof['adjusted_statement_balance'], 2, ',', '.') }}</td></tr>
        <tr><td class="label">Book closing</td><td>: Rp {{ number_format($balance_proof['book_closing'], 2, ',', '.') }}</td></tr>
        <tr><td class="label">Unexplained difference</td><td>: Rp {{ number_format($balance_proof['unexplained_difference'], 2, ',', '.') }}</td></tr>
    </table>

    <h3>Open statement items</h3>
    <table class="lines">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Reference</th>
                <th>Status</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement_items as $item)
            <tr>
                <td>{{ $item['date'] ?? '—' }}</td>
                <td>{{ $item['description'] ?? '—' }}</td>
                <td>{{ $item['reference'] ?? '—' }}</td>
                <td>{{ $item['status'] }}</td>
                <td class="num">{{ number_format($item['amount'], 2, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="5">No open statement items.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Open book items</h3>
    <table class="lines">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Reference</th>
                <th>Status</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($book_items as $item)
            <tr>
                <td>{{ $item['date'] ?? '—' }}</td>
                <td>{{ $item['description'] ?? '—' }}</td>
                <td>{{ $item['reference'] ?? '—' }}</td>
                <td>{{ $item['status'] }}</td>
                <td class="num">{{ number_format($item['amount'], 2, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="5">No open book items.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Adjustments</h3>
    <table class="lines">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Journal no.</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($adjustments as $item)
            <tr>
                <td>{{ $item['date'] ?? '—' }}</td>
                <td>{{ $item['description'] ?? '—' }}</td>
                <td>{{ $item['journal_no'] ?? '—' }}</td>
                <td class="num">{{ number_format($item['amount'], 2, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="4">No adjustments posted.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <strong>Prepared by</strong><br>
                {{ $sign_off['prepared_by'] ?? '—' }}<br>
                <small>{{ $sign_off['prepared_at'] ?? '' }}</small>
                <div class="line">Signature</div>
            </td>
            <td>
                <strong>Submitted by</strong><br>
                {{ $sign_off['submitted_by'] ?? '—' }}<br>
                <small>{{ $sign_off['submitted_at'] ?? '' }}</small>
                <div class="line">Signature</div>
            </td>
            <td>
                <strong>Validated by</strong><br>
                {{ $sign_off['validated_by'] ?? '—' }}<br>
                <small>{{ $sign_off['validated_at'] ?? '' }}</small>
                <div class="line">Signature</div>
            </td>
        </tr>
    </table>
</body>
</html>
