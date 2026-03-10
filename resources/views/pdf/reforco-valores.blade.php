<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reforço de Valores - {{ strtoupper($tipo) }}</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
            background: #fff;
        }
        
        .container {
            width: 100%;
            padding: 10mm;
            position: relative;
        }
        
        @if($tipo == 'duplicate')
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60pt;
            color: rgba(200, 0, 0, 0.08);
            z-index: 0;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 10px;
            pointer-events: none;
        }
        @endif
        
        .header {
            display: table;
            width: 100%;
            border-bottom: 2pt solid #000;
            padding-bottom: 8px;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .header-cell {
            display: table-cell;
            vertical-align: middle;
            padding: 0 10px;
        }
        
        .logo-area {
            width: 35%;
        }
        
        .logo-img {
            max-height: 20mm;
            max-width: 45mm;
            display: block;
        }
        
        .title-area {
            width: 40%;
            text-align: center;
        }
        
        .doc-title {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        
        .doc-subtitle {
            font-size: 8pt;
            color: #555;
        }
        
        .doc-type-area {
            width: 25%;
            text-align: right;
        }
        
        .doc-type-badge {
            display: inline-block;
            border: 1.5pt solid {{ $tipo == 'original' ? '#000' : '#666' }};
            padding: 4px 12px;
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            background: {{ $tipo == 'original' ? '#fff' : '#f5f5f5' }};
            color: {{ $tipo == 'original' ? '#000' : '#666' }};
        }
        
        .section {
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }
        
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1pt solid #333;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-cell {
            display: table-cell;
            padding: 4px 0;
            width: 50%;
        }
        
        .label {
            font-weight: bold;
            color: #333;
        }
        
        .expenses-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 9pt;
            position: relative;
            z-index: 1;
        }
        
        .expenses-table th {
            background: #2d3748;
            color: white;
            padding: 6px;
            text-align: left;
            font-weight: bold;
            border: 0.5pt solid #000;
        }
        
        .expenses-table td {
            border: 0.5pt solid #333;
            padding: 6px;
            vertical-align: middle;
        }
        
        .currency-cell {
            font-weight: bold;
            text-align: center;
            background: #f0f0f0;
        }
        
        .amount-cell {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        
        .extenso-cell {
            font-style: italic;
            color: #444;
            font-size: 8.5pt;
        }
        
        .totals-box {
            margin-top: 15px;
            border: 1pt solid #333;
            padding: 10px;
            background: #f8f9fa;
            position: relative;
            z-index: 1;
        }
        
        .signatures {
            margin-top: 30px;
            page-break-inside: avoid;
            position: relative;
            z-index: 1;
        }
        
        .signatures-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            font-size: 9pt;
            text-align: center;
        }
        
        .signatures-grid {
            display: table;
            width: 100%;
        }
        
        .signature-box {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 0 20px;
        }
        
        .signature-line {
            border-bottom: 1pt solid #000;
            height: 30px;
            margin-bottom: 5px;
        }
        
        .footer {
            margin-top: 25px;
            padding-top: 8px;
            border-top: 0.5pt solid #ccc;
            font-size: 7.5pt;
            color: #555;
            position: relative;
            z-index: 1;
        }

        .page-break {
            page-break-after: always;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .container {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        @if($tipo == 'duplicate')
        <div class="watermark">DUPLICADO</div>
        @endif
        
        <!-- ============================================================================ -->
        <!-- CABEÇALHO -->
        <!-- ============================================================================ -->
        <div class="header">
            <div class="header-cell logo-area">
                @if($logo_empresa)
                    <img src="{{ $logo_empresa }}" alt="Logo" class="logo-img">
                @else
                    <div style="font-size: 16pt; font-weight: bold; color: #0aca7d;">
                        {{ $empresa->nome ?? 'EMPRESA' }}
                    </div>
                @endif
            </div>
            
            <div class="header-cell title-area">
                <div class="doc-title">Reforço de Valores</div>
                <div class="doc-subtitle">Documento de Autorização de Despesas</div>
            </div>
            
            <div class="header-cell doc-type-area">
                <div class="doc-type-badge">{{ strtoupper($tipo) }}</div>
                <div style="margin-top: 5px; font-size: 9pt;">
                    <span class="label">Data:</span> {{ date('d/m/Y') }}
                </div>
            </div>
        </div>
        
        <!-- ============================================================================ -->
        <!-- INFORMAÇÕES DA VIAGEM -->
        <!-- ============================================================================ -->
        <div class="section">
            <div class="section-title">Informações da Viagem</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-cell">
                        <span class="label">Viagem:</span>
                        <span class="value" style="text-transform: uppercase; font-weight: bold;">
                            {{ $viagem->trip_number ?? $viagem->tripNumber ?? 'N/A' }}
                        </span>
                    </div>
                    <div class="info-cell">
                        <span class="label">Motorista:</span>
                        <span class="value">{{ $viagem->driver ?? 'N/A' }}</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-cell">
                        <span class="label">Origem:</span>
                        <span class="value">{{ $viagem->from_station ?? $viagem->fromStation ?? 'N/A' }}</span>
                    </div>
                    <div class="info-cell">
                        <span class="label">Destino:</span>
                        <span class="value">{{ $viagem->to_station ?? $viagem->toStation ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================================ -->
        <!-- TABELA DE DESPESAS -->
        <!-- ============================================================================ -->
        <div class="section">
            <div class="section-title">Detalhamento de Despesas por Moeda</div>
            
            @if(count($despesas_agrupadas) > 0)
                <table class="expenses-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Moeda</th>
                            <th style="width: 43%;">Descrição</th>
                            <th style="width: 15%; text-align: right;">Valor Total</th>
                            <th style="width: 30%;">Valor por Extenso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($despesas_agrupadas as $moeda => $dados)
                        <tr>
                            <td class="currency-cell">{{ $moeda }}</td>
                            <td>
                                @if(count($dados['descricoes']) > 0)
                                    {{ implode(' / ', array_slice($dados['descricoes'], 0, 3)) }}
                                    @if(count($dados['descricoes']) > 3)
                                        <span style="color: #666; font-size: 8pt;">
                                            (+{{ count($dados['descricoes']) - 3 }} itens)
                                        </span>
                                    @endif
                                @else
                                    <span style="color: #666;">Despesas diversas</span>
                                @endif
                            </td>
                            <td class="amount-cell">{{ number_format($dados['total'], 2, ',', '.') }}</td>
                            <td class="extenso-cell">
                                {{ number_format($dados['total'], 2, ',', '.') }} {{ $moeda }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <table class="expenses-table">
                    <thead>
                        <tr>
                            <th>Moeda</th>
                            <th>Descrição</th>
                            <th>Valor Total</th>
                            <th>Valor por Extenso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999; padding: 20px;">
                                Nenhuma despesa registrada
                            </td>
                        </tr>
                    </tbody>
                </table>
            @endif
            
            @if(count($despesas_agrupadas) > 0)
            <div class="totals-box">
                <div style="font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">Resumo Geral</div>
                @foreach($despesas_agrupadas as $moeda => $dados)
                <div style="display: table; width: 100%; margin: 3px 0;">
                    <span style="display: table-cell;">Total em {{ $moeda }} ({{ $dados['quantidade'] }} despesa{{ $dados['quantidade'] != 1 ? 's' : '' }}):</span>
                    <span style="display: table-cell; text-align: right; font-family: 'Courier New', monospace; font-weight: bold;">
                        {{ number_format($dados['total'], 2, ',', '.') }} {{ $moeda }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        
        <!-- ============================================================================ -->
        <!-- ASSINATURAS -->
        <!-- ============================================================================ -->
        <div class="signatures">
            <div class="signatures-title">Autorização e Conferência</div>
            <div class="signatures-grid">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase;">Entregue por</div>
                    <div style="font-size: 8pt; color: #555; margin-top: 3px;">
                        {{ $usuario ?? 'Sistema' }}
                    </div>
                    <div style="font-size: 7pt; color: #999; margin-top: 2px;">
                        {{ date('d/m/Y H:i:s') }}
                    </div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase;">Motorista</div>
                    <div style="font-size: 8pt; color: #555; margin-top: 3px;">
                        {{ $viagem->driver ?? 'N/A' }}
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================================ -->
        <!-- RODAPÉ -->
        <!-- ============================================================================ -->
        <div class="footer">
            @if($empresa)
            <div style="font-weight: bold; margin-bottom: 3px;">{{ $empresa->nome }}</div>
            <div style="font-size: 8pt;">
                @if($empresa->endereco){{ $empresa->endereco }} | @endif
                @if($empresa->telefone)Tel: {{ $empresa->telefone }} | @endif
                @if($empresa->email){{ $empresa->email }}@endif
            </div>
            @endif
            
            <div style="margin-top: 5px; font-size: 7pt; color: #777;">
                <strong>Documento processado por computador</strong> | 
                Emitido por: {{ $usuario ?? 'Sistema' }} | 
                Data/Hora: {{ $data_emissao ?? date('d/m/Y H:i:s') }} | 
                Referência: {{ $viagem->trip_number ?? $viagem->tripNumber ?? 'N/A' }}
            </div>
        </div>
        
    </div>
</body>
</html>