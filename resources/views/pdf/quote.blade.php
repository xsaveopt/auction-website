<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 13px; padding: 40px; }
        .header { border-bottom: 3px solid #2563eb; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { font-size: 24px; color: #1e3a5f; margin-bottom: 4px; }
        .header .subtitle { color: #6b7280; font-size: 12px; }
        .section { margin-bottom: 24px; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 8px; font-weight: 600; }
        .card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; }
        .auction-title { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 4px; }
        .auction-desc { color: #4b5563; font-size: 12px; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; padding: 8px 12px; border-bottom: 2px solid #e5e7eb; }
        td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; }
        .total-row td { border-top: 2px solid #d1d5db; border-bottom: none; font-weight: 700; font-size: 15px; }
        .amount { font-family: DejaVu Sans, monospace; }
        .highlight { color: #059669; font-weight: 700; }
        .badge { display: inline-block; background: #dcfce7; color: #166534; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
        .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 10px; }
        .meta-grid { display: table; width: 100%; }
        .meta-row { display: table-row; }
        .meta-label { display: table-cell; width: 120px; color: #6b7280; font-size: 12px; padding: 3px 0; }
        .meta-value { display: table-cell; font-size: 12px; font-weight: 600; padding: 3px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Auction Quote</h1>
        <div class="subtitle">Generated {{ $generated_at }}</div>
    </div>

    <div class="section">
        <div class="section-title">Auction Details</div>
        <div class="card">
            <div class="auction-title">{{ $auction['title'] }}</div>
            <div class="auction-desc">{{ $auction['description'] }}</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Winner</div>
        <div class="meta-grid">
            <div class="meta-row">
                <div class="meta-label">Bidder</div>
                <div class="meta-value">{{ $winner['username'] }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">Bid Placed</div>
                <div class="meta-value amount">{{ $currency }}{{ number_format($winner['bid_amount'], 2) }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">Auction Ended</div>
                <div class="meta-value">{{ $auction['ends_at'] }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">Status</div>
                <div class="meta-value"><span class="badge">Winner</span></div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Allocation</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $auction['title'] }}</td>
                    <td>{{ $winner['won_quantity'] }}</td>
                    <td style="text-align: right;" class="amount">{{ $currency }}{{ number_format($clearing_price, 2) }}</td>
                    <td style="text-align: right;" class="amount">{{ $currency }}{{ number_format($winner['total_owed'], 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3">Total Due</td>
                    <td style="text-align: right;" class="amount highlight">{{ $currency }}{{ number_format($winner['total_owed'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        This quote was automatically generated from the auction system. Clearing price ({{ $currency }}{{ number_format($clearing_price, 2) }}) is the
        lowest winning bid &mdash; all winners pay the same unit price regardless of their individual bid amount.
    </div>
</body>
</html>
