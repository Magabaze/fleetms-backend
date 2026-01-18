{{-- resources/views/pdf/simple.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .header { text-align: center; margin-bottom: 40px; }
        .title { font-size: 24px; font-weight: bold; color: #333; }
        .subtitle { font-size: 16px; color: #666; margin-top: 10px; }
        .content { margin-top: 30px; }
        .info-box { 
            border: 1px solid #ddd; 
            padding: 20px; 
            margin-top: 20px; 
            background: #f9f9f9;
        }
        .info-row { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
        .label { font-weight: bold; color: #555; min-width: 150px; display: inline-block; }
        .value { color: #333; }
        .footer { 
            margin-top: 50px; 
            border-top: 2px solid #333; 
            padding-top: 20px; 
            font-size: 12px; 
            color: #777; 
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $title }}</div>
        <div class="subtitle">Tipo: {{ ucfirst($printType) }} | Gerado em: {{ $date }}</div>
    </div>

    <div class="content">
        <div class="info-box">
            <h3>Informações da Viagem</h3>
            <div class="info-row">
                <span class="label">Número da Trip:</span>
                <span class="value">{{ $tripNumber }}</span>
            </div>
            <div class="info-row">
                <span class="label">Camião:</span>
                <span class="value">{{ $truckNumber }}</span>
            </div>
            <div class="info-row">
                <span class="label">Motorista:</span>
                <span class="value">{{ $driver }}</span>
            </div>
            <div class="info-row">
                <span class="label">Origem → Destino:</span>
                <span class="value">{{ $fromStation }} → {{ $toStation }}</span>
            </div>
            <div class="info-row">
                <span class="label">Cliente:</span>
                <span class="value">{{ $customerName }}</span>
            </div>
            <div class="info-row">
                <span class="label">Commodity:</span>
                <span class="value">{{ $commodity }}</span>
            </div>
        </div>

        <div style="margin-top: 30px; padding: 15px; background: #e8f4fd; border-left: 4px solid #2196f3;">
            <p><strong>Documento gerado automaticamente</strong></p>
            <p>Tipo: {{ $printType }}</p>
            <p>Data: {{ $date }}</p>
        </div>
    </div>

    <div class="footer">
        <p>Documento gerado automaticamente pelo sistema TCM Transportes</p>
        <p>© {{ date('Y') }} TCM Transportes Lda - Documento válido sem assinatura</p>
    </div>
</body>
</html>