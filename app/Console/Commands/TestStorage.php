<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\FileStorageService;

class TestStorage extends Command
{
    protected $signature = 'storage:test';
    protected $description = 'Testa o sistema de storage completo';
    
    public function handle()
    {
        $this->info('🧪 INICIANDO TESTE COMPLETO DO STORAGE');
        $this->line('');
        
        // Teste 1: Configurações
        $this->info('1. 📋 CONFIGURAÇÕES:');
        $this->table(
            ['Configuração', 'Valor'],
            [
                ['APP_URL', env('APP_URL')],
                ['FILESYSTEM_DISK', config('filesystems.default')],
                ['Storage Path', storage_path('app/public')],
                ['Public Path', public_path('storage')],
                ['Storage URL', config('filesystems.disks.public.url')],
            ]
        );
        
        // Teste 2: Link simbólico
        $this->info('2. 🔗 LINK SIMBÓLICO:');
        $symlink = public_path('storage');
        if (is_link($symlink) || is_dir($symlink)) {
            $this->info('   ✅ Link simbólico existe em: ' . $symlink);
            
            // Testar acesso
            $testFile = 'storage_test_' . time() . '.txt';
            file_put_contents(public_path('storage/' . $testFile), 'Teste de acesso');
            
            if (file_exists(public_path('storage/' . $testFile))) {
                $this->info('   ✅ Pode escrever no storage público');
                unlink(public_path('storage/' . $testFile));
            }
        } else {
            $this->error('   ❌ Link simbólico não existe!');
            $this->info('   Execute: php artisan storage:link');
        }
        
        // Teste 3: Verificar pastas de motoristas
        $this->info('3. 📁 PASTAS EXISTENTES:');
        $motoristasPath = storage_path('app/public/motoristas');
        if (is_dir($motoristasPath)) {
            $this->info('   ✅ Pasta motoristas existe');
            
            // Listar conteúdo
            $items = scandir($motoristasPath);
            $items = array_diff($items, ['.', '..']);
            
            if (count($items) > 0) {
                $this->info('   📂 Conteúdo da pasta motoristas:');
                foreach ($items as $item) {
                    $fullPath = $motoristasPath . '/' . $item;
                    if (is_dir($fullPath)) {
                        $this->line("      📁 $item/");
                    } else {
                        $this->line("      📄 $item");
                    }
                }
            } else {
                $this->info('   ℹ️ Pasta motoristas está vazia');
            }
        } else {
            $this->info('   ℹ️ Pasta motoristas não existe (será criada automaticamente)');
        }
        
        // Teste 4: Testar FileStorageService
        $this->info('4. 🧪 TESTANDO FileStorageService:');
        $storageService = new FileStorageService();
        
        // Criar arquivo de teste
        $testContent = 'Conteúdo de teste ' . date('Y-m-d H:i:s');
        $testPath = 'motoristas/teste/teste.txt';
        
        $saved = Storage::disk('public')->put($testPath, $testContent);
        
        if ($saved) {
            $this->info('   ✅ Arquivo salvo com sucesso');
            
            $exists = Storage::disk('public')->exists($testPath);
            $this->info('   ✅ Arquivo existe no storage: ' . ($exists ? 'SIM' : 'NÃO'));
            
            $url = $storageService->getUrl($testPath);
            $this->info('   ✅ URL gerada: ' . $url);
            
            // Verificar se URL é acessível
            if ($url) {
                $this->info('   🔗 URL completa: ' . $url);
            }
            
            // Limpar
            Storage::disk('public')->delete($testPath);
        } else {
            $this->error('   ❌ Falha ao salvar arquivo de teste');
        }
        
        $this->line('');
        $this->info('🎉 TESTE COMPLETO!');
        $this->line('');
        $this->info('📝 PRÓXIMOS PASSOS:');
        $this->line('   1. Execute este comando: php artisan storage:test');
        $this->line('   2. Crie um motorista pelo frontend');
        $this->line('   3. Verifique os logs: tail -f storage/logs/laravel.log');
        $this->line('   4. Teste a URL da foto diretamente no navegador');
        
        return 0;
    }
}