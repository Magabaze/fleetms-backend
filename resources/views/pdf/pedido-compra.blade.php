<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Pedido de Compra - {{ $pedido->numero }}</title>

    <style>
        /* ===== CONFIGURAÇÃO CRÍTICA ===== */
        @page {
            margin: 20px 30px;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
            color: #000;
            line-height: 1.3;
        }

        .container {
            width: 100%;
        }

        /* ===== MARCA D'ÁGUA ===== */
        @if(isset($copia) && $copia == 'true')
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 70px;
            color: #eeeeee;
            z-index: -1;
            text-transform: uppercase;
            font-weight: bold;
            white-space: nowrap;
        }
        @endif

        /* ===== HEADER COM TABELA ===== */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0 5px 10px 5px;
        }

        .logo-cell {
            width: 20%;
        }

        .logo-img {
            max-height: 70px;
            max-width: 100%;
        }

        .title-cell {
            width: 50%;
            text-align: center;
        }

        .doc-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            color: {{ $cor ?? '#000' }};
        }

        .doc-subtitle {
            font-size: 9px;
            color: #555;
        }

        .doc-number {
            font-size: 12px;
            font-weight: bold;
            margin-top: 5px;
        }

        .type-cell {
            width: 30%;
            text-align: right;
        }

        .doc-type-badge {
            display: inline-block;
            border: 1.5px solid {{ isset($copia) && $copia == 'true' ? '#666' : '#000' }};
            padding: 4px 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            background: {{ isset($copia) && $copia == 'true' ? '#f5f5f5' : '#fff' }};
            color: {{ isset($copia) && $copia == 'true' ? '#666' : '#000' }};
            margin-bottom: 5px;
        }

        /* ===== SEÇÕES ===== */
        .section {
            margin-bottom: 15px;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            border-bottom: 1px solid #333;
            padding-bottom: 3px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        /* ===== TABELAS DE DADOS ===== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .data-table td {
            border: 1px solid #333;
            padding: 6px;
        }

        .data-table th {
            background: #2d3748;
            color: white;
            padding: 6px;
            border: 1px solid #000;
            font-weight: bold;
            text-align: left;
        }

        .label-cell {
            background: #f0f0f0;
            font-weight: bold;
            width: 20%;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* ===== BADGES ===== */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-pendente {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
        }

        .badge-aprovado {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .badge-entregue {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .badge-rejeitado, .badge-cancelado {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* ===== BOX DE TOTAL ===== */
        .total-box {
            margin-top: 15px;
            border: 2px solid #000;
            padding: 10px;
            background: #f8f9fa;
        }

        .total-value {
            font-size: 16px;
            font-weight: bold;
            text-align: right;
        }

        .valor-extenso {
            font-style: italic;
            font-size: 9px;
            margin-top: 5px;
            text-align: right;
            color: #444;
        }

        /* ===== OBSERVAÇÕES ===== */
        .obs-box {
            border: 1px solid #000;
            padding: 8px;
            font-size: 10px;
            background: #fafafa;
        }

        /* ===== ASSINATURAS ===== */
        .signatures {
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .signatures-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            font-size: 10px;
            text-align: center;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 33.33%;
            text-align: center;
            border: none;
            padding: 0 10px;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            height: 40px;
            margin-bottom: 5px;
        }

        /* ===== RODAPÉ ===== */
        .footer {
            margin-top: 40px;
            font-size: 8px;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            color: #555;
        }

        /* ===== IMPRESSÃO ===== */
        @media print {
            .badge-pendente, .badge-aprovado, .badge-entregue, 
            .badge-rejeitado, .badge-cancelado, .data-table th {
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

    <!-- ===== HEADER COM TABELA ===== -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if($logo_empresa)
                    <img src="{{ $logo_empresa }}" class="logo-img">
                @else
                    <div style="font-size: 14px; font-weight: bold; color: {{ $cor ?? '#0aca7d' }};">
                        {{ $empresa->nome ?? 'EMPRESA' }}
                    </div>
                @endif
            </td>
            <td class="title-cell">
                <div class="doc-title">PEDIDO DE COMPRA</div>
                <div class="doc-subtitle">Requisição de Fornecimento</div>
                <div class="doc-number">Nº: {{ $pedido->numero }}</div>
            </td>
            <td class="type-cell">
                <div class="doc-type-badge">{{ isset($copia) && $copia == 'true' ? 'CÓPIA' : 'ORIGINAL' }}</div>
                <div><strong>Data:</strong> {{ $data_emissao }}</div>
            </td>
        </tr>
    </table>

    <!-- ===== INFORMAÇÕES DO FORNECEDOR ===== -->
    <div class="section">
        <div class="section-title">Informações do Fornecedor</div>
        <table class="data-table">
            <tr>
                <td class="label-cell">Fornecedor:</td>
                <td>{{ $pedido->fornecedor }}</td>
                <td class="label-cell">Pedido ID:</td>
                <td>#{{ $pedido->id }}</td>
            </tr>
            <tr>
                <td class="label-cell">Solicitante:</td>
                <td>{{ $pedido->criado_por }}</td>
                <td class="label-cell">Data Pedido:</td>
                <td>{{ $data_pedido }}</td>
            </tr>
        </table>
    </div>

    <!-- ===== INFORMAÇÕES DE ENTREGA ===== -->
    <div class="section">
        <div class="section-title">Informações de Entrega</div>
        <table class="data-table">
            <tr>
                <td class="label-cell">Prevista:</td>
                <td>{{ $data_entrega_prevista }}</td>
                <td class="label-cell">Real:</td>
                <td>{{ $data_entrega_real ?? 'Pendente' }}</td>
            </tr>
        </table>
    </div>

    <!-- ===== DETALHES DO PEDIDO ===== -->
    <div class="section">
        <div class="section-title">Detalhes do Produto</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Combustível</th>
                    <th class="text-right">Quantidade</th>
                    <th class="text-right">Preço Unit.</th>
                    <th class="text-right">Valor Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ str_replace('_', ' ', $pedido->tipo_combustivel) }}</td>
                    <td class="text-right">{{ number_format($pedido->quantidade, 2, ',', '.') }} {{ $pedido->unidade_medida }}</td>
                    <td class="text-right">{{ number_format($pedido->preco_unitario, 2, ',', '.') }} {{ $pedido->moeda }}</td>
                    <td class="text-right">{{ number_format($pedido->valor_total, 2, ',', '.') }} {{ $pedido->moeda }}</td>
                    <td class="text-center">
                        @if($pedido->status == 'pendente')
                            <span class="badge badge-pendente">PENDENTE</span>
                        @elseif($pedido->status == 'aprovado')
                            <span class="badge badge-aprovado">APROVADO</span>
                        @elseif($pedido->status == 'entregue')
                            <span class="badge badge-entregue">ENTREGUE</span>
                        @elseif($pedido->status == 'cancelado')
                            <span class="badge badge-cancelado">CANCELADO</span>
                        @else
                            {{ strtoupper($pedido->status) }}
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ===== TOTAL ===== -->
    <div class="total-box">
        <div class="total-value">
            {{ number_format($pedido->valor_total, 2, ',', '.') }} {{ $pedido->moeda }}
        </div>
        @if(isset($valor_extenso))
        <div class="valor-extenso">
            <strong>Valor por extenso:</strong> {{ $valor_extenso }}
        </div>
        @endif
    </div>

    <!-- ===== HISTÓRICO DE STATUS ===== -->
    <div class="section">
        <div class="section-title">Histórico de Aprovação</div>
        <table class="data-table">
            <tr>
                <td class="label-cell">Criado por:</td>
                <td>{{ $pedido->criado_por }}</td>
                <td class="label-cell">Data:</td>
                <td>{{ $data_pedido }}</td>
            </tr>
            @if($pedido->aprovado_por)
            <tr>
                <td class="label-cell">Aprovado por:</td>
                <td>{{ $pedido->aprovado_por }}</td>
                <td class="label-cell">Data:</td>
                <td>{{ $pedido->data_aprovacao ? date('d/m/Y', strtotime($pedido->data_aprovacao)) : '' }}</td>
            </tr>
            @endif
            @if($pedido->rejeitado_por)
            <tr>
                <td class="label-cell">Rejeitado por:</td>
                <td>{{ $pedido->rejeitado_por }}</td>
                <td class="label-cell">Motivo:</td>
                <td>{{ $pedido->motivo_rejeicao ?? 'Não informado' }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- ===== OBSERVAÇÕES ===== -->
    @if($pedido->observacoes)
        <div class="section">
            <div class="section-title">Observações</div>
            <div class="obs-box">
                {{ $pedido->observacoes }}
            </div>
        </div>
    @endif

    <!-- ===== ASSINATURAS ===== -->
    <div class="signatures">
        <div class="signatures-title">Assinaturas e Conferência</div>
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">SOLICITANTE</div>
                    <div style="font-size: 9px; color: #555;">{{ $pedido->criado_por ?? '____________________' }}</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">APROVAÇÃO</div>
                    <div style="font-size: 9px; color: #555;">{{ $pedido->aprovado_por ?? '____________________' }}</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">FORNECEDOR</div>
                    <div style="font-size: 9px; color: #555;">{{ $pedido->fornecedor ?? '____________________' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ===== RODAPÉ ===== -->
    <div class="footer">
        <div><strong>{{ $empresa->nome ?? '' }}</strong></div>
        <div>
            @if(isset($empresa->nif))NIF: {{ $empresa->nif }} | @endif
            @if(isset($empresa->endereco)){{ $empresa->endereco }} | @endif
            @if(isset($empresa->telefone))Tel: {{ $empresa->telefone }} | @endif
            @if(isset($empresa->email)){{ $empresa->email }}@endif
        </div>
        <div style="margin-top: 3px;">
            Documento processado por computador | Emitido por: {{ $usuario }} | Data/Hora: {{ $current_date }}
        </div>
    </div>

</div>
</body>
</html>