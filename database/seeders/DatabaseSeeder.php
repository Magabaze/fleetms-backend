<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Apenas seeders essenciais para produção
        $this->call([
            // Seeders básicos (se necessário)
        ]);
        
        $this->command->info('✅ Banco de dados pronto para produção.');
    }
}