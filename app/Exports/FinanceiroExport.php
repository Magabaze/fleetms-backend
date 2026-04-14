<?php
// app/Exports/FinanceiroExport.php - VERSÃO COMPLETA COM MÚLTIPLAS MOEDAS

namespace App\Exports;

use App\Models\Viagem;
use App\Models\DriverExpense;
use App\Models\OrdemFaturacao;
use App\Models\NotaFiscal;
use App\Models\CaixaRequisicao;
use App\Models\CaixaJustificativa;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinanceiroExport extends BaseExport implements
    FromArray,
    WithHeadings,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    protected $dataInicio;
    protected $dataFim;
    protected $tipo;
    protected $tenantId;
    
    // Taxas de câmbio (pode ser carregado da base de dados)
    protected $taxasCambio = [
        'MZN' => 1.0,
        'USD' => 63.5,
        'EUR' => 68.0,
        'ZAR' => 3.4,
        'GBP' => 80.0,
    ];

    public function __construct(string $dataInicio, string $dataFim, string $tipo = 'geral')
    {
        $this->dataInicio = $dataInicio;
        $this->dataFim    = $dataFim;
        $this->tipo       = $tipo;
        $this->tenantId   = Auth::user()->tenant_id ?? 'default';
        
        // Carregar taxas de câmbio da base de dados se existir
        $this->carregarTaxasCambio();
        
        $this->carregarEmpresaPorTenant();
    }

    /**
     * Carregar taxas de câmbio da base de dados
     */
    private function carregarTaxasCambio()
    {
        try {
            if (DB::getSchemaBuilder()->hasTable('taxas_cambio')) {
                $taxas = DB::table('taxas_cambio')
                    ->where('tenant_id', $this->tenantId)
                    ->where('status', 'ativo')
                    ->get();
                    
                foreach ($taxas as $taxa) {
                    $this->taxasCambio[$taxa->moeda] = floatval($taxa->taxa);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Não foi possível carregar taxas de câmbio: ' . $e->getMessage());
        }
    }

    /**
     * Converter valor para MZN
     */
    private function converterParaMZN($valor, $moeda): float
    {
        $moeda = strtoupper($moeda ?? 'MZN');
        $taxa = $this->taxasCambio[$moeda] ?? 1.0;
        return floatval($valor) * $taxa;
    }

    /**
     * Formatar valor com moeda original
     */
    private function formatarValorComMoeda($valor, $moeda): string
    {
        $valorMZN = $this->converterParaMZN($valor, $moeda);
        
        if (strtoupper($moeda) === 'MZN') {
            return number_format($valorMZN, 2, ',', '.');
        }
        
        // Mostrar valor original e convertido
        return number_format($valorMZN, 2, ',', '.') . " ({$moeda} " . number_format($valor, 2, ',', '.') . ")";
    }

    protected function totalColunas(): int
    {
        // Adicionar coluna de Moeda para despesas
        return $this->tipo === 'despesas' ? 8 : 7;
    }

    public function title(): string
    {
        $titulos = [
            'geral' => 'Resumo Financeiro',
            'ordens' => 'Ordens de Faturação',
            'debito' => 'Notas de Débito',
            'credito' => 'Notas de Crédito',
            'despesas' => 'Despesas de Motoristas'
        ];
        return $titulos[$this->tipo] ?? 'Financeiro';
    }

    public function array(): array
    {
        $movimentos = [];
        
        switch ($this->tipo) {
            case 'geral':
                $movimentos = $this->getResumoGeralAgrupado();
                break;
                
            case 'ordens':
                $movimentos = $this->getOrdens();
                break;
                
            case 'debito':
                $movimentos = $this->getNotasFiscais('debito');
                break;
                
            case 'credito':
                $movimentos = $this->getNotasFiscais('credito');
                break;
                
            case 'despesas':
                $movimentos = $this->getDespesasMotoristas();
                break;
        }
        
        return $movimentos;
    }

    /**
     * Resumo Geral AGRUPADO por categoria
     */
    private function getResumoGeralAgrupado(): array
    {
        $movimentos = [];
        
        // Adiciona separadores visuais entre categorias
        $movimentos[] = [
            'tipo' => '═══════════',
            'categoria' => 'RECEITAS - VIAGENS',
            'data' => '═══════════',
            'descricao' => '═══════════════════════════════════════',
            'cliente_motorista' => '═══════════',
            'valor' => 0,
            'status' => '═══════════'
        ];
        
        $movimentos = array_merge($movimentos, $this->getViagens());
        
        $movimentos[] = ['tipo' => '', 'categoria' => '', 'data' => '', 'descricao' => '', 'cliente_motorista' => '', 'valor' => 0, 'status' => ''];
        
        $movimentos[] = [
            'tipo' => '═══════════',
            'categoria' => 'RECEITAS - ORDENS DE FATURAÇÃO',
            'data' => '═══════════',
            'descricao' => '═══════════════════════════════════════',
            'cliente_motorista' => '═══════════',
            'valor' => 0,
            'status' => '═══════════'
        ];
        
        $movimentos = array_merge($movimentos, $this->getOrdens());
        
        $movimentos[] = ['tipo' => '', 'categoria' => '', 'data' => '', 'descricao' => '', 'cliente_motorista' => '', 'valor' => 0, 'status' => ''];
        
        $movimentos[] = [
            'tipo' => '═══════════',
            'categoria' => 'RECEITAS - NOTAS DE DÉBITO',
            'data' => '═══════════',
            'descricao' => '═══════════════════════════════════════',
            'cliente_motorista' => '═══════════',
            'valor' => 0,
            'status' => '═══════════'
        ];
        
        $movimentos = array_merge($movimentos, $this->getNotasFiscais('debito'));
        
        $movimentos[] = ['tipo' => '', 'categoria' => '', 'data' => '', 'descricao' => '', 'cliente_motorista' => '', 'valor' => 0, 'status' => ''];
        
        $movimentos[] = [
            'tipo' => '═══════════',
            'categoria' => 'DESPESAS - NOTAS DE CRÉDITO',
            'data' => '═══════════',
            'descricao' => '═══════════════════════════════════════',
            'cliente_motorista' => '═══════════',
            'valor' => 0,
            'status' => '═══════════'
        ];
        
        $movimentos = array_merge($movimentos, $this->getNotasFiscais('credito'));
        
        $movimentos[] = ['tipo' => '', 'categoria' => '', 'data' => '', 'descricao' => '', 'cliente_motorista' => '', 'valor' => 0, 'status' => ''];
        
        $movimentos[] = [
            'tipo' => '═══════════',
            'categoria' => 'DESPESAS - MOTORISTAS',
            'data' => '═══════════',
            'descricao' => '═══════════════════════════════════════',
            'cliente_motorista' => '═══════════',
            'valor' => 0,
            'status' => '═══════════'
        ];
        
        $despesasMotoristas = $this->getDespesasMotoristas();
        // Remover coluna de moeda para compatibilidade no resumo geral
        $despesasFormatadas = array_map(function($item) {
            unset($item['moeda']);
            return $item;
        }, $despesasMotoristas);
        
        $movimentos = array_merge($movimentos, $despesasFormatadas);
        
        return $movimentos;
    }

    private function getViagens(): array
    {
        $movimentos = [];
        
        try {
            $viagens = Viagem::where('tenant_id', $this->tenantId)
                ->where('status', 'CLOSED')
                ->whereBetween('created_at', [
                    $this->dataInicio . ' 00:00:00', 
                    $this->dataFim . ' 23:59:59'
                ])
                ->orderBy('created_at', 'desc')
                ->get();
                
            foreach ($viagens as $v) {
                $movimentos[] = [
                    'tipo' => 'RECEITA',
                    'categoria' => 'Viagem',
                    'data' => $v->created_at->format('d/m/Y'),
                    'descricao' => "Viagem {$v->trip_number} - {$v->from_station} → {$v->to_station}",
                    'cliente_motorista' => $v->customer_name ?? 'N/I',
                    'valor' => floatval($v->valor ?? 0),
                    'status' => 'Concluída'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar viagens: ' . $e->getMessage());
        }
        
        return $movimentos;
    }

    private function getOrdens(): array
    {
        $movimentos = [];
        
        try {
            if (!class_exists('\App\Models\OrdemFaturacao')) {
                return [];
            }
            
            $ordens = OrdemFaturacao::where('tenant_id', $this->tenantId)
                ->whereBetween('created_at', [
                    $this->dataInicio . ' 00:00:00', 
                    $this->dataFim . ' 23:59:59'
                ])
                ->orderBy('created_at', 'desc')
                ->get();
                
            foreach ($ordens as $ordem) {
                $movimentos[] = [
                    'tipo' => 'RECEITA',
                    'categoria' => 'Ordem Faturação',
                    'data' => $ordem->created_at->format('d/m/Y'),
                    'descricao' => "Ordem {$ordem->codigo} - {$ordem->origem} → {$ordem->destino}",
                    'cliente_motorista' => $ordem->cliente ?? 'N/I',
                    'valor' => floatval($ordem->valor ?? 0),
                    'status' => $this->formatarStatusOrdem($ordem->status)
                ];
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar ordens: ' . $e->getMessage());
        }
        
        return $movimentos;
    }

    private function getNotasFiscais($tipoEspecifico = null): array
    {
        $movimentos = [];
        
        try {
            if (!class_exists('\App\Models\NotaFiscal')) {
                return [];
            }
            
            $query = NotaFiscal::where('tenant_id', $this->tenantId)
                ->whereBetween('created_at', [
                    $this->dataInicio . ' 00:00:00', 
                    $this->dataFim . ' 23:59:59'
                ]);
                
            if ($tipoEspecifico) {
                $query->where('tipo', $tipoEspecifico);
            }
            
            $notas = $query->orderBy('created_at', 'desc')->get();
                
            foreach ($notas as $nota) {
                $tipo = $nota->tipo === 'debito' ? 'RECEITA' : 'DESPESA';
                $categoria = $nota->tipo === 'debito' ? 'Nota de Débito' : 'Nota de Crédito';
                
                $movimentos[] = [
                    'tipo' => $tipo,
                    'categoria' => $categoria,
                    'data' => $nota->created_at->format('d/m/Y'),
                    'descricao' => "NF {$nota->numero} - {$nota->motivo}",
                    'cliente_motorista' => $nota->cliente_nome ?? 'N/I',
                    'valor' => floatval($nota->valor ?? 0),
                    'status' => 'Emitida'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar notas fiscais: ' . $e->getMessage());
        }
        
        return $movimentos;
    }

    private function getDespesasMotoristas(): array
    {
        $movimentos = [];
        
        try {
            if (!class_exists('\App\Models\DriverExpense')) {
                return [];
            }
            
            $despesas = DriverExpense::where('is_active', true)
                ->whereBetween('created_at', [
                    $this->dataInicio . ' 00:00:00', 
                    $this->dataFim . ' 23:59:59'
                ])
                ->with(['viagem'])
                ->orderBy('created_at', 'desc')
                ->get();
                
            foreach ($despesas as $despesa) {
                $viagemInfo = $despesa->viagem ? " (Viagem: {$despesa->viagem->trip_number})" : '';
                $moeda = $despesa->currency ?? 'MZN';
                $valorOriginal = floatval($despesa->amount ?? 0);
                $valorMZN = $this->converterParaMZN($valorOriginal, $moeda);
                
                $movimento = [
                    'tipo' => 'DESPESA',
                    'categoria' => 'Despesa Motorista',
                    'data' => $despesa->created_at->format('d/m/Y'),
                    'descricao' => "{$despesa->expense_head}{$viagemInfo}",
                    'cliente_motorista' => $despesa->driver_name ?? 'N/I',
                    'valor' => $valorMZN,
                    'status' => $this->formatarStatusDespesa($despesa->status),
                    'moeda' => $moeda,
                    'valor_original' => $valorOriginal
                ];
                
                $movimentos[] = $movimento;
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar despesas: ' . $e->getMessage());
        }
        
        return $movimentos;
    }

    private function getRequisicoes(): array
    {
        $movimentos = [];
        
        try {
            if (!class_exists('\App\Models\CaixaRequisicao')) {
                return [];
            }
            
            $requisicoes = CaixaRequisicao::where('tenant_id', $this->tenantId)
                ->whereIn('status', ['aprovado', 'pago'])
                ->whereBetween('created_at', [
                    $this->dataInicio . ' 00:00:00', 
                    $this->dataFim . ' 23:59:59'
                ])
                ->orderBy('created_at', 'desc')
                ->get();
                
            foreach ($requisicoes as $req) {
                $movimentos[] = [
                    'tipo' => 'DESPESA',
                    'categoria' => 'Requisição Caixa',
                    'data' => $req->created_at->format('d/m/Y'),
                    'descricao' => "Requisição - {$req->descricao}",
                    'cliente_motorista' => $req->motorista_nome ?? 'N/I',
                    'valor' => floatval($req->valor ?? 0),
                    'status' => $this->formatarStatusRequisicao($req->status)
                ];
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar requisições: ' . $e->getMessage());
        }
        
        return $movimentos;
    }

    private function getJustificativas(): array
    {
        $movimentos = [];
        
        try {
            if (!class_exists('\App\Models\CaixaJustificativa')) {
                return [];
            }
            
            $justificativas = CaixaJustificativa::where('tenant_id', $this->tenantId)
                ->where('tipo', 'devolucao')
                ->whereBetween('created_at', [
                    $this->dataInicio . ' 00:00:00', 
                    $this->dataFim . ' 23:59:59'
                ])
                ->orderBy('created_at', 'desc')
                ->get();
                
            foreach ($justificativas as $just) {
                $movimentos[] = [
                    'tipo' => 'RECEITA',
                    'categoria' => 'Devolução Caixa',
                    'data' => $just->created_at->format('d/m/Y'),
                    'descricao' => "Devolução - " . ($just->observacoes ?: 'N/I'),
                    'cliente_motorista' => $just->motorista_nome ?? 'N/I',
                    'valor' => floatval($just->valor_devolvido ?? 0),
                    'status' => 'Devolvido'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar justificativas: ' . $e->getMessage());
        }
        
        return $movimentos;
    }

    public function headings(): array
    {
        $titulo = match($this->tipo) {
            'geral' => 'RELATÓRIO FINANCEIRO COMPLETO (AGRUPADO POR CATEGORIA)',
            'ordens' => 'RELATÓRIO DE ORDENS DE FATURAÇÃO',
            'debito' => 'RELATÓRIO DE NOTAS DE DÉBITO',
            'credito' => 'RELATÓRIO DE NOTAS DE CRÉDITO',
            'despesas' => 'RELATÓRIO DE DESPESAS DE MOTORISTAS (COM MOEDAS)',
            default => 'RELATÓRIO FINANCEIRO'
        };
        
        $headings = [
            [$this->empresaNome],
            [$titulo],
            ['Período: ' . date('d/m/Y', strtotime($this->dataInicio)) . ' a ' . date('d/m/Y', strtotime($this->dataFim))],
            ['Exportado em: ' . date('d/m/Y H:i:s')],
            []
        ];
        
        // Adicionar cabeçalho da tabela baseado no tipo
        if ($this->tipo === 'despesas') {
            $headings[] = ['Tipo', 'Categoria', 'Data', 'Descrição', 'Motorista', 'Valor (MZN)', 'Moeda Orig.', 'Valor Orig.', 'Status'];
        } else {
            $headings[] = ['Tipo', 'Categoria', 'Data', 'Descrição', 'Cliente/Motorista', 'Valor (MZN)', 'Status'];
        }
        
        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        $ultimaLinha = $sheet->getHighestRow();
        
        $sheet->getRowDimension(1)->setRowHeight(50);
        
        // Determinar número de colunas baseado no tipo
        $colunas = $this->totalColunas();
        $ultimaColuna = chr(64 + $colunas); // A=1, B=2, etc.
        
        // Mesclar cabeçalho
        $sheet->mergeCells('A1:' . $ultimaColuna . '1');
        $sheet->mergeCells('A2:' . $ultimaColuna . '2');
        $sheet->mergeCells('A3:' . $ultimaColuna . '3');
        $sheet->mergeCells('A4:' . $ultimaColuna . '4');
        
        // Estilo do título
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '013334']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        
        // Inserir logo
        if ($this->logoData && $this->logoMime) {
            try {
                $tempPath = sys_get_temp_dir() . '/logo_' . uniqid() . '_' . time() . '.png';
                file_put_contents($tempPath, $this->logoData);
                
                if (file_exists($tempPath) && filesize($tempPath) > 0) {
                    $drawing = new Drawing();
                    $drawing->setName('Logo');
                    $drawing->setPath($tempPath);
                    $drawing->setHeight(45);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(3);
                    $drawing->setWorksheet($sheet);
                }
            } catch (\Exception $e) {
                Log::error('FinanceiroExport: Erro ao inserir logo', ['message' => $e->getMessage()]);
            }
        }
        
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0aca7d']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        
        // Cabeçalho da tabela (linha 6)
        $sheet->getStyle('A6:' . $ultimaColuna . '6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '013334']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        if ($ultimaLinha >= 7) {
            // Bordas
            $sheet->getStyle('A6:' . $ultimaColuna . $ultimaLinha)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ]);
            
            // Calcular totais
            $totalReceitas = 0;
            $totalDespesas = 0;
            
            // Linhas alternadas e cores
            for ($i = 7; $i <= $ultimaLinha; $i++) {
                $tipo = $sheet->getCell('A' . $i)->getValue();
                
                // Pular linhas separadoras
                if (strpos($tipo, '═══') !== false || empty($tipo)) {
                    $sheet->getStyle('A' . $i . ':' . $ultimaColuna . $i)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('E8E8E8');
                    $sheet->getStyle('A' . $i . ':' . $ultimaColuna . $i)->getFont()->setBold(true);
                    continue;
                }
                
                $corFundo = ($i % 2 === 0) ? 'FFFFFF' : 'F5F5F5';
                $sheet->getStyle('A' . $i . ':' . $ultimaColuna . $i)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB($corFundo);
                
                // Formatar valor (coluna F)
                $sheet->getStyle('F' . $i)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
                
                // Cores por tipo
                $valor = floatval($sheet->getCell('F' . $i)->getCalculatedValue());
                
                if ($tipo === 'RECEITA') {
                    $sheet->getStyle('A' . $i)->getFont()->getColor()->setRGB('00B050');
                    $totalReceitas += $valor;
                } elseif ($tipo === 'DESPESA') {
                    $sheet->getStyle('A' . $i)->getFont()->getColor()->setRGB('FF0000');
                    $totalDespesas += $valor;
                }
            }
            
            // Totais
            $linhaTotal = $ultimaLinha + 2;
            $sheet->setCellValue('E' . $linhaTotal, 'TOTAL RECEITAS:');
            $sheet->setCellValue('F' . $linhaTotal, $totalReceitas);
            $sheet->getStyle('F' . $linhaTotal)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('F' . $linhaTotal)->getFont()->getColor()->setRGB('00B050');
            
            $linhaTotal2 = $ultimaLinha + 3;
            $sheet->setCellValue('E' . $linhaTotal2, 'TOTAL DESPESAS:');
            $sheet->setCellValue('F' . $linhaTotal2, $totalDespesas);
            $sheet->getStyle('F' . $linhaTotal2)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('F' . $linhaTotal2)->getFont()->getColor()->setRGB('FF0000');
            
            $linhaTotal3 = $ultimaLinha + 4;
            $saldo = $totalReceitas - $totalDespesas;
            $sheet->setCellValue('E' . $linhaTotal3, 'SALDO:');
            $sheet->setCellValue('F' . $linhaTotal3, $saldo);
            $sheet->getStyle('F' . $linhaTotal3)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('F' . $linhaTotal3)->getFont()->getColor()->setRGB($saldo >= 0 ? '00B050' : 'FF0000');
            
            $sheet->getStyle('E' . $linhaTotal . ':F' . $linhaTotal3)->getFont()->setBold(true);
            $sheet->getStyle('E' . $linhaTotal . ':E' . $linhaTotal3)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        
        // Largura das colunas
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(50);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(18);
        
        if ($this->tipo === 'despesas') {
            $sheet->getColumnDimension('G')->setWidth(12);
            $sheet->getColumnDimension('H')->setWidth(15);
            $sheet->getColumnDimension('I')->setWidth(15);
        } else {
            $sheet->getColumnDimension('G')->setWidth(15);
        }
        
        return [];
    }

    private function formatarStatusOrdem($status)
    {
        $map = [
            'pendente' => 'Pendente',
            'processado' => 'Processado',
            'cancelado' => 'Cancelado'
        ];
        return $map[$status] ?? $status;
    }

    private function formatarStatusDespesa($status)
    {
        $map = [
            'pending' => 'Pendente',
            'approved' => 'Aprovado',
            'paid' => 'Pago',
            'settled' => 'Quitado',
            'cancelled' => 'Cancelado'
        ];
        return $map[$status] ?? $status;
    }

    private function formatarStatusRequisicao($status)
    {
        $map = [
            'pendente' => 'Pendente',
            'aprovado' => 'Aprovado',
            'pago' => 'Pago',
            'rejeitado' => 'Rejeitado'
        ];
        return $map[$status] ?? $status;
    }
}