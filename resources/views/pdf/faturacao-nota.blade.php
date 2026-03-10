<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }} - {{ $nota->numero }}</title>

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

        /* ================= HEADER ================= */

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

        /* ================= SECTIONS ================= */

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

        /* ================= TOTAL BOX ================= */

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

        /* ================= OBS ================= */

        .obs-box {
            border: 1px solid #000;
            padding: 8px;
            font-size: 10px;
        }

        /* ================= SIGNATURES ================= */

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

        /* ================= FOOTER ================= */

        .footer {
            margin-top: 40px;
            font-size: 9px;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            color: #555;
        }

        /* ================= WATERMARK ================= */

        .watermark {
            position: fixed;
            top: 40%;
            left: 20%;
            font-size: 70px;
            color: #eeeeee;
            transform: rotate(-30deg);
            z-index: -1;
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
                    @if(isset($empresa->logo_url))
                        <img src="{{ $empresa->logo_url }}">
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
                    Nº: {{ $nota->numero }}<br>
                    Data: {{ $data_emissao }}
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= CLIENT INFO ================= -->

    <div class="section">
        <div class="section-title">Informações do Cliente</div>
        <table>
            <tr>
                <td><strong>Cliente:</strong> {{ $nota->cliente }}</td>
                <td><strong>Motivo:</strong> {{ $nota->motivo }}</td>
            </tr>
            @if($nota->ordem)
            <tr>
                <td><strong>Ordem:</strong> #{{ $nota->ordem->id }}</td>
                <td><strong>Data:</strong> {{ $data_emissao }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- ================= DETALHES ================= -->

    <div class="section">
        <div class="section-title">Detalhes</div>
        <table>
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th class="text-right">Valor (MZN)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $nota->motivo }}</td>
                    <td class="text-right">{{ number_format($nota->valor, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ================= TOTAL ================= -->

    <div class="total-box">
        <div class="total-value">
            {{ $sinal }} {{ number_format($nota->valor, 2, ',', '.') }} MZN
        </div>
        <div class="valor-extenso">
            <strong>Valor por extenso:</strong>
            {{ $sinal == '-' ? 'Menos ' : '' }}{{ $valor_extenso }}
        </div>
    </div>

    <!-- ================= OBS ================= -->

    @if($nota->observacoes)
        <div class="section">
            <div class="section-title">Observações</div>
            <div class="obs-box">
                {{ $nota->observacoes }}
            </div>
        </div>
    @endif

    <!-- ================= SIGNATURES ================= -->

    <div class="signatures">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line">
                        Assinatura do Cliente<br>
                        {{ $nota->cliente }}
                    </div>
                </td>
                <td>
                    <div class="signature-line">
                        Assinatura do Responsável<br>
                        {{ $empresa->nome ?? 'Empresa' }}
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