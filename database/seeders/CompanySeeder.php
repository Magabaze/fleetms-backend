<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company; // Vamos usar Company em vez de Tenant
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // Limpar tabelas relacionadas
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('tenants')->truncate();
        DB::table('domains')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        echo "Criando empresas de exemplo...\n";
        
        // Criar empresa 1
        $company1 = Company::createWithDomain([
            'id' => 'translog-express',
            'name' => 'TransLog Express Transportes',
            'email' => 'contato@translogexpress.com',
        ], 'translog.localhost');
        
        echo "- {$company1->name} ({$company1->id}) criada\n";
        
        // Criar empresa 2
        $company2 = Company::createWithDomain([
            'id' => 'rapid-fleet',
            'name' => 'Rapid Fleet Solutions',
            'email' => 'info@rapidfleet.com',
        ], 'rapid.localhost');
        
        echo "- {$company2->name} ({$company2->id}) criada\n";
        
        // Criar empresa 3 (sem email)
        $company3 = Company::createWithDomain([
            'id' => 'logistica-total',
            'name' => 'Logística Total',
        ], 'logistica.localhost');
        
        echo "- {$company3->name} ({$company3->id}) criada\n";
        
        echo "Total: 3 empresas criadas com seus domínios.\n";
    }
}
