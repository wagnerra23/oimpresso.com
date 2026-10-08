<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Tools\Fiscal;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Jana\Contracts\DeclaraPermissao;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Stringable;

/**
 * Tool fiscal da Jana — lê o cadastro fiscal do produto (playbook Fiscal thread 10 · D-IA · UC-NFTR-11).
 *
 * Devolve SÓ: descrição, unidade, categoria, NCM e as regras tributárias do NCM. Nada de preço,
 * custo, estoque, cliente, fornecedor ou CNPJ — o dado vai para um LLM.
 *
 * Tier 0 (ADR 0141): `business_id` vem do construtor e escopa todas as consultas; o LLM não
 * escolhe a empresa. Esta tool só LÊ — a Jana não escreve em tabela do NfeBrasil.
 */
class ProdutoFiscalTool implements Tool, DeclaraPermissao
{
    public function __construct(
        private readonly int $businessId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna o cadastro fiscal de produtos da empresa: nome, descrição, unidade, categoria, '
            .'NCM atual e as regras tributárias do NCM (CFOP, CSOSN/CST, cClassTrib, UFs). Não traz '
            .'preço, cliente nem CNPJ. Use para sugerir NCM, natureza de operação ou completar regra.';
    }

    public function handle(Request $request): Stringable|string
    {
        $productId = $request['product_id'] ?? null;

        return json_encode(
            $this->dados($productId !== null ? (int) $productId : null),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dados(?int $productId = null, int $limite = 30): array
    {
        $q = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoin('categories as c', fn ($j) => $j->on('c.id', '=', 'p.category_id')
                ->where('c.business_id', $this->businessId))
            ->where('p.business_id', $this->businessId)
            ->orderBy('p.id')
            ->limit(max(1, min($limite, 100)));

        if ($productId !== null) {
            $q->where('p.id', $productId);
        }

        $produtos = $q->get(['p.id', 'p.name', 'p.product_description', 'p.ncm', 'u.short_name', 'c.name as categoria']);

        $ncms = $produtos->pluck('ncm')->filter()->unique()->values()->all();
        // Model do dono (NfeBrasil) em vez de DB::table: preserva o scope e o SoftDeletes dele.
        // O where explícito mantém o mesmo resultado com sessão, sem sessão (agente) e superadmin.
        $regras = $ncms === [] ? collect() : NfeFiscalRule::query()
            ->where('business_id', $this->businessId)
            ->whereIn('ncm', $ncms)
            ->when(Schema::hasColumn('nfe_fiscal_rules', 'valida_ate'), fn ($w) => $w->where(
                fn ($v) => $v->whereNull('valida_ate')->orWhere('valida_ate', '>=', now()->toDateString())
            ))
            ->orderBy('id')
            ->get(['id', 'ncm', 'uf_origem', 'uf_destino', 'cfop', 'csosn', 'cst', 'c_class_trib'])
            ->groupBy('ncm');

        return $produtos->map(fn ($p) => [
            'id'         => (int) $p->id,
            'nome'       => (string) $p->name,
            'descricao'  => mb_substr(strip_tags((string) ($p->product_description ?? '')), 0, 300),
            'unidade'    => $p->short_name,
            'categoria'  => $p->categoria,
            'ncm'        => (string) $p->ncm,
            'regras'     => ($regras[$p->ncm] ?? collect())->map(fn ($r) => [
                'id'           => (int) $r->id,
                'uf_origem'    => $r->uf_origem,
                'uf_destino'   => $r->uf_destino,
                'cfop'         => $r->cfop,
                'csosn'        => $r->csosn,
                'cst'          => $r->cst,
                'c_class_trib' => $r->c_class_trib,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Permissão que o dado exige (contrato `DeclaraPermissao`: declara, não enforça — o
     * enforcement é do endpoint que dispara a sugestão, `nfe.tributacao.manage`).
     */
    public function permission(): string
    {
        return 'nfe.tributacao.manage';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->description('id do produto; vazio = primeiros produtos da empresa'),
        ];
    }
}
