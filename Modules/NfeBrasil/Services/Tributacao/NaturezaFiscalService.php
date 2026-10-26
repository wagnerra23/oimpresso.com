<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\NfeBrasil\Models\NfeNaturezaProduto;
use RuntimeException;

/**
 * Natureza fiscal do produto — ICMS (mercadoria) × ISS (serviço) — playbook Fiscal thread 29
 * (R-NFE-034 · D-NATUREZA, respondida [W] 2026-10-06: "o contador, por produto, com fonte
 * registrada; enquanto pendente, emite pelo padrão da UF. A Jana nunca decide.").
 *
 * Por que o sistema não decide sozinho: para banner, placa e adesivo personalizado as fontes
 * divergem. A SEFAZ-SP (Consulta 10406/2016) trata placas, banners e totens por encomenda como
 * industrialização, com ICMS; a Súmula 156 do STJ diz que "a prestação de serviço de composição
 * gráfica, personalizada e sob encomenda, ainda que envolva fornecimento de mercadorias, está
 * sujeita, apenas, ao ISS". Quem escolhe é o contador, e a escolha fica com autor, data e fonte.
 *
 * Duas portas, de propósito diferentes:
 *   - `marcarPendente` — qualquer fluxo (inclusive a Jana) pode apontar o caso; não decide nada e
 *     nunca desfaz uma decisão já tomada.
 *   - `decidir` — só usuário da empresa com `nfe.tributacao.manage`. Não existe caminho sem usuário.
 */
class NaturezaFiscalService
{
    public const PERMISSAO = 'nfe.tributacao.manage';

    public static function disponivel(): bool
    {
        return Schema::hasTable('nfe_naturezas_produto');
    }

    /** Marca o produto como caso a decidir. Produto já decidido não volta pra fila. */
    public function marcarPendente(int $businessId, int $productId): NfeNaturezaProduto
    {
        $this->produtoDaEmpresa($businessId, $productId);

        return NfeNaturezaProduto::withoutGlobalScopes() // SUPERADMIN: fila/console; escopo no where explícito
            ->firstOrCreate(
                ['business_id' => $businessId, 'product_id' => $productId],
                ['natureza' => NfeNaturezaProduto::PENDENTE],
            );
    }

    /**
     * O contador decide a natureza do produto.
     *
     * @param string $natureza mercadoria | servico
     * @param string|null $codigoServico item da LC 116/2003 (ex. "24.01"), obrigatório para servico
     * @param string $fonte a base da decisão (consulta, súmula, parecer), obrigatória
     */
    public function decidir(
        int $businessId,
        int $productId,
        string $natureza,
        ?string $codigoServico,
        string $fonte,
        User $autor,
    ): NfeNaturezaProduto {
        if ((int) $autor->business_id !== $businessId || ! $autor->can(self::PERMISSAO)) {
            throw new RuntimeException('Só o contador (permissão ' . self::PERMISSAO . ') decide a natureza fiscal.');
        }
        if (! in_array($natureza, [NfeNaturezaProduto::MERCADORIA, NfeNaturezaProduto::SERVICO], true)) {
            throw new InvalidArgumentException('Natureza deve ser mercadoria ou servico.');
        }
        if (trim($fonte) === '') {
            throw new InvalidArgumentException('Informe a fonte da decisão (consulta, súmula ou parecer).');
        }
        $codigo = $natureza === NfeNaturezaProduto::SERVICO ? trim((string) $codigoServico) : null;
        if ($natureza === NfeNaturezaProduto::SERVICO && ! preg_match('/^\d{2}\.\d{2}$/', (string) $codigo)) {
            throw new InvalidArgumentException('Serviço exige o item da LC 116/2003 no formato 00.00 (ex. 24.01).');
        }
        $this->produtoDaEmpresa($businessId, $productId);

        $registro = NfeNaturezaProduto::withoutGlobalScopes() // SUPERADMIN: escopo no where explícito
            ->updateOrCreate(
                ['business_id' => $businessId, 'product_id' => $productId],
                [
                    'natureza'       => $natureza,
                    'codigo_servico' => $codigo,
                    'fonte'          => trim($fonte),
                    'decidido_por'   => (int) $autor->id,
                    'decidido_em'    => now(),
                ],
            );

        activity('nfe.tributacao')
            ->causedBy($autor)
            ->withProperties([
                'business_id'    => $businessId,
                'product_id'     => $productId,
                'natureza'       => $natureza,
                'codigo_servico' => $codigo,
                'fonte'          => trim($fonte),
            ])
            ->log('natureza_fiscal.decidida');

        return $registro;
    }

    /**
     * Natureza de cada produto (sem linha = mercadoria).
     *
     * @param list<int> $productIds
     * @return array<int,string> product_id => natureza
     */
    public function naturezas(int $businessId, array $productIds): array
    {
        if ($productIds === [] || ! self::disponivel()) {
            return [];
        }

        return NfeNaturezaProduto::withoutGlobalScopes() // SUPERADMIN: emissão roda em fila; escopo no where
            ->where('business_id', $businessId)
            ->whereIn('product_id', $productIds)
            ->pluck('natureza', 'product_id')
            ->map(fn ($n) => (string) $n)
            ->all();
    }

    /**
     * Natureza de um produto da empresa, para mostrar no cadastro (UC-PEDIT-14 · CU-PROD-19).
     * Sem linha (ou sem a tabela) devolve null: o cadastro não mostra nada.
     *
     * @return array{natureza: string, codigo_servico: string|null}|null
     */
    public function daProduto(int $businessId, int $productId): ?array
    {
        if (! self::disponivel()) {
            return null;
        }

        $linha = NfeNaturezaProduto::withoutGlobalScopes() // SUPERADMIN: chamado pelo cadastro de produto; escopo no where
            ->where('business_id', $businessId)
            ->where('product_id', $productId)
            ->first(['natureza', 'codigo_servico']);

        return $linha === null ? null : [
            'natureza' => (string) $linha->natureza,
            'codigo_servico' => $linha->codigo_servico !== null ? (string) $linha->codigo_servico : null,
        ];
    }

    private function produtoDaEmpresa(int $businessId, int $productId): void
    {
        if (! DB::table('products')->where('business_id', $businessId)->where('id', $productId)->exists()) {
            throw new RuntimeException("Produto {$productId} não encontrado nesta empresa.");
        }
    }
}
