{{-- resources/views/pdf/document.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Documento PDF</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        h1 { color: #333; }
        p { margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Documento Gerado com Sucesso!</h1>
    <p><strong>Viagem:</strong> {{ $tripNumber }}</p>
    <p><strong>Camião:</strong> {{ $truckNumber }}</p>
    <p><strong>Motorista:</strong> {{ $driver }}</p>
    <p><strong>Rota:</strong> {{ $fromStation }} → {{ $toStation }}</p>
    <p><strong>Data:</strong> {{ $date }}</p>
    <p><strong>Tipo:</strong> {{ $printType }}</p>
    
    <div style="margin-top: 50px; border-top: 1px solid #ccc; padding-top: 20px; font-size: 12px; color: #666;">
        Sistema TCM Transportes - Documento gerado automaticamente
    </div>
</body>
</html>