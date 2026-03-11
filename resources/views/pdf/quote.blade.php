<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #333333;
            font-size: 11px;
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

    <table>
        <tr>
            <td style="font-size: 16px; font-weight: bold; color: #111111; padding-bottom: 50px;">
                Auction House
            </td>
            <td style="text-align: right; padding-bottom: 50px;">
                <span style="font-size: 24px; font-weight: bold; color: #111111;">Quote</span><br>
                <span style="color: #666666;">{{ $quote_number }}</span><br>
                <span style="color: #666666;">{{ $generated_at }}</span>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="padding-bottom: 40px; width: 50%;">
                <span style="color: #999999; font-size: 10px;">ISSUED TO</span><br>
                <span style="font-weight: bold; color: #111111; font-size: 12px;">{{ $winner['username'] }}</span>
            </td>
            <td style="padding-bottom: 40px; width: 50%; text-align: right;">
                <span style="color: #999999; font-size: 10px;">AUCTION ENDED</span><br>
                <span>{{ $auction['ends_at'] }}</span>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #111111; font-size: 10px; color: #666666; font-weight: bold;">Description</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #111111; font-size: 10px; color: #666666; font-weight: bold; text-align: right; width: 60px;">Qty</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #111111; font-size: 10px; color: #666666; font-weight: bold; text-align: right; width: 100px;">Unit price</td>
            <td style="padding: 8px 0; border-bottom: 1px solid #111111; font-size: 10px; color: #666666; font-weight: bold; text-align: right; width: 100px;">Amount</td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #eeeeee;">{{ $auction['title'] }}</td>
            <td style="padding: 12px 0; border-bottom: 1px solid #eeeeee; text-align: right;">{{ $winner['won_quantity'] }}</td>
            <td style="padding: 12px 0; border-bottom: 1px solid #eeeeee; text-align: right;">{{ $currency }}{{ number_format($clearing_price, 2) }}</td>
            <td style="padding: 12px 0; border-bottom: 1px solid #eeeeee; text-align: right;">{{ $currency }}{{ number_format($winner['total_owed'], 2) }}</td>
        </tr>
    </table>

    <br>

    <table>
        <tr>
            <td></td>
            <td style="width: 120px; padding: 6px 0; color: #666666;">Subtotal</td>
            <td style="width: 100px; padding: 6px 0; text-align: right;">{{ $currency }}{{ number_format($winner['total_owed'], 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td style="padding: 10px 0; font-weight: bold; color: #111111; font-size: 13px; border-top: 2px solid #111111;">Total due</td>
            <td style="padding: 10px 0; font-weight: bold; color: #111111; font-size: 13px; border-top: 2px solid #111111; text-align: right;">{{ $currency }}{{ number_format($winner['total_owed'], 2) }}</td>
        </tr>
    </table>

    <br><br><br>

    <table>
        <tr>
            <td style="border-top: 1px solid #eeeeee; padding-top: 15px; color: #999999; font-size: 10px;">
                Clearing price: {{ $currency }}{{ number_format($clearing_price, 2) }} per item.
                All winners pay the same unit price regardless of individual bid amount.
                <br>{{ $quote_number }}
            </td>
        </tr>
    </table>

</body>
</html>
