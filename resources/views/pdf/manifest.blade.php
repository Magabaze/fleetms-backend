<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MANIFESTO DE VIAGEM - {{ $viagem->trip_number }}</title>
    <style>
        /* Reset e configurações gerais */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            background: #fff;
            margin: 0;
            padding: 10mm;
        }
        
        /* Cabeçalho */
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #1a56db;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #1a56db;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .header .trip-number {
            font-size: 18px;
            font-weight: bold;
            color: #374151;
            background: #f3f4f6;
            display: inline-block;
            padding: 5px 15px;
            border-radius: 4px;
            margin: 5px 0;
        }
        
        .company-info {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        
        .company-info strong {
            display: block;
            font-size: 16px;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        /* Seções */
        .section {
            margin-bottom: 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .section-title {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 8px 15px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .section-content {
            padding: 15px;
        }
        
        /* Layout de colunas */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 8px;
            gap: 15px;
        }
        
        .col {
            flex: 1;
            min-width: 200px;
        }
        
        .col-full {
            flex: 0 0 100%;
        }
        
        /* Labels e valores */
        .field {
            margin-bottom: 8px;
        }
        
        .label {
            display: block;
            font-weight: bold;
            color: #4b5563;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        
        .value {
            color: #111827;
            font-size: 11px;
            padding: 4px 8px;
            background: #f9fafb;
            border-radius: 4px;
            border-left: 3px solid #3b82f6;
            min-height: 24px;
            display: flex;
            align-items: center;
        }
        
        /* Badge de status */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fbbf24; color: #78350f; }
        .status-running { background: #06b6d4; color: #164e63; }
        .status-completed { background: #10b981; color: #064e3b; }
        .status-closed { background: #6b7280; color: white; }
        .status-delivered { background: #8b5cf6; color: white; }
        
        /* Tabelas */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .table th {
            background: #f3f4f6;
            color: #374151;
            font-weight: bold;
            padding: 8px;
            text-align: left;
            border: 1px solid #d1d5db;
            font-size: 10px;
            text-transform: uppercase;
        }
        
        .table td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            font-size: 11px;
        }
        
        .table tr:nth-child(even) {
            background: #f9fafb;
        }
        
        /* Assinaturas */
        .signatures {
            margin-top: 30px;
            border-top: 2px solid #d1d5db;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 45%;
        }
        
        .signature-line {
            width: 80%;
            height: 1px;
            background: #000;
            margin: 40px auto 10px auto;
        }
        
        /* Informações do documento */
        .document-info {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 9px;
        }
        
        /* QR Code placeholder */
        .qrcode-placeholder {
            width: 100px;
            height: 100px;
            background: #f3f4f6;
            border: 1px dashed #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: #9ca3af;
            font-size: 8px;
            text-align: center;
        }
        
        /* Alertas e destaques */
        .alert {
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 10px;
        }
        
        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        
        .alert-info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        
        /* Break page */
        .page-break {
            page-break-before: always;
        }
        
        /* Logotipo */
        .logo {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .logo-text {
            font-size: 18px;
            font-weight: bold;
            color: #1a56db;
            letter-spacing: 2px;
        }
        
        /* Cores específicas */
        .text-primary { color: #1a56db; }
        .text-secondary { color: #6b7280; }
        .bg-light { background: #f9fafb; }
        
        /* Utilitários */
        .mt-2 { margin-top: 10px; }
        .mb-2 { margin-bottom: 10px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
    </style>
</head>
<body>
    <!-- Cabeçalho -->
    <div class="header">
        <div class="logo">
            <div class="logo-text">FLEETMS</div>
            <div style="font-size: 10px; color: #6b7280;">Sistema de Gestão de Transportes</div>
        </div>
        
        <h1>MANIFESTO DE VIAGEM</h1>
        <div class="trip-number">{{ $viagem->trip_number }}</div>
        
        <div style="margin-top: 10px; color: #6b7280;">
            Gerado em: {{ $data_geracao }}
        </div>
    </div>
    
    <!-- Informações da Empresa -->
    <div class="company-info">
        @if($empresa)
            <strong>{{ $empresa->nome }}</strong>
            @if($empresa->morada)<div>{{ $empresa->morada }}</div>@endif
            @if($empresa->nif)<div>NIF: {{ $empresa->nif }}</div>@endif
            @if($empresa->telefone)<div>Tel: {{ $empresa->telefone }}</div>@endif
        @else
            <strong>Transporte & Logística</strong>
            <div>Documento Oficial - Viagem Nº {{ $viagem->trip_number }}</div>
        @endif
    </div>
    
    <!-- Status e Informações Principais -->
    <div class="row">
        <div class="col">
            <div class="section">
                <div class="section-title">Status da Viagem</div>
                <div class="section-content">
                    <div class="field">
                        <span class="label">Status Principal</span>
                        <div class="value">
                            <span class="status-badge status-{{ strtolower($viagem->status) }}">
                                {{ $viagem->status }}
                            </span>
                        </div>
                    </div>
                    <div class="field">
                        <span class="label">Status Atual</span>
                        <div class="value">{{ $viagem->current_status ?? 'Não informado' }}</div>
                    </div>
                    <div class="field">
                        <span class="label">Última Posição</span>
                        <div class="value">{{ $viagem->current_position ?? 'Não informado' }}</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col">
            <div class="section">
                <div class="section-title">Datas</div>
                <div class="section-content">
                    <div class="field">
                        <span class="label">Data Agendada</span>
                        <div class="value">
                            @if($viagem->schedule_date)
                                {{ \Carbon\Carbon::parse($viagem->schedule_date)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="field">
                        <span class="label">Data de Entrega</span>
                        <div class="value">
                            @if($viagem->actual_delivery)
                                {{ \Carbon\Carbon::parse($viagem->actual_delivery)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="field">
                        <span class="label">Data POD</span>
                        <div class="value">
                            @if($viagem->pod_delivery_date)
                                {{ \Carbon\Carbon::parse($viagem->pod_delivery_date)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rota -->
    <div class="section">
        <div class="section-title">Rota da Viagem</div>
        <div class="section-content">
            <div class="row">
                <div class="col">
                    <div class="field">
                        <span class="label">Origem</span>
                        <div class="value bold text-primary">{{ $viagem->from_station }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="field">
                        <span class="label">Destino</span>
                        <div class="value bold text-primary">{{ $viagem->to_station }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cliente e Mercadoria -->
    <div class="section">
        <div class="section-title">Cliente e Mercadoria</div>
        <div class="section-content">
            <div class="row">
                <div class="col">
                    <div class="field">
                        <span class="label">Cliente</span>
                        <div class="value">{{ $viagem->customer_name }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="field">
                        <span class="label">Nº Ordem</span>
                        <div class="value">{{ $viagem->order_number ?? '-' }}</div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-2">
                <div class="col">
                    <div class="field">
                        <span class="label">Mercadoria</span>
                        <div class="value">{{ $viagem->commodity }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="field">
                        <span class="label">Tipo de Carga</span>
                        <div class="value">{{ $viagem->cargo_type }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="field">
                        <span class="label">Peso</span>
                        <div class="value">
                            @if($viagem->weight)
                                {{ number_format($viagem->weight, 2, ',', '.') }} kg
                            @else
                                -
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            @if($viagem->container_no || $viagem->bl_number)
            <div class="row mt-2">
                @if($viagem->container_no)
                <div class="col">
                    <div class="field">
                        <span class="label">Nº Container</span>
                        <div class="value">{{ $viagem->container_no }}</div>
                    </div>
                </div>
                @endif
                
                @if($viagem->bl_number)
                <div class="col">
                    <div class="field">
                        <span class="label">Nº BL/AWB</span>
                        <div class="value">{{ $viagem->bl_number }}</div>
                    </div>
                </div>
                @endif
            </div>
            @endif
            
            @if($viagem->isEmptyTrip)
            <div class="alert alert-warning mt-2">
                ⚠️ VIAGEM VAZIA - Retorno sem carga
            </div>
            @endif
        </div>
    </div>
    
    <!-- Veículos e Motorista -->
    <div class="section">
        <div class="section-title">Recursos da Viagem</div>
        <div class="section-content">
            <div class="row">
                <div class="col">
                    <div class="field">
                        <span class="label">Camião</span>
                        <div class="value bold">{{ $viagem->truck_number }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="field">
                        <span class="label">Trela</span>
                        <div class="value">{{ $viagem->trailer_number ?? '-' }}</div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-2">
                <div class="col">
                    <div class="field">
                        <span class="label">Motorista</span>
                        <div class="value bold">{{ $viagem->driver }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="field">
                        <span class="label">Transportadora</span>
                        <div class="value">{{ $viagem->transporter ?? ($viagem->isCompanyOwned ? 'Própria' : 'Externa') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Informações de Tracking -->
    @if($viagem->tracking_comments || $viagem->border_arrival_date || $viagem->offloading_arrival_date)
    <div class="section">
        <div class="section-title">Tracking & Observações</div>
        <div class="section-content">
            @if($viagem->tracking_comments)
            <div class="field">
                <span class="label">Observações</span>
                <div class="value bg-light" style="white-space: pre-wrap; min-height: 40px; padding: 10px;">
                    {{ $viagem->tracking_comments }}
                </div>
            </div>
            @endif
            
            <div class="row mt-2">
                @if($viagem->border_arrival_date)
                <div class="col">
                    <div class="field">
                        <span class="label">Chegada à Fronteira</span>
                        <div class="value">
                            {{ \Carbon\Carbon::parse($viagem->border_arrival_date)->format('d/m/Y') }}
                            @if($viagem->border_demurrage_days)
                                <br><small>Demurrage: {{ $viagem->border_demurrage_days }} dias</small>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
                
                @if($viagem->offloading_arrival_date)
                <div class="col">
                    <div class="field">
                        <span class="label">Chegada à Descarga</span>
                        <div class="value">
                            {{ \Carbon\Carbon::parse($viagem->offloading_arrival_date)->format('d/m/Y') }}
                            @if($viagem->offloading_demurrage_days)
                                <br><small>Demurrage: {{ $viagem->offloading_demurrage_days }} dias</small>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
    
    <!-- Informações Financeiras -->
    @if($viagem->isReadyForInvoice || $viagem->invoice_number)
    <div class="section">
        <div class="section-title">Informações Financeiras</div>
        <div class="section-content">
            <div class="row">
                @if($viagem->invoice_number)
                <div class="col">
                    <div class="field">
                        <span class="label">Nº Fatura</span>
                        <div class="value">{{ $viagem->invoice_number }}</div>
                    </div>
                </div>
                @endif
                
                <div class="col">
                    <div class="field">
                        <span class="label">Pronto para Faturar</span>
                        <div class="value">
                            @if($viagem->isReadyForInvoice)
                                <span style="color: #10b981;">● SIM</span>
                            @else
                                <span style="color: #ef4444;">● NÃO</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Assinaturas -->
    <div class="signatures">
        <div class="signature-box">
            <div class="value" style="text-align: center; min-height: 60px;">
                Assinatura do Motorista
            </div>
            <div class="signature-line"></div>
            <div style="margin-top: 5px; font-size: 10px;">Nome: {{ $viagem->driver }}</div>
            <div style="font-size: 9px; color: #6b7280;">Data: _________________</div>
        </div>
        
        <div class="signature-box">
            <div class="value" style="text-align: center; min-height: 60px;">
                Autorização da Empresa
            </div>
            <div class="signature-line"></div>
            <div style="margin-top: 5px; font-size: 10px;">Responsável</div>
            <div style="font-size: 9px; color: #6b7280;">Data: _________________</div>
        </div>
    </div>
    
    <!-- QR Code (opcional) -->
    <div style="text-align: center; margin-top: 30px;">
        <div class="qrcode-placeholder">
            QR Code<br>Viagem {{ $viagem->trip_number }}
        </div>
        <div style="font-size: 8px; color: #6b7280; margin-top: 5px;">
            Escaneie para verificar autenticidade
        </div>
    </div>
    
    <!-- Informações do Documento -->
    <div class="document-info">
        <div>Documento gerado por: {{ $usuario->name ?? 'Sistema FleetMS' }}</div>
        <div>Tenant ID: {{ $tenant_id }} | Viagem ID: {{ $viagem->id }}</div>
        <div>© {{ date('Y') }} FleetMS Transport Management System</div>
        <div style="font-size: 8px; margin-top: 5px;">
            Página 1 de 1 | Documento oficial - Não é válido sem assinaturas
        </div>
    </div>
</body>
</html>