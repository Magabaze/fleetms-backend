<?php
// app/Exports/FrotaExport.php

namespace App\Exports;

use App\Models\Camiao;
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

class FrotaExport extends BaseExport implements
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
        return 'Frota';
    }

    public function collection()
    {
        $query = Camiao::query()
            ->orderBy('matricula');

        if ($this->tipo === 'disponivel') {
            $query->where('estado', 'Operacional');
        } elseif ($this->tipo === 'manutencao') {
            $query->where('estado', 'Manutenção');
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            [$this->empresaNome],
            ['RELATÓRIO DA FROTA'],
            ['Período: ' . date('d/m/Y', strtotime($this->dataInicio)) . ' a ' . date('d/m/Y', strtotime($this->dataFim))],
            ['Exportado em: ' . date('d/m/Y H:i:s')],
            [],
            ['ID', 'Matrícula', 'Marca', 'Modelo', 'Ano', 'Capacidade', 'Tipo Combustível', 'Estado', 'Última Atualização']
        ];
    }

    public function map($c): array
    {
        return [
            $c->id,
            $c->matricula ?? 'N/I',
            $c->marca ?? 'N/I',
            $c->modelo ?? 'N/I',
            $c->ano_fabricacao ?? 'N/I',
            $c->capacidade_carga ?? '0',
            $c->tipo_combustivel ?? 'N/I',
            $c->estado ?? 'N/I',
            $c->updated_at ? $c->updated_at->format('d/m/Y H:i') : 'N/I',
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
                Log::error('FrotaExport: Erro ao inserir logo', [
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
        }
        
        // Totais no final
        $linhaTotal = $ultimaLinha + 2;
        $sheet->setCellValue('H' . $linhaTotal, 'TOTAL CAMIÕES:');
        $sheet->setCellValue('I' . $linhaTotal, '=COUNTA(A7:A' . $ultimaLinha . ')');
        $sheet->getStyle('H' . $linhaTotal . ':I' . $linhaTotal)->getFont()->setBold(true);
        
        return [];
    }
}