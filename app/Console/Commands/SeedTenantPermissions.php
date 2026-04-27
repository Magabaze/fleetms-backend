<?php

namespace App\Console\Commands;

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedTenantPermissions extends Command
{
    protected $signature = 'tenants:seed-permissions {tenant_id? : ID específico do tenant (omitir para todos)}';

    protected $description = 'Cria/actualiza as permissões do sistema para um tenant ou para todos os tenants existentes';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        if ($tenantId) {
            $tenantIds = [$tenantId];
        } else {
            $tenantIds = DB::table('roles')
                ->select('tenant_id')
                ->distinct()
                ->whereNotNull('tenant_id')
                ->pluck('tenant_id')
                ->toArray();
        }

        if (empty($tenantIds)) {
            $this->warn('Nenhum tenant encontrado.');
            return;
        }

        $seeder = new PermissionSeeder();
        $total = count($tenantIds);

        $this->info("A processar {$total} tenant(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($tenantIds as $tid) {
            $seeder->run($tid);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Permissões criadas/actualizadas com sucesso para todos os tenants.');
    }
}
