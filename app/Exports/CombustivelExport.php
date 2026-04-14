<?php

namespace App\Exports;

use App\Models\Combustivel\AbastecimentoInterno;
use App\Models\Combustivel\AbastecimentoExterno;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class CombustivelExport extends BaseExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    protected $dataInicio;
    protected $dataFim;
    protected $tipo;

    public function __construct(string $dataInicio, string $dataFim, string $tipo)
    {
        $this->dataInicio = $dataInicio;
        $this->dataFim    = $dataFim;
        $this->tipo       = $tipo;
        
        $this->carregarEmpresaPorTenant();
    }

    protected function totalColunas(): int
    {
        return 11;
    }

    public function title(): string
    {
        $titulos = [
            'interno' => 'Abastecimentos Internos',
            'externo' => 'Abastecimentos Externos',
            'todos' => 'Todos Abastecimentos'
        ];
        return $titulos[$this->tipo] ?? 'Combustível';
    }

    public function collection()
    {
        $dados = collect();
        
        // Buscar internos se for 'interno' ou 'todos'
        if ($this->tipo === 'interno' || $this->tipo === 'todos') {
            $internos = AbastecimentoInterno::with(['camiao', 'motorista', 'tanque'])
                ->whereBetween('data_abastecimento', [$this->dataInicio, $this->dataFim])
                ->get()
                ->map(function($item) {
                    $item->origem = 'Interno';
                    return $item;
                });
            $dados = $dados->merge($internos);
        }
        
        // Buscar externos se for 'externo' ou 'todos'
        if ($this->tipo === 'externo' || $this->tipo === 'todos') {
            $externos = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto'])
                ->whereBetween('data_abastecimento', [$this->dataInicio, $this->dataFim])
                ->get()
                ->map(function($item) {
                    $item->origem = 'Externo';
                    return $item;
                });
            $dados = $dados->merge($externos);
        }
        
        // Ordenar por data
        return $dados->sortByDesc(function($item) {
            return $item->data_abastecimento ?? $item->created_at;
        })->values();
    }

    public function headings(): array
    {
        return [
            [$this->empresaNome],
            ['RELATÓRIO DE COMBUSTÍVEL - ' . strtoupper($this->title())],
            ['Período: ' . date('d/m/Y', strtotime($this->dataInicio)) . ' a ' . date('d/m/Y', strtotime($this->dataFim))],
            ['Exportado em: ' . date('d/m/Y H:i:s')],
            [],
            ['Origem', 'ID', 'Data', 'Hora', 'Veículo', 'Motorista', 'Posto/Tanque', 'Combustível', 'Litros', 'Preço/L', 'Total', 'Status']
        ];
    }

    public function map($item): array
    {
        // Verificar se é interno ou externo
        $isInterno = $item->origem === 'Interno';
        
        // Dados comuns
        $data = $item->data_abastecimento ? date('d/m/Y', strtotime($item->data_abastecimento)) : 'N/I';
        $hora = $item->hora_abastecimento ?? 'N/I';
        $veiculo = $isInterno 
            ? ($item->camiao->matricula ?? 'N/I')
            : ($item->veiculo->matricula ?? $item->veiculo_matricula ?? 'N/I');
        $motorista = $isInterno
            ? ($item->motorista->nome_completo ?? 'N/I')
            : ($item->motorista->nome_completo ?? $item->motorista->nome ?? $item->motorista_nome ?? 'N/I');
        $postoTanque = $isInterno
            ? ($item->tanque->nome ?? 'N/I')
            : ($item->posto->nome ?? 'N/I');
        $combustivel = $this->formatarCombustivel($item->tipo_combustivel);
        $litros = number_format($item->quantidade ?? 0, 2, ',', '.');
        
        // Dados específicos
        if ($isInterno) {
            $preco = '-';
            $total = '-';
        } else {
            $preco = number_format($item->preco_unitario ?? 0, 2, ',', '.');
            $total = number_format($item->valor_total ?? 0, 2, ',', '.');
        }
        
        $status = $this->formatarStatus($item->status);
        
        return [
            $item->origem,
            $item->id,
            $data,
            $hora,
            $veiculo,
            $motorista,
            $postoTanque,
            $combustivel,
            $litros,
            $preco,
            $total,
            $status,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $ultimaLinha = $sheet->getHighestRow();
        
        $sheet->getRowDimension(1)->setRowHeight(50);
        
        // Mesclar cabeçalho (A até L = 12 colunas)
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');
        $sheet->mergeCells('A3:L3');
        $sheet->mergeCells('A4:L4');
        
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
                Log::error('Erro ao inserir logo: ' . $e->getMessage());
            }
        }
        
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0aca7d']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        
        // Cabeçalho da tabela
        $sheet->getStyle('A6:L6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '013334']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        // Bordas
        $sheet->getStyle('A6:L' . $ultimaLinha)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        // Linhas alternadas
        for ($i = 7; $i <= $ultimaLinha; $i++) {
            $cor = ($i % 2 == 0) ? 'F5F5F5' : 'FFFFFF';
            $sheet->getStyle('A' . $i . ':L' . $i)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($cor);
            
            // Colorir baseado na origem
            $origem = $sheet->getCell('A' . $i)->getValue();
            if ($origem === 'Interno') {
                $sheet->getStyle('A' . $i)->getFont()->getColor()->setRGB('0066CC');
            } else {
                $sheet->getStyle('A' . $i)->getFont()->getColor()->setRGB('CC6600');
            }
        }
        
        // Formatar números
        $sheet->getStyle('I7:K' . $ultimaLinha)->getNumberFormat()
            ->setFormatCode('#,##0.00');
        
        // Totais
        $linhaTotal = $ultimaLinha + 2;
        $sheet->setCellValue('H' . $linhaTotal, 'TOTAL REGISTROS:');
        $sheet->setCellValue('I' . $linhaTotal, '=COUNTA(A7:A' . $ultimaLinha . ')');
        $sheet->setCellValue('J' . $linhaTotal, 'TOTAL LITROS:');
        $sheet->setCellValue('K' . $linhaTotal, '=SUM(I7:I' . $ultimaLinha . ')');
        $sheet->getStyle('H' . $linhaTotal . ':L' . $linhaTotal)->getFont()->setBold(true);
        
        return [];
    }

    private function formatarCombustivel($tipo)
    {
        $map = [
            'diesel_s10' => 'Diesel S10',
            'diesel_s500' => 'Diesel S500',
            'diesel_s50' => 'Diesel S50',
            'gasolina_95' => 'Gasolina 95',
            'gasolina_98' => 'Gasolina 98',
        ];
        return $map[$tipo] ?? $tipo;
    }

    private function formatarStatus($status)
    {
        $map = [
            'pendente' => 'Pendente',
            'aprovado' => 'Aprovado',
            'realizado' => 'Realizado',
            'cancelado' => 'Cancelado',
            'pago' => 'Pago',
            'rejeitado' => 'Rejeitado'
        ];
        return $map[$status] ?? $status;
    }
}