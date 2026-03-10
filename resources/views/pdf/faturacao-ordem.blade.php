{{-- resources/views/pdf/faturacao-ordem.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Ordem de Faturação - {{ $ordem->codigo }}</title>
    <style>
        /* RESET E CONFIGURAÇÕES BASE */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #2c3e50;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm 20mm;
            background: white;
        }

        /* CABEÇALHO */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .empresa-info {
            flex: 1;
        }

        .empresa-nome {
            font-size: 18pt;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }

        .empresa-detalhes {
            font-size: 8pt;
            color: #5d6d7e;
            line-height: 1.5;
        }

        .empresa-detalhes p {
            margin: 2px 0;
        }

        .documento-tipo {
            text-align: right;
            min-width: 200px;
        }

        .documento-titulo {
            font-size: 16pt;
            font-weight: 700;
            color: #0aca7d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .documento-numero {
            font-size: 12pt;
            font-weight: 600;
            color: #34495e;
            background: #f8f9fa;
            padding: 5px 15px;
            border-radius: 4px;
            display: inline-block;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 8pt;
            font-weight: 600;
            margin-top: 8px;
        }

        .status-pendente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .status-processado {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-cancelado {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* SEÇÕES */
        .section {
            margin-bottom: 20px;
        }

        .section-titulo {
            font-size: 11pt;
            font-weight: 600;
            color: #1e3c72;
            text-transform: uppercase;
            border-bottom: 1px solid #d0d7de;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }

        /* GRADE DE INFORMAÇÕES */
        .info-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-card {
            flex: 1 1 calc(33.333% - 15px);
            background: #f8fafc;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px 15px;
        }

        .info-row {
            display: flex;
            margin-bottom: 6px;
            font-size: 9pt;
        }

        .info-label {
            width: 80px;
            font-weight: 600;
            color: #5d6d7e;
        }

        .info-value {
            flex: 1;
            color: #1e3c72;
            font-weight: 500;
        }

        /* TABELAS */
        .tabela {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }

        .tabela th {
            background: #1e3c72;
            color: white;
            font-weight: 600;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #2c3e50;
        }

        .tabela td {
            padding: 8px 10px;
            border: 1px solid #d0d7de;
        }

        .tabela tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* RESUMO FINANCEIRO */
        .resumo-box {
            background: #f8fafc;
            border: 2px solid #1e3c72;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .resumo-linha {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #d0d7de;
        }

        .resumo-linha:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 12pt;
            color: #0aca7d;
            padding-top: 12px;
            margin-top: 8px;
            border-top: 2px solid #1e3c72;
        }

        .resumo-label {
            color: #5d6d7e;
        }

        .resumo-valor {
            font-weight: 600;
        }

        .text-credito {
            color: #dc2626;
        }

        .text-debito {
            color: #16a34a;
        }

        /* VALOR EXTENSO */
        .valor-extenso {
            background: #f8fafc;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px 15px;
            margin: 15px 0;
            font-style: italic;
            color: #5d6d7e;
            font-size: 9pt;
        }

        /* OBSERVAÇÕES */
        .observacoes-box {
            background: #f8fafc;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }

        .observacoes-titulo {
            font-size: 9pt;
            font-weight: 600;
            color: #5d6d7e;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .observacoes-conteudo {
            font-size: 9pt;
            color: #2c3e50;
            line-height: 1.5;
        }

        /* ALERTAS */
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin: 15px 0;
            font-size: 9pt;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }

        /* ASSINATURAS */
        .assinaturas {
            display: flex;
            justify-content: space-between;
            margin: 30px 0 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .assinatura-box {
            flex: 1;
            text-align: center;
            margin: 0 10px;
        }

        .assinatura-linha {
            border-bottom: 1px solid #2c3e50;
            height: 40px;
            margin-bottom: 8px;
        }

        .assinatura-label {
            font-size: 8pt;
            font-weight: 600;
            color: #5d6d7e;
            text-transform: uppercase;
        }

        .assinatura-nome {
            font-size: 8pt;
            color: #1e3c72;
            margin-top: 3px;
        }

        /* RODAPÉ */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            font-size: 7pt;
            color: #95a5a6;
            text-align: center;
        }

        .footer-info {
            margin-bottom: 3px;
        }

        /* UTILITÁRIOS */
        .bold { font-weight: 600; }
        
        /* RESPONSIVIDADE */
        @media print {
            body { background: white; }
            .container { padding: 0; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- CABEÇALHO -->
        <div class="header">
            <div class="empresa-info">
                @if($empresa && $empresa->logo)
                    <img src="{{ $empresa->logo }}" alt="Logo" style="max-height: 60px; max-width: 200px; margin-bottom: 10px;">
                @else
                    <div class="empresa-nome">{{ $empresa->nome ?? 'EMPRESA' }}</div>
                @endif
                <div class="empresa-detalhes">
                    <p>{{ $empresa->endereco ?? 'Endereço da Empresa' }}</p>
                    <p>NIF: {{ $empresa->nif ?? '123456789' }} | Tel: {{ $empresa->telefone ?? '+258 84 000 0000' }}</p>
                    <p>{{ $empresa->email ?? 'geral@empresa.co.mz' }}</p>
                </div>
            </div>
            
            <div class="documento-tipo">
                <div class="documento-titulo">ORDEM DE FATURAÇÃO</div>
                <div class="documento-numero">{{ $ordem->codigo }}</div>
                <div class="documento-data">Data: {{ $data_emissao }}</div>
                <div class="status-badge status-{{ $ordem->status }}">
                    {{ $status }}
                </div>
            </div>
        </div>

        <!-- INFORMAÇÕES PRINCIPAIS -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Cliente:</span>
                    <span class="info-value">{{ $ordem->cliente }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Motorista:</span>
                    <span class="info-value">{{ $ordem->motorista }}</span>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Origem:</span>
                    <span class="info-value">{{ $ordem->origem }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Destino:</span>
                    <span class="info-value">{{ $ordem->destino }}</span>
                </div>
            </div>
            
            @if($viagem)
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Viagem:</span>
                    <span class="info-value">{{ $viagem->trip_number }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Data Viagem:</span>
                    <span class="info-value">{{ date('d/m/Y', strtotime($ordem->dataViagem)) }}</span>
                </div>
            </div>
            @endif
        </div>

        <!-- SERVIÇOS -->
        <div class="section">
            <div class="section-titulo">Serviços Prestados</div>
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th class="text-right">Valor (MZN)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Serviços de Transporte - {{ $ordem->origem }} → {{ $ordem->destino }}</td>
                        <td class="text-right">{{ $servicos_formatado }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- NOTAS FISCAIS (se houver) -->
        @if(isset($notas) && count($notas) > 0)
        <div class="section">
            <div class="section-titulo">Notas Fiscais Relacionadas</div>
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nº Nota</th>
                        <th>Tipo</th>
                        <th>Motivo</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($notas as $notaItem)
                    <tr>
                        <td>{{ $notaItem->numero }}</td>
                        <td>{{ ucfirst($notaItem->tipo) }}</td>
                        <td>{{ $notaItem->motivo }}</td>
                        <td class="text-right {{ $notaItem->tipo == 'credito' ? 'text-credito' : 'text-debito' }}">
                            {{ $notaItem->tipo == 'credito' ? '- ' : '+ ' }}
                            {{ number_format($notaItem->valor, 2, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- RESUMO FINANCEIRO -->
        <div class="resumo-box">
            <div class="resumo-linha">
                <span class="resumo-label">Valor dos Serviços:</span>
                <span class="resumo-valor">{{ $servicos_formatado }} MZN</span>
            </div>
            
            @if($totais['debitos'] > 0)
            <div class="resumo-linha">
                <span class="resumo-label text-debito">Notas de Débito (+):</span>
                <span class="resumo-valor text-debito">+ {{ $debitos_formatado }} MZN</span>
            </div>
            @endif
            
            @if($totais['creditos'] > 0)
            <div class="resumo-linha">
                <span class="resumo-label text-credito">Notas de Crédito (-):</span>
                <span class="resumo-valor text-credito">- {{ $creditos_formatado }} MZN</span>
            </div>
            @endif
            
            <div class="resumo-linha">
                <span class="resumo-label">TOTAL A FATURAR:</span>
                <span class="resumo-valor">{{ $saldo_formatado }} MZN</span>
            </div>
        </div>

        <!-- VALOR POR EXTENSO -->
        <div class="valor-extenso">
            <strong>Valor por extenso:</strong> {{ $valor_extenso }}
        </div>

        <!-- OBSERVAÇÕES -->
        @if($ordem->observacoes)
        <div class="observacoes-box">
            <div class="observacoes-titulo">Observações</div>
            <div class="observacoes-conteudo">{{ $ordem->observacoes }}</div>
        </div>
        @endif

        <!-- ALERTA PARA ORDENS PENDENTES -->
        @if($ordem->status == 'pendente')
        <div class="alert alert-warning">
            <strong>⚠️ ATENÇÃO:</strong> Esta ordem de faturação ainda não foi processada. 
            Após a emissão da fatura oficial, marque como "Faturado" no sistema.
        </div>
        @endif

        <!-- ASSINATURAS -->
        <div class="assinaturas">
            <div class="assinatura-box">
                <div class="assinatura-linha"></div>
                <div class="assinatura-label">Assinatura do Cliente</div>
                <div class="assinatura-nome">{{ $ordem->cliente }}</div>
            </div>
            
            <div class="assinatura-box">
                <div class="assinatura-linha"></div>
                <div class="assinatura-label">Assinatura do Responsável</div>
                <div class="assinatura-nome">{{ $empresa->nome ?? 'Empresa' }}</div>
            </div>
        </div>

        <!-- RODAPÉ -->
        <div class="footer">
            <div class="footer-info">{{ $empresa->nome ?? 'Empresa' }} - NIF: {{ $empresa->nif ?? '123456789' }}</div>
            <div class="footer-info">Documento gerado por {{ $usuario }} em {{ $current_date }}</div>
            <div class="footer-info">Este documento serve como pré-fatura e guia para emissão do documento fiscal oficial</div>
        </div>
    </div>
</body>
</html>