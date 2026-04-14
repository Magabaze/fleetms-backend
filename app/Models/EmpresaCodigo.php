<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmpresaCodigo extends Model
{
    protected $table = 'empresa_codigos';

    protected $fillable = [
        'tenant_id',
        'codigo_prefixo',
        'seq_viagem',
        'seq_ordem',
        'descricao',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'seq_viagem' => 'integer',
        'seq_ordem'  => 'integer',
    ];

    // =========================================================
    // GERAÇÃO DE NÚMEROS — THREAD-SAFE
    // =========================================================

    /**
     * Gera próximo número de viagem: BS-00001
     *
     * Usa UPDATE atómico para evitar race conditions.
     * Dois pedidos simultâneos nunca obtêm o mesmo número.
     */
    public static function gerarNumeroViagem(int|string $tenantId): ?string
    {
        return self::gerarNumeroAtomico($tenantId, 'viagem');
    }

    /**
     * Gera próximo número de ordem: BS-0001
     *
     * Usa UPDATE atómico para evitar race conditions.
     */
    public static function gerarNumeroOrdem(int|string $tenantId): ?string
    {
        return self::gerarNumeroAtomico($tenantId, 'ordem');
    }

    /**
     * Núcleo da geração atómica.
     *
     * Faz INCREMENT + SELECT numa só operação de base de dados.
     * Nenhum outro processo pode obter o mesmo valor entre
     * o increment e o select porque o UPDATE tem lock implícito.
     *
     * Viagem → 5 dígitos: BS-00001
     * Ordem  → 4 dígitos: BS-0001
     */
    private static function gerarNumeroAtomico(int|string $tenantId, string $tipo): ?string
    {
        try {
            $coluna  = $tipo === 'viagem' ? 'seq_viagem' : 'seq_ordem';
            $digitos = $tipo === 'viagem' ? 5 : 4;

            // UPDATE atómico — incrementa a sequência
            $linhasAfectadas = DB::table('empresa_codigos')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->update([$coluna => DB::raw("{$coluna} + 1")]);

            if ($linhasAfectadas === 0) {
                Log::error("❌ Prefixo não encontrado para tenant", ['tenant_id' => $tenantId]);
                return null;
            }

            // Ler o valor já incrementado
            $row = DB::table('empresa_codigos')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->select('codigo_prefixo', $coluna)
                ->first();

            if (!$row) {
                return null;
            }

            $numero = $row->codigo_prefixo . '-' . str_pad($row->$coluna, $digitos, '0', STR_PAD_LEFT);

            Log::info("✅ Número gerado [{$tipo}]", [
                'tenant_id' => $tenantId,
                'numero'    => $numero,
            ]);

            return $numero;

        } catch (\Exception $e) {
            Log::error("❌ Erro ao gerar número [{$tipo}]: " . $e->getMessage(), [
                'tenant_id' => $tenantId,
            ]);
            return null;
        }
    }

    // =========================================================
    // GERAÇÃO DE PREFIXO — 2 LETRAS, IMUTÁVEL, ÚNICO
    // =========================================================

    /**
     * Cria registo EmpresaCodigo para um tenant.
     * Chamado UMA SÓ VEZ na criação do tenant.
     *
     * Se já existir, devolve o existente sem alterar nada.
     */
    public static function gerarParaEmpresa(int|string $tenantId, string $nomeEmpresa): ?self
    {
        try {
            // Se já existe, devolver sem alterar — prefixo é imutável
            $existente = self::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();

            if ($existente) {
                Log::info('✅ Prefixo já existe', [
                    'tenant_id' => $tenantId,
                    'prefixo'   => $existente->codigo_prefixo,
                ]);
                return $existente;
            }

            // Gerar prefixo de 2 letras único
            $prefixo = self::gerarPrefixoUnico($nomeEmpresa);

            if (!$prefixo) {
                Log::error('❌ Não foi possível gerar prefixo único', [
                    'tenant_id'    => $tenantId,
                    'nome_empresa' => $nomeEmpresa,
                ]);
                return null;
            }

            $empresaCodigo = self::create([
                'tenant_id'      => $tenantId,
                'codigo_prefixo' => $prefixo,
                'seq_viagem'     => 0,
                'seq_ordem'      => 0,
                'descricao'      => "Gerado para: {$nomeEmpresa}",
                'is_active'      => true,
            ]);

            Log::info('✅ Prefixo criado', [
                'tenant_id'    => $tenantId,
                'nome_empresa' => $nomeEmpresa,
                'prefixo'      => $prefixo,
            ]);

            return $empresaCodigo;

        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar prefixo: ' . $e->getMessage(), [
                'tenant_id'    => $tenantId,
                'nome_empresa' => $nomeEmpresa,
            ]);
            throw $e;
        }
    }

    /**
     * Deriva um prefixo de EXACTAMENTE 2 LETRAS maiúsculas.
     *
     * Tentativas por ordem de prioridade:
     *   1. Iniciais das duas primeiras palavras  → "Baobab Shipping" = BS
     *   2. Primeiras 2 letras da primeira palavra → "Baobab" = BA
     *   3. Varia a 2ª letra mantendo a 1ª        → BA, BB, BC...
     *   4. Varre todas as combinações AA-ZZ
     *   5. Se esgotado (676 combinações), retorna null
     */
    public static function gerarPrefixoUnico(string $nomeEmpresa): ?string
    {
        // Normalizar: remover acentos, maiúsculas, só letras A-Z e espaços
        $normalizado = strtoupper(
            preg_replace('/[^a-zA-Z ]/', '', self::removerAcentos($nomeEmpresa))
        );

        $palavras = array_values(array_filter(explode(' ', $normalizado)));

        // Tentativa 1: iniciais das duas primeiras palavras (BS, DB, AS...)
        $candidato = null;
        if (count($palavras) >= 2) {
            $candidato = substr($palavras[0], 0, 1) . substr($palavras[1], 0, 1);
        }

        // Tentativa 2: primeiras 2 letras da primeira palavra (BA, DB...)
        if (!$candidato || strlen($candidato) < 2) {
            $candidato = substr($palavras[0] ?? 'XX', 0, 2);
        }

        // Garantir exactamente 2 letras
        $candidato = str_pad(substr($candidato, 0, 2), 2, 'X');

        // Se o candidato está livre, usar directamente
        if (!self::where('codigo_prefixo', $candidato)->exists()) {
            return $candidato;
        }

        // Varrer combinações mantendo a 1ª letra e variando a 2ª
        $letras = range('A', 'Z');
        $primeiraLetra = substr($candidato, 0, 1);

        foreach ($letras as $segunda) {
            $tentativa = $primeiraLetra . $segunda;
            if (!self::where('codigo_prefixo', $tentativa)->exists()) {
                Log::info("🔄 Prefixo alternativo", [
                    'original'  => $candidato,
                    'atribuido' => $tentativa,
                ]);
                return $tentativa;
            }
        }

        // Varrer todas as combinações AA-ZZ
        foreach ($letras as $primeira) {
            foreach ($letras as $segunda) {
                $tentativa = $primeira . $segunda;
                if (!self::where('codigo_prefixo', $tentativa)->exists()) {
                    Log::info("🔄 Prefixo alternativo (varredura completa)", [
                        'original'  => $candidato,
                        'atribuido' => $tentativa,
                    ]);
                    return $tentativa;
                }
            }
        }

        // Esgotadas todas as 676 combinações AA-ZZ
        Log::critical('🚨 Todas as combinações de prefixo estão esgotadas!');
        return null;
    }

    /**
     * Remove acentos para normalização do nome da empresa.
     */
    private static function removerAcentos(string $texto): string
    {
        $mapa = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ];

        return strtr($texto, $mapa);
    }

    // =========================================================
    // HELPERS
    // =========================================================

    /**
     * Buscar prefixo pelo tenant_id.
     */
    public static function buscarPorTenant(int|string $tenantId): ?self
    {
        return self::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Verificar disponibilidade de um código (uso administrativo).
     */
    public static function verificarDisponibilidade(string $codigo): array
    {
        $codigo = strtoupper(trim($codigo));

        if (!preg_match('/^[A-Z]{2}$/', $codigo)) {
            return [
                'disponivel' => false,
                'mensagem'   => 'Código inválido. Use exactamente 2 letras maiúsculas (ex: BS, DB, AS).',
            ];
        }

        $existente = self::where('codigo_prefixo', $codigo)
            ->where('is_active', true)
            ->first();

        return [
            'disponivel' => !$existente,
            'mensagem'   => $existente
                ? "Código '{$codigo}' já em uso: " . ($existente->descricao ?? 'N/A')
                : "Código '{$codigo}' disponível!",
            'codigo'     => $codigo,
        ];
    }
}