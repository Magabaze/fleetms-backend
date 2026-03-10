<?php
// app/Exports/ManutencaoExport.php

namespace App\Exports;

use App\Models\Manutencao\OrdemTrabalho;
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

class ManutencaoExport extends BaseExport implements
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
        return 9;
    }

    public function title(): string
    {
        return 'Manutenção';
    }

    public function collection()
    {
        $query = OrdemTrabalho::with('camiao')
            ->whereBetween('created_at', [
                $this->dataInicio . ' 00:00:00',
                $this->dataFim    . ' 23:59:59',
            ])
            ->orderBy('created_at', 'desc');

        if ($this->tipo !== 'todos' && in_array($this->tipo, ['preventiva', 'corretiva', 'inspecao'])) {
            $query->where('tipo', $this->tipo);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            [$this->empresaNome],
            ['RELATÓRIO DE MANUTENÇÃO'],
            ['Período: ' . date('d/m/Y', strtotime($this->dataInicio)) . ' a ' . date('d/m/Y', strtotime($this->dataFim))],
            ['Exportado em: ' . date('d/m/Y H:i:s')],
            [],
            ['ID', 'Nº Ordem', 'Camião', 'Tipo', 'Descrição', 'Status', 'Custo (MZN)', 'Aberto em', 'Concluído em']
        ];
    }

    public function map($o): array
    {
        return [
            $o->id,
            $o->codigo ?? 'N/I',
            $o->camiao->matricula ?? $o->matricula ?? 'N/I',
            $this->getTipoTexto($o->tipo),
            $o->descricao ?? 'N/I',
            $this->getStatusTexto($o->status),
            number_format($o->custo_total ?? 0, 2, ',', '.'),
            $o->created_at ? $o->created_at->format('d/m/Y H:i') : 'N/I',
            $o->concluido_em ?? 'N/I',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $ultimaLinha = $sheet->getHighestRow();
        
        // Altura da linha 1
        $sheet->getRowDimension(1)->setRowHeight(50);
        
        // Mesclar células do cabeçalho (A até I = 9 colunas)
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->mergeCells('A3:I3');
        $sheet->mergeCells('A4:I4');
        
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
                Log::error('ManutencaoExport: Erro ao inserir logo', [
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
        $sheet->getStyle('A6:I6')->applyFromArray([
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
        $sheet->getStyle('A6:I' . $ultimaLinha)->applyFromArray([
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
            $sheet->getStyle('A' . $i . ':I' . $i)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB($corFundo);
            
            // Formatar coluna de custo
            $sheet->getStyle('G' . $i)
                ->getNumberFormat()
                ->setFormatCode('#,##0.00 "MZN"');
        }
        
        // Totais no final
        $linhaTotal = $ultimaLinha + 2;
        $sheet->setCellValue('F' . $linhaTotal, 'TOTAL ORDENS:');
        $sheet->setCellValue('G' . $linhaTotal, '=COUNTA(A7:A' . $ultimaLinha . ')');
        $sheet->getStyle('F' . $linhaTotal . ':G' . $linhaTotal)->getFont()->setBold(true);
        
        return [];
    }

    private function getTipoTexto($tipo)
    {
        $map = [
            'preventiva' => 'Preventiva',
            'corretiva' => 'Corretiva',
            'inspecao' => 'Inspeção'
        ];
        return $map[$tipo] ?? $tipo;
    }

    private function getStatusTexto($status)
    {
        $map = [
            'pendente' => 'Pendente',
            'em_progresso' => 'Em Progresso',
            'concluida' => 'Concluída',
            'cancelada' => 'Cancelada'
        ];
        return $map[$status] ?? $status;
    }
}