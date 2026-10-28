<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding guiado da empresa nova (US-SUPER-012) — card "Primeiros passos" da Visão geral.
 *
 * Cada passo é FEITO pelo dado real da empresa, nunca por clique: clicar no passo só leva à
 * tela que já existe. O que a empresa escolhe fica em `business.common_settings['primeiros_passos']`
 * (sem migration): `pulados` = passos tirados da conta sem fingir que foram feitos, e
 * `dispensado_em` = o card some para a empresa inteira até alguém clicar em "Retomar".
 *
 * Multi-tenant Tier 0 (ADR 0093): toda contagem recebe `$businessId` explícito.
 */
class PrimeirosPassosService
{
    public const CHAVE = 'primeiros_passos';

    /** Ordem é a ordem do card: o primeiro passo aberto é o destacado. */
    public const PASSOS = [
        'empresa' => ['titulo' => 'Dados da empresa', 'acao' => 'Completar dados', 'href' => '/business/settings',
            'sub' => 'Preencha o CNPJ e o endereço da empresa.', 'feito_sub' => 'CNPJ preenchido.'],
        'fiscal' => ['titulo' => 'Certificado e fiscal', 'acao' => 'Enviar certificado', 'href' => '/fiscal/config',
            'sub' => 'Envie o certificado A1. A nota fiscal só sai com ele.', 'feito_sub' => 'Certificado ativo.'],
        'produto' => ['titulo' => 'Primeiro produto', 'acao' => 'Cadastrar produto', 'href' => '/products/create',
            'sub' => 'Cadastre o primeiro produto ou serviço que você vende.', 'feito_sub' => 'Produto cadastrado.'],
        'cliente' => ['titulo' => 'Primeiro cliente', 'acao' => 'Cadastrar cliente', 'href' => '/contacts?type=customer',
            'sub' => 'Cadastre o primeiro cliente.', 'feito_sub' => 'Cliente cadastrado.'],
        'venda' => ['titulo' => 'Primeira venda', 'acao' => 'Fazer a primeira venda', 'href' => '/sells/create',
            'sub' => 'Lance uma venda finalizada, no PDV ou pela tela de venda.', 'feito_sub' => 'Venda finalizada.'],
        'equipe' => ['titulo' => 'Convidar a equipe', 'acao' => 'Convidar', 'href' => '/users/create',
            'sub' => 'Crie o acesso de quem vai usar o sistema com você.', 'feito_sub' => 'Equipe convidada.'],
    ];

    /**
     * Estado do card. `visivel=false` quando a empresa dispensou ou quando tudo está feito.
     *
     * @return array{visivel: bool, dispensado: bool, feitos: int, pulados: int, total: int, passos: list<array<string, mixed>>}
     */
    public function estado(int $businessId): array
    {
        $escolhas = $this->escolhas($businessId);
        $feitos = $this->feitos($businessId);

        $passos = [];
        foreach (self::PASSOS as $chave => $p) {
            $status = $feitos[$chave] ? 'feito' : (in_array($chave, $escolhas['pulados'], true) ? 'pulado' : 'aberto');
            $passos[] = [
                'chave' => $chave,
                'titulo' => $p['titulo'],
                'sub' => $status === 'feito' ? $p['feito_sub'] : $p['sub'],
                'acao' => $p['acao'],
                'href' => $p['href'],
                'status' => $status,
            ];
        }

        $nFeitos = count(array_filter($passos, fn ($p) => $p['status'] === 'feito'));
        $dispensado = $escolhas['dispensado_em'] !== null;

        return [
            'visivel' => ! $dispensado && $nFeitos < count($passos),
            'dispensado' => $dispensado,
            'feitos' => $nFeitos,
            'pulados' => count(array_filter($passos, fn ($p) => $p['status'] === 'pulado')),
            'total' => count($passos),
            'passos' => $passos,
        ];
    }

    /**
     * Grava a escolha da empresa. `pular`/`voltar` exigem um passo conhecido.
     */
    public function registrar(int $businessId, string $acao, ?string $passo = null): void
    {
        $business = Business::query()->whereKey($businessId)->firstOrFail();
        $comum = is_array($business->common_settings) ? $business->common_settings : [];
        $escolhas = $this->normalizar($comum[self::CHAVE] ?? []);

        match ($acao) {
            'pular' => $escolhas['pulados'] = array_values(array_unique([...$escolhas['pulados'], $passo])),
            'voltar' => $escolhas['pulados'] = array_values(array_diff($escolhas['pulados'], [$passo])),
            'dispensar' => $escolhas['dispensado_em'] = now()->toIso8601String(),
            'retomar' => $escolhas['dispensado_em'] = null,
            // O controller valida `acao` (in:pular,voltar,dispensar,retomar); chamada direta
            // com outro valor é erro de programação, não escolha a gravar.
            default => throw new \InvalidArgumentException("Ação de primeiros passos desconhecida: {$acao}"),
        };

        $comum[self::CHAVE] = $escolhas;
        $business->common_settings = $comum;
        $business->save();
    }

    /** @return array<string, bool> */
    private function feitos(int $businessId): array
    {
        $existe = fn (string $tabela, callable $filtro): bool => Schema::hasTable($tabela)
            && $filtro(DB::table($tabela)->where('business_id', $businessId))->exists();

        return [
            'empresa' => DB::table('business')->where('id', $businessId)
                ->whereNotNull('tax_number_1')->where('tax_number_1', '!=', '')->exists(),
            'fiscal' => $existe('nfe_certificados', fn ($q) => $q->where('ativo', true)),
            'produto' => $existe('products', fn ($q) => $q),
            'cliente' => $existe('contacts', fn ($q) => $q->whereIn('type', ['customer', 'both'])
                ->where('is_default', 0)->whereNull('deleted_at')),
            'venda' => $existe('transactions', fn ($q) => $q->where('type', 'sell')->where('status', 'final')),
            'equipe' => DB::table('users')->where('business_id', $businessId)
                ->where('user_type', 'user')->whereNull('deleted_at')->count() > 1,
        ];
    }

    /** @return array{pulados: list<string>, dispensado_em: ?string} */
    private function escolhas(int $businessId): array
    {
        $comum = Business::query()->whereKey($businessId)->value('common_settings');
        $comum = is_string($comum) ? (json_decode($comum, true) ?: []) : (array) $comum;

        return $this->normalizar($comum[self::CHAVE] ?? []);
    }

    /** @return array{pulados: list<string>, dispensado_em: ?string} */
    private function normalizar(mixed $bruto): array
    {
        $bruto = is_array($bruto) ? $bruto : [];
        $pulados = array_values(array_intersect((array) ($bruto['pulados'] ?? []), array_keys(self::PASSOS)));

        return ['pulados' => $pulados, 'dispensado_em' => $bruto['dispensado_em'] ?? null];
    }
}
