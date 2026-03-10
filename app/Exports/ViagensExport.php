<?php
// app/Exports/ViagensExport.php

namespace App\Exports;

use App\Models\Viagem;
use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ViagensExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    protected $dataInicio;
    protected $dataFim;
    protected $tipo;
    protected $empresaNome = 'ABDAGO - LOGÍSTICA E TRANSPORTES';
    protected $logoData = null;
    protected $logoMime = null;
    
    public function __construct($dataInicio, $dataFim, $tipo)
    {
        $this->dataInicio = $dataInicio;
        $this->dataFim = $dataFim;
        $this->tipo = $tipo;
        
        Log::info('🔍 ViagensExport: Inicializando');
        $this->carregarDadosEmpresa();
    }
    
    private function carregarDadosEmpresa()
    {
        try {
            if (!Auth::check()) {
                Log::warning('🔍 ViagensExport: Usuário não autenticado');
                return;
            }

            $tenantId = Auth::user()->tenant_id;
            Log::info('🔍 ViagensExport: Tenant ID', ['tenant_id' => $tenantId]);
            
            if (!$tenantId) {
                Log::warning('🔍 ViagensExport: Tenant ID não encontrado');
                return;
            }

            $empresa = Empresa::where('tenant_id', $tenantId)->first();
            
            if (!$empresa) {
                Log::warning('🔍 ViagensExport: Empresa não encontrada para tenant', ['tenant_id' => $tenantId]);
                return;
            }

            Log::info('🔍 ViagensExport: Empresa encontrada', [
                'nome' => $empresa->nome,
                'logo_url' => $empresa->logo_url
            ]);

            if ($empresa->nome) {
                $this->empresaNome = strtoupper($empresa->nome);
            }
            
            // Carregar logo se existir
            if ($empresa->logo_url) {
                $this->carregarLogo($empresa->logo_url);
            } else {
                Log::warning('🔍 ViagensExport: Empresa sem logo_url');
            }

        } catch (\Exception $e) {
            Log::error('🔍 ViagensExport: Erro ao buscar empresa', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    private function carregarLogo($logoUrl)
    {
        try {
            Log::info('🔍 ViagensExport: Tentando carregar logo', ['url' => $logoUrl]);
            
            // Tentar via Storage R2
            if (Storage::disk('r2')->exists($logoUrl)) {
                Log::info('🔍 ViagensExport: Logo encontrado no R2');
                
                $this->logoData = Storage::disk('r2')->get($logoUrl);
                $this->logoMime = Storage::disk('r2')->mimeType($logoUrl);
                
                Log::info('🔍 ViagensExport: Logo carregado do R2', [
                    'tamanho' => strlen($this->logoData),
                    'mime' => $this->logoMime
                ]);
                return;
            }
            
            Log::warning('🔍 ViagensExport: Logo não encontrado no R2');
            
            // Fallback para URL pública
            $publicDomain = rtrim(env('R2_PUBLIC_DOMAIN', ''), '/');
            if ($publicDomain) {
                $urlPublica = $publicDomain . '/' . ltrim($logoUrl, '/');
                Log::info('🔍 ViagensExport: Tentando URL pública', ['url' => $urlPublica]);
                
                $ch = curl_init($urlPublica);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                
                $this->logoData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $this->logoData) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $this->logoMime = finfo_buffer($finfo, $this->logoData);
                    finfo_close($finfo);
                    
                    Log::info('🔍 ViagensExport: Logo carregado via URL pública', [
                        'tamanho' => strlen($this->logoData),
                        'mime' => $this->logoMime,
                        'http_code' => $httpCode
                    ]);
                    return;
                } else {
                    Log::warning('🔍 ViagensExport: Falha na URL pública', ['http_code' => $httpCode]);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('🔍 ViagensExport: Erro ao carregar logo', [
                'message' => $e->getMessage()
            ]);
        }
        
        $this->logoData = null;
        $this->logoMime = null;
    }
    
    public function title(): string
    {
        return 'Relatório de Viagens';
    }
    
    public function collection()
    {
        Log::info('🔍 ViagensExport: Buscando viagens', [
            'data_inicio' => $this->dataInicio,
            'data_fim' => $this->dataFim,
            'tipo' => $this->tipo
        ]);
        
        $query = Viagem::whereBetween('schedule_date', [$this->dataInicio, $this->dataFim])
            ->orderBy('schedule_date', 'desc');
        
        if ($this->tipo !== 'todas') {
            $statusMap = [
                'concluida' => 'CLOSED',
                'andamento' => 'RUNNING',
                'cancelada' => 'CANCELLED',
                'pendente' => 'PENDING'
            ];
            
            $statusBanco = $statusMap[$this->tipo] ?? $this->tipo;
            $query->where('status', $statusBanco);
        }
        
        $resultados = $query->get();
        Log::info('🔍 ViagensExport: Viagens encontradas', ['total' => $resultados->count()]);
        
        return $resultados;
    }
    
    public function headings(): array
    {
        Log::info('🔍 ViagensExport: Gerando headings');
        return [
            [$this->empresaNome],
            ['RELATÓRIO DE VIAGENS'],
            ['Período: ' . date('d/m/Y', strtotime($this->dataInicio)) . ' a ' . date('d/m/Y', strtotime($this->dataFim))],
            ['Exportado em: ' . date('d/m/Y H:i:s')],
            [],
            [
                'Nº Viagem',
                'Data',
                'Motorista',
                'Camião',
                'Trela',
                'Cliente',
                'Origem',
                'Destino',
                'Container',
                'Commodity',
                'Peso (kg)',
                'Status',
                'Criado por'
            ]
        ];
    }
    
    public function map($viagem): array
    {
        return [
            $viagem->trip_number ?? 'N/I',
            $viagem->schedule_date ? date('d/m/Y', strtotime($viagem->schedule_date)) : 'N/I',
            $viagem->driver ?? 'N/I',
            $viagem->truck_number ?? 'N/I',
            $viagem->trailer_number ?? 'N/I',
            $viagem->customer_name ?? 'N/I',
            $viagem->from_station ?? 'N/I',
            $viagem->to_station ?? 'N/I',
            $viagem->container_no ?? 'N/I',
            $viagem->commodity ?? 'N/I',
            $viagem->weight ?? '0',
            $this->getStatusText($viagem->status),
            $viagem->created_by ?? 'Sistema'
        ];
    }
    
    public function styles(Worksheet $sheet)
    {
        Log::info('🔍 ViagensExport: Aplicando estilos');
        $ultimaLinha = $sheet->getHighestRow();
        
        // Altura da linha 1
        $sheet->getRowDimension(1)->setRowHeight(50);
        
        // Mesclar células do cabeçalho
        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('A2:M2');
        $sheet->mergeCells('A3:M3');
        $sheet->mergeCells('A4:M4');
        
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
                Log::info('🔍 ViagensExport: Tentando inserir logo no Excel');
                
                // Criar arquivo temporário (única maneira confiável no PhpSpreadsheet)
                $tempPath = sys_get_temp_dir() . '/logo_' . uniqid() . '_' . time() . '.png';
                
                // Salvar dados da imagem
                file_put_contents($tempPath, $this->logoData);
                
                Log::info('🔍 ViagensExport: Arquivo temporário criado', [
                    'path' => $tempPath,
                    'tamanho' => filesize($tempPath),
                    'existe' => file_exists($tempPath) ? 'sim' : 'não'
                ]);
                
                // Verificar se o arquivo foi criado
                if (file_exists($tempPath) && filesize($tempPath) > 0) {
                    // Inserir no Excel
                    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                    $drawing->setName('Logo');
                    $drawing->setDescription('Logo da Empresa');
                    $drawing->setPath($tempPath);
                    $drawing->setHeight(45);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(3);
                    $drawing->setWorksheet($sheet);
                    
                    Log::info('✅ LOGO INSERIDO COM SUCESSO!');
                    
                    // NÃO DELETAR AQUI - O PHP VAI DELETAR AUTOMATICAMENTE NO FIM DA REQUISIÇÃO
                } else {
                    Log::error('❌ Arquivo temporário inválido', [
                        'path' => $tempPath,
                        'tamanho' => filesize($tempPath),
                        'existe' => file_exists($tempPath) ? 'sim' : 'não'
                    ]);
                }
                
            } catch (\Exception $e) {
                Log::error('❌ Erro ao inserir logo', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::warning('🔍 ViagensExport: Sem dados de logo para inserir', [
                'logoData' => $this->logoData ? 'presente' : 'ausente',
                'logoMime' => $this->logoMime ? 'presente' : 'ausente'
            ]);
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
        
        // Cabeçalho da tabela (linha 6)
        $sheet->getStyle('A6:M6')->applyFromArray([
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
        $sheet->getStyle('A6:M' . $ultimaLinha)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);
        
        // Cores para status
        for ($i = 7; $i <= $ultimaLinha; $i++) {
            $status = $sheet->getCell('L' . $i)->getValue();
            $cor = $this->getStatusColor($status);
            if ($cor) {
                $sheet->getStyle('L' . $i)->getFont()->getColor()->setARGB($cor);
            }
        }
        
        // Totais no final
        $linhaTotal = $ultimaLinha + 2;
        $sheet->setCellValue('K' . $linhaTotal, 'TOTAL VIAGENS:');
        $sheet->setCellValue('L' . $linhaTotal, '=COUNTA(A7:A' . $ultimaLinha . ')');
        $sheet->getStyle('K' . $linhaTotal . ':L' . $linhaTotal)->getFont()->setBold(true);
        
        return [];
    }
    
    private function getStatusText($status)
    {
        $map = [
            'PENDING' => 'Pendente',
            'RUNNING' => 'Em Andamento',
            'COMPLETED' => 'Concluída',
            'CLOSED' => 'Fechada',
            'CANCELLED' => 'Cancelada',
            'SCHEDULED' => 'Agendada'
        ];
        return $map[$status] ?? $status;
    }
    
    private function getStatusColor($status)
    {
        $map = [
            'Pendente' => 'FF808080',
            'Em Andamento' => 'FFFFC000',
            'Concluída' => 'FF00B050',
            'Fechada' => 'FF00B050',
            'Cancelada' => 'FFFF0000',
            'Agendada' => 'FF0000FF'
        ];
        return $map[$status] ?? null;
    }
}