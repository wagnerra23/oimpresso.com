<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Custo de terceiros da OS da gráfica (US-COMVIS-005): serviço comprado fora que entra no custo
 * real do pós-cálculo. Não mexe em preço, total nem estoque.
 *
 * Tier 0 (ADR 0093): tudo pelo `$businessId` recebido. OS de outra empresa = ModelNotFoundException
 * (o controller devolve 404); fornecedor de outra empresa = DomainException (422).
 */
final class TerceirosDaOs
{
    /** @return list<array{id: int, descricao: string, fornecedor_id: ?int, valor: string}> */
    public function listar(int $businessId, int $osId): array
    {
        return DB::table('comvis_os_terceiros')->where('business_id', $businessId)->where('os_id', $osId)
            ->orderBy('id')->get(['id', 'descricao', 'fornecedor_id', 'valor'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'descricao' => $t->descricao,
                'fornecedor_id' => $t->fornecedor_id === null ? null : (int) $t->fornecedor_id, 'valor' => (string) $t->valor])
            ->all();
    }

    /** @param array{descricao: string, valor: string, fornecedor_id?: ?int} $dados */
    public function lancar(int $businessId, int $osId, array $dados, ?int $userId): int
    {
        $this->osDaEmpresa($businessId, $osId);
        $fornecedor = $dados['fornecedor_id'] ?? null;
        if ($fornecedor !== null && ! DB::table('contacts')->where('business_id', $businessId)->where('id', $fornecedor)->exists()) {
            throw new \DomainException('Fornecedor não encontrado nesta empresa.');
        }

        return (int) DB::table('comvis_os_terceiros')->insertGetId([
            'business_id' => $businessId, 'os_id' => $osId, 'descricao' => $dados['descricao'],
            'fornecedor_id' => $fornecedor, 'valor' => $dados['valor'], 'created_by' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function remover(int $businessId, int $osId, int $id): void
    {
        $this->osDaEmpresa($businessId, $osId);
        $apagou = DB::table('comvis_os_terceiros')->where('business_id', $businessId)->where('os_id', $osId)->where('id', $id)->delete();
        if ($apagou === 0) {
            throw new ModelNotFoundException('Lançamento não encontrado.');
        }
    }

    private function osDaEmpresa(int $businessId, int $osId): void
    {
        if (! DB::table('comvis_os')->where('business_id', $businessId)->where('id', $osId)->whereNull('deleted_at')->exists()) {
            throw new ModelNotFoundException('OS não encontrada.');
        }
    }
}
