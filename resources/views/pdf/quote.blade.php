<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #333333;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
    </style>
</head>
<body>

    {{-- Header bar --}}
    <table cellpadding="0" cellspacing="0" style="background-color: #1e3e50; color: #ffffff;">
        <tr>
            <td style="width: 25%; padding: 22px 15px 22px 50px; vertical-align: middle;">
                <span style="font-size: 22px; font-weight: bold; letter-spacing: 0.5px;">{{ $company['name'] }}</span>
            </td>
            <td style="width: 25%; padding: 22px 15px; vertical-align: top; font-size: 8.5px; line-height: 1.6;">
                <span style="font-size: 9px; font-weight: bold;">Bezoekadres</span><br>
                {{ $company['name'] }}<br>
                {{ $company['street'] }}<br>
                {{ $company['postal_code'] }} {{ $company['city'] }}
            </td>
            <td style="width: 25%; padding: 22px 15px; vertical-align: top; font-size: 8.5px; line-height: 1.6;">
                <span style="font-size: 9px; font-weight: bold;">Zakelijk</span><br>
                KvK {{ $company['kvk'] }}<br>
                BTW {{ $company['btw'] }}
            </td>
            <td style="width: 25%; padding: 22px 15px 22px 15px; vertical-align: top; font-size: 8.5px; line-height: 1.6;">
                <span style="font-size: 9px; font-weight: bold;">Financieel</span><br>
                {{ $company['iban_1'] }}<br>
                {{ $company['iban_2'] }}
            </td>
        </tr>
    </table>

    {{-- Content --}}
    <div style="padding: 40px 50px 120px 50px;">

        {{-- Recipient --}}
        <div style="margin-bottom: 40px;">
            <div style="font-size: 8.5px; color: #999999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
                {{ $company['name'] }}
            </div>
            <div style="font-size: 11px; font-weight: bold; color: #111111; line-height: 1.6;">
                {{ $winner['username'] }}
            </div>
        </div>

        {{-- Title --}}
        <div style="font-size: 28px; font-weight: bold; color: #111111; margin-bottom: 20px;">
            Leverbon
        </div>

        {{-- Details --}}
        <table style="margin-bottom: 50px;">
            <tr>
                <td style="width: 180px; padding: 3px 0; font-size: 10px;">Datum:</td>
                <td style="padding: 3px 0; font-size: 10px;">{{ $generated_at }}</td>
            </tr>
            @if(count($items) === 1)
            <tr>
                <td style="padding: 3px 0; font-size: 10px;">Artikel:</td>
                <td style="padding: 3px 0; font-size: 10px;">{{ $items[0]['title'] }}</td>
            </tr>
            @endif
            @if(isset($payment_reference))
            <tr>
                <td style="padding: 3px 0; font-size: 10px;">Kenmerk:</td>
                <td style="padding: 3px 0; font-size: 10px; font-weight: bold;">{{ $payment_reference }}</td>
            </tr>
            @endif
        </table>

        {{-- Line items --}}
        <table>
            <tr>
                <td style="font-weight: bold; font-size: 10px; padding: 8px 0; border-bottom: 2px solid #c0392b;">
                    Omschrijving
                </td>
                <td style="font-weight: bold; font-size: 10px; padding: 8px 0; border-bottom: 2px solid #c0392b; text-align: right; width: 120px;">
                    Totaal
                </td>
            </tr>
            @foreach($items as $item)
            <tr>
                <td style="padding: 12px 0; border-bottom: 1px solid #eeeeee; font-size: 10px;">
                    {{ $item['title'] }}
                    @if($item['quantity'] > 1)
                        <br><span style="color: #666666; font-size: 9px;">{{ $item['quantity'] }}&times; {{ $currency }} {{ number_format($item['price_per_item'], 2, ',', '.') }}</span>
                    @endif
                </td>
                <td style="padding: 12px 0; border-bottom: 1px solid #eeeeee; text-align: right; font-size: 10px; white-space: nowrap; vertical-align: top;">
                    {{ $currency }} {{ number_format($item['total'], 2, ',', '.') }}
                </td>
            </tr>
            @endforeach
        </table>

        <br>

        {{-- Totals --}}
        <table>
            <tr>
                <td></td>
                <td style="width: 240px; padding: 4px 0; font-weight: bold; font-size: 10px; text-align: right; padding-right: 15px;">
                    Totaal excl. BTW
                </td>
                <td style="width: 15px; text-align: right; font-size: 10px; padding: 4px 0;">{{ $currency }}</td>
                <td style="width: 80px; text-align: right; font-size: 10px; padding: 4px 0;">
                    {{ number_format($subtotal, 2, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td></td>
                <td style="padding: 4px 0; font-size: 10px; text-align: right; padding-right: 15px;">
                    <strong>{{ $btw_percentage }}% BTW</strong>
                    &nbsp;&nbsp;<span style="color: #666666;">over {{ $currency }} {{ number_format($subtotal, 2, ',', '.') }}</span>
                </td>
                <td style="text-align: right; font-size: 10px; padding: 4px 0;">{{ $currency }}</td>
                <td style="text-align: right; font-size: 10px; padding: 4px 0;">
                    {{ number_format($btw_amount, 2, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td></td>
                <td style="padding: 4px 0; font-weight: bold; font-size: 10px; text-align: right; padding-right: 15px;">
                    Totaal te voldoen
                </td>
                <td style="text-align: right; font-size: 10px; padding: 4px 0;">{{ $currency }}</td>
                <td style="text-align: right; font-size: 10px; padding: 4px 0;">
                    {{ number_format($total, 2, ',', '.') }}
                </td>
            </tr>
        </table>

        {{-- Note --}}
        <div style="margin-top: 50px; font-size: 10px; line-height: 1.6;">
            Dit document is een leverbon en geldt niet als factuur.
        </div>

        @if(isset($payment_reference))
        <div style="margin-top: 10px; font-size: 10px; line-height: 1.6;">
            Gelieve bij uw overboeking het kenmerk <strong>{{ $payment_reference }}</strong> te vermelden.
        </div>
        @endif

        <div style="margin-top: 20px; font-size: 10px;">
            {{ $company['name'] }}
        </div>

    </div>

    {{-- Footer --}}
    <div style="position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #cccccc; padding: 15px 50px;">
        <table>
            <tr>
                <td style="font-weight: bold; font-size: 9px;">{{ $company['name'] }}</td>
                <td style="text-align: right; font-size: 9px;">Pagina 1</td>
            </tr>
        </table>
    </div>

</body>
</html>
