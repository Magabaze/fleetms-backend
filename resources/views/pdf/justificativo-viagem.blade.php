<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Justificativo - {{ $viagem->trip_number }}</title>

    <style>
        /* ===== CONFIGURAÇÃO CRÍTICA ===== */
        @page {
            margin: 20px 25px;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 10px;
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
            color: rgba(238, 238, 238, 0.5);
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
            margin-bottom: 15px;
            border-bottom: 2px solid #013334;
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
            color: {{ $cor ?? '#0aca7d' }};
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
            border: 1.5px solid {{ isset($copia) && $copia == 'true' ? '#666' : '#013334' }};
            padding: 4px 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            background: {{ isset($copia) && $copia == 'true' ? '#f5f5f5' : '#fff' }};
            color: {{ isset($copia) && $copia == 'true' ? '#666' : '#013334' }};
            margin-bottom: 5px;
        }

        /* ===== SEÇÕES ===== */
        .section {
            margin-bottom: 15px;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            border-bottom: 1px solid #013334;
            padding-bottom: 3px;
            margin-bottom: 8px;
            text-transform: uppercase;
            color: #013334;
        }

        /* ===== INFO GRID ===== */
        .info-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .info-grid td {
            padding: 5px;
            border: 1px solid #ccc;
        }

        .info-label {
            background: #f0f0f0;
            font-weight: bold;
            width: 15%;
        }

        /* ===== TABELAS DE DADOS ===== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .data-table th {
            background: #013334;
            color: white;
            padding: 6px;
            border: 1px solid #000;
            font-weight: bold;
            text-align: left;
        }

        .data-table td {
            border: 1px solid #333;
            padding: 5px;
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
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-pendente {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
        }

        .badge-justificado {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        /* ===== TOTAL BOX ===== */
        .total-box {
            margin-top: 15px;
            border: 2px solid #0aca7d;
            padding: 10px;
            background: #f0fdf4;
        }

        .total-value {
            font-size: 16px;
            font-weight: bold;
            text-align: right;
            color: #0aca7d;
        }

        .valor-extenso {
            font-style: italic;
            font-size: 9px;
            margin-top: 5px;
            text-align: right;
            color: #444;
        }

        /* ===== ASSINATURAS ===== */
        .signatures {
            margin-top: 40px;
            page-break-inside: avoid;
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
            margin-top: 30px;
            font-size: 7px;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            color: #555;
        }

        /* ===== IMPRESSÃO ===== */
        @media print {
            .badge-pendente, .badge-justificado, .data-table th {
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

    <!-- ===== HEADER ===== -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if($logo_empresa)
                    <img src="{{ $logo_empresa }}" class="logo-img" alt="Logo">
                @else
                    <div style="font-size: 14px; font-weight: bold; color: {{ $cor ?? '#0aca7d' }};">
                        {{ $empresa->nome ?? 'EMPRESA' }}
                    </div>
                @endif
            </td>
            <td class="title-cell">
                <div class="doc-title">{{ $titulo }}</div>
                <div class="doc-subtitle">{{ $subtitulo }}</div>
                <div class="doc-number">Nº: {{ $viagem->trip_number }}</div>
            </td>
            <td class="type-cell">
                <div class="doc-type-badge">{{ isset($copia) && $copia == 'true' ? 'CÓPIA' : 'ORIGINAL' }}</div>
                <div><strong>Emissão:</strong> {{ $data_emissao }}</div>
            </td>
        </tr>
    </table>

    <!-- ===== INFORMAÇÕES DA VIAGEM ===== -->
    <div class="section">
        <div class="section-title">Informações da Viagem</div>
        <table class="info-grid">
            <tr>
                <td class="info-label">Motorista:</td>
                <!-- ✅ Corrigido: acessa propriedade direta -->
                <td>{{ $viagem->driver ?? 'N/I' }}</td>
                <td class="info-label">Camião:</td>
                <!-- ✅ Corrigido -->
                <td>{{ $viagem->truck_number ?? 'N/I' }}</td>
            </tr>
            <tr>
                <td class="info-label">Origem:</td>
                <!-- ✅ Corrigido -->
                <td>{{ $viagem->from_station ?? 'N/I' }}</td>
                <td class="info-label">Destino:</td>
                <!-- ✅ Corrigido -->
                <td>{{ $viagem->to_station ?? 'N/I' }}</td>
            </tr>
            <tr>
                <td class="info-label">Data Saída:</td>
                <!-- ✅ Corrigido: schedule_date -->
                <td>{{ $viagem->schedule_date ? date('d/m/Y', strtotime($viagem->schedule_date)) : 'N/I' }}</td>
                <td class="info-label">Cliente:</td>
                <!-- ✅ Corrigido -->
                <td>{{ $viagem->customer_name ?? 'N/I' }}</td>
            </tr>
             @if($viagem->actual_delivery)
            <tr>
                <td class="info-label">Data Entrega:</td>
                <td>{{ date('d/m/Y', strtotime($viagem->actual_delivery)) }}</td>
                <td class="info-label">KM Real:</td>
                <td>{{ $km_real ?? 0 }} km</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- ===== DESPESAS JUSTIFICADAS ===== -->
    <div class="section">
        <div class="section-title">Despesas Justificadas</div>
        @if($despesasJustificadas->count() > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th class="text-right">Valor</th>
                        <th>Moeda</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($despesasJustificadas as $despesa)
                    <tr>
                        <td>{{ $despesa->expenseHead }}</td>
                        <td>{{ $despesa->paymentDescription ?? '-' }}</td>
                        <td class="text-right">{{ number_format($despesa->amount, 2, ',', '.') }}</td>
                        <td>{{ $despesa->currency }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="text-align: center; padding: 15px; background: #f9f9f9;">Nenhuma despesa justificada</p>
        @endif
    </div>

    <!-- ===== DESPESAS PENDENTES ===== -->
    @if($despesasPendentes->count() > 0)
    <div class="section">
        <div class="section-title">Despesas Pendentes</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th class="text-right">Valor</th>
                    <th>Moeda</th>
                </tr>
            </thead>
            <tbody>
                @foreach($despesasPendentes as $despesa)
                <tr>
                    <td>{{ $despesa->expenseHead }}</td>
                    <td>{{ $despesa->paymentDescription ?? '-' }}</td>
                    <td class="text-right">{{ number_format($despesa->amount, 2, ',', '.') }}</td>
                    <td>{{ $despesa->currency }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- ===== TOTAIS ===== -->
    <div class="total-box">
        <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;">RESUMO DE VALORES</div>
        @foreach($totaisFormatados as $moeda => $valor)
            <div class="total-value">{{ $moeda }} {{ $valor }}</div>
        @endforeach
        @if(isset($valorExtenso))
        <div class="valor-extenso">
            <strong>Valor por extenso:</strong> {{ $valorExtenso }}
        </div>
        @endif
    </div>

    <!-- ===== OBSERVAÇÕES ===== -->
    @if($viagem->tracking_comments)
        <div class="section">
            <div class="section-title">Observações</div>
            <div style="border: 1px solid #ccc; padding: 8px; background: #fafafa;">
                {{ $viagem->tracking_comments }}
            </div>
        </div>
    @endif

    <!-- ===== ASSINATURAS ===== -->
    <div class="signatures">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">MOTORISTA</div>
                    <!-- ✅ Corrigido -->
                    <div style="font-size: 9px; color: #555;">{{ $viagem->driver ?? '____________________' }}</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">RESPONSÁVEL</div>
                    <div style="font-size: 9px; color: #555;">{{ $usuario ?? '____________________' }}</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">CAIXA</div>
                    <div style="font-size: 9px; color: #555;">____________________</div>
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
            Documento processado por computador | Emitido por: {{ $usuario }} | {{ $current_date }}
        </div>
    </div>

</div>
</body>
</html>