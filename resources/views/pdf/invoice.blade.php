<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $shipment->reference_no }}</title>
    <style>
        @page { margin: 30px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; line-height: 1.4; font-size: 13px; margin: 0; padding: 0; }
        .container { padding: 20px; }
        
        /* Header Section */
        .header { margin-bottom: 5px; min-height: 85px; }
        .header-logo { float: left; width: 35%; }
        .logo { max-height: 70px; max-width: 100%; object-fit: contain; }
        .header-info { float: right; width: 60%; text-align: right; font-size: 11px; color: #444; }
        .header-info strong { font-size: 15px; color: #003366; display: block; margin-bottom: 2px; text-transform: uppercase; }
        .divider { border-top: 4px solid #990000; margin-top: 15px; margin-bottom: 25px; clear: both; }

        /* Invoice Title */
        .invoice-header-row { margin-bottom: 25px; vertical-align: middle; }
        .invoice-title { font-size: 48px; font-weight: 900; color: #003366; margin: 0; text-transform: uppercase; font-family: 'Arial Narrow', sans-serif; display: inline-block; }
        .status-badge { float: right; margin-top: 10px; padding: 5px 15px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .status-paid { background-color: #dcfce7; color: #15803d; border: 1px solid #bdf0d0; }
        .status-pending { background-color: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }

        /* Billing & Metadata Section */
        .summary-section { width: 100%; border-collapse: collapse; margin-bottom: 35px; }
        .summary-section td { vertical-align: top; }
        .bill-to { width: 55%; padding-right: 20px; }
        .meta-data { width: 45%; }
        
        .section-label { font-size: 11px; font-weight: 900; color: #003366; text-transform: uppercase; margin-bottom: 8px; display: block; border-bottom: 2px solid #003366; padding-bottom: 3px; width: 60px; }
        .bill-to-content { font-size: 13px; line-height: 1.4; color: #111; }
        .bill-to-name { font-size: 17px; font-weight: 800; display: block; margin-bottom: 4px; color: #000; }

        .meta-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .meta-table td { padding: 4px 0; vertical-align: middle; border-bottom: 1px solid #f3f4f6; }
        .meta-label { font-weight: bold; color: #003366; width: 130px; }
        .meta-value { color: #000; text-align: left; }
        .invoice-no { font-size: 15px; font-weight: 900; color: #990000; }

        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 25px; }
        .items-table th { border-top: 4px solid #990000; background-color: #f8fafc; text-align: left; padding: 12px 10px; font-size: 13px; color: #003366; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        .items-table td { padding: 12px 10px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
        .item-category { font-weight: bold; font-size: 14px; display: block; margin-bottom: 3px; color: #1e293b; }
        .item-subtext { font-size: 11px; color: #64748b; line-height: 1.3; text-transform: uppercase; font-family: monospace; }

        .price-col { text-align: right; font-weight: bold; font-size: 14px; color: #0f172a; width: 150px; }
        
        /* Totals */
        .totals-container { float: right; width: 300px; margin-top: 20px; }
        .total-row-table { width: 100%; border-collapse: collapse; }
        .total-label { text-align: right; padding: 8px 15px; font-weight: bold; color: #64748b; font-size: 13px; }
        .total-amount-val { text-align: right; padding: 8px 5px; font-weight: bold; color: #1e293b; font-size: 13px; }
        .grand-total-row { background-color: #f8fafc; }
        .grand-total-label { text-align: right; padding: 12px 15px; font-weight: 900; color: #003366; font-size: 18px; border-top: 2px solid #003366; }
        .grand-total-val { text-align: right; padding: 12px 5px; font-weight: 900; color: #003366; font-size: 18px; border-top: 2px solid #003366; }

        /* Footer Section */
        .footer { margin-top: 80px; clear: both; border-top: 1px solid #e2e8f0; padding-top: 30px; }
        .footer-thanks { float: left; width: 60%; }
        .thanks-msg { font-size: 16px; font-weight: bold; color: #003366; margin-bottom: 5px; }
        .thanks-sub { font-size: 11px; color: #64748b; line-height: 1.5; }

        .qr-container { float: right; text-align: center; width: 140px; }
        .ref-no-footer { font-size: 11px; font-weight: bold; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: 1px; }
        .qr-code { width: 110px; height: 110px; padding: 5px; border: 1px solid #e2e8f0; background: #fff; border-radius: 4px; }

        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header clearfix">
            <div class="header-logo">
                @if(isset($settings) && $settings->logoBase64())
                    <img src="{{ $settings->logoBase64() }}" alt="Logo" class="logo">
                @else
                    <strong style="font-size: 24px; color: #003366;">{{ ($settings->company_name ?? null) ?: config('app.name') }}</strong>
                @endif
            </div>
            <div class="header-info">
                <strong>{{ ($settings->company_name ?? null) ?: config('app.name') }}</strong>
                @if($settings->phone) Phon: {{ $settings->phone }} @endif
                @if($settings->email) | Email: {{ $settings->email }} @endif <br>
                {{ $settings->address ?? '' }}
                @if($settings->city), {{ optional($settings->city)->name }} @endif
                @if($settings->state), {{ optional($settings->state)->iso2 ?? optional($settings->state)->name }} @endif
                @if($settings->zipcode) {{ $settings->zipcode }} @endif
                @if($settings->country), {{ optional($settings->country)->iso2 ?? optional($settings->country)->name }} @endif
            </div>
        </div>

        <div class="divider"></div>

        <!-- Title & Status -->
        <div class="invoice-header-row clearfix">
            <h1 class="invoice-title">INVOICE</h1>
            @if(isset($invoice->status))
                @if($invoice->status->name === 'Paid')
                    <div class="status-badge status-paid">Payment Received</div>
                @else
                    <div class="status-badge status-pending">Balance Due</div>
                @endif
            @endif
        </div>

        <!-- Billing & Summary Grid -->
        <table class="summary-section">
            <tr>
                <td class="bill-to">
                    <span class="section-label">BILL TO</span>
                    <div class="bill-to-content">
                        <span class="bill-to-name">{{ $shipment->shipper?->company_name ?: $shipment->shipper?->user?->name ?: '—' }}</span>
                        @if($shipment->shipper?->address) {{ $shipment->shipper->address }} <br> @endif
                        @if($shipment->shipper?->city) {{ optional($shipment->shipper->city)->name }}, @endif
                        @if($shipment->shipper?->state) {{ optional($shipment->shipper->state)->name }} @endif
                        @if($shipment->shipper?->zipcode) {{ $shipment->shipper->zipcode }} @endif <br>
                        @if($shipment->shipper?->country) {{ optional($shipment->shipper->country)->name }} @endif <br>
                        @if($shipment->shipper?->user?->email) <span style="color: #64748b; font-size: 11px;">{{ $shipment->shipper->user->email }}</span> @endif
                    </div>
                </td>
                <td class="meta-data">
                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">Payment Method</td>
                            <td class="meta-value">{{ optional($shipment->paymentMethod)->name ?: 'Wire Transfer' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Ocean Carrier</td>
                            <td class="meta-value">{{ optional($shipment->carrier)->name ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Shipping Mode</td>
                            <td class="meta-value">{{ optional($shipment->shipping_mode)->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Origin Port</td>
                            <td class="meta-value">{{ optional($shipment->originPort)->name ?: '—' }} {{ optional($shipment->originPort?->country)->iso2 ? '('.optional($shipment->originPort?->country)->iso2.')' : '' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Destination Port</td>
                            <td class="meta-value">{{ optional($shipment->destinationPort)->name ?: '—' }} {{ optional($shipment->destinationPort?->country)->iso2 ? '('.optional($shipment->destinationPort?->country)->iso2.')' : '' }}</td>
                        </tr>
                        <tr style="height: 10px; border-bottom: none;"><td></td><td></td></tr>
                        <tr style="border-bottom: none;">
                            <td class="meta-label" style="font-size: 14px; border-bottom: none;">Invoice #</td>
                            <td class="meta-value invoice-no" style="border-bottom: none;">{{ $shipment->reference_no }}</td>
                        </tr>
                        <tr style="border-bottom: none;">
                            <td class="meta-label" style="font-size: 11px; color: #64748b; border-bottom: none;">Date</td>
                            <td class="meta-value" style="font-size: 11px; color: #64748b; border-bottom: none;">{{ optional($invoice->issued_at)->format('M d, Y') ?? now()->format('M d, Y') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 75%;">Service Description</th>
                    <th style="width: 25%; text-align: right; padding-right: 10px;">Amount (USD)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>
                        <span class="item-category">{{ $item->description }}</span>
                        @if($shipment->vehicle)
                        <div class="item-subtext">
                            {{ $shipment->vehicle->year }} {{ $shipment->vehicle->make }} {{ $shipment->vehicle->model }} &bull; VIN {{ $shipment->vehicle->vin ?: $shipment->vin }}
                        </div>
                        @else
                        <div class="item-subtext">REF: {{ $shipment->reference_no }} &bull; VIN {{ $shipment->vin ?: 'N/A' }}</div>
                        @endif
                    </td>
                    <td class="price-col">
                        $ {{ number_format((float) $item->amount, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="totals-container clearfix">
            <table class="total-row-table">
                <tr>
                    <td class="total-label">Subtotal</td>
                    <td class="total-amount-val">$ {{ number_format((float) $invoice->subtotal, 2) }}</td>
                </tr>
                @if($invoice->tax_amount > 0)
                <tr>
                    <td class="total-label">Tax Total</td>
                    <td class="total-amount-val">$ {{ number_format((float) $invoice->tax_amount, 2) }}</td>
                </tr>
                @endif
                <tr class="grand-total-row">
                    <td class="grand-total-label">TOTAL</td>
                    <td class="grand-total-val">
                        $ {{ number_format((float) $invoice->total_amount, 2) }}
                    </td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer clearfix">
            <div class="footer-thanks">
                <div class="thanks-msg">THANK YOU FOR YOUR BUSINESS!</div>
                <div class="thanks-sub">
                    If you have any questions concerning this invoice, please contact our logistics team.<br>
                    Payments are due within 7 days of invoice issuance unless otherwise agreed.<br><br>
                    <strong>Anka Shipping & Logistics</strong>
                </div>
            </div>
            <div class="qr-container">
                <span class="ref-no-footer">{{ $shipment->reference_no }}</span>
                @if(isset($qrCode) && $qrCode)
                    <img src="{{ $qrCode }}" alt="QR Code" class="qr-code">
                @else
                    <div style="height: 110px; width: 110px; background: #f8fafc; border: 1px dashed #e2e8f0; border-radius: 4px; padding: 10px; font-size: 10px; color: #64748b;">
                        Scan to Track Shipment
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
