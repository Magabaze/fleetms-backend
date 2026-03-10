<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Plano de Manutenção - {{ $plano->veiculo }}</title>

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
            width: 25%;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* ===== BADGES DE STATUS ===== */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-ok {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .badge-alerta {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
        }

        .badge-vencido {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* ===== BARRA DE PROGRESSO ===== */
        .progress-bar-container {
            width: 100%;
            background-color: #f0f0f0;
            border-radius: 10px;
            height: 20px;
            margin: 10px 0;
            border: 1px solid #ccc;
        }

        .progress-bar {
            height: 20px;
            border-radius: 10px;
            background-color: {{ $cor }};
            width: {{ $progresso }}%;
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
            width: 50%;
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

        /* ===== QR CODE ===== */
        .qr-code {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            z-index: 2;
        }

        /* ===== IMPRESSÃO ===== */
        @media print {
            .badge-ok, .badge-alerta, .badge-vencido, .data-table th {
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
                @if(isset($logo_empresa))
                    <img src="{{ $logo_empresa }}" class="logo-img">
                @else
                    <div style="font-size: 14px; font-weight: bold; color: {{ $cor ?? '#0aca7d' }};">
                        {{ $empresa->nome ?? 'EMPRESA' }}
                    </div>
                @endif
            </td>
            <td class="title-cell">
                <div class="doc-title">PLANO DE MANUTENÇÃO</div>
                <div class="doc-subtitle">Cronograma Preventivo</div>
                <div class="doc-number">Veículo: {{ $plano->matricula }}</div>
            </td>
            <td class="type-cell">
                <div class="doc-type-badge">{{ isset($copia) && $copia == 'true' ? 'CÓPIA' : 'ORIGINAL' }}</div>
                <div><strong>Data:</strong> {{ $data_emissao }}</div>
            </td>
        </tr>
    </table>

    <!-- ===== INFORMAÇÕES DO VEÍCULO ===== -->
    <div class="section">
        <div class="section-title">Informações do Veículo</div>
        <table class="data-table">
            <tr>
                <td class="label-cell">Veículo:</td>
                <td>{{ $plano->veiculo }}</td>
                <td class="label-cell">Matrícula:</td>
                <td>{{ $plano->matricula }}</td>
            </tr>
            <tr>
                <td class="label-cell">Tipo de Manutenção:</td>
                <td>{{ $plano->tipo }}</td>
                <td class="label-cell">Status:</td>
                <td>
                    <span class="badge badge-{{ $plano->status }}">
                        {{ $status_label }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ===== INTERVALOS DE MANUTENÇÃO ===== -->
    <div class="section">
        <div class="section-title">Intervalos de Manutenção</div>
        <table class="data-table">
            <tr>
                <td class="label-cell">Intervalo por KM:</td>
                <td>{{ number_format($plano->intervalo_km, 0, ',', '.') }} km</td>
                <td class="label-cell">Intervalo por Dias:</td>
                <td>{{ $plano->intervalo_dias }} dias</td>
            </tr>
        </table>
    </div>

    <!-- ===== ÚLTIMA MANUTENÇÃO ===== -->
    <div class="section">
        <div class="section-title">Última Manutenção</div>
        <table class="data-table">
            <tr>
                <td class="label-cell">KM da Última:</td>
                <td>{{ number_format($plano->ultimo_km, 0, ',', '.') }} km</td>
                <td class="label-cell">Data da Última:</td>
                <td>{{ $ultima_data }}</td>
            </tr>
        </table>
    </div>

    <!-- ===== PROGRESSO ===== -->
    <div class="section">
        <div class="section-title">Progresso para Próxima Manutenção</div>
        <table class="data-table">
            <tr>
                <td class="label-cell">KM Atual:</td>
                <td>{{ number_format($plano->km_atual, 0, ',', '.') }} km</td>
                <td class="label-cell">Progresso:</td>
                <td>{{ $progresso }}%</td>
            </tr>
        </table>
        
        <div class="progress-bar-container">
            <div class="progress-bar"></div>
        </div>
        
        <table class="data-table">
            <tr>
                <td class="label-cell">KM Restantes:</td>
                <td>
                    @if($km_restantes > 0)
                        {{ number_format($km_restantes, 0, ',', '.') }} km
                    @else
                        <span style="color: #991b1b;">{{ number_format(abs($km_restantes), 0, ',', '.') }} km de atraso</span>
                    @endif
                </td>
                <td class="label-cell">Próxima Data:</td>
                <td>{{ $proxima_data }}</td>
            </tr>
        </table>
    </div>

    <!-- ===== OBSERVAÇÕES ===== -->
    @if($plano->observacoes)
    <div class="section">
        <div class="section-title">Observações</div>
        <div class="obs-box">
            {{ $plano->observacoes }}
        </div>
    </div>
    @endif

    <!-- ===== ASSINATURAS ===== -->
    <div class="signatures">
        <div class="signatures-title">Responsáveis</div>
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">RESPONSÁVEL PELA MANUTENÇÃO</div>
                    <div style="font-size: 9px; color: #555;">____________________</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">RESPONSÁVEL PELO VEÍCULO</div>
                    <div style="font-size: 9px; color: #555;">____________________</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ===== RODAPÉ ===== -->
    <div class="footer">
        <div><strong>{{ $empresa->nome ?? '' }}</strong></div>
        <div>
            @if($empresa->nif ?? false)NIF: {{ $empresa->nif }} | @endif
            @if($empresa->endereco ?? false){{ $empresa->endereco }} | @endif
            @if($empresa->telefone ?? false)Tel: {{ $empresa->telefone }} | @endif
            @if($empresa->email ?? false){{ $empresa->email }}@endif
        </div>
        <div style="margin-top: 3px;">
            Documento processado por computador | 
            Emitido por: {{ $usuario }} | 
            Data/Hora: {{ $current_date }}
        </div>
    </div>

    <!-- ===== QR CODE ===== -->
    @if(isset($qr_code))
    <div class="qr-code">
        <img src="{{ $qr_code }}" style="width: 100%; height: 100%;">
    </div>
    @endif

</div>
</body>
</html>