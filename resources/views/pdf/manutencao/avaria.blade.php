<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Avaria - {{ $avaria->codigo }}</title>

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

        /* ===== BADGES DE STATUS ===== */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-aberta {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .badge-diagnostico {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
        }

        .badge-reparacao {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .badge-resolvida {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .badge-baixa {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #9ca3af;
        }

        .badge-media {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .badge-alta {
            background: #fed7aa;
            color: #9a3412;
            border: 1px solid #f97316;
        }

        .badge-urgente {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
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
            .badge-aberta, .badge-diagnostico, .badge-reparacao, 
            .badge-resolvida, .badge-baixa, .badge-media, .badge-alta,
            .badge-urgente, .data-table th {
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
                <div class="doc-title">RELATÓRIO DE AVARIA</div>
                <div class="doc-subtitle">Documento de Ocorrência Técnica</div>
                <div class="doc-number">Nº: {{ $avaria->codigo }}</div>
            </td>
            <td class="type-cell">
                <div class="doc-type-badge">{{ isset($copia) && $copia == 'true' ? 'CÓPIA' : 'ORIGINAL' }}</div>
                <div><strong>Data:</strong> {{ $data_emissao }}</div>
            </td>
        </tr>
    </table>

    <!-- ===== INFORMAÇÕES DA AVARIA ===== -->
    <div class="section">
        <div class="section-title">Informações da Avaria</div>
        <table class="data-table">
            <tr>
                <td class="label-cell">Veículo:</td>
                <td>{{ $avaria->veiculo }}</td>
                <td class="label-cell">Matrícula:</td>
                <td>{{ $avaria->matricula }}</td>
            </tr>
            <tr>
                <td class="label-cell">Data do Reporte:</td>
                <td>{{ $data_reporte }}</td>
                <td class="label-cell">Horas Imobilizado:</td>
                <td>{{ $avaria->horas_imobilizado }}h</td>
            </tr>
            <tr>
                <td class="label-cell">Reportado por:</td>
                <td>{{ $avaria->reportado_por }}</td>
                <td class="label-cell">Técnico:</td>
                <td>{{ $avaria->tecnico }}</td>
            </tr>
            <tr>
                <td class="label-cell">Prioridade:</td>
                <td>
                    <span class="badge badge-{{ $avaria->prioridade }}">
                        {{ $prioridade_label }}
                    </span>
                </td>
                <td class="label-cell">Status:</td>
                <td>
                    <span class="badge badge-{{ $avaria->status }}">
                        {{ $status_label }}
                    </span>
                </td>
            </tr>
            @if($avaria->local_avaria)
            <tr>
                <td class="label-cell">Local:</td>
                <td colspan="3">{{ $avaria->local_avaria }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- ===== DESCRIÇÃO DA AVARIA ===== -->
    <div class="section">
        <div class="section-title">Descrição da Avaria</div>
        <table class="data-table">
            <tr>
                <td style="background: #fafafa;">{{ $avaria->descricao }}</td>
            </tr>
        </table>
    </div>

    <!-- ===== CAUSA RAIZ ===== -->
    @if($avaria->causa_raiz)
    <div class="section">
        <div class="section-title">Causa Raiz</div>
        <table class="data-table">
            <tr>
                <td style="background: #fafafa;">{{ $avaria->causa_raiz }}</td>
            </tr>
        </table>
    </div>
    @endif

    <!-- ===== OBSERVAÇÕES ===== -->
    @if($avaria->observacoes)
    <div class="section">
        <div class="section-title">Observações</div>
        <div class="obs-box">
            {{ $avaria->observacoes }}
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
                    <div style="font-weight: bold;">TÉCNICO RESPONSÁVEL</div>
                    <div style="font-size: 9px; color: #555;">{{ $avaria->tecnico }}</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">RESPONSÁVEL</div>
                    <div style="font-size: 9px; color: #555;">{{ $usuario }}</div>
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