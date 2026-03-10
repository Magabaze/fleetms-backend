<?php
// app/Exports/FinanceiroExport.php

namespace App\Exports;

use App\Models\Viagem;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Facades\Log;

class FinanceiroExport extends BaseExport implements
    FromArray,
    WithHeadings,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    protected $dataInicio;
    protected $dataFim;

    public function __construct(string $dataInicio, string $dataFim)
    {
        $this->dataInicio = $dataInicio;
        $this->dataFim    = $dataFim;
        
        // Carregar dados da empresa
        $this->carregarEmpresaPorTenant();
    }

    protected function totalColunas(): int
    {
        return 6;
    }

    public function title(): string
    {
        return 'Financeiro';
    }

    public function array(): array
    {
        // Receitas (viagens concluídas)
        $receitas = Viagem::where('status', 'CLOSED')
            ->whereBetween('created_at', [$this->dataInicio . ' 00:00:00', $this->dataFim . ' 23:59:59'])
            ->get()
            ->map(function($v) {
                return [
                    'tipo' => 'RECEITA',
                    'data' => $v->created_at->format('d/m/Y'),
                    'descricao' => "Viagem {$v->trip_number} - {$v->from_station} → {$v->to_station}",
                    'cliente' => $v->customer_name ?? 'N/I',
                    'valor' => $v->valor ?? 0,
                    'categoria' => 'Viagem'
                ];
            })->toArray();

        // Aqui você pode adicionar despesas se tiver o modelo
        $despesas = [];

        $movimentos = array_merge($receitas, $despesas);
        usort($movimentos, fn($a, $b) => strtotime($b['data']) - strtotime($a['data']));

        return $movimentos;
    }

    public function headings(): array
    {
        return [
            [$this->empresaNome],
            ['RELATÓRIO FINANCEIRO'],
            ['Período: ' . date('d/m/Y', strtotime($this->dataInicio)) . ' a ' . date('d/m/Y', strtotime($this->dataFim))],
            ['Exportado em: ' . date('d/m/Y H:i:s')],
            [],
            ['Tipo', 'Data', 'Descrição', 'Cliente', 'Valor (MZN)', 'Categoria']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $ultimaLinha = $sheet->getHighestRow();
        
        // Altura da linha 1
        $sheet->getRowDimension(1)->setRowHeight(50);
        
        // Mesclar células do cabeçalho (A até F = 6 colunas)
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');
        $sheet->mergeCells('A3:F3');
        $sheet->mergeCells('A4:F4');
        
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
                Log::error('FinanceiroExport: Erro ao inserir logo', [
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
        $sheet->getStyle('A6:F6')->applyFromArray([
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
        $sheet->getStyle('A6:F' . $ultimaLinha)->applyFromArray([
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
            $sheet->getStyle('A' . $i . ':F' . $i)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB($corFundo);
            
            // Formatar coluna de valor
            $sheet->getStyle('E' . $i)
                ->getNumberFormat()
                ->setFormatCode('#,##0.00 "MZN"');
            
            // Cores para receitas/despesas
            $tipo = $sheet->getCell('A' . $i)->getValue();
            if ($tipo === 'RECEITA') {
                $sheet->getStyle('A' . $i)->getFont()->getColor()->setARGB('FF00B050');
            } else {
                $sheet->getStyle('A' . $i)->getFont()->getColor()->setARGB('FFFF0000');
            }
        }
        
        // Totais no final
        $totalReceitas = 0;
        $totalDespesas = 0;
        
        for ($i = 7; $i <= $ultimaLinha; $i++) {
            $tipo = $sheet->getCell('A' . $i)->getValue();
            $valor = floatval(str_replace(['.', ','], ['', '.'], $sheet->getCell('E' . $i)->getValue()));
            
            if ($tipo === 'RECEITA') {
                $totalReceitas += $valor;
            } else {
                $totalDespesas += $valor;
            }
        }
        
        $linhaTotal = $ultimaLinha + 2;
        $sheet->setCellValue('D' . $linhaTotal, 'TOTAL RECEITAS:');
        $sheet->setCellValue('E' . $linhaTotal, number_format($totalReceitas, 2, ',', '.'));
        
        $linhaTotal2 = $ultimaLinha + 3;
        $sheet->setCellValue('D' . $linhaTotal2, 'TOTAL DESPESAS:');
        $sheet->setCellValue('E' . $linhaTotal2, number_format($totalDespesas, 2, ',', '.'));
        
        $linhaTotal3 = $ultimaLinha + 4;
        $sheet->setCellValue('D' . $linhaTotal3, 'SALDO:');
        $sheet->setCellValue('E' . $linhaTotal3, number_format($totalReceitas - $totalDespesas, 2, ',', '.'));
        
        $sheet->getStyle('D' . $linhaTotal . ':E' . $linhaTotal3)->getFont()->setBold(true);
        
        return [];
    }
}