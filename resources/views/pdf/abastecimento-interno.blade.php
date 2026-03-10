<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Abastecimento Interno - {{ $abastecimento->numero }}</title>

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

        .badge-pago, .badge-realizado {
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
            .badge-pendente, .badge-aprovado, .badge-pago, .badge-realizado, 
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
                @if(isset($empresa->logo_url) && $empresa->logo_url)
                    <img src="{{ $empresa->logo_url }}" class="logo-img">
                @else
                    <div style="font-size: 14px; font-weight: bold; color: {{ $cor ?? '#0aca7d' }};">
                        {{ $empresa->nome ?? 'EMPRESA' }}
                    </div>
                @endif
            </td>
            <td class="title-cell">
                <div class="doc-title">ABASTECIMENTO INTERNO</div>
                <div class="doc-subtitle">Comprovante de Abastecimento</div>
                <div class="doc-number">Nº: {{ $abastecimento->numero }}</div>
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
                <td class="label-cell">Matrícula:</td>
                <td>{{ $abastecimento->veiculo->matricula ?? $abastecimento->veiculo_matricula ?? 'N/A' }}</td>
                <td class="label-cell">Motorista:</td>
                <td>{{ $abastecimento->motorista->nome ?? $abastecimento->motorista_nome ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Odômetro:</td>
                <td>{{ number_format($abastecimento->odometro ?? 0, 0, ',', '.') }} km</td>
                <td class="label-cell">Responsável:</td>
                <td>{{ $abastecimento->responsavel_registro ?? 'Sistema' }}</td>
            </tr>
        </table>
    </div>

    <!-- ===== DETALHES DO ABASTECIMENTO ===== -->
    <div class="section">
        <div class="section-title">Detalhes do Abastecimento</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Combustível</th>
                    <th class="text-right">Quantidade (L)</th>
                    <th>Data/Hora</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ str_replace('_', ' ', $abastecimento->tipo_combustivel) }}</td>
                    <td class="text-right">{{ number_format($abastecimento->quantidade, 2, ',', '.') }} L</td>
                    <td>{{ $abastecimento->data ?? date('d/m/Y') }} {{ $abastecimento->hora ?? '' }}</td>
                    <td class="text-center">
                        @if($abastecimento->status == 'pendente')
                            <span class="badge badge-pendente">PENDENTE</span>
                        @elseif($abastecimento->status == 'aprovado')
                            <span class="badge badge-aprovado">APROVADO</span>
                        @elseif(in_array($abastecimento->status, ['realizado', 'concluido']))
                            <span class="badge badge-realizado">REALIZADO</span>
                        @elseif(in_array($abastecimento->status, ['cancelado', 'rejeitado']))
                            <span class="badge badge-cancelado">CANCELADO</span>
                        @else
                            {{ strtoupper($abastecimento->status) }}
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ===== TOTAL ===== -->
    <div class="total-box">
        <div class="total-value">
            {{ number_format($abastecimento->quantidade, 2, ',', '.') }} Litros
        </div>
        @if(isset($abastecimento->observacoes_quantidade))
        <div class="valor-extenso">
            <strong>Observação:</strong> {{ $abastecimento->observacoes_quantidade }}
        </div>
        @endif
    </div>

    <!-- ===== HISTÓRICO DE STATUS ===== -->
    <div class="section">
        <div class="section-title">Histórico de Status</div>
        <table class="data-table">
            <tr>
                <td class="label-cell">Registrado por:</td>
                <td>{{ $abastecimento->responsavel_registro ?? 'Sistema' }}</td>
                <td class="label-cell">Data/Hora:</td>
                <td>{{ isset($abastecimento->data_registro) ? date('d/m/Y H:i', strtotime($abastecimento->data_registro)) : $current_date }}</td>
            </tr>
            @if($abastecimento->aprovado_por)
            <tr>
                <td class="label-cell">Aprovado por:</td>
                <td>{{ $abastecimento->aprovado_por }}</td>
                <td class="label-cell">Data:</td>
                <td>{{ $abastecimento->data_aprovacao ? date('d/m/Y', strtotime($abastecimento->data_aprovacao)) : '' }}</td>
            </tr>
            @endif
            @if($abastecimento->conferido_por)
            <tr>
                <td class="label-cell">Conferido por:</td>
                <td>{{ $abastecimento->conferido_por }}</td>
                <td class="label-cell">Data:</td>
                <td>{{ $abastecimento->data_conferencia ? date('d/m/Y', strtotime($abastecimento->data_conferencia)) : '' }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- ===== OBSERVAÇÕES ===== -->
    @if($abastecimento->observacoes)
        <div class="section">
            <div class="section-title">Observações</div>
            <div class="obs-box">
                {{ $abastecimento->observacoes }}
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
                    <div style="font-weight: bold;">MOTORISTA</div>
                    <div style="font-size: 9px; color: #555;">{{ $abastecimento->motorista->nome ?? $abastecimento->motorista_nome ?? '____________________' }}</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">RESPONSÁVEL</div>
                    <div style="font-size: 9px; color: #555;">{{ $abastecimento->responsavel_registro ?? '____________________' }}</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold;">CONFERENTE</div>
                    <div style="font-size: 9px; color: #555;">{{ $abastecimento->conferido_por ?? $usuario ?? '____________________' }}</div>
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

    <!-- ===== QR CODE ===== -->
    @if(isset($qr_code))
    <div class="qr-code">
        <img src="{{ $qr_code }}" style="width: 100%; height: 100%;">
    </div>
    @endif

</div>
</body>
</html>