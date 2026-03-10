<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }} - {{ $pagamento->motorista }}</title>

    <style>
        @page {
            margin: 20px 30px;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
            color: #000;
        }

        .container {
            width: 100%;
        }

        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .logo {
            width: 25%;
        }

        .logo img {
            max-height: 80px;
        }

        .company-info {
            width: 50%;
            text-align: center;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: {{ $cor }};
        }

        .company-details {
            font-size: 10px;
            margin-top: 5px;
            line-height: 1.4;
        }

        .doc-info {
            width: 25%;
            text-align: right;
            font-size: 10px;
        }

        .doc-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .section {
            margin-bottom: 15px;
        }

        .section-title {
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        th {
            background: #333;
            color: #fff;
            padding: 6px;
            border: 1px solid #000;
        }

        td {
            border: 1px solid #000;
            padding: 6px;
        }

        .text-right {
            text-align: right;
        }

        .total-box {
            margin-top: 15px;
            border: 1px solid #000;
            padding: 10px;
        }

        .total-value {
            font-size: 14px;
            font-weight: bold;
            text-align: right;
        }

        .valor-extenso {
            font-style: italic;
            font-size: 9px;
            margin-top: 5px;
        }

        .obs-box {
            border: 1px solid #000;
            padding: 8px;
            font-size: 10px;
        }

        .signatures {
            margin-top: 50px;
        }

        .signature-table td {
            width: 50%;
            text-align: center;
        }

        .signature-line {
            margin-top: 40px;
            border-top: 1px solid #000;
            padding-top: 5px;
            font-size: 10px;
        }

        .footer {
            margin-top: 40px;
            font-size: 9px;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            color: #555;
        }

        .watermark {
            position: fixed;
            top: 40%;
            left: 20%;
            font-size: 70px;
            color: #eeeeee;
            transform: rotate(-30deg);
            z-index: -1;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-credito {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-debito {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-pagamento {
            background: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>

<body>
<div class="container">

    @if(isset($copia) && $copia == 'true')
        <div class="watermark">CÓPIA</div>
    @endif

    <!-- ================= HEADER ================= -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo">
                    @if(isset($logo_empresa))
                        <img src="{{ $logo_empresa }}">
                    @endif
                </td>

                <td class="company-info">
                    <div class="company-name">{{ $empresa->nome ?? 'EMPRESA' }}</div>
                    <div class="company-details">
                        @if($empresa->nif) NIF: {{ $empresa->nif }}<br>@endif
                        @if($empresa->endereco) {{ $empresa->endereco }}<br>@endif
                        @if($empresa->telefone) Tel: {{ $empresa->telefone }}<br>@endif
                        @if($empresa->email) Email: {{ $empresa->email }}@endif
                    </div>
                </td>

                <td class="doc-info">
                    <div class="doc-title">{{ $titulo }}</div>
                    Motorista: {{ $pagamento->motorista }}<br>
                    Data: {{ $data_emissao }}
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= RESUMO DO PAGAMENTO ================= -->
    <div class="section">
        <div class="section-title">Resumo do Pagamento</div>
        <table>
            <tr>
                <td><strong>Tipo de Pagamento:</strong> {{ $pagamento->tipo_pagamento }}</td>
                <td><strong>Data:</strong> {{ $data_pagamento }}</td>
            </tr>
            <tr>
                <td><strong>Valor Pago:</strong> {{ $valor_formatado }} MZN</td>
                <td><strong>Desconto Aplicado:</strong> {{ $desconto_formatado }} MZN</td>
            </tr>
        </table>
    </div>

    <!-- ================= BÓNUS ================= -->
    <div class="section">
        <div class="section-title">Bónus</div>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bonus as $b)
                <tr>
                    <td>{{ date('d/m/Y', strtotime($b->created_at)) }}</td>
                    <td>{{ $b->descricao }}</td>
                    <td class="text-right">{{ number_format($b->valor, 2, ',', '.') }} MZN</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="text-center">Nenhum bónus encontrado</td>
                </tr>
                @endforelse
                <tr>
                    <td colspan="2" class="text-right"><strong>Total Bónus:</strong></td>
                    <td class="text-right"><strong>{{ $total_bonus }} MZN</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ================= DESCONTOS ================= -->
    <div class="section">
        <div class="section-title">Descontos</div>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse($descontos as $d)
                <tr>
                    <td>{{ date('d/m/Y', strtotime($d->data_desconto)) }}</td>
                    <td>{{ $d->tipo }}</td>
                    <td>{{ $d->descricao }}</td>
                    <td class="text-right">{{ number_format($d->valor, 2, ',', '.') }} MZN</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center">Nenhum desconto encontrado</td>
                </tr>
                @endforelse
                <tr>
                    <td colspan="3" class="text-right"><strong>Total Descontos:</strong></td>
                    <td class="text-right"><strong>{{ $total_descontos }} MZN</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ================= VALOR POR EXTENSO ================= -->
    <div class="total-box">
        <div class="total-value">
            {{ $valor_formatado }} MZN
        </div>
        <div class="valor-extenso">
            <strong>Valor por extenso:</strong> {{ $valor_extenso }}
        </div>
    </div>

    <!-- ================= OBS ================= -->
    @if($pagamento->observacoes)
        <div class="section">
            <div class="section-title">Observações</div>
            <div class="obs-box">
                {{ $pagamento->observacoes }}
            </div>
        </div>
    @endif

    <!-- ================= ASSINATURAS ================= -->
    <div class="signatures">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line">
                        Assinatura do Motorista<br>
                        {{ $pagamento->motorista }}
                    </div>
                </td>
                <td>
                    <div class="signature-line">
                        Assinatura do Responsável<br>
                        {{ $usuario }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= FOOTER ================= -->
    <div class="footer">
        Documento processado por computador |
        Emitido por: {{ $usuario }} |
        Data/Hora: {{ $current_date }}
    </div>

</div>
</body>
</html>