<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Portal do cliente (US-FIN-082): uma página, por link e sem conta, com os títulos a receber de UM
 * cliente de UMA empresa — os em aberto (com a segunda via que já existe), os pagos recentes, e o
 * botão de pagar.
 *
 * Gerar: o operador pede o link de um cliente (contato da própria empresa). Token aleatório; só o
 * sha256 em `fin_portal_cliente_links`, válido por `dias_validade` dias. Gerar outro fecha o anterior.
 *
 * Pagar: o portal abre um link de pagamento do título (`LinkDePagamentoDoTitulo`, US-FIN-081) sem
 * fechar o do operador e paga por ele — mesmo valor, mesma emissão, mesma baixa pelo webhook.
 *
 * Tier 0 (ADR 0093): o token resolve empresa E cliente; toda consulta filtra pelos dois.
 */
final class PortalDoCliente
{
    private const HISTORICO = 20;

    public function __construct(
        private readonly LinkDePagamentoDoTitulo $links,
        private readonly MeioDePagarDoTitulo $meio,
    ) {}

    public static function ligado(): bool
    {
        return (bool) config('financeiro.portal_cliente.enabled', false);
    }

    /**
     * @return array{token: string, expira_em: Carbon}
     * @throws ModelNotFoundException cliente de outra empresa ou inexistente
     */
    public function gerar(int $businessId, int $contactId, ?int $userId = null): array
    {
        $existe = DB::table('contacts')->where('business_id', $businessId)->where('id', $contactId)->whereNull('deleted_at')->exists();
        if (! $existe) {
            throw (new ModelNotFoundException())->setModel('contacts', [$contactId]);
        }

        return DB::transaction(function () use ($businessId, $contactId, $userId) {
            $agora = now();
            DB::table('fin_portal_cliente_links')->where('business_id', $businessId)->where('contact_id', $contactId)
                ->where('expira_em', '>', $agora)->update(['expira_em' => $agora, 'updated_at' => $agora]);

            $token = Str::random(48);
            $expira = $agora->copy()->addDays((int) config('financeiro.portal_cliente.dias_validade', 30));
            DB::table('fin_portal_cliente_links')->insert([
                'business_id' => $businessId, 'contact_id' => $contactId, 'token_hash' => LinkDePagamentoDoTitulo::hash($token),
                'expira_em' => $expira, 'created_by' => $userId, 'created_at' => $agora, 'updated_at' => $agora,
            ]);

            return ['token' => $token, 'expira_em' => $expira];
        });
    }

    /**
     * O que a página mostra. Null para token desconhecido; `vencido` = link expirado (sem títulos).
     * Nada de documento, e-mail ou id interno do cliente.
     *
     * @return array<string, mixed>|null
     */
    public function paraCliente(string $token): ?array
    {
        $portal = $this->portal($token);
        if ($portal === null) {
            return null;
        }
        $biz = (int) $portal->business_id;
        $base = [
            'empresa' => (string) DB::table('business')->where('id', $biz)->value('name'),
            'cliente' => (string) DB::table('contacts')->where('business_id', $biz)->where('id', $portal->contact_id)->value('name'),
        ];
        if (Carbon::parse($portal->expira_em)->isPast()) {
            return $base + ['situacao' => 'vencido', 'total_aberto' => 0.0, 'abertos' => [], 'pagos' => []];
        }

        $abertos = $this->titulos($portal)->whereIn('status', ['aberto', 'parcial'])->where('valor_aberto', '>', 0.004)
            ->orderBy('vencimento')->get();
        $pagos = $this->titulos($portal)->where('status', 'quitado')->orderByDesc('updated_at')->limit(self::HISTORICO)->get();

        return $base + [
            'situacao' => 'aberto',
            'total_aberto' => round((float) $abertos->sum(fn ($t) => (float) $t->valor_aberto), 2),
            'abertos'  => $abertos->map(fn ($t) => [
                'id' => (int) $t->id, 'numero' => (string) $t->numero, 'vencimento' => $t->vencimento,
                'valor_aberto' => round((float) $t->valor_aberto, 2), 'meio' => $this->meio->de($biz, $t),
            ])->all(),
            'pagos' => $pagos->map(fn ($t) => [
                'numero' => (string) $t->numero, 'vencimento' => $t->vencimento, 'valor_total' => round((float) $t->valor_total, 2),
            ])->all(),
        ];
    }

    /**
     * @return array{linha_digitavel: ?string, pix_copia_cola: ?string, boleto_url: ?string}
     * @throws \DomainException link vencido, título que não é deste cliente, ou pagamento recusado
     */
    public function pagar(string $token, int $tituloId, string $metodo): array
    {
        $portal = $this->portal($token);
        if ($portal === null || Carbon::parse($portal->expira_em)->isPast()) {
            throw new \DomainException('Este link venceu. Peça um novo à empresa.');
        }
        if (! $this->titulos($portal)->where('id', $tituloId)->exists()) {
            throw new \DomainException('Este título não está mais disponível.');
        }
        $link = $this->links->gerar((int) $portal->business_id, $tituloId, $portal->created_by ? (int) $portal->created_by : null, false);

        return $this->links->pagar($link['token'], $metodo);
    }

    private function portal(string $token): ?object
    {
        return DB::table('fin_portal_cliente_links')->where('token_hash', LinkDePagamentoDoTitulo::hash($token))->first();
    }

    /** Títulos a receber DESTE cliente DESTA empresa. */
    private function titulos(object $portal): \Illuminate\Database\Query\Builder
    {
        return DB::table('fin_titulos')->where('business_id', (int) $portal->business_id)
            ->where('cliente_id', (int) $portal->contact_id)->where('tipo', 'receber')->whereNull('deleted_at')
            ->select(['id', 'numero', 'tipo', 'status', 'origem', 'origem_id', 'valor_total', 'valor_aberto', 'vencimento']);
    }
}
