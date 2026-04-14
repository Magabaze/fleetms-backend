{{-- resources/views/pdf/faturacao-ordem.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Ordem de Faturação - {{ $ordem->codigo }}</title>
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
        .status-processado { background-color: #28a745; color: #fff; }
        .status-cancelado  { background-color: #dc3545; color: #fff; }
        
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
        
        /* TABELA DE ITEMS/SERVIÇOS */
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
        
        /* NOTAS ASSOCIADAS */
        .notas-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
            margin-top: 1mm;
        }
        
        .notas-table th {
            background: #718096;
            color: white;
            padding: 1mm 2mm;
            border: 0.5pt solid #000;
            text-align: left;
            font-weight: bold;
            font-size: 6pt;
        }
        
        .notas-table td {
            border: 0.5pt solid #ddd;
            padding: 0.8mm 2mm;
            vertical-align: top;
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
            border: 0.5pt dashed #f0d060;
            background: #fffbea;
            padding: 2mm 3mm;
            margin: 3mm 0;
        }
        
        .obs-label {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #5a4200;
            margin-bottom: 1mm;
        }
        
        /* ALERTA */
        .alert-warning {
            background: #fff3cd;
            border: 0.5pt solid #ffeeba;
            padding: 2mm 3mm;
            margin: 3mm 0;
            font-size: 6.5pt;
            color: #856404;
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
            width: 50%;
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
        
        /* EVITA QUEBRAS */
        .section, .signatures, .totals-box, .valor-extenso, .obs-box, .alert-warning {
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
                <div class="company-name">{{ $empresa->nome ?? '—' }}</div>
                <div class="company-info">
                    {{ $empresa->endereco ?? '' }}<br>
                    NUIT: {{ $empresa->nif ?? '—' }} | Tel: {{ $empresa->telefone ?? '—' }}<br>
                    {{ $empresa->email ?? '' }}
                </div>
            </div>
            
            <div class="header-cell doc-meta">
                <div class="doc-title">Ordem de Faturação</div>
                <div class="doc-number">Nº: {{ $ordem->codigo }}</div>
                <div>
                    <span class="status-badge status-{{ $ordem->status }}">{{ $status }}</span>
                </div>
                <div style="margin-top: 1mm;">
                    <strong>Data:</strong> {{ $data_emissao }}
                </div>
                @if($viagem)
                <div><strong>Ref. Viagem:</strong> {{ $viagem->trip_number }}</div>
                @endif
            </div>
        </div>
        
        <!-- CONTEÚDO PRINCIPAL -->
        <div class="content">
            
            <!-- COLUNA ESQUERDA -->
            <div class="column left-column">
                
                <!-- CLIENTE -->
                <div class="section">
                    <div class="section-title">Cliente</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Nome</td>
                                <td class="bold">{{ $cliente['nome'] ?? $ordem->cliente ?? ($viagem->customer_name ?? '—') }}</td>
                            </tr>
                            @if(!empty($cliente['endereco']))
                            <tr>
                                <td class="label">Endereço</td>
                                <td>{{ $cliente['endereco'] }}</td>
                            </tr>
                            @endif
                            @if(!empty($cliente['nuit']))
                            <tr>
                                <td class="label">NUIT/NIF</td>
                                <td>{{ $cliente['nuit'] }}</td>
                            </tr>
                            @endif
                            @if(!empty($cliente['telefone']))
                            <tr>
                                <td class="label">Telefone</td>
                                <td>{{ $cliente['telefone'] }}</td>
                            </tr>
                            @endif
                            @if(!empty($cliente['email']))
                            <tr>
                                <td class="label">Email</td>
                                <td>{{ $cliente['email'] }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                
            </div>
            
            <!-- COLUNA DIREITA -->
            <div class="column right-column">
                
                <!-- INFORMAÇÕES DA VIAGEM -->
                <div class="section">
                    <div class="section-title">Informações da Viagem</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Origem</td>
                                <td>{{ $ordem->origem ?? ($viagem->from_station ?? '—') }}</td>
                            </tr>
                            <tr>
                                <td class="label">Destino</td>
                                <td>{{ $ordem->destino ?? ($viagem->to_station ?? '—') }}</td>
                            </tr>
                            @if($viagem)
                            <tr>
                                <td class="label">Camião</td>
                                <td>{{ $viagem->truck_number ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Reboque</td>
                                <td>{{ $viagem->trailer_number ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Motorista</td>
                                <td>{{ $viagem->driver ?? '—' }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- SERVIÇOS -->
        <div class="section">
            <div class="section-title">Serviços</div>
            <div class="section-content">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:5%">#</th>
                            <th>DESCRIÇÃO</th>
                            <th style="width:25%; text-align:right;">VALOR (MZN)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>
                                Serviços de Transporte
                                @if($ordem->origem && $ordem->destino)
                                    — {{ $ordem->origem }} → {{ $ordem->destino }}
                                @elseif($viagem)
                                    — {{ $viagem->from_station ?? '' }} → {{ $viagem->to_station ?? '' }}
                                @endif
                            </td>
                            <td class="text-right">{{ $servicos_formatado }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- NOTAS ASSOCIADAS -->
        @if(isset($notas) && count($notas) > 0)
        <div class="section">
            <div class="section-title">Notas Associadas</div>
            <div class="section-content">
                <table class="notas-table">
                    <thead>
                        <tr>
                            <th>Nº Nota</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th class="text-right">Valor (MZN)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notas as $nota)
                        <tr>
                            <td>{{ $nota->numero }}</td>
                            <td>{{ ucfirst($nota->tipo) }}</td>
                            <td>{{ $nota->motivo }}</td>
                            <td class="text-right">
                                {{ $nota->tipo == 'credito' ? '- ' : '+ ' }}{{ number_format($nota->valor, 2, ',', '.') }}
                            </td>
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
                <tr>
                    <td>Valor dos Serviços:</td>
                    <td class="text-right">{{ $servicos_formatado }} MZN</td>
                </tr>
                @if($totais['debitos'] > 0)
                <tr>
                    <td>Notas de Débito (+):</td>
                    <td class="text-right">+ {{ $debitos_formatado }} MZN</td>
                </tr>
                @endif
                @if($totais['creditos'] > 0)
                <tr>
                    <td>Notas de Crédito (-):</td>
                    <td class="text-right">- {{ $creditos_formatado }} MZN</td>
                </tr>
                @endif
                <tr>
                    <td><strong>TOTAL A FATURAR:</strong></td>
                    <td class="text-right"><strong>{{ $saldo_formatado }} MZN</strong></td>
                </tr>
            </table>
        </div>
        
        <!-- VALOR EXTENSO -->
        <div class="valor-extenso">
            <strong>Valor por extenso:</strong> {{ ucfirst($valor_extenso) }}
        </div>
        
        <!-- OBSERVAÇÕES -->
        @if(!empty($ordem->observacoes))
        <div class="obs-box">
            <div class="obs-label">Observações</div>
            <div>{{ $ordem->observacoes }}</div>
        </div>
        @endif
        
        <!-- ALERTA PENDENTE -->
        @if($ordem->status == 'pendente')
        <div class="alert-warning">
            <strong>⚠️ ATENÇÃO:</strong>
            Esta ordem ainda não foi processada. Após emissão da fatura oficial, marque como "Processado" no sistema.
        </div>
        @endif
        
        <!-- ASSINATURAS -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-label">Assinatura do Cliente</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $cliente['nome'] ?? $ordem->cliente ?? ($viagem->customer_name ?? '—') }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Assinatura do Responsável</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $empresa->nome ?? '—' }}</div>
            </div>
        </div>
        
        <!-- RODAPÉ -->
        <div class="footer">
            <div>
                Documento gerado por {{ $criadoPor }} em {{ $current_date }}
                &nbsp;|&nbsp;
                Este documento serve como pré-fatura e guia para emissão do documento fiscal oficial.
            </div>
            <div style="margin-top: 1mm;">
                <span class="footer-brand">abdago Fleet</span><br>
                Sistema de Gestão de Transportes
            </div>
        </div>
        
    </div>
</body>
</html>