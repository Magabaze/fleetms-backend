{{-- resources/views/pdf/pedido-compra.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Pedido de Compra - {{ $pedido->numero ?? $pedido->id }}</title>
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
        .status-entregue   { background-color: #10b981; color: #fff; }
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
            .status-pendente, .status-aprovado, .status-entregue, 
            .status-cancelado, .status-rejeitado, .items-table th {
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
                <div class="doc-title">PEDIDO DE COMPRA</div>
                <div class="doc-number">Nº: {{ $pedido->numero ?? $pedido->id }}</div>
                <div>
                    @php
                        $statusClass = match($pedido->status ?? 'pendente') {
                            'aprovado' => 'status-aprovado',
                            'entregue' => 'status-entregue',
                            'cancelado', 'rejeitado' => 'status-cancelado',
                            default => 'status-pendente'
                        };
                        $statusText = strtoupper($pedido->status ?? 'PENDENTE');
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ $statusText }}</span>
                </div>
                <div style="margin-top: 1mm;">
                    <strong>Data:</strong> {{ $data_pedido ?? date('d/m/Y') }}
                </div>
            </div>
        </div>
        
        <!-- CONTEÚDO PRINCIPAL -->
        <div class="content">
            
            <!-- COLUNA ESQUERDA -->
            <div class="column left-column">
                
                <!-- FORNECEDOR -->
                <div class="section">
                    <div class="section-title">Fornecedor</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Nome</td>
                                <td class="bold">{{ $pedido->fornecedor ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Pedido ID</td>
                                <td>#{{ $pedido->id }}</td>
                            </tr>
                            <tr>
                                <td class="label">Solicitante</td>
                                <td>{{ $pedido->criado_por ?? '—' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
            </div>
            
            <!-- COLUNA DIREITA -->
            <div class="column right-column">
                
                <!-- INFORMAÇÕES DE ENTREGA -->
                <div class="section">
                    <div class="section-title">Informações de Entrega</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Data Prevista</td>
                                <td>{{ $data_entrega_prevista ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Data Real</td>
                                <td>{{ $data_entrega_real ?? 'Pendente' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Data Pedido</td>
                                <td>{{ $data_pedido ?? '—' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- DETALHES DO PEDIDO -->
        <div class="section">
            <div class="section-title">Detalhes do Produto</div>
            <div class="section-content">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>COMBUSTÍVEL</th>
                            <th class="text-right">QUANTIDADE</th>
                            <th class="text-right">PREÇO UNIT.</th>
                            <th class="text-right">VALOR TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ str_replace('_', ' ', $pedido->tipo_combustivel ?? 'DIESEL') }}</td>
                            <td class="text-right">{{ number_format($pedido->quantidade ?? 0, 2, ',', '.') }} {{ $pedido->unidade_medida ?? 'L' }}</td>
                            <td class="text-right">{{ number_format($pedido->preco_unitario ?? 0, 2, ',', '.') }} {{ $pedido->moeda ?? 'MZN' }}</td>
                            <td class="text-right">{{ number_format($pedido->valor_total ?? 0, 2, ',', '.') }} {{ $pedido->moeda ?? 'MZN' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TOTAIS -->
        <div class="totals-box">
            <table class="totals-table">
                <tr>
                    <td>Valor Total do Pedido:</td>
                    <td class="text-right">{{ number_format($pedido->valor_total ?? 0, 2, ',', '.') }} {{ $pedido->moeda ?? 'MZN' }}</td>
                </tr>
                <tr>
                    <td><strong>TOTAL A PAGAR:</strong></td>
                    <td class="text-right"><strong>{{ number_format($pedido->valor_total ?? 0, 2, ',', '.') }} {{ $pedido->moeda ?? 'MZN' }}</strong></td>
                </tr>
            </table>
        </div>
        
        <!-- VALOR EXTENSO -->
        @if(isset($valor_extenso))
        <div class="valor-extenso">
            <strong>Valor por extenso:</strong> {{ ucfirst($valor_extenso) }}
        </div>
        @endif
        
        <!-- HISTÓRICO DE APROVAÇÃO -->
        <div class="section">
            <div class="section-title">Histórico de Aprovação</div>
            <div class="section-content">
                <table class="table-info">
                    <tr>
                        <td class="label">Criado por</td>
                        <td>{{ $pedido->criado_por ?? '—' }}</td>
                    </tr>
                    @if($pedido->aprovado_por)
                    <tr>
                        <td class="label">Aprovado por</td>
                        <td>{{ $pedido->aprovado_por }}</td>
                    </tr>
                    <tr>
                        <td class="label">Data Aprovação</td>
                        <td>{{ $pedido->data_aprovacao ? date('d/m/Y', strtotime($pedido->data_aprovacao)) : '' }}</td>
                    </tr>
                    @endif
                    @if($pedido->rejeitado_por)
                    <tr>
                        <td class="label">Rejeitado por</td>
                        <td>{{ $pedido->rejeitado_por }}</td>
                    </tr>
                    <tr>
                        <td class="label">Motivo Rejeição</td>
                        <td>{{ $pedido->motivo_rejeicao ?? 'Não informado' }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
        
        <!-- OBSERVAÇÕES -->
        @if($pedido->observacoes)
        <div class="obs-box">
            <div class="obs-label">Observações</div>
            <div>{{ $pedido->observacoes }}</div>
        </div>
        @endif
        
        <!-- ALERTA PENDENTE -->
        @if(($pedido->status ?? 'pendente') == 'pendente')
        <div class="alert-warning">
            <strong>⚠️ ATENÇÃO:</strong>
            Este pedido de compra ainda não foi aprovado. Aguarde a aprovação para prosseguir com o fornecimento.
        </div>
        @endif
        
        <!-- ASSINATURAS -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-label">Solicitante</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $pedido->criado_por ?? '—' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Aprovação</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $pedido->aprovado_por ?? '—' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Fornecedor</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $pedido->fornecedor ?? '—' }}</div>
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