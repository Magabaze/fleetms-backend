{{-- resources/views/pdf/nota-fiscal.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>{{ $tipo_info['titulo'] }} - {{ $numero_formatado }}</title>
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
            background-color: {{ $tipo_info['cor_fundo'] }};
            color: {{ $tipo_info['cor'] }};
            border: 0.5pt solid {{ $tipo_info['cor'] }};
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
        
        /* TABELA DE ITENS/SERVIÇOS */
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
                <div class="company-name">{{ $empresa->nome ?? 'Transportes ABC' }}</div>
                <div class="company-info">
                    NIF: {{ $empresa->nif ?? '400123456' }}<br>
                    {{ $empresa->endereco ?? 'Av. Principal, 123 — Maputo' }}<br>
                    {{ $empresa->telefone ?? '+258 84 123 4567' }} · {{ $empresa->email ?? 'geral@transportes.co.mz' }}
                </div>
            </div>
            
            <div class="header-cell doc-meta">
                <div class="doc-title">{{ $tipo_info['titulo'] }}</div>
                <div class="doc-number">Nº: {{ $numero_formatado }}</div>
                <div>
                    <span class="badge">{{ $tipo_info['titulo'] }}</span>
                </div>
                <div style="margin-top: 1mm;">
                    <strong>Data:</strong> {{ $data_emissao }}
                </div>
                @if($nota->ordem && $nota->ordem->viagem)
                <div><strong>Ref. Viagem:</strong> {{ $nota->ordem->viagem->trip_number ?? '—' }}</div>
                @endif
            </div>
        </div>
        
        <!-- CONTEÚDO PRINCIPAL -->
        <div class="content">
            
            <!-- COLUNA ESQUERDA -->
            <div class="column left-column">
                
                <!-- CLIENTE - EXATAMENTE IGUAL À ORDEM DE FATURAÇÃO -->
                <div class="section">
                    <div class="section-title">Cliente</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Nome</td>
                                <td class="bold">{{ $cliente['nome'] ?? $nota->cliente ?? '—' }}</td>
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
                
                <!-- DETALHES DA NOTA -->
                <div class="section">
                    <div class="section-title">Detalhes da Nota</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Motivo</td>
                                <td class="bold">{{ $nota->motivo }}</td>
                            </tr>
                            @if($nota->tipo == 'credito')
                            <tr>
                                <td class="label">Doc. Original</td>
                                <td>Fatura/Recibo relacionado</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="label">Referência</td>
                                <td>{{ $nota->numero }}</td>
                            </tr>
                            @if($nota->ordem)
                            <tr>
                                <td class="label">Ordem de Serviço</td>
                                <td>{{ $nota->ordem->numero }} — {{ $nota->ordem->descricao ?? 'Serviços de Transporte' }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                
            </div>
            
            <!-- COLUNA DIREITA -->
            <div class="column right-column">
                
                <!-- INFORMAÇÕES ADICIONAIS -->
                <div class="section">
                    <div class="section-title">Informações Adicionais</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Data Emissão</td>
                                <td>{{ $data_emissao }}</td>
                            </tr>
                            <tr>
                                <td class="label">Data por Extenso</td>
                                <td>{{ $data_extenso }}</td>
                            </tr>
                            @if($nota->ordem && $nota->ordem->viagem)
                            <tr>
                                <td class="label">Origem</td>
                                <td>{{ $nota->ordem->viagem->from_station ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Destino</td>
                                <td>{{ $nota->ordem->viagem->to_station ?? '—' }}</td>
                            </tr>
                            @endif
                            @if($nota->ordem && $nota->ordem->viagem && $nota->ordem->viagem->truck_number)
                            <tr>
                                <td class="label">Camião</td>
                                <td>{{ $nota->ordem->viagem->truck_number }}</td>
                            </tr>
                            @endif
                            @if($nota->ordem && $nota->ordem->viagem && $nota->ordem->viagem->driver)
                            <tr>
                                <td class="label">Motorista</td>
                                <td>{{ $nota->ordem->viagem->driver }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- SERVIÇOS / ITENS -->
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
                                {{ $tipo_info['titulo'] }} — {{ $nota->motivo }}
                                @if($nota->ordem && $nota->ordem->viagem)
                                    @php
                                        $origem = $nota->ordem->viagem->from_station ?? '';
                                        $destino = $nota->ordem->viagem->to_station ?? '';
                                    @endphp
                                    @if($origem && $destino)
                                        — {{ $origem }} → {{ $destino }}
                                    @endif
                                @endif
                            </td>
                            <td class="text-right">
                                {{ $tipo_info['sinal'] ?? '' }}{{ $valor_formatado }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TOTAIS -->
        <div class="totals-box">
            <table class="totals-table">
                <tr>
                    <td>Valor dos Serviços:</td>
                    <td class="text-right">{{ $tipo_info['sinal'] ?? '' }}{{ $valor_formatado }} MZN</td>
                </tr>
                <tr>
                    <td><strong>TOTAL {{ strtoupper($tipo_info['titulo']) }}:</strong></td>
                    <td class="text-right"><strong>{{ $tipo_info['sinal'] ?? '' }}{{ $valor_formatado }} MZN</strong></td>
                </tr>
            </table>
        </div>
        
        <!-- VALOR EXTENSO -->
        <div class="valor-extenso">
            <strong>Valor por extenso:</strong> {{ ucfirst($valor_extenso) }}
        </div>
        
        <!-- OBSERVAÇÕES -->
        @if($nota->observacoes)
        <div class="obs-box">
            <div class="obs-label">Observações</div>
            <div>{{ $nota->observacoes }}</div>
        </div>
        @endif
        
        <!-- ASSINATURAS -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-label">Assinatura do Cliente</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $cliente['nome'] ?? $nota->cliente ?? '—' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Assinatura do Responsável</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $empresa->nome ?? 'Transportes ABC' }}</div>
            </div>
        </div>
        
        <!-- RODAPÉ -->
        <div class="footer">
            <div>
                Documento processado por computador · Válido sem assinatura<br>
                Emissão: {{ $current_date }}@if($criadoPor) · Operador: {{ $criadoPor }}@endif
            </div>
            <div style="margin-top: 1mm;">
                <span class="footer-brand">abdago Fleet</span><br>
                Sistema de Gestão de Transportes
            </div>
        </div>
        
    </div>
</body>
</html>