<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PublicTrackingController extends Controller
{
    /**
     * Parse a tracking code like BD-56778/1 or BD-56778-1
     */
    private function parseTrackingCode(string $code): ?array
    {
        // Remove espaços e converte para maiúsculas
        $code = strtoupper(trim($code));
        
        Log::info('🔍 Parsing tracking code:', ['code' => $code]);
        
        // Padrões aceitos:
        // BD-56778/1  (com slash)
        // BD-56778-1  (com hífen)
        
        if (preg_match('/^([A-Z0-9]+-[A-Z0-9]+)[\/-](\d+)$/', $code, $matches)) {
            $tripNumber = $matches[1];  // BD-56778
            $legNumber = (int) $matches[2];  // 1
            
            Log::info('✅ Code parsed successfully', [
                'trip_number' => $tripNumber,
                'leg' => $legNumber
            ]);
            
            return [
                'trip_number' => $tripNumber,
                'leg' => $legNumber
            ];
        }
        
        Log::error('❌ Invalid tracking code format', ['code' => $code]);
        return null;
    }

    /**
     * GET /api/public/tracking/{code}
     * Exemplo: /api/public/tracking/BD-56778/1
     */
    public function trackByCode(string $code)
    {
        try {
            Log::info('📥 Public tracking request', ['code' => $code]);
            
            // Parsear o código
            $parsed = $this->parseTrackingCode($code);
            
            if (!$parsed) {
                return response()->json([
                    'success' => false,
                    'error' => 'Formato de código inválido. Use: BD-56778/1 ou BD-56778-1'
                ], 400);
            }
            
            $tripNumber = $parsed['trip_number'];
            $legNumber = $parsed['leg'];
            
            // Buscar a leg específica
            $viagem = Viagem::where('trip_number', $tripNumber)
                ->where('trip_slno', $legNumber)
                ->first();
            
            if (!$viagem) {
                Log::warning('⚠️ Trip/leg not found', [
                    'trip_number' => $tripNumber,
                    'leg' => $legNumber
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Carga não encontrada. Verifique o código.'
                ], 404);
            }
            
            // Verificar se a viagem está ativa/visível
            if ($viagem->status === 'CANCELLED') {
                return response()->json([
                    'success' => false,
                    'error' => 'Esta carga foi cancelada.'
                ], 404);
            }
            
            // Formatar os eventos de tracking baseados no histórico
            $trackingEvents = $this->generateTrackingEvents($viagem);
            
            // Calcular progresso baseado no status
            $progress = $this->calculateProgress($viagem->status, $viagem->current_status);
            
            Log::info('✅ Tracking data found', [
                'trip_number' => $tripNumber,
                'leg' => $legNumber,
                'customer' => $viagem->customer_name
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'numero' => $code, // Código completo
                    'tripNumber' => $tripNumber,
                    'tripSlno' => $legNumber,
                    'status' => $this->mapStatus($viagem->status),
                    'descricaoStatus' => $this->getStatusDescription($viagem->current_status),
                    'destinatario' => $viagem->customer_name,
                    'origem' => $viagem->from_station,
                    'destino' => $viagem->to_station,
                    'dataPostagem' => $viagem->schedule_date ? $viagem->schedule_date->toIso8601String() : null,
                    'dataEstimada' => $viagem->delivery_date ? $viagem->delivery_date->toIso8601String() : null,
                    'transportadora' => 'TruckLink Africa', // Você pode personalizar
                    'tipoCarga' => $viagem->cargo_type ?? 'Container',
                    'containerNo' => $viagem->container_no,
                    'blNumber' => $viagem->bl_number,
                    'peso' => $viagem->weight,
                    'commodity' => $viagem->commodity,
                    'motorista' => $viagem->driver,
                    'camiao' => $viagem->truck_number,
                    'progresso' => $progress,
                    'eventos' => $trackingEvents
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Public tracking error: ' . $e->getMessage(), [
                'code' => $code,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno no servidor. Tente novamente mais tarde.'
            ], 500);
        }
    }
    
    /**
     * Gerar eventos de tracking baseados no histórico da viagem
     */
    private function generateTrackingEvents(Viagem $viagem): array
    {
        $events = [];
        $now = now();
        
        // Evento 1: Agendamento (sempre existe)
        $events[] = [
            'id' => 1,
            'status' => 'completed',
            'descricao' => 'Viagem agendada',
            'data' => $viagem->schedule_date ? $viagem->schedule_date->toIso8601String() : $viagem->created_at->toIso8601String(),
            'local' => $viagem->from_station,
            'detalhes' => "Motorista: {$viagem->driver} | Camião: {$viagem->truck_number}"
        ];
        
        // Evento 2: Carregamento (se status >= LOADED)
        if (in_array($viagem->status, ['LOADED', 'IN TRANSIT', 'AT THE BORDER', 'DELIVERED'])) {
            $events[] = [
                'id' => 2,
                'status' => 'completed',
                'descricao' => 'Carga carregada',
                'data' => $viagem->schedule_date ? $viagem->schedule_date->addHour()->toIso8601String() : null,
                'local' => $viagem->from_station,
                'detalhes' => "Container: {$viagem->container_no} | Peso: {$viagem->weight} kg"
            ];
        }
        
        // Evento 3: Em trânsito (se status >= IN TRANSIT)
        if (in_array($viagem->status, ['IN TRANSIT', 'AT THE BORDER', 'DELIVERED'])) {
            $events[] = [
                'id' => 3,
                'status' => 'completed',
                'descricao' => 'Em trânsito',
                'data' => $viagem->schedule_date ? $viagem->schedule_date->addHours(2)->toIso8601String() : null,
                'local' => 'Em rota',
                'detalhes' => $viagem->current_status ?: 'A caminho do destino'
            ];
        }
        
        // Evento 4: Na fronteira (se status = AT THE BORDER)
        if ($viagem->status === 'AT THE BORDER') {
            $events[] = [
                'id' => 4,
                'status' => 'in_progress',
                'descricao' => 'Na fronteira',
                'data' => $viagem->border_arrival_date ? $viagem->border_arrival_date->toIso8601String() : null,
                'local' => 'Fronteira',
                'detalhes' => $viagem->tracking_comments ?: 'Processamento alfandegário'
            ];
        }
        
        // Evento 5: Entregue (se status = DELIVERED)
        if ($viagem->status === 'DELIVERED') {
            $events[] = [
                'id' => 5,
                'status' => 'completed',
                'descricao' => 'Carga entregue',
                'data' => $viagem->actual_delivery ? $viagem->actual_delivery->toIso8601String() : null,
                'local' => $viagem->to_station,
                'detalhes' => 'Entrega concluída com sucesso'
            ];
        }
        
        // Evento 6: Avariado (se status = BREAKDOWN)
        if ($viagem->status === 'BREAKDOWN') {
            $events[] = [
                'id' => 5,
                'status' => 'in_progress',
                'descricao' => 'Carga avariada/parada',
                'data' => $now->toIso8601String(),
                'local' => $viagem->current_position ?: 'Local desconhecido',
                'detalhes' => $viagem->tracking_comments ?: 'Problemas técnicos'
            ];
        }
        
        // Adicionar evento atual baseado no current_status
        $currentEventId = count($events) + 1;
        $events[] = [
            'id' => $currentEventId,
            'status' => $this->getEventStatus($viagem->status),
            'descricao' => $viagem->current_status ?: 'Status atual',
            'data' => $now->toIso8601String(),
            'local' => $viagem->current_position ?: $viagem->to_station,
            'detalhes' => $viagem->tracking_comments ?: 'Atualização mais recente'
        ];
        
        return $events;
    }
    
    /**
     * Mapear status interno para status público
     */
    private function mapStatus(string $internalStatus): string
    {
        $map = [
            'SCHEDULED' => 'scheduled',
            'LOADED' => 'loaded',
            'IN TRANSIT' => 'in_transit',
            'AT THE BORDER' => 'at_border',
            'DELIVERED' => 'delivered',
            'BREAKDOWN' => 'breakdown',
            'PENDING' => 'scheduled',
            'RUNNING' => 'in_transit',
            'COMPLETED' => 'delivered',
            'CLOSED' => 'delivered'
        ];
        
        return $map[$internalStatus] ?? 'scheduled';
    }
    
    /**
     * Obter descrição do status
     */
    private function getStatusDescription(string $currentStatus): string
    {
        if (empty($currentStatus)) {
            return 'Aguardando processamento';
        }
        
        return $currentStatus;
    }
    
    /**
     * Calcular progresso baseado no status
     */
    private function calculateProgress(string $status, string $currentStatus): int
    {
        $progressMap = [
            'SCHEDULED' => 10,
            'PENDING' => 20,
            'LOADED' => 40,
            'IN TRANSIT' => 60,
            'RUNNING' => 70,
            'AT THE BORDER' => 80,
            'DELIVERED' => 100,
            'COMPLETED' => 100,
            'CLOSED' => 100,
            'BREAKDOWN' => 50
        ];
        
        return $progressMap[$status] ?? 10;
    }
    
    /**
     * Obter status do evento para timeline
     */
    private function getEventStatus(string $viagemStatus): string
    {
        if (in_array($viagemStatus, ['DELIVERED', 'COMPLETED', 'CLOSED'])) {
            return 'completed';
        }
        
        if (in_array($viagemStatus, ['BREAKDOWN'])) {
            return 'in_progress';
        }
        
        return 'pending';
    }
}