<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Documento de Teste - Viagem {{ $tripId }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #1e40af;
            margin: 0;
        }
        .header .subtitle {
            color: #666;
            font-size: 14px;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #4b5563;
            min-width: 150px;
        }
        .info-value {
            color: #1f2937;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        .success-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ DOCUMENTO DE TESTE</h1>
        <p class="subtitle">PDF gerado com sucesso - Sistema FleetMS</p>
        <div class="success-badge">CONEXÃO API FUNCIONANDO</div>
    </div>

    <div class="info-box">
        <h2>Informações do Documento</h2>
        <div class="info-row">
            <span class="info-label">Tipo de Documento:</span>
            <span class="info-value">{{ strtoupper($printType) }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">ID da Viagem:</span>
            <span class="info-value">#{{ $tripId }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Data de Geração:</span>
            <span class="info-value">{{ $date }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Gerado por:</span>
            <span class="info-value">{{ $user }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Status:</span>
            <span class="info-value" style="color: #10b981;">✓ Funcionando</span>
        </div>
    </div>

    <div class="info-box">
        <h2>Próximos Passos</h2>
        <p>Este é um documento de teste. Agora você pode:</p>
        <ol>
            <li>Criar templates específicos para cada tipo de documento</li>
            <li>Adicionar os dados reais da viagem</li>
            <li>Customizar o layout conforme necessário</li>
            <li>Implementar cache para melhor performance</li>
        </ol>
    </div>

    <div class="footer">
        <p>Documento gerado automaticamente pelo Sistema FleetMS</p>
        <p>© {{ date('Y') }} - Todos os direitos reservados</p>
        <p>Página 1 de 1</p>
    </div>
</body>
</html>