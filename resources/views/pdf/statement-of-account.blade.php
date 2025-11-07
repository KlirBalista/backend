<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statement of Account</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 20px;
            color: #000;
            line-height: 1.2;
        }
        
        .header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .header-row {
            display: flex;
            align-items: flex-start;
            width: 100%;
        }
        
        .logo-section {
            margin-right: 20px;
            margin-top: 5px;
        }
        
        .logo-circle {
            width: 50px;
            height: 50px;
            border: 2px solid #333;
            border-radius: 50%;
            background: #f9f9f9;
        }
        
        .header-text {
            flex: 1;
            text-align: center;
        }
        
        .republic-text {
            font-size: 9px;
            margin-bottom: 2px;
        }
        
        .facility-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .subtitle {
            font-size: 10px;
            font-style: italic;
            margin-bottom: 10px;
        }
        
        .form-fields {
            margin: 20px 0;
        }
        
        .field-row {
            display: flex;
            margin-bottom: 12px;
            align-items: baseline;
        }
        
        .field-left, .field-right {
            flex: 1;
            display: flex;
            align-items: baseline;
        }
        
        .field-full {
            flex: 1;
            display: flex;
            align-items: baseline;
        }
        
        .field-label {
            font-size: 10px;
            font-weight: normal;
            white-space: nowrap;
            margin-right: 8px;
        }
        
        .field-line {
            border-bottom: 1px solid #333;
            flex: 1;
            min-height: 14px;
            font-size: 10px;
            padding-bottom: 1px;
            margin-right: 20px;
        }
        
        .field-line.full-width {
            margin-right: 0;
        }
        
        .soa-title {
            font-size: 14px;
            font-weight: bold;
            margin: 15px 0;
            text-align: center;
        }
        
        .patient-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            margin-bottom: 8px;
            overflow: hidden;
        }
        
        .info-left {
            float: left;
            width: 48%;
        }
        
        .info-right {
            float: right;
            width: 48%;
        }
        
        .info-label {
            display: inline-block;
            width: 130px;
            font-weight: normal;
        }
        
        .info-value {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 180px;
            padding-bottom: 1px;
            font-weight: normal;
        }
        
        .summary-title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0 15px 0;
        }
        
        .fees-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 10px;
        }
        
        .fees-table th, .fees-table td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
        }
        
        .fees-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 9px;
        }
        
        .fees-table .service-name {
            text-align: left;
            padding-left: 6px;
        }
        
        .amount-cell {
            text-align: right;
            font-weight: normal;
        }
        
        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .signature-section {
            margin-top: 40px;
        }
        
        .signature-row {
            overflow: hidden;
            margin-top: 30px;
        }
        
        .signature-left, .signature-right {
            float: left;
            width: 45%;
            padding: 0 2.5%;
        }
        
        .signature-title {
            font-weight: bold;
            margin-bottom: 30px;
            font-size: 12px;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            height: 20px;
        }
        
        .signature-label {
            font-size: 10px;
            text-align: center;
        }
        
        .clear {
            clear: both;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-row">
            <div class="logo-section">
                <div class="logo-circle"></div>
            </div>
            <div class="header-text">
                <div class="republic-text">Republic of the Philippines</div>
                <div class="facility-name">{{ strtoupper($facility->name ?? 'HEALTH CARE FACILITY') }}</div>
                <div class="subtitle">{{ $facility->description ?? ($facility->address ?? 'Health Care Services') }}</div>
                @if(isset($facility->contact_number) && !empty($facility->contact_number))
                    <div style="font-size: 9px; margin-top: 2px;">Contact: {{ $facility->contact_number }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="soa-title">STATEMENT OF ACCOUNT</div>

    <div class="form-fields">
        <div class="field-row">
            <div class="field-left">
                <span class="field-label">SOA REFERENCE NO:</span>
                <span class="field-line">{{ $soa->soa_number ?? '' }}</span>
            </div>
            <div class="field-right">
                <span class="field-label">PHILHEALTH NO:</span>
                <span class="field-line">{{ $soa->patient->philhealth_number ?? '' }}</span>
            </div>
        </div>
        
        <div class="field-row">
            <div class="field-left">
                <span class="field-label">PATIENT'S NAME:</span>
                <span class="field-line">{{ strtoupper($soa->patient->full_name ?? '') }}</span>
            </div>
            <div class="field-right">
                <span class="field-label">AGE:</span>
                <span class="field-line">{{ $soa->patient->age ?? '' }}</span>
            </div>
        </div>
        
        <div class="field-row">
            <div class="field-full">
                <span class="field-label">COMPLETE ADDRESS:</span>
                <span class="field-line full-width">{{ strtoupper($soa->patient->address ?? '') }}</span>
            </div>
        </div>
        
        <div class="field-row">
            <div class="field-left">
                <span class="field-label">ADMISSION DATE:</span>
                <span class="field-line">{{ $soa->admission->admission_date ? $soa->admission->admission_date->format('Y-m-d') : '' }}</span>
            </div>
            <div class="field-right">
                <span class="field-label">ADMISSION TIME:</span>
                <span class="field-line">{{ $soa->admission->admission_time ? $soa->admission->admission_time->format('H:i:s') : '' }}</span>
            </div>
        </div>
        
        <div class="field-row">
            <div class="field-left">
                <span class="field-label">DISCHARGED DATE:</span>
                <span class="field-line">______________________</span>
            </div>
            <div class="field-right">
                <span class="field-label">DISCHARGED TIME:</span>
                <span class="field-line">______________________</span>
            </div>
        </div>
        
        <div class="field-row">
            <div class="field-full">
                <span class="field-label">DIAGNOSIS:</span>
                <span class="field-line full-width">_________________________________________________________________</span>
            </div>
        </div>
    </div>

    <div class="summary-title">SUMMARY OF FEES</div>

    <table class="fees-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 15%;">Particulars</th>
                <th rowspan="2" style="width: 10%;">Actual<br/>Charges</th>
                <th rowspan="2" style="width: 5%;">VAT</th>
                <th colspan="3" style="width: 20%;">Amounts of Discounts</th>
                <th colspan="4" style="width: 30%;">Philhealth Benefits</th>
                <th rowspan="2" style="width: 10%;">Out of<br/>Pocket<br/>Patient</th>
            </tr>
            <tr>
                <th style="font-size: 8px;">Senior<br/>Citizen/<br/>PWD</th>
                <th style="font-size: 8px;">DSWD<br/>DOH</th>
                <th style="font-size: 8px;">Others</th>
                <th style="font-size: 8px;">First<br/>Case<br/>Rate</th>
                <th style="font-size: 8px;">Second<br/>Case<br/>Rate</th>
                <th style="font-size: 8px;">Final<br/>amount</th>
                <th style="font-size: 8px;">Patient<br/>amount</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($soa->itemized_charges) && count($soa->itemized_charges) > 0)
                @foreach($soa->itemized_charges as $charge)
                @php
                    $chargeAmount = $charge->total_price ?? 0;
                    $totalCharges = $soa->totals->current_charges ?? 0;
                    $outstandingBalance = $soa->totals->outstanding_balance ?? 0;
                    $chargeBalance = $totalCharges > 0 ? ($chargeAmount * $outstandingBalance) / $totalCharges : $chargeAmount;
                @endphp
                <tr>
                    <td class="service-name">{{ $charge->service_name ?? 'Medical Service' }}</td>
                    <td class="amount-cell">{{ number_format($chargeAmount, 2) }}</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td class="amount-cell">{{ number_format($chargeAmount, 2) }}</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td class="amount-cell">0.00</td>
                    <td class="amount-cell">{{ number_format(max(0, $chargeBalance), 2) }}</td>
                </tr>
                @endforeach
            @else
                @php
                    $totalCharges = $soa->totals->current_charges ?? 9000;
                    $outstandingBalance = $soa->totals->outstanding_balance ?? $totalCharges;
                    $normalDeliveryBalance = $totalCharges > 0 ? (8000 * $outstandingBalance) / $totalCharges : 8000;
                    $newbornCareBalance = $totalCharges > 0 ? (1000 * $outstandingBalance) / $totalCharges : 1000;
                @endphp
                <!-- Use sample data with correct balance calculations -->
                <tr>
                    <td class="service-name">Normal Delivery Package</td>
                    <td class="amount-cell">8,000.00</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td class="amount-cell">8,000.00</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td class="amount-cell">0.00</td>
                    <td class="amount-cell">{{ number_format(max(0, $normalDeliveryBalance), 2) }}</td>
                </tr>
                <tr>
                    <td class="service-name">Newborn Care Package</td>
                    <td class="amount-cell">1,000.00</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td class="amount-cell">1,000.00</td>
                    <td>N/A</td>
                    <td>N/A</td>
                    <td class="amount-cell">0.00</td>
                    <td class="amount-cell">{{ number_format(max(0, $newbornCareBalance), 2) }}</td>
                </tr>
                <tr>
                    <td class="service-name">Subtotal</td>
                    <td class="amount-cell">{{ number_format($totalCharges, 2) }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="amount-cell">{{ number_format($totalCharges, 2) }}</td>
                    <td></td>
                    <td></td>
                    <td class="amount-cell">0.00</td>
                    <td class="amount-cell">{{ number_format(max(0, $outstandingBalance), 2) }}</td>
                </tr>
            @endif
            
            @php
                $finalTotalCharges = $soa->totals->current_charges ?? 9000;
                $finalOutstandingBalance = $soa->totals->outstanding_balance ?? $finalTotalCharges;
            @endphp
            <tr class="total-row">
                <td class="service-name">Total</td>
                <td class="amount-cell">{{ number_format($finalTotalCharges, 2) }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td class="amount-cell">{{ number_format($finalTotalCharges, 2) }}</td>
                <td></td>
                <td></td>
                <td class="amount-cell">0.00</td>
                <td class="amount-cell">{{ number_format(max(0, $finalOutstandingBalance), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-row">
            <div class="signature-left">
                <div class="signature-title">Prepared by:</div>
                <div class="signature-line"></div>
                <div class="signature-label">Billing Clerk/Accountant</div>
                <div class="signature-line mt-15"></div>
                <div class="signature-label">(Signature over printed name)</div>
                <div class="signature-line mt-15"></div>
                <div class="signature-label">Date signed: {{ $generated_date }}</div>
                <div class="signature-line mt-15"></div>
                <div class="signature-label">Contact No.: ______________</div>
            </div>
            
            <div class="signature-right">
                <div class="signature-title">Conforme:</div>
                <div class="signature-line"></div>
                <div class="signature-label">Member/Patient/Authorized Representative</div>
                <div class="signature-line mt-15"></div>
                <div class="signature-label">(Signature over printed name)</div>
                <div class="signature-line mt-15"></div>
                <div class="signature-label">Date signed: ______________</div>
                <div class="signature-line mt-15"></div>
                <div class="signature-label">Contact No.: ______________</div>
            </div>
        </div>
        <div class="clear"></div>
    </div>
</body>
</html>