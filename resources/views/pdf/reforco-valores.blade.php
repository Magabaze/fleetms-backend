{{-- resources/views/pdf/reforco-valores.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Reforço de Valores - {{ strtoupper($tipo) }}</title>
    <style>
        /* RESET E CONFIGURAÇÕES BASE */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
        
        /* CABEÇALHO */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 1.2pt solid #013334;
        }
        
        .header-cell {
            display: table-cell;
            vertical-align: middle;
        }
        
        .logo {
            width: 45mm;
        }
        
        .logo img {
            max-width: 40mm;
            max-height: 18mm;
            display: block;
        }
        
        .company-info {
            font-size: 7pt;
            color: #5a5f6e;
            line-height: 1.6;
        }
        
        .company-name {
            font-size: 11pt;
            font-weight: bold;
            color: #013334;
            margin-bottom: 1mm;
        }
        
        .doc-meta {
            text-align: right;
            width: 55mm;
        }
        
        .doc-title {
            font-size: 12pt;
            font-weight: bold;
            color: #013334;
            margin-bottom: 2mm;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }
        
        .doc-number {
            font-size: 9pt;
            font-weight: bold;
            color: #0f1116;
        }
        
        .badge {
            display: inline-block;
            padding: 1mm 3mm;
            border-radius: 1mm;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 1mm 0;
            background-color: #e8f0f0;
            color: #013334;
            border: 0.5pt solid #013334;
        }
        
        /* CONTEÚDO PRINCIPAL */
        .content {
            display: table;
            width: 100%;
            margin-bottom: 3mm;
        }
        
        .column {
            display: table-cell;
            vertical-align: top;
            padding: 0 2mm;
        }
        
        .left-column {
            width: 50%;
            padding-left: 0;
        }
        
        .right-column {
            width: 50%;
            padding-right: 0;
        }
        
        /* SEÇÕES */
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
        
        .section-content {
            padding: 2mm 3mm;
        }
        
        /* TABELAS */
        .table-info {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        
        .table-info td {
            padding: 1mm 0;
            vertical-align: top;
            border: none;
        }
        
        .label {
            font-weight: bold;
            width: 35%;
            color: #5a5f6e;
            text-transform: uppercase;
            font-size: 6.5pt;
        }
        
        /* TABELA DE DESPESAS */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5pt;
            margin-top: 1mm;
        }
        
        .items-table th {
            background: #4a5568;
            color: white;
            padding: 1mm 2mm;
            border: 0.5pt solid #000;
            text-align: left;
            font-weight: bold;
            font-size: 6.5pt;
            text-transform: uppercase;
        }
        
        .items-table td {
            border: 0.5pt solid #ddd;
            padding: 1mm 2mm;
            vertical-align: top;
        }
        
        .items-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        
        /* TOTAIS */
        .totals-box {
            background: #f5f6f8;
            border: 0.5pt solid #e4e6ec;
            padding: 2mm 3mm;
            margin: 3mm 0;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        
        .totals-table td {
            padding: 0.8mm 0;
            border: none;
        }
        
        .totals-table tr:last-child td {
            border-top: 0.5pt solid #013334;
            padding-top: 1.5mm;
            font-weight: bold;
            font-size: 8pt;
        }
        
        /* VALOR EXTENSO */
        .valor-extenso {
            background: #e8f0f0;
            border-left: 3pt solid #013334;
            padding: 2mm 3mm;
            margin: 2mm 0;
            font-style: italic;
            font-size: 6.5pt;
            color: #013334;
        }
        
        /* ASSINATURAS */
        .signatures {
            display: table;
            width: 100%;
            margin-top: 4mm;
            padding-top: 3mm;
            border-top: 0.5pt solid #e4e6ec;
        }
        
        .signature-box {
            display: table-cell;
            text-align: center;
            width: 50%;
        }
        
        .signature-line {
            height: 12mm;
            border-bottom: 0.5pt solid #9fa4b0;
            margin: 1mm 5mm;
        }
        
        .signature-label {
            font-size: 6.5pt;
            font-weight: bold;
            margin-bottom: 0.5mm;
            color: #5a5f6e;
        }
        
        /* RODAPÉ */
        .footer {
            width: 100%;
            text-align: center;
            font-size: 6pt;
            color: #9fa4b0;
            padding-top: 2mm;
            border-top: 0.5pt solid #e4e6ec;
            margin-top: 3mm;
        }
        
        .footer-brand {
            font-weight: bold;
            color: #013334;
        }
        
        /* UTILITÁRIOS */
        .bold { font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #9fa4b0; }
        
        /* MARCA D'ÁGUA PARA DUPLICADO */
        @if(isset($tipo) && $tipo == 'duplicate')
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
        
        /* EVITA QUEBRAS */
        .section, .signatures, .totals-box {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                padding: 2mm;
            }
            .items-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        @if(isset($tipo) && $tipo == 'duplicate')
            <div class="watermark">DUPLICADO</div>
        @endif
        
        <!-- CABEÇALHO -->
        <div class="header">
            <div class="header-cell logo">
                @if($logo_empresa)
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
                <div class="doc-title">REFORÇO DE VALORES</div>
                <div class="doc-number">Viagem: {{ $viagem->trip_number ?? '—' }}</div>
                <div>
                    <span class="badge">{{ strtoupper($tipo ?? 'ORIGINAL') }}</span>
                </div>
                <div style="margin-top: 1mm;">
                    <strong>Data:</strong> {{ $data_emissao ?? date('d/m/Y H:i') }}
                </div>
            </div>
        </div>
        
        <!-- CONTEÚDO PRINCIPAL -->
        <div class="content">
            
            <!-- COLUNA ESQUERDA -->
            <div class="column left-column">
                
                <!-- INFORMAÇÕES DA VIAGEM -->
                <div class="section">
                    <div class="section-title">Informações da Viagem</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Viagem</td>
                                <td class="bold">{{ $viagem->trip_number ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Motorista</td>
                                <td>{{ $viagem->driver ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Camião</td>
                                <td>{{ $viagem->truck_number ?? '—' }}</td>
                            </tr>
                            @if($viagem->trailer_number)
                            <tr>
                                <td class="label">Reboque</td>
                                <td>{{ $viagem->trailer_number }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                
            </div>
            
            <!-- COLUNA DIREITA -->
            <div class="column right-column">
                
                <!-- ROTA -->
                <div class="section">
                    <div class="section-title">Rota</div>
                    <div class="section-content">
                        <table class="table-info">
                            <tr>
                                <td class="label">Origem</td>
                                <td>{{ $viagem->from_station ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Destino</td>
                                <td>{{ $viagem->to_station ?? '—' }}</td>
                            </tr>
                            @if($viagem->schedule_date)
                            <tr>
                                <td class="label">Data Prevista</td>
                                <td>{{ date('d/m/Y', strtotime($viagem->schedule_date)) }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- DETALHAMENTO DE DESPESAS -->
        <div class="section">
            <div class="section-title">Detalhamento de Despesas</div>
            <div class="section-content">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:8%">MOEDA</th>
                            <th style="width:20%">TIPO</th>
                            <th>DESCRIÇÃO</th>
                            <th style="width:20%; text-align:right;">VALOR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($despesas as $despesa)
                        <tr>
                            <td class="text-center bold">{{ $despesa->currency ?? $despesa->moeda ?? 'MZN' }}</td>
                            <td>
                                @if(isset($despesa->tipoDespesa) && $despesa->tipoDespesa)
                                    {{ $despesa->tipoDespesa->nome }}
                                @elseif(isset($despesa->tipo))
                                    {{ $despesa->tipo }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $despesa->descricao ?? $despesa->payment_description ?? $despesa->description ?? '—' }}</td>
                            <td class="text-right">
                                {{ number_format(floatval($despesa->amount ?? $despesa->valor_estimado ?? $despesa->valor ?? 0), 2, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted" style="padding:10mm;">
                                Nenhuma despesa registada
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- RESUMO POR MOEDA -->
        @if(count($resumo_por_moeda) > 0)
        <div class="totals-box">
            <table class="totals-table">
                @foreach($resumo_por_moeda as $moeda => $dados)
                <tr>
                    <td>
                        Total em <strong>{{ $moeda }}</strong>
                        <span style="color:#666; font-size:6pt;">
                            ({{ $dados['quantidade'] }} despesa{{ $dados['quantidade'] != 1 ? 's' : '' }})
                        </span>
                    </td>
                    <td class="text-right">
                        <strong>{{ number_format($dados['total'], 2, ',', '.') }} {{ $moeda }}</strong>
                    </td>
                </tr>
                @endforeach
            </table>
            
            @foreach($resumo_por_moeda as $moeda => $dados)
            @php
                $total = $dados['total'];
                $inteiro = (int) floor($total);
                $centavos = (int) round(($total - $inteiro) * 100);
                
                $nomeMoeda = match($moeda) {
                    'USD' => ['singular' => 'Dólar', 'plural' => 'Dólares', 'cent_s' => 'Cêntimo', 'cent_p' => 'Cêntimos'],
                    'ZAR' => ['singular' => 'Rand', 'plural' => 'Rands', 'cent_s' => 'Cêntimo', 'cent_p' => 'Cêntimos'],
                    'EUR' => ['singular' => 'Euro', 'plural' => 'Euros', 'cent_s' => 'Cêntimo', 'cent_p' => 'Cêntimos'],
                    default => ['singular' => 'Metical', 'plural' => 'Meticais', 'cent_s' => 'Centavo', 'cent_p' => 'Centavos'],
                };
            @endphp
            <div class="valor-extenso">
                <strong>{{ $moeda }} por extenso:</strong> 
                {{ numeroPorExtenso($inteiro) }} {{ $inteiro === 1 ? $nomeMoeda['singular'] : $nomeMoeda['plural'] }}
                @if($centavos > 0)
                    e {{ numeroPorExtenso($centavos) }} {{ $centavos === 1 ? $nomeMoeda['cent_s'] : $nomeMoeda['cent_p'] }}
                @endif
            </div>
            @endforeach
        </div>
        @endif
        
        <!-- ASSINATURAS -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-label">Entregue por</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $usuario ?? 'Sistema' }}</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Motorista</div>
                <div class="signature-line"></div>
                <div class="text-muted">{{ $viagem->driver ?? '—' }}</div>
            </div>
        </div>
        
        <!-- RODAPÉ -->
        <div class="footer">
            <div>
                Documento processado por computador · Válido sem assinatura<br>
                Emissão: {{ $data_emissao ?? date('d/m/Y H:i:s') }} · Operador: {{ $usuario ?? 'Sistema' }}
            </div>
            <div style="margin-top: 1mm;">
                <span class="footer-brand">abdago Fleet</span><br>
                Sistema de Gestão de Transportes
            </div>
        </div>
        
    </div>
</body>
</html>

@php
// Função auxiliar para número por extenso
function numeroPorExtenso(int $n): string {
    if ($n === 0) return 'Zero';
    $unidades  = ['', 'Um', 'Dois', 'Três', 'Quatro', 'Cinco', 'Seis', 'Sete', 'Oito', 'Nove',
                  'Dez', 'Onze', 'Doze', 'Treze', 'Catorze', 'Quinze', 'Dezasseis',
                  'Dezassete', 'Dezoito', 'Dezanove'];
    $dezenas   = ['', '', 'Vinte', 'Trinta', 'Quarenta', 'Cinquenta',
                  'Sessenta', 'Setenta', 'Oitenta', 'Noventa'];
    $centenas  = ['', 'Cem', 'Duzentos', 'Trezentos', 'Quatrocentos', 'Quinhentos',
                  'Seiscentos', 'Setecentos', 'Oitocentos', 'Novecentos'];

    $partes = [];

    if ($n >= 1000000) {
        $m = (int)($n / 1000000);
        $partes[] = numeroPorExtenso($m) . ($m === 1 ? ' Milhão' : ' Milhões');
        $n %= 1000000;
    }
    if ($n >= 1000) {
        $k = (int)($n / 1000);
        $partes[] = ($k === 1 ? 'Mil' : numeroPorExtenso($k) . ' Mil');
        $n %= 1000;
    }
    if ($n >= 100) {
        $c = (int)($n / 100);
        $partes[] = ($n === 100 ? 'Cem' : $centenas[$c]);
        $n %= 100;
    }
    if ($n >= 20) {
        $d = (int)($n / 10);
        $u = $n % 10;
        $partes[] = $dezenas[$d] . ($u > 0 ? ' e ' . $unidades[$u] : '');
        $n = 0;
    }
    if ($n > 0) {
        $partes[] = $unidades[$n];
    }

    return implode(' e ', array_filter($partes));
}
@endphp