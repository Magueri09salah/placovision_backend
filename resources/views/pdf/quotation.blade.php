<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Devis {{ $quotation->reference }}</title>
    <style>
        @page { 
            margin: 16mm 12mm 20mm 12mm; 
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a; 
            font-size: 11px;
            line-height: 1.4;
            padding: 50px;
        }
        
        /* Colors */
        .accent { color: #9E3D36; }
        .muted { color: #64748b; }
        .bg-accent { background-color: #9E3D36; color: white; }
        
        /* Layout */
        .row { 
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }
        .col { 
            display: table-cell;
            vertical-align: top;
            padding-right: 12px;
        }
        .col:last-child { padding-right: 0; }
        .col-half { width: 50%; }
        
        /* Header */
        .header {
            border-bottom: 3px solid #9E3D36;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .logo-wrapper {
            text-align: left;
        }
        .logo { 
            width: 150px; 
            object-fit: contain;
            display: block;
            margin-bottom: 8px;
        }
        .company-info {
            font-size: 10px;
            color: #64748b;
            line-height: 1.5;
        }
        
        /* Quote badge */
        .quote-badge {
            text-align: right;
        }
        .quote-title {
            font-size: 28px;
            font-weight: 900;
            color: #9E3D36;
            letter-spacing: 1px;
        }
        .quote-meta {
            margin-top: 8px;
        }
        .pill { 
            display: inline-block; 
            border: 1px solid #e2e8f0; 
            border-radius: 999px; 
            padding: 4px 12px; 
            margin: 2px;
            font-size: 10px;
            background: #f8fafc;
        }
        
        /* Cards */
        .card { 
            border: 1px solid #e2e8f0; 
            border-radius: 10px;
            padding: 12px;
            background: #ffffff;
        }
        .card-title { 
            font-size: 11px; 
            font-weight: 800; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9E3D36;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        /* Tables */
        table { 
            width: 100%; 
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 10px;
        }
        thead th {
            background: #9E3D36;
            color: white;
            padding: 10px 8px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
        }
        thead th:first-child { 
            border-top-left-radius: 8px;
        }
        thead th:last-child { 
            border-top-right-radius: 8px;
        }
        tbody td { 
            border-bottom: 1px solid #e2e8f0;
            padding: 8px;
            vertical-align: top;
        }
        tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        tbody tr:last-child td:first-child { 
            border-bottom-left-radius: 8px;
        }
        tbody tr:last-child td:last-child { 
            border-bottom-right-radius: 8px;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        
        /* Room/Work sections */
        .section-room {
            background: #fef2f2;
            font-weight: 800;
            font-size: 11px;
        }
        .section-room td {
            padding: 10px 8px;
            border-bottom: 2px solid #e8c4c4;
        }
        .section-work {
            background: #f1f5f9;
            font-weight: 600;
        }
        .section-work td {
            padding: 8px;
            font-size: 10px;
        }
        .item-modified {
            background: #fef3c7 !important;
        }
        .modified-badge {
            font-size: 8px;
            color: #d97706;
            font-style: italic;
        }
        
        /* Totals */
        .totals-wrapper {
            margin-top: 20px;
            margin-left: auto;
            width: 280px;
        }
        .totals { 
            border: 2px solid #9E3D36;
            border-radius: 10px;
            overflow: hidden;
        }
        .total-line { 
            display: table;
            width: 100%;
            padding: 10px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .total-line:last-child {
            border-bottom: none;
        }
        .total-label {
            display: table-cell;
            color: #64748b;
        }
        .total-value {
            display: table-cell;
            text-align: right;
            font-weight: 600;
        }
        .total-grand { 
            background: #9E3D36;
            color: white;
        }
        .total-grand .total-label {
            color: white;
            font-weight: 700;
            font-size: 12px;
        }
        .total-grand .total-value {
            font-size: 16px;
            font-weight: 900;
        }
        
        /* Signatures */
        .signatures {
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signature-box {
            display: table-cell;
            width: 50%;
            padding: 10px;
        }
        .signature-title {
            font-size: 10px;
            color: #64748b;
            margin-bottom: 50px;
        }
        .signature-line {
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
            font-size: 10px;
        }
        
        /* Footer */
        .footer {
            position: fixed;
            bottom: 5mm;
            left: 12mm;
            right: 12mm;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            text-align: center;
        }
        
        /* Page break */
        .no-break { page-break-inside: avoid; }
        .page-break { page-break-before: always; }
        
        /* Status badges */
        .status-draft { background: #e5e7eb; color: #374151; }
        .status-sent { background: #dbeafe; color: #1d4ed8; }
        .status-accepted { background: #dcfce7; color: #15803d; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>

@php
    // Format helpers
    $fmtMoney = fn($n) => number_format((float)$n, 2, ',', ' ') . ' DH';
    $fmtQty = fn($n) => rtrim(rtrim(number_format((float)$n, 2, ',', ' '), '0'), ',');
    
    // Room type labels
    $roomLabels = [
        'salon_sejour' => 'Salon / Sejour',
        'chambre' => 'Chambre',
        'cuisine' => 'Cuisine',
        'salle_de_bain' => 'Salle de bain',
        'wc' => 'WC',
        'bureau' => 'Bureau',
        'garage' => 'Garage / Local technique',
        'exterieur' => 'Exterieur',
        'autre' => 'Autre',
    ];
    
    // Work type labels
    $workLabels = [
        'habillage_mur' => 'Habillage de mur',
        'plafond_ba13' => 'Plafond BA13',
        'cloison' => 'Cloison',
        'gaine_creuse' => 'Gaine creuse',
    ];
    
    $workUnits = [
        'habillage_mur' => 'm2',
        'plafond_ba13' => 'm2',
        'cloison' => 'm2',
        'gaine_creuse' => 'ml',
    ];
    
    // Company info
    $companyName = "L'AS DU PLACO";
    $companyAddressLine1 = "25, zone industrielle Sidi Ghanem -3 40010";
    $companyCity = "Marrakech - Maroc";
    $companyPhone = "+212 7 67 91 54 25";
    $companyEmail = "contact@asduplaco.com";
    $companyIce = "003890458000001";
    
    // LOGO - Fixed path: public/images/logo.svg
    $logoBase64 = null;
    $logoPath = public_path('images/logo.svg');
    
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/svg+xml;base64,' . base64_encode($logoData);
    }
@endphp

{{-- ==================== HEADER ==================== --}}
<div class="header">
    <div class="row">
        <div class="col col-half">
            <div class="logo-wrapper">
                @if($logoBase64)
                    <img class="logo" src="{{ $logoBase64 }}" alt="Logo">
                @endif
                <div class="company-info">
                    @if($companyAddressLine1)
                        {{ $companyAddressLine1 }}
                        @if($companyCity), {{ $companyCity }}@endif
                        <br>
                    @endif
                    @if($companyPhone)
                        Tel: {{ $companyPhone }}
                    @endif
                    <br>
                    @if($companyEmail)
                        {{ $companyEmail }}
                    @endif
                    <br>
                    <strong>ICE:</strong> {{ $companyIce ?? '---' }}
                </div>
            </div>
        </div>
        <div class="col col-half quote-badge">
            <div class="quote-title">DEVIS</div>
            <div class="quote-meta">
                <span class="pill"><strong>N.</strong> {{ $quotation->reference }}</span>
                <span class="pill"><strong>Date</strong> {{ $quotation->created_at->format('d/m/Y') }}</span>
                <br>
                <!-- <span class="pill"><strong>Validite</strong> {{ $quotation->validity_date ? $quotation->validity_date->format('d/m/Y') : '30 jours' }}</span>
                <span class="pill status-{{ $quotation->status }}">{{ ucfirst($quotation->status) }}</span> -->
            </div>
        </div>
    </div>
</div>

{{-- ==================== CLIENT & CHANTIER ==================== --}}
<div class="row">
    <div class="col col-half">
        <div class="card">
            <div class="card-title">Client</div>
            <div style="font-weight: 700; font-size: 12px; margin-bottom: 5px;">
                {{ $quotation->client_name }}
            </div>
            @if($quotation->client_email)
                <div class="muted">Email: {{ $quotation->client_email }}</div>
            @endif
            @if($quotation->client_phone)
                <div class="muted">Tel: {{ $quotation->client_phone }}</div>
            @endif
        </div>
    </div>
    <div class="col col-half">
        <div class="card">
            <div class="card-title">Chantier</div>
            <div style="font-weight: 700; font-size: 12px; margin-bottom: 5px;">
                {{ $quotation->site_address }}
            </div>
            <div class="muted">
                {{ $quotation->site_city }}
                @if($quotation->site_postal_code)
                    , {{ $quotation->site_postal_code }}
                @endif
            </div>
            @if($quotation->project)
                <div style="margin-top: 5px;">
                    <span class="muted">Projet:</span> {{ $quotation->project->name }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ==================== TABLEAU DES MATERIAUX ==================== --}}
@if($quotation->rooms && count($quotation->rooms) > 0)
    <table>
        <thead>
            <tr>
                <th style="width: 35%;">Designation</th>
                <!-- <th style="width: 12%;" class="center">Qte calc.</th> -->
                <th style="width: 12%;" class="center">Qte</th>
                <th style="width: 10%;" class="center">Unite</th>
                <th style="width: 15%;" class="right">P.U. HT</th>
                <th style="width: 16%;" class="right">Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->rooms as $room)
                {{-- Room Header --}}
                <tr class="section-room">
                    <td colspan="5">
                        {{ $roomLabels[$room->room_type] ?? $room->room_name }}
                        <!-- <span class="muted" style="font-weight: 400; margin-left: 10px;">
                            Sous-total: {{ $fmtMoney($room->subtotal_ht ?? 0) }}
                        </span> -->
                    </td>
                </tr>
                
                @if($room->works && count($room->works) > 0)
                    @foreach($room->works as $work)
                        {{-- Work Header --}}
                        <tr class="section-work">
                            <td colspan="5">
                                 {{ $workLabels[$work->work_type] ?? $work->work_type }}
                                - {{ $fmtQty($work->surface ?? 0) }} {{ $workUnits[$work->work_type] ?? 'm²' }}
                                <span class="muted" style="float: right;">{{ $fmtMoney($work->subtotal_ht ?? 0) }}</span>
                            </td>
                        </tr>
                        
                        {{-- Materials/Items --}}
                        @if($work->items && count($work->items) > 0)
                            @foreach($work->items as $item)
                                <tr>
                                    <td>
                                        {{ $item->designation ?? 'N/A' }}
                                        <!-- @if($item->is_modified)
                                            <span class="modified-badge">(modifie)</span>
                                        @endif -->
                                        @if($item->description)
                                            <div class="muted" style="font-size: 9px;">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <!-- <td class="center muted">{{ $fmtQty($item->quantity_calculated ?? 0) }}</td> -->
                                    <td class="center" style="font-weight: 600;">{{ $fmtQty($item->quantity_adjusted ?? 0) }}</td>
                                    <td class="center">{{ $item->unit ?? '' }}</td>
                                    <td class="right">{{ $fmtMoney($item->unit_price ?? 0) }}</td>
                                    <td class="right" style="font-weight: 600;">{{ $fmtMoney($item->total_ht ?? 0) }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="center muted" style="padding: 20px;">
                                    Aucun materiaux defini pour ce travail
                                </td>
                            </tr>
                        @endif
                    @endforeach
                @else
                    <tr>
                        <td colspan="6" class="center muted" style="padding: 15px;">
                            Aucun travail defini pour cette piece
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@else
    <div style="text-align: center; padding: 30px; color: #64748b; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 20px;">
        Aucune piece definie dans ce devis
    </div>
@endif

<p class="font-weight: 700; font-size: 12px">NB : La durée de validité de ce devis est de 30 jours.</p>

{{-- ==================== TOTAUX ==================== --}}
<div class="totals-wrapper no-break">
    <div class="totals">
        <div class="total-line">
            <span class="total-label">Sous-total HT</span>
            <span class="total-value">{{ $fmtMoney($quotation->total_ht ?? 0) }}</span>
        </div>
        @if(($quotation->discount_amount ?? 0) > 0)
            <div class="total-line">
                <span class="total-label">Remise ({{ $quotation->discount_percent ?? 0 }}%)</span>
                <span class="total-value" style="color: #16a34a;">-{{ $fmtMoney($quotation->discount_amount ?? 0) }}</span>
            </div>
        @endif
        <div class="total-line">
            <span class="total-label">TVA ({{ $quotation->tva_rate ?? 20 }}%)</span>
            <span class="total-value">{{ $fmtMoney($quotation->total_tva ?? 0) }}</span>
        </div>
        <div class="total-line total-grand">
            <span class="total-label">TOTAL TTC</span>
            <span class="total-value">{{ $fmtMoney($quotation->total_ttc ?? 0) }}</span>
        </div>
    </div>
</div>

<div style="margin-top : 100px">
    <p><strong>DTU 25.41 :</strong> Les calculs et quantités de ce devis sont établis conformément aux règles de calcul et de mise en œuvre du DTU 25.41 .</p>
</div>


{{-- ==================== FOOTER ==================== --}}
<div class="footer">
    <strong>{{ $companyName }}</strong>
    @if($companyAddressLine1) - {{ $companyAddressLine1 }}@endif
    @if($companyCity), {{ $companyCity }}@endif
    @if($companyIce) - <strong>ICE:</strong> {{ $companyIce }}@endif
</div>

</body>
</html>