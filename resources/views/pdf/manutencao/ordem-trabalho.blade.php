{{-- resources/views/pdf/ordem-servico.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Ordem de Serviço - {{ $ordem->codigo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
        
        .header {
            display: table;
            width: 100%;
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 1.2pt solid #013334;
        }
        
        .header-cell { display: table-cell; vertical-align: middle; }
        .logo { width: 45mm; }
        .logo img { max-width: 40mm; max-height: 18mm; display: block; }
        
        .company-info { font-size: 7pt; color: #5a5f6e; line-height: 1.6; }
        .company-name { font-size: 11pt; font-weight: bold; color: #013334; margin-bottom: 1mm; }
        
        .doc-meta { text-align: right; width: 55mm; }
        .doc-title { font-size: 12pt; font-weight: bold; color: #013334; margin-bottom: 2mm; text-transform: uppercase; letter-spacing: 0.3pt; }
        .doc-number { font-size: 9pt; font-weight: bold; color: #0f1116; }
        
        .status-badge {
            display: inline-block;
            padding: 1mm 3mm;
            border-radius: 1mm;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 1mm 0;
        }
        .status-pendente { background-color: #f59e0b; color: #000; }
        .status-progresso { background-color: #3b82f6; color: #fff; }
        .status-concluida { background-color: #10b981; color: #fff; }
        .status-cancelada { background-color: #ef4444; color: #fff; }
        
        .prioridade-baixa { background-color: #9ca3af; color: #fff; }
        .prioridade-media { background-color: #3b82f6; color: #fff; }
        .prioridade-alta { background-color: #f97316; color: #fff; }
        .prioridade-urgente { background-color: #ef4444; color: #fff; }
        
        .content { display: table; width: 100%; margin-bottom: 3mm; }
        .column { display: table-cell; vertical-align: top; padding: 0 2mm; }
        .left-column { width: 50%; padding-left: 0; }
        .right-column { width: 50%; padding-right: 0; }
        
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
        
        .section-content { padding: 2mm 3mm; }
        
        .table-info { width: 100%; border-collapse: collapse; font-size: 7pt; }
        .table-info td { padding: 1mm 0; vertical-align: top; border: none; }
        .label { font-weight: bold; width: 35%; color: #5a5f6e; text-transform: uppercase; font-size: 6.5pt; }
        
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
        
        .signatures {
            display: table;
            width: 100%;
            margin-top: 4mm;
            padding-top: 3mm;
            border-top: 0.5pt solid #e4e6ec;
        }
        
        .signature-box { display: table-cell; text-align: center; width: 50%; }
        .signature-line { height: 12mm; border-bottom: 0.5pt solid #9fa4b0; margin: 1mm 5mm; }
        .signature-label { font-size: 6.5pt; font-weight: bold; margin-bottom: 0.5mm; color: #5a5f6e; }
        
        .footer {
            width: 100%;
            text-align: center;
            font-size: 6pt;
            color: #9fa4b0;
            padding-top: 2mm;
            border-top: 0.5pt solid #e4e6ec;
            margin-top: 3mm;
        }
        
        .footer-brand { font-weight: bold; color: #013334; }
        .bold { font-weight: bold; }
        .text-muted { color: #9fa4b0; }
        
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
        
        .section, .signatures, .obs-box { break-inside: avoid; page-break-inside: avoid; }
        
        @media print {
            body { margin: 0; padding: 0; }
            .container { padding: 2mm; }
            .status-pendente, .status-progresso, .status-concluida, .status-cancelada,
            .prioridade-baixa, .prioridade-media, .prioridade-alta, .prioridade-urgente {
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
        
        <div class="header">
            <div class="header-cell logo">
                @if(isset($logo_empresa))
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
                <div class="doc-title">ORDEM DE SERVIÇO</div>
                <div class="doc-number">Nº: {{ $ordem->codigo }}</div>
                <div>
                    <span class="status-badge status-{{ $ordem->status }}">{{ $status_label }}</span>
                </div>
                <div style="margin-top: 1mm;">
                    <strong>Data:</strong> {{ $data_criacao }}
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="column left-column">
                <div class="section">
                    <div class="section-title">Veículo</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr><td class="label">Veículo</td><td class="bold">{{ $ordem->veiculo }}</td></tr>
                            <tr><td class="label">Matrícula</td><td>{{ $ordem->matricula }}</td></tr>
                            <tr><td class="label">Tipo</td><td>{{ $tipo_label }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="column right-column">
                <div class="section">
                    <div class="section-title">Detalhes</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr><td class="label">Técnico</td><td>{{ $ordem->tecnico }}</td></tr>
                            <tr>
                                <td class="label">Prioridade</td>
                                <td><span class="status-badge prioridade-{{ $ordem->prioridade }}">{{ $prioridade_label }}</span></td>
                            </tr>
                            <tr><td class="label">Data Prevista</td><td>{{ $data_prevista }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Descrição do Trabalho</div>
            <div class="section-content">
                <div style="font-size: 7.5pt; line-height: 1.5;">{{ $ordem->descricao }}</div>
            </div>
        </div>
        
        @if($ordem->observacoes)
        <div class="obs-box">
            <div class="obs-label">Observações</div>
            <div>{{ $ordem->observacoes }}</div>
        </div>
        @endif
        
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-label">Técnico Responsável</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $ordem->tecnico }}</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Responsável</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $usuario }}</div>
            </div>
        </div>
        
        <div class="footer">
            <div>
                Documento processado por computador · Válido sem assinatura<br>
                Emissão: {{ $current_date }} · Operador: {{ $usuario }}
            </div>
            <div style="margin-top: 1mm;">
                <span class="footer-brand">abdago Fleet</span><br>
                Sistema de Gestão de Transportes
            </div>
        </div>
        
    </div>
</body>
</html>