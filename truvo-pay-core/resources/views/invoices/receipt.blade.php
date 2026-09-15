<!DOCTYPE html>
<html lang="bn">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', 'SolaimanLipi', 'Noto Sans Bengali', sans-serif;
            font-size: 13px;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 30px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .company-title {
            font-size: 22px;
            font-weight: bold;
            color: #4338ca;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            text-align: right;
            color: #0f172a;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .meta-col {
            vertical-align: top;
            width: 50%;
        }
        .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .value {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #475569;
            text-align: left;
            padding: 10px 14px;
            font-size: 11px;
            text-transform: uppercase;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .items-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .total-table {
            width: 40%;
            float: right;
            margin-bottom: 30px;
        }
        .total-table td {
            padding: 6px 10px;
        }
        .grand-total {
            font-size: 16px;
            font-weight: bold;
            color: #4338ca;
            border-top: 2px solid #cbd5e1;
        }
        .badge-paid {
            display: inline-block;
            background-color: #dcfce7;
            color: #15803d;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .footer {
            clear: both;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="company-title">{{ $company_name }}</div>
                <div style="color: #64748b; font-size: 11px;">{{ $company_address }}</div>
            </td>
            <td style="text-align: right;">
                <div class="invoice-title">INVOICE / চালান</div>
                <div class="value">#{{ $invoice_number }}</div>
                <div style="margin-top: 5px;">
                    <span class="badge-paid">PAID / পরিশোধিত</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-col">
                <div class="label">Billed To / গ্রাহক:</div>
                <div class="value">{{ $transaction->customer_name ?: 'Valued Customer' }}</div>
                @if($transaction->customer_email)
                    <div>{{ $transaction->customer_email }}</div>
                @endif
                @if($transaction->customer_phone)
                    <div>{{ $transaction->customer_phone }}</div>
                @endif
            </td>
            <td class="meta-col" style="text-align: right;">
                <div class="label">Invoice Date / তারিখ:</div>
                <div class="value">{{ $date }}</div>
                <div class="label" style="margin-top: 8px;">Truvo Reference / ট্রুভো রেফারেন্স:</div>
                <div class="value">{{ $transaction->truvo_reference }}</div>
                <div class="label" style="margin-top: 8px;">Gateway Transaction ID / TrxID:</div>
                <div class="value">{{ $transaction->transaction_id ?: 'Verified by AI' }}</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description / বিবরণ</th>
                <th>Order # / রেফারেন্স</th>
                <th>Gateway / মাধ্যম</th>
                <th style="text-align: right;">Amount / পরিমাণ</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Payment for Order #{{ $transaction->merchant_order_id }}</td>
                <td>{{ $transaction->merchant_order_id }}</td>
                <td>{{ $transaction->gateway_key }}</td>
                <td style="text-align: right; font-weight: bold;">
                    {{ $currency_symbol }} {{ number_format($transaction->amount, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <table class="total-table">
        <tr>
            <td>Subtotal:</td>
            <td style="text-align: right;">{{ $currency_symbol }} {{ number_format($transaction->amount, 2) }}</td>
        </tr>
        <tr>
            <td>Tax / VAT:</td>
            <td style="text-align: right;">{{ $currency_symbol }} 0.00</td>
        </tr>
        <tr class="grand-total">
            <td>Total Paid:</td>
            <td style="text-align: right;">{{ $currency_symbol }} {{ number_format($transaction->amount, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        This is a computer-generated tax invoice verified by Truvo Pay AI Verification Engine. Thank you for your business!
    </div>
</body>
</html>
