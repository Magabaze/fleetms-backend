{{-- resources/views/pdf/justificativo-viagem.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Justificativo - {{ $viagem->trip_number ?? '—' }}</title>
    <style>
        /* RESET E CONFIGURAÇÕES BASE */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            line-height: 1.4;
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
            max-width: 190mm;
            margin: 0 auto;
            padding: 3mm 4mm;
            background: #fff;
        }
        
        /* CABEÇALHO */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 1.2pt solid #013334;
        }
        
        .header-cell {
            display: table-cell;
            vertical-align: middle;
        }
        
        .logo {
            width: 45mm;
        }
        
        .logo img {
            max-width: 40mm;
            max-height: 18mm;
            display: block;
        }
        
        .company-info {
            font-size: 7pt;
            color: #5a5f6e;
            line-height: 1.6;
        }
        
        .company-name {
            font-size: 11pt;
            font-weight: bold;
            color: #013334;
            margin-bottom: 1mm;
        }
        
        .doc-meta {
            text-align: right;
            width: 55mm;
        }
        
        .doc-title {
            font-size: 12pt;
            font-weight: bold;
            color: #013334;
            margin-bottom: 2mm;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }
        
        .doc-number {
            font-size: 9pt;
            font-weight: bold;
            color: #0f1116;
        }
        
        .badge {
            display: inline-block;
            padding: 1mm 3mm;
            border-radius: 1mm;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 1mm 0;
            background-color: #e8f0f0;
            color: #013334;
            border: 0.5pt solid #013334;
        }
        
        /* CONTEÚDO PRINCIPAL */
        .content {
            display: table;
            width: 100%;
            margin-bottom: 3mm;
        }
        
        .column {
            display: table-cell;
            vertical-align: top;
            padding: 0 2mm;
        }
        
        .left-column {
            width: 50%;
            padding-left: 0;
        }
        
        .right-column {
            width: 50%;
            padding-right: 0;
        }
        
        /* SEÇÕES */
        .section {
            border: 0.5pt solid #e4e6ec;
            margin-bottom: 3mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        
        .section-title {
            background: #013334;
            color: white;
            padding: 1.5mm 3mm;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }
        
        .section-content {
            padding: 2mm 3mm;
        }
        
        /* TABELAS */
        .table-info {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        
        .table-info td {
            padding: 1mm 0;
            vertical-align: top;
            border: none;
        }
        
        .label {
            font-weight: bold;
            width: 35%;
            color: #5a5f6e;
            text-transform: uppercase;
            font-size: 6.5pt;
        }
        
        /* TABELA DE DESPESAS */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5pt;
            margin-top: 1mm;
        }
        
        .items-table th {
            background: #4a5568;
            color: white;
            padding: 1mm 2mm;
            border: 0.5pt solid #000;
            text-align: left;
            font-weight: bold;
            font-size: 6.5pt;
            text-transform: uppercase;
        }
        
        .items-table td {
            border: 0.5pt solid #ddd;
            padding: 1mm 2mm;
            vertical-align: top;
        }
        
        .items-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        
        /* STATUS BADGE */
        .status-badge {
            display: inline-block;
            padding: 0.5mm 2mm;
            border-radius: 1mm;
            font-size: 5.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pendente { background-color: #f59e0b; color: #000; }
        .status-justificado { background-color: #10b981; color: #fff; }
        
        /* TOTAIS */
        .totals-box {
            background: #f5f6f8;
            border: 0.5pt solid #e4e6ec;
            padding: 2mm 3mm;
            margin: 3mm 0;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        
        .totals-table td {
            padding: 0.8mm 0;
            border: none;
        }
        
        .totals-table tr:last-child td {
            border-top: 0.5pt solid #013334;
            padding-top: 1.5mm;
            font-weight: bold;
            font-size: 8pt;
        }
        
        /* VALOR EXTENSO */
        .valor-extenso {
            background: #e8f0f0;
            border-left: 3pt solid #013334;
            padding: 2mm 3mm;
            margin: 3mm 0;
            font-style: italic;
            font-size: 7pt;
            color: #013334;
        }
        
        /* OBSERVAÇÕES */
        .obs-box {
            border: 0.5pt dashed #e4e6ec;
            background: #fafafa;
            padding: 2mm 3mm;
            margin: 3mm 0;
        }
        
        .obs-label {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #5a5f6e;
            margin-bottom: 1mm;
        }
        
        /* ASSINATURAS */
        .signatures {
            display: table;
            width: 100%;
            margin-top: 4mm;
            padding-top: 3mm;
            border-top: 0.5pt solid #e4e6ec;
        }
        
        .signature-box {
            display: table-cell;
            text-align: center;
            width: 33.33%;
        }
        
        .signature-line {
            height: 12mm;
            border-bottom: 0.5pt solid #9fa4b0;
            margin: 1mm 5mm;
        }
        
        .signature-label {
            font-size: 6.5pt;
            font-weight: bold;
            margin-bottom: 0.5mm;
            color: #5a5f6e;
        }
        
        /* RODAPÉ */
        .footer {
            width: 100%;
            text-align: center;
            font-size: 6pt;
            color: #9fa4b0;
            padding-top: 2mm;
            border-top: 0.5pt solid #e4e6ec;
            margin-top: 3mm;
        }
        
        .footer-brand {
            font-weight: bold;
            color: #013334;
        }
        
        /* UTILITÁRIOS */
        .bold { font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #9fa4b0; }
        
        /* MARCA D'ÁGUA */
        @if(isset($copia) && $copia == 'true')
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(1, 51, 52, 0.08);
            z-index: -1;
            text-transform: uppercase;
            font-weight: bold;
            white-space: nowrap;
        }
        @endif
        
        /* EVITA QUEBRAS */
        .section, .signatures, .totals-box, .valor-extenso, .obs-box {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                padding: 2mm;
            }
            .status-pendente, .status-justificado, .items-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        @if(isset($copia) && $copia == 'true')
            <div class="watermark">CÓPIA</div>
        @endif
        
        <!-- CABEÇALHO -->
        <div class="header">
            <div class="header-cell logo">
                @if($logo_empresa)
                    <img src="{{ $logo_empresa }}" alt="Logo">
                @endif
                <div class="company-name">{{ $empresa->nome ?? 'Transportes ABC' }}</div>
                <div class="company-info">
                    NIF: {{ $empresa->nif ?? '400123456' }}<br>
                    {{ $empresa->endereco ?? 'Av. Principal, 123 — Maputo' }}<br>
                    {{ $empresa->telefone ?? '+258 84 123 4567' }} · {{ $empresa->email ?? 'geral@transportes.co.mz' }}
                </div>
            </div>
            
            <div class="header-cell doc-meta">
                <div class="doc-title">JUSTIFICATIVO DE DESPESAS</div>
                <div class="doc-number">Viagem: {{ $viagem->trip_number ?? '—' }}</div>
                <div>
                    <span class="badge">{{ isset($copia) && $copia == 'true' ? 'CÓPIA' : 'ORIGINAL' }}</span>
                </div>
                <div style="margin-top: 1mm;">
                    <strong>Emissão:</strong> {{ $data_emissao ?? date('d/m/Y H:i') }}
                </div>
            </div>
        </div>
        
        <!-- CONTEÚDO PRINCIPAL -->
        <div class="content">
            
            <!-- COLUNA ESQUERDA -->
            <div class="column left-column">
                
                <!-- INFORMAÇÕES DA VIAGEM -->
                <div class="section">
                    <div class="section-title">Informações da Viagem</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Motorista</td>
                                <td class="bold">{{ $viagem->driver ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Camião</td>
                                <td>{{ $viagem->truck_number ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Origem</td>
                                <td>{{ $viagem->from_station ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Destino</td>
                                <td>{{ $viagem->to_station ?? '—' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
            </div>
            
            <!-- COLUNA DIREITA -->
            <div class="column right-column">
                
                <!-- DATAS E CLIENTE -->
                <div class="section">
                    <div class="section-title">Datas e Cliente</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Data Saída</td>
                                <td>{{ $viagem->schedule_date ? date('d/m/Y', strtotime($viagem->schedule_date)) : '—' }}</td>
                            </tr>
                            @if($viagem->actual_delivery)
                            <tr>
                                <td class="label">Data Entrega</td>
                                <td>{{ date('d/m/Y', strtotime($viagem->actual_delivery)) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="label">Cliente</td>
                                <td>{{ $viagem->customer_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">KM Real</td>
                                <td>{{ number_format($viagem->km_real ?? 0, 0, ',', '.') }} km</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- DESPESAS JUSTIFICADAS -->
        <div class="section">
            <div class="section-title">Despesas Justificadas</div>
            <div class="section-content">
                @if($despesasJustificadas->count() > 0)
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>TIPO</th>
                            <th>DESCRIÇÃO</th>
                            <th class="text-right">VALOR</th>
                            <th class="text-center">MOEDA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($despesasJustificadas as $despesa)
                        <tr>
                            <td>{{ $despesa->expenseHead ?? $despesa->expense_head ?? '—' }}</td>
                            <td>{{ $despesa->paymentDescription ?? $despesa->payment_description ?? '—' }}</td>
                            <td class="text-right">{{ number_format($despesa->amount ?? 0, 2, ',', '.') }}</td>
                            <td class="text-center">{{ $despesa->currency ?? 'MZN' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="text-center text-muted" style="padding: 5mm;">Nenhuma despesa justificada</div>
                @endif
            </div>
        </div>
        
        <!-- DESPESAS PENDENTES -->
        @if($despesasPendentes->count() > 0)
        <div class="section">
            <div class="section-title">Despesas Pendentes</div>
            <div class="section-content">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>TIPO</th>
                            <th>DESCRIÇÃO</th>
                            <th class="text-right">VALOR</th>
                            <th class="text-center">MOEDA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($despesasPendentes as $despesa)
                        <tr>
                            <td>{{ $despesa->expenseHead ?? $despesa->expense_head ?? '—' }}</td>
                            <td>{{ $despesa->paymentDescription ?? $despesa->payment_description ?? '—' }}</td>
                            <td class="text-right">{{ number_format($despesa->amount ?? 0, 2, ',', '.') }}</td>
                            <td class="text-center">{{ $despesa->currency ?? 'MZN' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        
        <!-- TOTAIS -->
        <div class="totals-box">
            <table class="totals-table">
                @foreach($totaisFormatados as $moeda => $valor)
                <tr>
                    <td>Total em <strong>{{ $moeda }}</strong>:</td>
                    <td class="text-right"><strong>{{ $valor }} {{ $moeda }}</strong></td>
                </tr>
                @endforeach
            </table>
        </div>
        
        <!-- VALOR EXTENSO -->
        @if(isset($valorExtenso) && $valorExtenso)
        <div class="valor-extenso">
            <strong>Valor por extenso:</strong> {{ ucfirst($valorExtenso) }}
        </div>
        @endif
        
        <!-- OBSERVAÇÕES -->
        @if($viagem->tracking_comments)
        <div class="obs-box">
            <div class="obs-label">Observações</div>
            <div>{{ $viagem->tracking_comments }}</div>
        </div>
        @endif
        
        <!-- ASSINATURAS -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-label">Motorista</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $viagem->driver ?? '—' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Responsável</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $usuario ?? '—' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Caixa</div>
                <div class="signature-line"></div>
                <div class="text-muted">____________________</div>
            </div>
        </div>
        
        <!-- RODAPÉ -->
        <div class="footer">
            <div>
                Documento processado por computador · Válido sem assinatura<br>
                Emissão: {{ $current_date ?? date('d/m/Y H:i:s') }} · Operador: {{ $usuario ?? 'Sistema' }}
            </div>
            <div style="margin-top: 1mm;">
                <span class="footer-brand">{{ config('app.name', 'SGT') }}</span><br>
                Sistema de Gestão de Transportes
            </div>
        </div>
        
    </div>
</body>
</html>