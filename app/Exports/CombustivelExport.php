<?php
// app/Exports/CombustivelExport.php

namespace App\Exports;

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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Facades\Log;

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
        
        // Carregar dados da empresa
        $this->carregarEmpresaPorTenant();
    }

    protected function totalColunas(): int
    {
        return 10;
    }

    public function title(): string
    {
        return 'Combustível';
    }

    public function collection()
    {
        $query = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto'])
            ->whereBetween('data_abastecimento', [
                $this->dataInicio,
                $this->dataFim,
            ])
            ->orderBy('data_abastecimento', 'desc');

        if ($this->tipo !== 'todos') {
            $query->where('tipo', $this->tipo);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            [$this->empresaNome],
            ['RELATÓRIO DE COMBUSTÍVEL'],
            ['Período: ' . date('d/m/Y', strtotime($this->dataInicio)) . ' a ' . date('d/m/Y', strtotime($this->dataFim))],
            ['Exportado em: ' . date('d/m/Y H:i:s')],
            [],
            ['ID', 'Data', 'Camião', 'Motorista', 'Posto', 'Tipo', 'Litros', 'Preço/L', 'Total (MZN)', 'Status']
        ];
    }

    public function map($a): array
    {
        return [
            $a->id,
            $a->data_abastecimento ? date('d/m/Y', strtotime($a->data_abastecimento)) : 'N/I',
            $a->veiculo->matricula ?? $a->veiculo_matricula ?? 'N/I',
            $a->motorista->nome_completo ?? $a->motorista_nome ?? 'N/I',
            $a->posto->nome ?? $a->posto_nome ?? 'N/I',
            $this->getTipoTexto($a->tipo_combustivel ?? $a->tipo ?? 'N/I'),
            number_format($a->quantidade ?? 0, 2, ',', '.'),
            number_format($a->preco_unitario ?? 0, 2, ',', '.'),
            number_format($a->valor_total ?? 0, 2, ',', '.'),
            $this->getStatusTexto($a->status ?? 'N/I'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $ultimaLinha = $sheet->getHighestRow();
        
        // Altura da linha 1
        $sheet->getRowDimension(1)->setRowHeight(50);
        
        // Mesclar células do cabeçalho (A até J = 10 colunas)
        $sheet->mergeCells('A1:J1');
        $sheet->mergeCells('A2:J2');
        $sheet->mergeCells('A3:J3');
        $sheet->mergeCells('A4:J4');
        
        // Estilo do título principal
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 18,
                'color' => ['rgb' => '013334']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ]
        ]);
        
        // INSERIR LOGO SE TIVER DADOS
        if ($this->logoData && $this->logoMime) {
            try {
                $tempPath = sys_get_temp_dir() . '/logo_' . uniqid() . '_' . time() . '.png';
                file_put_contents($tempPath, $this->logoData);
                
                if (file_exists($tempPath) && filesize($tempPath) > 0) {
                    $drawing = new Drawing();
                    $drawing->setName('Logo');
                    $drawing->setDescription('Logo da Empresa');
                    $drawing->setPath($tempPath);
                    $drawing->setHeight(45);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(3);
                    $drawing->setWorksheet($sheet);
                }
            } catch (\Exception $e) {
                Log::error('CombustivelExport: Erro ao inserir logo', [
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        // Estilo do subtítulo
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '0aca7d']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);
        
        // Estilo do período
        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 11
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);
        
        // Cabeçalho da tabela (linha 6)
        $sheet->getStyle('A6:J6')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '013334']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFFFFF']
                ]
            ]
        ]);
        
        // Bordas para toda a tabela
        $sheet->getStyle('A6:J' . $ultimaLinha)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);
        
        // Linhas alternadas
        for ($i = 7; $i <= $ultimaLinha; $i++) {
            $corFundo = ($i % 2 === 0) ? 'FFFFFF' : 'F5F5F5';
            $sheet->getStyle('A' . $i . ':J' . $i)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB($corFundo);
            
            // Formatar colunas de valores
            $sheet->getStyle('G' . $i . ':H' . $i)
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            
            $sheet->getStyle('I' . $i)
                ->getNumberFormat()
                ->setFormatCode('#,##0.00 "MZN"');
        }
        
        // Totais no final
        $linhaTotal = $ultimaLinha + 2;
        $sheet->setCellValue('F' . $linhaTotal, 'TOTAL ABASTECIMENTOS:');
        $sheet->setCellValue('G' . $linhaTotal, '=COUNTA(A7:A' . $ultimaLinha . ')');
        $sheet->getStyle('F' . $linhaTotal . ':G' . $linhaTotal)->getFont()->setBold(true);
        
        return [];
    }

    private function getTipoTexto($tipo)
    {
        $map = [
            'diesel_s10' => 'Diesel S10',
            'diesel_s500' => 'Diesel S500',
            'diesel_s50' => 'Diesel S50',
            'gasolina_95' => 'Gasolina 95',
            'gasolina_98' => 'Gasolina 98',
            'interno' => 'Interno',
            'externo' => 'Externo'
        ];
        return $map[$tipo] ?? $tipo;
    }

    private function getStatusTexto($status)
    {
        $map = [
            'pendente' => 'Pendente',
            'aprovado' => 'Aprovado',
            'realizado' => 'Realizado',
            'cancelado' => 'Cancelado',
            'pago' => 'Pago'
        ];
        return $map[$status] ?? $status;
    }
}