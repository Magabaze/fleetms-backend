<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Manifest - {{ $trip_number }}</title>
    
    <style>
        /* RESET E CONFIGURAÇÕES BASE */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* CENTRALIZAÇÃO */
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6.2pt;  /* REDUZIDO de 7pt */
            line-height: 1.4;  /* REDUZIDO de 1.1 */
            color: #000;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 5mm;
        }
        
        .container {
            width: 100%;
            max-width: 290mm;  /* Largura máxima para A4 paisagem */
            margin: 0 auto;
            margin-left: -3mm;
            padding: 2mm 3mm;  /* MARGENS REDUZIDAS */
            background: #fff;
        }
        
        /* CABEÇALHO - MANTIDO ORIGINAL */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 3mm;  /* REDUZIDO de 4mm */
            padding-bottom: 1.5mm;  /* REDUZIDO de 2mm */
            border-bottom: 1.2pt solid #000;
        }
        
        .header-cell {
            display: table-cell;
            vertical-align: middle;
        }
        
        .logo {
            width: 38mm;  /* REDUZIDO de 45mm */
        }
        
        .logo img {
            max-width: 38mm;  /* REDUZIDO de 40mm */
            max-height: 19mm;  /* REDUZIDO de 15mm */
            display: block;
        }
        
        .title {
            text-align: center;
        }
        
        .title h1 {
            font-size: 10pt;  /* REDUZIDO de 11pt */
            font-weight: bold;
            margin: 0 0 0.5mm 0;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }
        
        .title h2 {
            font-size: 6pt;  /* REDUZIDO de 7pt */
            margin: 0;
            color: #555;
            font-weight: normal;
        }
        
        .doc-info {
            text-align: right;
            width: 38mm;  /* REDUZIDO de 45mm */
        }
        
        .doc-number {
            font-size: 8pt;  /* REDUZIDO de 9pt */
            font-weight: bold;
            color: #1a56db;
            margin-bottom: 0.5mm;
        }
        
        .doc-date {
            font-size: 6.5pt;  /* REDUZIDO de 7pt */
            color: #666;
        }
        
        /* CONTEÚDO PRINCIPAL - MANTIDO TABLE ORIGINAL */
        .content {
            display: table;
            width: 100%;
            margin-bottom: 2mm;
        }
        
        .column {
            display: table-cell;
            vertical-align: top;
            padding: 0 2mm;  /* REDUZIDO de 3mm */
        }
        
        .left-column {
            width: 60%;
        }
        
        .right-column {
            width: 40%;
        }
        
        /* SEÇÕES */
        .section {
            border: 0.5pt solid #000;
            margin-bottom: 2.5mm;  /* REDUZIDO de 3mm */
            break-inside: avoid;
            page-break-inside: avoid;
        }
        
        .section-title {
            background: #2d3748;
            color: white;
            padding: 1mm 2mm;  /* REDUZIDO de 1.5mm 3mm */
            font-size: 7pt;  /* REDUZIDO de 8pt */
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        
        .section-content {
            padding: 1.5mm;  /* REDUZIDO de 2mm */
        }
        
        /* TABELAS */
        .table-info {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.2pt;
        }
        
        .table-info td {
            border: 0.5pt solid #ddd;
            padding: 0.8mm 1.2mm;  /* REDUZIDO de 1mm 2mm */
            vertical-align: middle;
        }
        
        .label {
            background: #f5f5f5;
            font-weight: bold;
            width: 35%;
            font-size: 6pt;
        }
        
        /* TABELA DE CARGA */
        .cargo-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
            margin-top: 1mm;  /* REDUZIDO de 2mm */
        }
        
        .cargo-table th {
            background: #4a5568;
            color: white;
            padding: 0.8mm;  /* REDUZIDO de 1.5mm */
            border: 0.5pt solid #000;
            text-align: center;
            font-weight: bold;
            font-size: 6.2pt;
        }
        
        .cargo-table td {
            border: 0.5pt solid #000;
            padding: 0.8mm;  /* REDUZIDO de 1.5mm */
            text-align: center;
            font-size: 6pt;
        }
        
        /* INFORMAÇÕES DO MOTORISTA - MANTIDO TABLE ORIGINAL */
        .driver-info {
            display: table;
            width: 100%;
        }
        
        .driver-photo {
            display: table-cell;
            width: 20mm;  /* REDUZIDO de 25mm */
            vertical-align: top;
            padding-right: 1.5mm;  /* REDUZIDO de 2mm */
        }
        
        .photo-frame {
            width: 18mm;  /* REDUZIDO de 23mm */
            height: 22mm;  /* REDUZIDO de 28mm */
            border: 0.5pt solid #000;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .driver-photo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .driver-name {
            font-size: 5.5pt;
            font-weight: bold;
            text-align: center;
            margin-top: 0.5mm;
        }
        
        .driver-details {
            display: table-cell;
            vertical-align: top;
        }
        
        /* VEÍCULO - MANTIDO TABLE ORIGINAL */
        .vehicle-grid {
            display: table;
            width: 100%;
            margin-top: 1mm;  /* REDUZIDO de 2mm */
        }
        
        .vehicle-row {
            display: table-row;
        }
        
        .vehicle-item {
            display: table-cell;
            border: 0.5pt solid #ddd;
            padding: 1mm;  /* REDUZIDO de 2mm */
            text-align: center;
            width: 50%;
        }
        
        .vehicle-label {
            font-size: 5.5pt;
            color: #666;
            margin-bottom: 0.5mm;
            text-transform: uppercase;
        }
        
        .vehicle-value {
            font-size: 7pt;
            font-weight: bold;
        }
        
        /* DECLARAÇÃO */
        .declaration {
            padding: 1.5mm 2mm;  /* REDUZIDO de 2mm 3mm */
            border: 0.5pt solid #000;
            font-size: 5.8pt;  /* REDUZIDO de 6.5pt */
            line-height: 1.2;
            background: #f9fafb;
            margin-top: 2mm;  /* REDUZIDO de 3mm */
            text-align: justify;
        }
        
        /* ASSINATURAS - MANTIDO TABLE ORIGINAL */
        .signatures {
            display: table;
            width: 100%;
            margin-top: 2mm;  /* REDUZIDO de 3mm */
            padding-top: 1.5mm;  /* REDUZIDO de 2mm */
            border-top: 0.5pt solid #000;
        }
        
        .signature-box {
            display: table-cell;
            text-align: center;
            width: 50%;
        }
        
        .signature-line {
            height: 5mm;  /* REDUZIDO de 8mm */
            border-bottom: 0.5pt solid #000;
            margin: 1mm 2mm;
        }
        
        .signature-label {
            font-size: 6pt;
            font-weight: bold;
            margin-bottom: 0.5mm;
        }
        
        .signature-name {
            font-size: 5.5pt;
            color: #555;
        }
        
        /* ALFÂNDEGA */
        .customs-box {
            border: 0.5pt solid #000;
            padding: 1.5mm;  /* REDUZIDO de 2mm */
            text-align: center;
            margin-top: 2mm;  /* REDUZIDO de 3mm */
        }
        
        .customs-stamp {
            height: 12mm;  /* REDUZIDO de 15mm */
            border: 0.5pt dashed #999;
            margin: 1.5mm 0;
            position: relative;
        }
        
        .stamp-label {
            position: absolute;
            bottom: 0.5mm;
            left: 0;
            right: 0;
            font-size: 5.5pt;
            color: #666;
        }
        
        /* RODAPÉ */
        .footer {
            width: 100%;
            text-align: center;
            font-size: 5.5pt;  /* REDUZIDO de 6pt */
            color: #666;
            padding-top: 1.5mm;  /* REDUZIDO de 2mm */
            border-top: 0.5pt solid #ccc;
            margin-top: 2mm;
        }
        
        .footer-company {
            font-weight: bold;
            margin-bottom: 0.5mm;
        }
        
        /* UTILITÁRIOS */
        .bold { font-weight: bold; }
        .text-center { text-align: center; }
        .no-wrap { white-space: nowrap; }
        
        /* EVITA QUEBRAS DESNECESSÁRIAS */
        .section, .signatures, .declaration, .customs-box {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        
        /* IMPRESSÃO */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                padding: 2mm;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- CABEÇALHO -->
        <div class="header">
            <div class="header-cell logo">
                @if($logo_empresa)
                    <img src="{{ $logo_empresa }}" alt="Logo">
                @endif
            </div>
            
            <div class="header-cell title">
                <h1>CUSTOMS ROAD FREIGHT MANIFEST</h1>
                <h2>Documento Oficial de Transporte Rodoviário Internacional</h2>
            </div>
            
            <div class="header-cell doc-info">
                <div class="doc-number">REF: {{ $trip_number }}</div>
                <div class="doc-date">Date: {{ $current_date }}</div>
            </div>
        </div>
        
        <!-- CONTEÚDO PRINCIPAL - 2 COLUNAS LADO A LADO -->
        <div class="content">
            
            <!-- COLUNA ESQUERDA -->
            <div class="column left-column">
                
                <div class="section">
                    <div class="section-title">Trip Information</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr><td class="label">TRIP REF</td><td class="bold">{{ $trip_number }}</td></tr>
                            <tr><td class="label">ORDER NO.</td><td>{{ $order_number }}</td></tr>
                            <tr><td class="label">BL/AWB NO.</td><td>{{ $bl_number }}</td></tr>
                            <tr><td class="label">DATE</td><td>{{ $schedule_date }}</td></tr>
                            <tr><td class="label">FROM</td><td class="bold">{{ $from_station }}</td></tr>
                            <tr><td class="label">TO</td><td class="bold">{{ $to_station }}</td></tr>
                            <tr><td class="label">CUSTOMER</td><td>{{ $customer_name }}</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Cargo Details</div>
                    <div class="section-content">
                        <table class="cargo-table">
                            <thead>
                                <tr>
                                    <th>NO.</th>
                                    <th>CONTAINER NO.</th>
                                    <th>TYPE</th>
                                    <th>SEAL NO.</th>
                                    <th>WEIGHT (kg)</th>
                                    <th>COMMODITY</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>{{ $container_no }}</td>
                                    <td>{{ $container_type }}</td>
                                    <td>{{ $seal_no }}</td>
                                    <td>{{ $weight }}</td>
                                    <td>{{ $commodity }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="declaration">
                    <strong>DECLARATION:</strong> I hereby certify that the particulars shown on this manifest are true reflection of all authorised goods carried on the above mentioned vehicle. {{ $empresa_nome }} shall bear no responsibility for any cargo not declared on the road freight manifest.
                </div>
                
                <div class="signatures">
                    <div class="signature-box">
                        <div class="signature-label">DRIVER SIGNATURE</div>
                        <div class="signature-line"></div>
                        <div class="signature-name">{{ $driver }}</div>
                    </div>
                    
                    <div class="signature-box">
                        <div class="signature-label">TRANSPORTER STAMP</div>
                        <div class="signature-line"></div>
                        <div class="signature-name">{{ $empresa_nome }}</div>
                    </div>
                </div>
                
            </div>
            
            <!-- COLUNA DIREITA -->
            <div class="column right-column">
                
                <div class="section">
                    <div class="section-title">Driver Information</div>
                    <div class="section-content">
                        <div class="driver-info">
                            <div class="driver-photo">
                                <div class="photo-frame">
                                    @if($foto_motorista)
                                        <img src="{{ $foto_motorista }}" alt="Driver" class="driver-photo-img">
                                    @else
                                        <div style="width:100%;height:100%;background:#f5f5f5;display:flex;align-items:center;justify-content:center;">
                                            <span style="color:#666;font-size:5pt;">No Photo</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="driver-name">{{ $driver }}</div>
                            </div>
                            
                            <div class="driver-details">
                                <table class="table-info">
                                    <tr><td class="label">NAME</td><td class="bold">{{ $driver }}</td></tr>
                                    <tr><td class="label">LICENSE</td><td>{{ $driver_license }}</td></tr>
                                    <tr><td class="label">PASSPORT</td><td>{{ $driver_passport }}</td></tr>
                                    <tr><td class="label">PHONE</td><td>{{ $driver_phone }}</td></tr>
                                    <tr><td class="label">NATIONALITY</td><td>{{ $driver_nationality }}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Vehicle Information</div>
                    <div class="section-content">
                        <div class="vehicle-grid">
                            <div class="vehicle-row">
                                <div class="vehicle-item">
                                    <div class="vehicle-label">Truck</div>
                                    <div class="vehicle-value">{{ $truck_number }}</div>
                                </div>
                                <div class="vehicle-item">
                                    <div class="vehicle-label">Trailer</div>
                                    <div class="vehicle-value">{{ $trailer_number }}</div>
                                </div>
                            </div>
                            <div class="vehicle-row">
                                <div class="vehicle-item">
                                    <div class="vehicle-label">Body Type</div>
                                    <div class="vehicle-value">{{ $container_type }}</div>
                                </div>
                                <div class="vehicle-item">
                                    <div class="vehicle-label">Axle</div>
                                    <div class="vehicle-value">{{ $truck_axle }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Border Information</div>
                    <div class="section-content">
                        <div class="vehicle-grid">
                            <div class="vehicle-row">
                                <div class="vehicle-item">
                                    <div class="vehicle-label">Entry Border</div>
                                    <div class="vehicle-value">{{ $entry_border ?? 'N/A' }}</div>
                                </div>
                                <div class="vehicle-item">
                                    <div class="vehicle-label">Drop Off</div>
                                    <div class="vehicle-value">{{ $to_station }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="customs-box">
                    <div class="signature-label">FOR CUSTOMS USE ONLY</div>
                    <div class="customs-stamp">
                        <div class="stamp-label">Customs Stamp & Signature</div>
                    </div>
                    <div style="font-size: 6pt;">Report No.: ________________</div>
                </div>
                
            </div>
            
        </div>
        
        <!-- RODAPÉ -->
        <div class="footer">
            <div class="footer-company">{{ $empresa_nome }}</div>
            <div>
                @if($empresa_morada){{ $empresa_morada }} | @endif
                @if($empresa_telefone)Tel: {{ $empresa_telefone }} | @endif
                @if($empresa_email)Email: {{ $empresa_email }} | @endif
                Generated: {{ $current_datetime }}
            </div>
        </div>
        
    </div>
</body>
</html>