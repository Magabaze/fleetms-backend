{{-- resources/views/pdf/nota-fiscal.blade.php --}}
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>{{ $tipo_info['titulo'] }} - {{ $numero_formatado }}</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'Plus Jakarta Sans', 'plusjakartasans', DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #0f1116;
    background: #fff;
    line-height: 1.5;
}

.page {
    width: 750px;
    margin: 0 auto;
    padding: 0;
}

.content {
    padding: 32px 36px 28px 36px;
}

/* ── LEFT ACCENT BORDER via table cell ── */
.accent-col {
    width: 5px;
    background: #013334;
}

.company-name {
    font-size: 17px;
    font-weight: bold;
    color: #013334;
    margin-bottom: 4px;
}

.company-meta {
    font-size: 10px;
    color: #5a5f6e;
    line-height: 1.9;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 9.5px;
    font-weight: bold;
    letter-spacing: 0.7px;
    text-transform: uppercase;
    background-color: {{ $tipo_info['cor_fundo'] }};
    color: {{ $tipo_info['cor'] }};
}

.doc-number-label {
    font-size: 9px;
    color: #9fa4b0;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-top: 8px;
    margin-bottom: 2px;
}

.doc-number {
    font-size: 19px;
    font-weight: bold;
    color: #0f1116;
}

.plabel {
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #9fa4b0;
    margin-bottom: 3px;
}

.pname {
    font-size: 12px;
    font-weight: bold;
    color: #0f1116;
    margin-bottom: 1px;
}

.psub { font-size: 10px; color: #5a5f6e; }

.card-head {
    background: #013334;
    color: #ffffff;
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 0.9px;
    text-transform: uppercase;
    padding: 7px 14px;
}

.alabel {
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #5a5f6e;
    margin-bottom: 3px;
}

/* VALOR — tamanho reduzido e proporcional */
.avalue {
    font-size: 18px;
    font-weight: bold;
    color: #0f1116;
    line-height: 1.2;
}

.avalue-currency {
    font-size: 12px;
    font-weight: normal;
    color: #5a5f6e;
}

.aext {
    font-size: 10px;
    color: #5a5f6e;
    font-style: italic;
    margin-top: 3px;
}

.obs-label {
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #9fa4b0;
    margin-bottom: 4px;
}

.sig-name { font-size: 10px; color: #9fa4b0; }
.footer-brand { font-weight: bold; color: #013334; }
</style>
</head>
<body>
<div class="page">
<div class="content">

    <!-- HEADER -->
    <table width="100%" style="border-collapse:collapse; margin-bottom:16px;">
        <tr>
            <td style="vertical-align:top;">
                @if($logo_empresa)
                    <img src="{{ $logo_empresa }}" style="max-height:42px;max-width:140px;display:block;margin-bottom:7px;">
                @endif
                <div class="company-name">{{ $empresa->nome ?? 'Transportes ABC' }}</div>
                <div class="company-meta">
                    NIF: {{ $empresa->nif ?? '400123456' }}<br>
                    {{ $empresa->endereco ?? 'Av. Principal, 123 — Maputo' }}<br>
                    {{ $empresa->telefone ?? '+258 84 123 4567' }} · {{ $empresa->email ?? 'geral@transportes.co.mz' }}
                </div>
            </td>
            <td style="vertical-align:top; text-align:right; width:210px;">
                <span class="badge">{{ $tipo_info['titulo'] }}</span>
                <div class="doc-number-label">Número</div>
                <div class="doc-number">{{ $numero_formatado }}</div>
            </td>
        </tr>
    </table>

    <table width="100%" style="border-collapse:collapse; margin-bottom:14px;">
        <tr><td style="border-top:1px solid #e4e6ec; font-size:0;">&nbsp;</td></tr>
    </table>

    <!-- PARTIES -->
    <table width="100%" style="border-collapse:collapse; margin-bottom:14px;">
        <tr>
            <td width="49%" style="background:#f5f6f8; border-radius:5px; padding:11px 13px; vertical-align:top;">
                <div class="plabel">Cliente</div>
                <div class="pname">{{ $nota->cliente }}</div>
                @if($nota->ordem)
                    <div class="psub">OS: #{{ $nota->ordem->numero ?? $nota->ordemId }}</div>
                @endif
            </td>
            <td width="2%"></td>
            <td width="49%" style="background:#f5f6f8; border-radius:5px; padding:11px 13px; vertical-align:top;">
                <div class="plabel">Data de Emissão</div>
                <div class="pname">{{ $data_emissao }}</div>
                <div class="psub">{{ $data_extenso }}</div>
            </td>
        </tr>
    </table>

    <!-- DETAILS CARD -->
    <table width="100%" style="border-collapse:collapse; border:1px solid #e4e6ec; margin-bottom:14px;">
        <tr>
            <td class="card-head" colspan="2">Detalhes · {{ $tipo_info['titulo'] }}</td>
        </tr>
        <tr>
            <td width="150" style="padding:8px 13px; border-bottom:1px solid #e4e6ec; font-weight:bold; color:#5a5f6e; font-size:10px;">Motivo</td>
            <td style="padding:8px 13px; border-bottom:1px solid #e4e6ec;">{{ $nota->motivo }}</td>
        </tr>
        @if($nota->tipo == 'credito')
        <tr>
            <td width="150" style="padding:8px 13px; border-bottom:1px solid #e4e6ec; font-weight:bold; color:#5a5f6e; font-size:10px;">Documento Original</td>
            <td style="padding:8px 13px; border-bottom:1px solid #e4e6ec;">Fatura/Recibo relacionado</td>
        </tr>
        @endif
        <tr>
            <td width="150" style="padding:8px 13px; {{ $nota->ordem ? 'border-bottom:1px solid #e4e6ec;' : '' }} font-weight:bold; color:#5a5f6e; font-size:10px;">Referência</td>
            <td style="padding:8px 13px; {{ $nota->ordem ? 'border-bottom:1px solid #e4e6ec;' : '' }}">{{ $nota->numero }}</td>
        </tr>
        @if($nota->ordem)
        <tr>
            <td width="150" style="padding:8px 13px; font-weight:bold; color:#5a5f6e; font-size:10px;">Ordem de Serviço</td>
            <td style="padding:8px 13px;">{{ $nota->ordem->numero }} — {{ $nota->ordem->descricao ?? '' }}</td>
        </tr>
        @endif
    </table>

    <!-- AMOUNT -->
    <table width="100%" style="border-collapse:collapse; margin-bottom:13px;">
        <tr>
            <td style="vertical-align:top;">&nbsp;</td>
            <td width="300" style="background:#e8f0f0; border-left:4px solid #013334; padding:12px 16px; vertical-align:top;">
                <div class="alabel">Valor {{ $tipo_info['titulo'] }}</div>
                <div class="avalue">
                    {{ $valor_formatado }} <span class="avalue-currency">MZN</span>
                </div>
                <div class="aext">{{ $valor_extenso }}</div>
            </td>
        </tr>
    </table>

    <!-- OBSERVAÇÕES -->
    @if($nota->observacoes)
    <table width="100%" style="border-collapse:collapse; margin-bottom:13px;">
        <tr>
            <td style="border:1px dashed #e4e6ec; padding:10px 13px;">
                <div class="obs-label">Observações</div>
                <div style="color:#374151; font-size:10.5px;">{{ $nota->observacoes }}</div>
            </td>
        </tr>
    </table>
    @endif

    <!-- ASSINATURAS -->
    <table width="100%" style="border-collapse:collapse; border-top:1px solid #e4e6ec; margin-top:16px; margin-bottom:18px;">
        <tr>
            <td width="50%" style="text-align:center; padding-top:16px;">
                <table width="65%" style="border-collapse:collapse; margin:0 auto;">
                    <tr><td style="border-bottom:1px solid #9fa4b0; padding-top:40px;"></td></tr>
                </table>
                <div class="sig-name" style="margin-top:5px;">Assinatura do Cliente</div>
            </td>
            <td width="50%" style="text-align:center; padding-top:16px;">
                <table width="65%" style="border-collapse:collapse; margin:0 auto;">
                    <tr><td style="border-bottom:1px solid #9fa4b0; padding-top:40px;"></td></tr>
                </table>
                <div class="sig-name" style="margin-top:5px;">Assinatura do Responsável</div>
            </td>
        </tr>
    </table>

    <!-- FOOTER -->
    <table width="100%" style="border-collapse:collapse; border-top:1px solid #e4e6ec;">
        <tr>
            <td style="font-size:9px; color:#9fa4b0; line-height:1.8; padding-top:9px; vertical-align:bottom;">
                Documento processado por computador · Válido sem assinatura<br>
                Emissão: {{ $current_date }}@if($criadoPor) · Operador: {{ $criadoPor }}@endif
            </td>
            <td style="font-size:9px; color:#9fa4b0; text-align:right; padding-top:9px; vertical-align:bottom; line-height:1.8;">
                <span class="footer-brand">{{ config('app.name') }}</span><br>
                Sistema de Gestão de Transportes
            </td>
        </tr>
    </table>

</div>
</div>
</body>
</html>