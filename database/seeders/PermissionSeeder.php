<?php
// database/seeders/PermissionSeeder.php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissoes = [
            // ===== DASHBOARD =====
            ['nome' => 'Ver Dashboard', 'chave' => 'dashboard.ver', 'modulo' => 'Dashboard', 'tipo' => 'pagina', 'icone' => 'Home', 'ordem' => 1],
            
            // ===== CADASTRO =====
            ['nome' => 'Ver Cadastro', 'chave' => 'cadastro.ver', 'modulo' => 'Cadastro', 'tipo' => 'pagina', 'icone' => 'Users', 'ordem' => 10],
            ['nome' => 'Ver Clientes', 'chave' => 'clientes.ver', 'modulo' => 'Cadastro', 'tipo' => 'pagina', 'icone' => 'Users', 'ordem' => 11],
            ['nome' => 'Criar Cliente', 'chave' => 'clientes.criar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 12],
            ['nome' => 'Editar Cliente', 'chave' => 'clientes.editar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 13],
            ['nome' => 'Excluir Cliente', 'chave' => 'clientes.excluir', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 14],
            
            ['nome' => 'Ver Agentes', 'chave' => 'agentes.ver', 'modulo' => 'Cadastro', 'tipo' => 'pagina', 'icone' => 'UserCheck', 'ordem' => 15],
            ['nome' => 'Criar Agente', 'chave' => 'agentes.criar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 16],
            ['nome' => 'Editar Agente', 'chave' => 'agentes.editar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 17],
            ['nome' => 'Excluir Agente', 'chave' => 'agentes.excluir', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 18],
            
            ['nome' => 'Ver Cargas', 'chave' => 'cargas.ver', 'modulo' => 'Cadastro', 'tipo' => 'pagina', 'icone' => 'Package', 'ordem' => 19],
            ['nome' => 'Criar Carga', 'chave' => 'cargas.criar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 20],
            ['nome' => 'Editar Carga', 'chave' => 'cargas.editar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 21],
            ['nome' => 'Excluir Carga', 'chave' => 'cargas.excluir', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 22],
            
            ['nome' => 'Ver Distâncias', 'chave' => 'distancias.ver', 'modulo' => 'Cadastro', 'tipo' => 'pagina', 'icone' => 'MapPin', 'ordem' => 23],
            ['nome' => 'Criar Distância', 'chave' => 'distancias.criar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 24],
            ['nome' => 'Editar Distância', 'chave' => 'distancias.editar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 25],
            ['nome' => 'Excluir Distância', 'chave' => 'distancias.excluir', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 26],
            
            ['nome' => 'Ver Rates', 'chave' => 'rates.ver', 'modulo' => 'Cadastro', 'tipo' => 'pagina', 'icone' => 'DollarSign', 'ordem' => 27],
            ['nome' => 'Criar Rate', 'chave' => 'rates.criar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 28],
            ['nome' => 'Editar Rate', 'chave' => 'rates.editar', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 29],
            ['nome' => 'Excluir Rate', 'chave' => 'rates.excluir', 'modulo' => 'Cadastro', 'tipo' => 'botao', 'ordem' => 30],
            
            ['nome' => 'Ver Despesas Motoristas', 'chave' => 'despesas_motoristas.ver', 'modulo' => 'Cadastro', 'tipo' => 'pagina', 'icone' => 'CreditCard', 'ordem' => 31],
            
            // ===== FROTA =====
            ['nome' => 'Ver Frota', 'chave' => 'frota.ver', 'modulo' => 'Frota', 'tipo' => 'pagina', 'icone' => 'Truck', 'ordem' => 40],
            
            ['nome' => 'Ver Camiões', 'chave' => 'camioes.ver', 'modulo' => 'Frota', 'tipo' => 'pagina', 'icone' => 'Truck', 'ordem' => 41],
            ['nome' => 'Criar Camião', 'chave' => 'camioes.criar', 'modulo' => 'Frota', 'tipo' => 'botao', 'ordem' => 42],
            ['nome' => 'Editar Camião', 'chave' => 'camioes.editar', 'modulo' => 'Frota', 'tipo' => 'botao', 'ordem' => 43],
            ['nome' => 'Excluir Camião', 'chave' => 'camioes.excluir', 'modulo' => 'Frota', 'tipo' => 'botao', 'ordem' => 44],
            
            ['nome' => 'Ver Motoristas', 'chave' => 'motoristas.ver', 'modulo' => 'Frota', 'tipo' => 'pagina', 'icone' => 'User', 'ordem' => 45],
            ['nome' => 'Criar Motorista', 'chave' => 'motoristas.criar', 'modulo' => 'Frota', 'tipo' => 'botao', 'ordem' => 46],
            ['nome' => 'Editar Motorista', 'chave' => 'motoristas.editar', 'modulo' => 'Frota', 'tipo' => 'botao', 'ordem' => 47],
            ['nome' => 'Excluir Motorista', 'chave' => 'motoristas.excluir', 'modulo' => 'Frota', 'tipo' => 'botao', 'ordem' => 48],
            
            // ===== OPERAÇÕES =====
            ['nome' => 'Ver Operações', 'chave' => 'operacoes.ver', 'modulo' => 'Operações', 'tipo' => 'pagina', 'icone' => 'ClipboardList', 'ordem' => 50],
            
            ['nome' => 'Ver Ordens', 'chave' => 'ordens.ver', 'modulo' => 'Operações', 'tipo' => 'pagina', 'icone' => 'ClipboardList', 'ordem' => 51],
            ['nome' => 'Criar Ordem', 'chave' => 'ordens.criar', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 52],
            ['nome' => 'Editar Ordem', 'chave' => 'ordens.editar', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 53],
            ['nome' => 'Excluir Ordem', 'chave' => 'ordens.excluir', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 54],
            ['nome' => 'Aprovar Ordem', 'chave' => 'ordens.aprovar', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 55],
            
            ['nome' => 'Ver Viagens', 'chave' => 'viagens.ver', 'modulo' => 'Operações', 'tipo' => 'pagina', 'icone' => 'Route', 'ordem' => 56],
            ['nome' => 'Criar Viagem', 'chave' => 'viagens.criar', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 57],
            ['nome' => 'Editar Viagem', 'chave' => 'viagens.editar', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 58],
            ['nome' => 'Excluir Viagem', 'chave' => 'viagens.excluir', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 59],
            ['nome' => 'Atualizar Status', 'chave' => 'viagens.atualizar_status', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 60],
            ['nome' => 'Fechar Viagem', 'chave' => 'viagens.fechar', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 61],
            ['nome' => 'POD Management', 'chave' => 'viagens.pod', 'modulo' => 'Operações', 'tipo' => 'botao', 'ordem' => 62],
            
            // ===== BÓNUS MOTORISTAS =====
            ['nome' => 'Ver Bónus Motoristas', 'chave' => 'bonus.ver', 'modulo' => 'Bónus Motoristas', 'tipo' => 'pagina', 'icone' => 'Award', 'ordem' => 70],
            ['nome' => 'Ver Master', 'chave' => 'bonus.master.ver', 'modulo' => 'Bónus Motoristas', 'tipo' => 'pagina', 'icone' => 'Settings', 'ordem' => 71],
            ['nome' => 'Criar Regra', 'chave' => 'bonus.regras.criar', 'modulo' => 'Bónus Motoristas', 'tipo' => 'botao', 'ordem' => 72],
            ['nome' => 'Editar Regra', 'chave' => 'bonus.regras.editar', 'modulo' => 'Bónus Motoristas', 'tipo' => 'botao', 'ordem' => 73],
            ['nome' => 'Excluir Regra', 'chave' => 'bonus.regras.excluir', 'modulo' => 'Bónus Motoristas', 'tipo' => 'botao', 'ordem' => 74],
            
            ['nome' => 'Ver Bónus', 'chave' => 'bonus.bonus.ver', 'modulo' => 'Bónus Motoristas', 'tipo' => 'pagina', 'icone' => 'CheckCircle', 'ordem' => 75],
            ['nome' => 'Aprovar Bónus', 'chave' => 'bonus.bonus.aprovar', 'modulo' => 'Bónus Motoristas', 'tipo' => 'botao', 'ordem' => 76],
            ['nome' => 'Rejeitar Bónus', 'chave' => 'bonus.bonus.rejeitar', 'modulo' => 'Bónus Motoristas', 'tipo' => 'botao', 'ordem' => 77],
            
            ['nome' => 'Ver Descontos', 'chave' => 'bonus.descontos.ver', 'modulo' => 'Bónus Motoristas', 'tipo' => 'pagina', 'icone' => 'MinusCircle', 'ordem' => 78],
            ['nome' => 'Criar Desconto', 'chave' => 'bonus.descontos.criar', 'modulo' => 'Bónus Motoristas', 'tipo' => 'botao', 'ordem' => 79],
            
            ['nome' => 'Ver Pagamentos', 'chave' => 'bonus.pagamentos.ver', 'modulo' => 'Bónus Motoristas', 'tipo' => 'pagina', 'icone' => 'DollarSign', 'ordem' => 80],
            ['nome' => 'Registrar Pagamento', 'chave' => 'bonus.pagamentos.registrar', 'modulo' => 'Bónus Motoristas', 'tipo' => 'botao', 'ordem' => 81],
            
            // ===== FATURAÇÃO =====
            ['nome' => 'Ver Faturação', 'chave' => 'faturacao.ver', 'modulo' => 'Faturação', 'tipo' => 'pagina', 'icone' => 'FileText', 'ordem' => 90],
            ['nome' => 'Ver Ordens Faturação', 'chave' => 'faturacao.ordens.ver', 'modulo' => 'Faturação', 'tipo' => 'pagina', 'icone' => 'ClipboardList', 'ordem' => 91],
            ['nome' => 'Gerar Ordem Faturação', 'chave' => 'faturacao.ordens.gerar', 'modulo' => 'Faturação', 'tipo' => 'botao', 'ordem' => 92],
            ['nome' => 'Marcar Faturado', 'chave' => 'faturacao.ordens.marcar', 'modulo' => 'Faturação', 'tipo' => 'botao', 'ordem' => 93],
            
            ['nome' => 'Ver Notas Crédito', 'chave' => 'faturacao.credito.ver', 'modulo' => 'Faturação', 'tipo' => 'pagina', 'icone' => 'TrendingDown', 'ordem' => 94],
            ['nome' => 'Criar Nota Crédito', 'chave' => 'faturacao.credito.criar', 'modulo' => 'Faturação', 'tipo' => 'botao', 'ordem' => 95],
            
            ['nome' => 'Ver Notas Débito', 'chave' => 'faturacao.debito.ver', 'modulo' => 'Faturação', 'tipo' => 'pagina', 'icone' => 'TrendingUp', 'ordem' => 96],
            ['nome' => 'Criar Nota Débito', 'chave' => 'faturacao.debito.criar', 'modulo' => 'Faturação', 'tipo' => 'botao', 'ordem' => 97],
            
            // ===== FINANÇAS =====
            ['nome' => 'Ver Finanças', 'chave' => 'financas.ver', 'modulo' => 'Finanças', 'tipo' => 'pagina', 'icone' => 'Landmark', 'ordem' => 100],
            ['nome' => 'Ver Caixa', 'chave' => 'financas.caixa.ver', 'modulo' => 'Finanças', 'tipo' => 'pagina', 'icone' => 'Wallet', 'ordem' => 101],
            ['nome' => 'Abertura de Caixa', 'chave' => 'financas.caixa.abertura', 'modulo' => 'Finanças', 'tipo' => 'pagina', 'icone' => 'LogIn', 'ordem' => 102],
            ['nome' => 'Movimentos de Caixa', 'chave' => 'financas.caixa.movimentos', 'modulo' => 'Finanças', 'tipo' => 'pagina', 'icone' => 'ArrowUpDown', 'ordem' => 103],
            ['nome' => 'Requisições de Caixa', 'chave' => 'financas.caixa.requisicoes', 'modulo' => 'Finanças', 'tipo' => 'pagina', 'icone' => 'List', 'ordem' => 104],
            ['nome' => 'Justificativos', 'chave' => 'financas.caixa.justificativos', 'modulo' => 'Finanças', 'tipo' => 'pagina', 'icone' => 'CheckCircle', 'ordem' => 105],
            ['nome' => 'Fecho de Caixa', 'chave' => 'financas.caixa.fecho', 'modulo' => 'Finanças', 'tipo' => 'pagina', 'icone' => 'LogOut', 'ordem' => 106],
            
            // ===== COMBUSTÍVEL =====
            ['nome' => 'Ver Combustível', 'chave' => 'combustivel.ver', 'modulo' => 'Combustível', 'tipo' => 'pagina', 'icone' => 'Fuel', 'ordem' => 110],
            ['nome' => 'Pedidos de Compra', 'chave' => 'combustivel.pedidos.ver', 'modulo' => 'Combustível', 'tipo' => 'pagina', 'icone' => 'ClipboardList', 'ordem' => 111],
            ['nome' => 'Criar Pedido', 'chave' => 'combustivel.pedidos.criar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 112],
            ['nome' => 'Aprovar Pedido', 'chave' => 'combustivel.pedidos.aprovar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 113],
            
            ['nome' => 'Abastecimento Interno', 'chave' => 'combustivel.interno.ver', 'modulo' => 'Combustível', 'tipo' => 'pagina', 'icone' => 'Fuel', 'ordem' => 114],
            ['nome' => 'Registrar Abastecimento', 'chave' => 'combustivel.interno.registrar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 115],
            ['nome' => 'Aprovar Abastecimento', 'chave' => 'combustivel.interno.aprovar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 116],
            
            ['nome' => 'Abastecimento Externo', 'chave' => 'combustivel.externo.ver', 'modulo' => 'Combustível', 'tipo' => 'pagina', 'icone' => 'Globe', 'ordem' => 117],
            ['nome' => 'Registrar Externo', 'chave' => 'combustivel.externo.registrar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 118],
            ['nome' => 'Aprovar Externo', 'chave' => 'combustivel.externo.aprovar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 119],
            ['nome' => 'Pagar Externo', 'chave' => 'combustivel.externo.pagar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 120],
            
            ['nome' => 'Fornecedores', 'chave' => 'combustivel.fornecedores.ver', 'modulo' => 'Combustível', 'tipo' => 'pagina', 'icone' => 'Building', 'ordem' => 121],
            ['nome' => 'Criar Fornecedor', 'chave' => 'combustivel.fornecedores.criar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 122],
            ['nome' => 'Editar Fornecedor', 'chave' => 'combustivel.fornecedores.editar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 123],
            
            ['nome' => 'Tanques', 'chave' => 'combustivel.tanques.ver', 'modulo' => 'Combustível', 'tipo' => 'pagina', 'icone' => 'Fuel', 'ordem' => 124],
            ['nome' => 'Criar Tanque', 'chave' => 'combustivel.tanques.criar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 125],
            ['nome' => 'Editar Tanque', 'chave' => 'combustivel.tanques.editar', 'modulo' => 'Combustível', 'tipo' => 'botao', 'ordem' => 126],
            
            // ===== MANUTENÇÃO =====
            ['nome' => 'Ver Manutenção', 'chave' => 'manutencao.ver', 'modulo' => 'Manutenção', 'tipo' => 'pagina', 'icone' => 'Wrench', 'ordem' => 130],
            ['nome' => 'Ordens de Trabalho', 'chave' => 'manutencao.ordens.ver', 'modulo' => 'Manutenção', 'tipo' => 'pagina', 'icone' => 'ClipboardList', 'ordem' => 131],
            ['nome' => 'Criar Ordem Trabalho', 'chave' => 'manutencao.ordens.criar', 'modulo' => 'Manutenção', 'tipo' => 'botao', 'ordem' => 132],
            
            ['nome' => 'Manutenção Preventiva', 'chave' => 'manutencao.preventiva.ver', 'modulo' => 'Manutenção', 'tipo' => 'pagina', 'icone' => 'CalendarClock', 'ordem' => 133],
            ['nome' => 'Agendar Preventiva', 'chave' => 'manutencao.preventiva.agendar', 'modulo' => 'Manutenção', 'tipo' => 'botao', 'ordem' => 134],
            
            ['nome' => 'Manutenção Corretiva', 'chave' => 'manutencao.corretiva.ver', 'modulo' => 'Manutenção', 'tipo' => 'pagina', 'icone' => 'AlertTriangle', 'ordem' => 135],
            ['nome' => 'Registrar Corretiva', 'chave' => 'manutencao.corretiva.registrar', 'modulo' => 'Manutenção', 'tipo' => 'botao', 'ordem' => 136],
            
            ['nome' => 'Peças e Stock', 'chave' => 'manutencao.pecas.ver', 'modulo' => 'Manutenção', 'tipo' => 'pagina', 'icone' => 'PackageSearch', 'ordem' => 137],
            ['nome' => 'Adicionar Peça', 'chave' => 'manutencao.pecas.adicionar', 'modulo' => 'Manutenção', 'tipo' => 'botao', 'ordem' => 138],
            
            ['nome' => 'Fornecedores Manutenção', 'chave' => 'manutencao.fornecedores.ver', 'modulo' => 'Manutenção', 'tipo' => 'pagina', 'icone' => 'Building2', 'ordem' => 139],
            ['nome' => 'Orçamentos', 'chave' => 'manutencao.orcamentos.ver', 'modulo' => 'Manutenção', 'tipo' => 'pagina', 'icone' => 'FileText', 'ordem' => 140],
            ['nome' => 'Inspeções', 'chave' => 'manutencao.inspecoes.ver', 'modulo' => 'Manutenção', 'tipo' => 'pagina', 'icone' => 'ShieldCheck', 'ordem' => 141],
            
            // ===== RELATÓRIOS =====
            ['nome' => 'Ver Relatórios', 'chave' => 'relatorios.ver', 'modulo' => 'Relatórios', 'tipo' => 'pagina', 'icone' => 'BarChart', 'ordem' => 150],
            ['nome' => 'Exportar Relatórios', 'chave' => 'relatorios.exportar', 'modulo' => 'Relatórios', 'tipo' => 'botao', 'ordem' => 151],
            
            // ===== DEFINIÇÕES =====
            ['nome' => 'Ver Definições', 'chave' => 'definicoes.ver', 'modulo' => 'Definições', 'tipo' => 'pagina', 'icone' => 'Settings', 'ordem' => 200],
            ['nome' => 'Gerenciar Usuários', 'chave' => 'usuarios.gerenciar', 'modulo' => 'Definições', 'tipo' => 'pagina', 'icone' => 'Users', 'ordem' => 201],
            ['nome' => 'Criar Usuário', 'chave' => 'usuarios.criar', 'modulo' => 'Definições', 'tipo' => 'botao', 'ordem' => 202],
            ['nome' => 'Editar Usuário', 'chave' => 'usuarios.editar', 'modulo' => 'Definições', 'tipo' => 'botao', 'ordem' => 203],
            ['nome' => 'Excluir Usuário', 'chave' => 'usuarios.excluir', 'modulo' => 'Definições', 'tipo' => 'botao', 'ordem' => 204],
            
            ['nome' => 'Gerenciar Perfis', 'chave' => 'perfis.gerenciar', 'modulo' => 'Definições', 'tipo' => 'pagina', 'icone' => 'Shield', 'ordem' => 205],
            ['nome' => 'Criar Perfil', 'chave' => 'perfis.criar', 'modulo' => 'Definições', 'tipo' => 'botao', 'ordem' => 206],
            ['nome' => 'Editar Perfil', 'chave' => 'perfis.editar', 'modulo' => 'Definições', 'tipo' => 'botao', 'ordem' => 207],
            ['nome' => 'Excluir Perfil', 'chave' => 'perfis.excluir', 'modulo' => 'Definições', 'tipo' => 'botao', 'ordem' => 208],
            
            ['nome' => 'Gerenciar Permissões', 'chave' => 'permissoes.gerenciar', 'modulo' => 'Definições', 'tipo' => 'pagina', 'icone' => 'Key', 'ordem' => 209],
            ['nome' => 'Atribuir Permissões', 'chave' => 'permissoes.atribuir', 'modulo' => 'Definições', 'tipo' => 'botao', 'ordem' => 210],
        ];

        foreach ($permissoes as $permissao) {
            Permission::updateOrCreate(
                ['chave' => $permissao['chave']],
                $permissao
            );
        }
    }
}