<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 6px; }
        .label { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        img { max-width: 240px; border: 1px solid #ccc; }
    </style>
</head>
<body>
<h1>Pickup Receipt</h1>
<p><span class="label">Company:</span> {{ $repair->company->name ?? 'N/A' }}</p>
<p><span class="label">Repair Ref:</span> R-{{ $repair->id }}</p>
<p><span class="label">Customer:</span> {{ $repair->customer->name ?? 'N/A' }}</p>
<p><span class="label">Device:</span> {{ $repair->device_brand }} {{ $repair->device_model }} ({{ $repair->serial_number }})</p>
<p><span class="label">Issue:</span> {{ $repair->reported_issue }}</p>
<p><span class="label">Pickup timestamp:</span> {{ $pickup->picked_up_at }}</p>

@if($salesDocument)
    <p><span class="label">Sales document:</span> {{ $salesDocument->full_number ?? ('#'.$salesDocument->id) }}</p>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Gross</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->qty }}</td>
                    <td>{{ number_format((float) $line->line_gross, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p><span class="label">Net total:</span> {{ number_format((float) $salesDocument->net_total, 2) }}</p>
    <p><span class="label">Tax total:</span> {{ number_format((float) $salesDocument->tax_total, 2) }}</p>
    <p><span class="label">Gross total:</span> {{ number_format((float) $salesDocument->gross_total, 2) }}</p>
@endif

<p><span class="label">Signature:</span></p>
<img src="{{ $signaturePath }}" alt="signature" />
</body>
</html>
