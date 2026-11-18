<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Empresa do portal público — código na URL ↔ business_id (decisão [W] 2026-11-17).
 *
 * O código é a única forma de o portal saber a empresa: a rota não tem sessão. Ele é aleatório
 * (10 caracteres de um alfabeto sem 0/o/1/l/i), não deriva do id e não é o `business.uuid`.
 * Query builder de propósito: o lookup roda sem contexto de empresa (é ele que o define), então
 * um model com HasBusinessScope não serve aqui. Cada linha é da própria empresa (business_id único).
 */
class EmpresaPublica
{
    public const TAMANHO = 10;

    /** Formato aceito na rota — também separa o código de `install`/`buscar`. */
    public const FORMATO = '[a-hjkmnp-z2-9]{10}';

    private const ALFABETO = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** Código existente da empresa, ou cria um. */
    public static function codigoDe(int $businessId): string
    {
        $existente = DB::table('consulta_os_empresas')->where('business_id', $businessId)->value('codigo');
        if (is_string($existente) && $existente !== '') {
            return $existente;
        }

        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            $codigo = self::gerar();
            try {
                DB::table('consulta_os_empresas')->insert([
                    'business_id' => $businessId,
                    'codigo' => $codigo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $codigo;
            } catch (QueryException $e) {
                // Corrida: outra requisição criou o da empresa, ou o código colidiu. Relê e tenta de novo.
                $existente = DB::table('consulta_os_empresas')->where('business_id', $businessId)->value('codigo');
                if (is_string($existente) && $existente !== '') {
                    return $existente;
                }
            }
        }

        throw new \RuntimeException('consultaos.codigo_empresa_indisponivel');
    }

    /**
     * Link público completo do portal da empresa (decisão [W] 2026-11-18: o link nasce sozinho).
     *
     * Cria o código na primeira vez que o sistema precisa dele (tela de configuração do Repair,
     * aviso da OS, impressão); depois devolve sempre o mesmo — idempotente via codigoDe().
     */
    public static function linkDe(int $businessId): string
    {
        return route('consulta-os.empresa', ['empresa' => self::codigoDe($businessId)]);
    }

    /**
     * linkDe() para quem não pode falhar por causa do portal (tela de configuração, aviso da OS,
     * impressão): devolve null se a rota do portal não estiver registrada ou se o código não puder
     * ser criado (tabela ausente, banco fora) — e registra o motivo no log.
     */
    public static function linkSeDisponivel(int $businessId): ?string
    {
        if ($businessId <= 0 || ! Route::has('consulta-os.empresa')) {
            return null;
        }

        try {
            return self::linkDe($businessId);
        } catch (\Throwable $e) {
            Log::warning('consultaos.link_indisponivel', ['business_id' => $businessId, 'erro' => $e->getMessage()]);

            return null;
        }
    }

    /** business_id do código, ou null se não existe. */
    public static function empresaDe(string $codigo): ?int
    {
        if (preg_match('/^'.self::FORMATO.'$/', $codigo) !== 1) {
            return null;
        }

        $id = DB::table('consulta_os_empresas')->where('codigo', $codigo)->value('business_id');

        return $id === null ? null : (int) $id;
    }

    private static function gerar(): string
    {
        $codigo = '';
        $max = strlen(self::ALFABETO) - 1;
        for ($i = 0; $i < self::TAMANHO; $i++) {
            $codigo .= self::ALFABETO[random_int(0, $max)];
        }

        return $codigo;
    }
}
