<?php
// database/seeders/OrdemSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // ✅ ACRESCENTADO
use App\Models\Cliente;
use App\Models\Ordem;
use App\Models\Container;
use App\Models\BreakBulkItem;
use App\Models\TipoEmbalagem;
use Carbon\Carbon;

class OrdemSeeder extends Seeder
{
    public function run(): void // ✅ tipagem acrescentada
    {
        DB::transaction(function () { // ✅ ACRESCENTADO

            // Criar tipos de embalagem padrão
            $tiposEmbalagem = [
                [
                    'nome' => 'Big Bag 30T',
                    'descricao' => 'Big Bag para carga a granel, capacidade 30 toneladas',
                    'peso_padrao' => 30,
                    'unidade' => 'camião',
                    'dimensoes_padrao' => json_encode(['comprimento' => 12, 'largura' => 2.5, 'altura' => 2.7]),
                    'volume_padrao' => 81,
                    'empilhavel' => true,
                    'max_empilhamento' => 1,
                    'instrucoes_manuseio' => 'Usar guindaste para movimentação',
                    'capacidade_maxima' => 30,
                    'criado_por' => 'System',
                    'tenant_id' => 'default'
                ],
                [
                    'nome' => 'Pallet 1T',
                    'descricao' => 'Pallet padrão para carga unitizada',
                    'peso_padrao' => 1,
                    'unidade' => 'pallet',
                    'dimensoes_padrao' => json_encode(['comprimento' => 1.2, 'largura' => 0.8, 'altura' => 1.5]),
                    'volume_padrao' => 1.44,
                    'empilhavel' => true,
                    'max_empilhamento' => 3,
                    'instrucoes_manuseio' => 'Empilhar até 3 unidades',
                    'capacidade_maxima' => 1.2,
                    'criado_por' => 'System',
                    'tenant_id' => 'default'
                ],
                [
                    'nome' => 'Saco 50kg',
                    'descricao' => 'Saco para produtos em pó ou granel',
                    'peso_padrao' => 0.05,
                    'unidade' => 'saco',
                    'dimensoes_padrao' => json_encode(['comprimento' => 0.8, 'largura' => 0.5, 'altura' => 0.3]),
                    'volume_padrao' => 0.12,
                    'empilhavel' => true,
                    'max_empilhamento' => 10,
                    'instrucoes_manuseio' => 'Evitar exposição à umidade',
                    'capacidade_maxima' => 0.05,
                    'criado_por' => 'System',
                    'tenant_id' => 'default'
                ],
                [
                    'nome' => 'Tambor 200kg',
                    'descricao' => 'Tambor metálico para líquidos',
                    'peso_padrao' => 0.2,
                    'unidade' => 'tambor',
                    'dimensoes_padrao' => json_encode(['diametro' => 0.6, 'altura' => 0.9]),
                    'volume_padrao' => 0.254,
                    'empilhavel' => true,
                    'max_empilhamento' => 2,
                    'instrucoes_manuseio' => 'Manter na vertical',
                    'capacidade_maxima' => 0.2,
                    'criado_por' => 'System',
                    'tenant_id' => 'default'
                ]
            ];

            foreach ($tiposEmbalagem as $tipo) {

                // ✅ ACRESCENTADO (conversão segura do JSON)
                if (is_string($tipo['dimensoes_padrao'])) {
                    $tipo['dimensoes_padrao'] = json_decode($tipo['dimensoes_padrao'], true);
                }

                // ✅ ACRESCENTADO (evita duplicar registros)
                TipoEmbalagem::firstOrCreate(
                    ['nome' => $tipo['nome']],
                    $tipo
                );
            }

            // Buscar clientes existentes
            $clientes = Cliente::take(5)->get();

            if ($clientes->isEmpty()) {
                $this->command->info('Nenhum cliente encontrado. Execute o ClienteSeeder primeiro.');
                return;
            }

            // Criar algumas ordens de exemplo
            $ordens = [
                [
                    'order_numero' => 'ORD-' . date('Y') . '0001',
                    'tipo_transito' => 'Export',
                    'cliente_id' => $clientes[0]->id,
                    'consignee_id' => $clientes[1]->id ?? null,
                    'expedidor_id' => $clientes[2]->id ?? null,
                    'origem' => 'Beira',
                    'destino' => 'Harare',
                    'commodity' => 'Coal',
                    'tipo_carga' => 'Container',
                    'status' => 'approved',
                    'created_date' => Carbon::now()->subDays(10)->toDateString(),
                    'previsao_carregamento' => Carbon::now()->subDays(5)->toDateString(),
                    'previsao_entrega' => Carbon::now()->addDays(5)->toDateString(),
                    'numero_bl' => 'BL' . rand(100000, 999999),
                    'shipping_line' => 'Maersk Line',
                    'fronteira' => 'Machipanda',
                    'agente_fronteira' => 'Portnet',
                    'moeda_fatura' => 'USD',
                    'valor_fatura' => 25000,
                    'peso_total' => 25.5,
                    'volume_total' => 33.2,
                    'tipo_transporte' => 'Rodoviário',
                    'numero_transporte' => 'TR' . rand(1000, 9999),
                    'motorista' => 'João Silva',
                    'veiculo' => 'Volvo FH16',
                    'observacoes' => 'Carga urgente para mineradora',
                    'instrucoes_especiais' => 'Manter temperatura controlada',
                    'empty_container' => false,
                    'perigosa' => false,
                    'criado_por' => 'Admin User',
                    'tenant_id' => 'default'
                ],
                [
                    'order_numero' => 'ORD-' . date('Y') . '0002',
                    'tipo_transito' => 'Import',
                    'cliente_id' => $clientes[1]->id,
                    'origem' => 'Johannesburg',
                    'destino' => 'Maputo',
                    'commodity' => 'Machinery',
                    'tipo_carga' => 'Break Bulk',
                    'status' => 'pending',
                    'created_date' => Carbon::now()->subDays(3)->toDateString(),
                    'previsao_carregamento' => Carbon::now()->addDays(2)->toDateString(),
                    'numero_bl' => 'BL' . rand(100000, 999999),
                    'moeda_fatura' => 'USD',
                    'valor_fatura' => 18000,
                    'peso_total' => 18.2,
                    'volume_total' => 45.6,
                    'observacoes' => 'Equipamentos para construção',
                    'criado_por' => 'Admin User',
                    'tenant_id' => 'default'
                ]
            ];

            foreach ($ordens as $ordemData) {
                $ordem = Ordem::create($ordemData);

                // Containers
                if ($ordem->tipo_carga === 'Container') {
                    foreach ([
                        [
                            'tipo_recipiente' => 'Container 40" Dry',
                            'tipo_carga' => 'FCL',
                            'unidade' => 'EM TONELADAS MÉTRICAS',
                            'peso_liquido' => 12.5,
                            'peso_container' => 3.7,
                            'peso_total' => 16.2,
                            'numero_container' => 'MSCU' . rand(1000000, 9999999),
                            'selo' => 'SE' . rand(10000, 99999),
                            'drop_off_details' => 'Drop Off no Porto',
                            'deposito_contentores' => 'Depósito A - Porto da Beira',
                            'status' => 'loaded',
                            'tenant_id' => 'default'
                        ]
                    ] as $containerData) {
                        $ordem->containers()->create($containerData);
                    }
                }

                // Evento inicial
                $ordem->events()->create([
                    'event_type' => 'status_change',
                    'status_from' => null,
                    'status_to' => $ordem->status,
                    'description' => 'Ordem criada',
                    'responsavel' => $ordem->criado_por,
                    'responsavel_tipo' => 'user',
                    'tenant_id' => 'default'
                ]);
            }

            $this->command->info('Ordens seedadas com sucesso!');
        });
    }
}
