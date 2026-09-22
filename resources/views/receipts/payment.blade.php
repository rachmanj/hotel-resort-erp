<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tanda Terima Pembayaran {{ $receipt['number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #262626; }
        .header { text-align: center; margin-bottom: 20px; }
        .header .company { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .header .title { font-size: 14px; margin-top: 6px; font-weight: bold; }
        .header .subtitle { font-size: 11px; color: #595959; font-style: italic; }
        .no { margin-bottom: 14px; }
        .no .label { color: #595959; }
        table.fields { width: 100%; border-collapse: collapse; }
        table.fields td { padding: 6px 4px; vertical-align: top; border-bottom: 1px dotted #bfbfbf; }
        table.fields td.label { width: 190px; color: #595959; }
        table.fields td.label .id { display: block; color: #262626; }
        table.fields td.label .en { display: block; font-style: italic; font-size: 10px; }
        .amount { font-size: 13px; font-weight: bold; }
        .words { font-style: italic; margin-top: 2px; }
        .summary { width: 60%; margin-top: 18px; border-collapse: collapse; }
        .summary td { padding: 4px 6px; }
        .summary .value { text-align: right; }
        .summary .outstanding td { font-weight: bold; border-top: 1px solid #d9d9d9; }
        .methods { margin-top: 18px; }
        .methods .box { display: inline-block; border: 1px solid #262626; width: 10px; height: 10px; text-align: center; line-height: 10px; font-size: 9px; margin-right: 6px; }
        .methods .option { display: inline-block; margin-right: 28px; }
        .signatures { width: 100%; margin-top: 40px; border-collapse: collapse; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; }
        .signatures .name { margin-top: 58px; border-top: 1px solid #262626; display: inline-block; padding-top: 4px; min-width: 170px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $company['name'] }}</div>
        <div class="title">TANDA TERIMA PEMBAYARAN</div>
        <div class="subtitle">Payment Receipt</div>
    </div>

    <div class="no">
        <span class="label">No.</span> : <strong>{{ $receipt['number'] }}</strong>
        <span style="float: right;">{{ $receipt['date'] }}</span>
    </div>

    <table class="fields">
        <tr>
            <td class="label">
                <span class="id">Sudah Terima Dari</span>
                <span class="en">Received From</span>
            </td>
            <td>: {{ $receipt['received_from'] }}</td>
        </tr>
        <tr>
            <td class="label">
                <span class="id">Jumlah Diterima</span>
                <span class="en">Amount Received</span>
            </td>
            <td>
                <div class="amount">: Rp {{ number_format($receipt['amount'], 0, ',', '.') }}</div>
                <div class="words">{{ $receipt['amount_in_words'] }}</div>
            </td>
        </tr>
        <tr>
            <td class="label">
                <span class="id">Untuk Pembayaran</span>
                <span class="en">In Payment Of</span>
            </td>
            <td>: {{ $receipt['in_payment_of'] }}</td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>Proforma Invoice {{ $proforma['number'] }}</td>
            <td class="value">Rp {{ number_format($proforma['total'], 0, ',', '.') }}</td>
        </tr>
        <tr class="outstanding">
            <td>Outstanding Amount</td>
            <td class="value">Rp {{ number_format($proforma['outstanding_total'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="methods">
        <span class="option"><span class="box">{{ $receipt['is_cash'] ? 'X' : '' }}</span>Cash</span>
        <span class="option"><span class="box">{{ $receipt['is_transfer'] ? 'X' : '' }}</span>Transfer Bank</span>
        @if (! empty($receipt['reference_no']))
        <span class="option">Ref. {{ $receipt['reference_no'] }}</span>
        @endif
    </div>

    <table class="signatures">
        <tr>
            <td>
                Finance,
                <div class="name">&nbsp;</div>
            </td>
            <td>
                Accounting,
                <div class="name">&nbsp;</div>
            </td>
        </tr>
    </table>
</body>
</html>
