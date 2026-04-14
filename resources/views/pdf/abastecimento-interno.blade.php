{{-- resources/views/pdf/abastecimento-interno.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Abastecimento Interno - {{ $abastecimento->numero ?? $abastecimento->id }}</title>
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
        
        /* STATUS BADGE */
        .status-badge {
            display: inline-block;
            padding: 1mm 3mm;
            border-radius: 1mm;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 1mm 0;
        }
        .status-pendente   { background-color: #ffc107; color: #000; }
        .status-aprovado   { background-color: #3b82f6; color: #fff; }
        .status-realizado  { background-color: #10b981; color: #fff; }
        .status-concluido  { background-color: #10b981; color: #fff; }
        .status-cancelado  { background-color: #ef4444; color: #fff; }
        .status-rejeitado  { background-color: #ef4444; color: #fff; }
        
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
        
        /* TABELA DE ITENS */
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
        .section, .signatures, .totals-box, .obs-box {
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
            .status-pendente, .status-aprovado, .status-realizado, 
            .status-concluido, .status-cancelado, .status-rejeitado, .items-table th {
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
                <div class="doc-title">ABASTECIMENTO INTERNO</div>
                <div class="doc-number">Nº: {{ $abastecimento->numero ?? $abastecimento->id }}</div>
                <div>
                    @php
                        $statusClass = match($abastecimento->status ?? 'pendente') {
                            'aprovado' => 'status-aprovado',
                            'realizado', 'concluido' => 'status-realizado',
                            'cancelado', 'rejeitado' => 'status-cancelado',
                            default => 'status-pendente'
                        };
                        $statusText = strtoupper($abastecimento->status ?? 'PENDENTE');
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ $statusText }}</span>
                </div>
                <div style="margin-top: 1mm;">
                    <strong>Data:</strong> {{ $data_abastecimento ?? date('d/m/Y') }}
                </div>
                @if(isset($abastecimento->viagem) && $abastecimento->viagem)
                <div><strong>Ref. Viagem:</strong> {{ $abastecimento->viagem->trip_number ?? '—' }}</div>
                @endif
            </div>
        </div>
        
        <!-- CONTEÚDO PRINCIPAL -->
        <div class="content">
            
            <!-- COLUNA ESQUERDA -->
            <div class="column left-column">
                
                <!-- VEÍCULO E MOTORISTA -->
                <div class="section">
                    <div class="section-title">Veículo e Motorista</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Matrícula</td>
                                <td class="bold">{{ $abastecimento->camiao->matricula ?? $abastecimento->veiculo_matricula ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Motorista</td>
                                <td>{{ $abastecimento->motorista->nome ?? $abastecimento->motorista_nome ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Odômetro</td>
                                <td>{{ number_format($abastecimento->odometro ?? 0, 0, ',', '.') }} km</td>
                            </tr>
                            <tr>
                                <td class="label">Responsável</td>
                                <td>{{ $abastecimento->responsavel_registro ?? 'Sistema' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- TANQUE -->
                @if(isset($abastecimento->tanque))
                <div class="section">
                    <div class="section-title">Tanque</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Tanque</td>
                                <td class="bold">{{ $abastecimento->tanque->nome ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Combustível</td>
                                <td>{{ str_replace('_', ' ', $abastecimento->tanque->tipo_combustivel ?? $abastecimento->tipo_combustivel ?? 'DIESEL') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                @endif
                
            </div>
            
            <!-- COLUNA DIREITA -->
            <div class="column right-column">
                
                <!-- HISTÓRICO -->
                <div class="section">
                    <div class="section-title">Histórico</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Registrado por</td>
                                <td>{{ $abastecimento->responsavel_registro ?? 'Sistema' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Data/Hora Registro</td>
                                <td>{{ isset($abastecimento->data_registro) ? date('d/m/Y H:i', strtotime($abastecimento->data_registro)) : $current_date }}</td>
                            </tr>
                            @if($abastecimento->aprovado_por)
                            <tr>
                                <td class="label">Aprovado por</td>
                                <td>{{ $abastecimento->aprovado_por }}</td>
                            </tr>
                            <tr>
                                <td class="label">Data Aprovação</td>
                                <td>{{ $abastecimento->data_aprovacao ? date('d/m/Y', strtotime($abastecimento->data_aprovacao)) : '' }}</td>
                            </tr>
                            @endif
                            @if($abastecimento->conferido_por)
                            <tr>
                                <td class="label">Conferido por</td>
                                <td>{{ $abastecimento->conferido_por }}</td>
                            </tr>
                            <tr>
                                <td class="label">Data Conferência</td>
                                <td>{{ $abastecimento->data_conferencia ? date('d/m/Y', strtotime($abastecimento->data_conferencia)) : '' }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- DETALHES DO ABASTECIMENTO -->
        <div class="section">
            <div class="section-title">Detalhes do Abastecimento</div>
            <div class="section-content">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>COMBUSTÍVEL</th>
                            <th class="text-right">QUANTIDADE (L)</th>
                            <th>DATA/HORA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ str_replace('_', ' ', $abastecimento->tipo_combustivel ?? 'DIESEL') }}</td>
                            <td class="text-right">{{ number_format($abastecimento->quantidade ?? 0, 2, ',', '.') }} L</td>
                            <td>{{ $data_abastecimento ?? date('d/m/Y') }} {{ $hora_abastecimento ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TOTAIS -->
        <div class="totals-box">
            <table class="totals-table">
                <tr>
                    <td>Quantidade Total:</td>
                    <td class="text-right">{{ number_format($abastecimento->quantidade ?? 0, 2, ',', '.') }} Litros</td>
                </tr>
                <tr>
                    <td><strong>TOTAL ABASTECIDO:</strong></td>
                    <td class="text-right"><strong>{{ number_format($abastecimento->quantidade ?? 0, 2, ',', '.') }} Litros</strong></td>
                </tr>
            </table>
        </div>
        
        <!-- OBSERVAÇÕES -->
        @if($abastecimento->observacoes || isset($abastecimento->observacoes_quantidade))
        <div class="obs-box">
            <div class="obs-label">Observações</div>
            <div>
                @if($abastecimento->observacoes)
                    {{ $abastecimento->observacoes }}<br>
                @endif
                @if(isset($abastecimento->observacoes_quantidade))
                    {{ $abastecimento->observacoes_quantidade }}
                @endif
            </div>
        </div>
        @endif
        
        <!-- ASSINATURAS -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-label">Motorista</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $abastecimento->motorista->nome ?? $abastecimento->motorista_nome ?? '—' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Responsável</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $abastecimento->responsavel_registro ?? '—' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Conferente</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $abastecimento->conferido_por ?? $usuario ?? '—' }}</div>
            </div>
        </div>
        
        <!-- RODAPÉ -->
        <div class="footer">
            <div>
                Documento processado por computador · Válido sem assinatura<br>
                Emissão: {{ $current_date }} · Operador: {{ $usuario ?? 'Sistema' }}
            </div>
            <div style="margin-top: 1mm;">
                <span class="footer-brand">abdago Fleet</span><br>
                Sistema de Gestão de Transportes
            </div>
        </div>
        
    </div>
</body>
</html>