<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

use App\Services\Sells\SellsCockpitAggregator;
use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Modules\Jana\Entities\AcaoAprovacao;

/**
 * Prévia e aprovação das ações sugeridas no Painel (`/ia`) — o HITL da ordem 1 do
 * `memory/requisitos/Jana/Index-visual-comparison.md`.
 *
 * A PRÉVIA NASCE AQUI, e não no frontend, pela mesma razão do farol
 * (`ApuracaoService::farol`) e da fonte do drill (`JANA_DRILL_FONTES`): texto que
 * afirma número é veredito, e veredito é do servidor. A tela que consome isto (o
 * modal de confirmação) só EXIBE o que recebe daqui — nunca calcula.
 *
 * ⛔ NÃO derivar da âncora. O `JmAcaoModal` do `prototipo-ui/cowork/jana-merge.jsx`
 * traz 4 prévias em texto FIXO, com dados do Martinho (biz=164), e cita
 * `Analise*Service` que não existem no repo (medido 2026-08-17 no espelho e no
 * Cowork vivo). A fonte real é `SellsCockpitAggregator::buildInsightsAggregates` —
 * o MESMO agregado que pinta a linha da ação no cockpit, então prévia e linha não
 * podem divergir.
 *
 * @see Modules\Jana\Http\Controllers\AcaoHitlController
 * @see resources/js/Pages/Jana/_components/JanaAcaoModal.tsx
 */
class AcaoHitlService
{
    /**
     * As 5 regras do `JanaCockpit` §acoes → rótulo do CTA.
     *
     * "Revisar", e não "Disparar": este PR registra a aprovação, nada sai. Trocar
     * um botão morto por um botão que mente seria a mesma classe que o charter já
     * barra em §Anti-hooks ("prometer no botão o que a rota não entrega").
     *
     * A paridade com os rótulos do `.tsx` é amarrada por teste (UC-JPAIN-12).
     */
    public const ACOES = [
        'regua-whatsapp'       => 'Revisar régua',
        'negociar-top'         => 'Revisar proposta',
        'investigar-ticket'    => 'Revisar recorte',
        'pix-adocao'           => 'Revisar leitura',
        'preventivo-pendentes' => 'Revisar lembrete',
    ];

    /**
     * Título de cada ação na FILA (`/ia/acoes`) — copy literal da âncora
     * (`jana-telas-novas.jsx` §JTN_HITL). O Painel monta título próprio, com o número
     * da linha; a fila lista as 5 sempre, então o título é fixo e mora ao lado da chave.
     */
    public const TITULOS = [
        'regua-whatsapp'       => 'Régua de cobrança no WhatsApp',
        'negociar-top'         => 'Negociar com o maior devedor',
        'investigar-ticket'    => 'Investigar a queda do ticket médio',
        'pix-adocao'           => 'Adoção de PIX contra o faturado',
        'preventivo-pendentes' => 'Lembrete antes do vencimento',
    ];

    public function __construct(private SellsCockpitAggregator $aggregator) {}

    /**
     * A fila de `/ia/acoes`: as 5 ações com prévia do SERVIDOR (o mesmo agregado que
     * pinta a linha do Painel) e o último recibo de aprovação de cada uma.
     *
     * Escopo Tier 0: `business_id` da sessão — `AcaoAprovacao` já carrega o
     * `HasBusinessScope`; o `where` explícito é cinto e suspensório.
     *
     * @return list<array{key: string, titulo: string, cta: string, previa: string, contexto: array<string, mixed>, alcance: int|null, recibo: array{quem: string|null, quando: string|null, previa: string, contexto: array<string, mixed>}|null}>
     */
    public function fila(int $businessId): array
    {
        $recibos = AcaoAprovacao::where('business_id', $businessId)
            ->where('status', 'aprovada')
            ->orderByDesc('aprovada_em')
            ->get()
            ->unique('acao_key')
            ->keyBy('acao_key');

        $nomes = \App\User::whereIn('id', $recibos->pluck('user_id')->unique()->all())
            ->pluck('first_name', 'id');

        $fila = [];

        foreach (self::ACOES as $key => $cta) {
            $p = $this->previa($key, $businessId);
            /** @var AcaoAprovacao|null $r */
            $r = $recibos->get($key);

            $fila[] = [
                'key'      => $key,
                'titulo'   => self::TITULOS[$key],
                'cta'      => $cta,
                'previa'   => $p['previa'],
                'contexto' => $p['contexto'],
                'alcance'  => $p['alcance'],
                'recibo'   => $r ? [
                    'quem'     => $nomes->get($r->user_id),
                    'quando'   => $r->aprovada_em?->toIso8601String(),
                    'previa'   => $r->previa,
                    'contexto' => (array) ($r->contexto ?? []),
                ] : null,
            ];
        }

        return $fila;
    }

    public function existe(string $acaoKey): bool
    {
        return array_key_exists($acaoKey, self::ACOES);
    }

    /**
     * Prévia do que a ação faria, com os números do business no instante da leitura.
     *
     * `alcance` = quantos destinatários a ação atinge, ou `null` quando a ação é
     * LEITURA (não manda mensagem pra ninguém). `null` e `0` são coisas diferentes:
     * zero destinatários seria uma ação que não faz nada; `null` é "não se aplica".
     *
     * @return array{previa: string, contexto: array<string, mixed>, alcance: int|null}
     */
    public function previa(string $acaoKey, int $businessId): array
    {
        return OtelHelper::spanBiz('jana.acao.previa', function () use ($acaoKey, $businessId) {
            $agg = $this->aggregator->buildInsightsAggregates($businessId);

            $brl = fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
            $topDevedorNome = $agg['topDevedor']['name'] ?? null;

            [$previa, $contexto, $alcance] = match ($acaoKey) {
                'regua-whatsapp' => [
                    "Mensagem de cobrança para {$agg['overdueCount']} venda(s) vencida(s), somando "
                        .$brl((float) $agg['overdueValue']).'. Uma mensagem por cliente, com o valor e o '
                        .'vencimento de cada título — nada agregado, nada genérico.',
                    ['overdueCount' => $agg['overdueCount'], 'overdueValue' => $agg['overdueValue']],
                    (int) $agg['overdueCount'],
                ],
                'negociar-top' => [
                    'Proposta de negociação para '.($topDevedorNome ?? 'o maior devedor').' — '
                        .$brl((float) ($agg['topDevedor']['total'] ?? 0))
                        .'. Contato direto, uma pessoa só: não entra na régua automática.',
                    ['topDevedor' => $agg['topDevedor']],
                    1,
                ],
                'investigar-ticket' => [
                    'Recorte do ticket médio ('.$brl((float) $agg['ticketMedio']).') por produto e por '
                        .'vendedor na janela de 30 dias, pra achar o mix que puxou pra baixo. Nenhuma '
                        .'mensagem sai: é leitura.',
                    ['ticketMedio' => $agg['ticketMedio']],
                    null,
                ],
                'pix-adocao' => [
                    'Leitura da adoção de PIX de hoje contra o faturado, com a quebra por forma de '
                        .'pagamento dos últimos 30 dias. Nenhuma mensagem sai: é leitura.',
                    ['methodsAgg' => $agg['methodsAgg']],
                    null,
                ],
                'preventivo-pendentes' => [
                    'Lembrete amigável para os títulos que ainda NÃO venceram — antes da régua. Um por '
                        .'cliente, citando a data de vencimento. A receber hoje: '
                        .$brl((float) $agg['totalAReceber']).'.',
                    ['totalAReceber' => $agg['totalAReceber']],
                    null,
                ],
                // O `match` fecha o domínio no próprio Service, e não só no
                // Controller (que já faz `abort_unless(existe(), 404)`): sem este
                // arm o PHPStan acusa "does not handle remaining value: string" —
                // e ele tem razão, porque quem chamar o Service direto (job, tinker,
                // teste) não passa pelo guard da rota. Defesa no dono do contrato.
                default => throw new \InvalidArgumentException(
                    "Ação HITL desconhecida: '{$acaoKey}'. Conhecidas: ".implode(', ', array_keys(self::ACOES))
                ),
            };

            return ['previa' => $previa, 'contexto' => $contexto, 'alcance' => $alcance];
        });
    }

    /**
     * Registra a aprovação. NÃO dispara nada — o envio é PR próprio.
     */
    public function aprovar(string $acaoKey, int $businessId, int $userId): AcaoAprovacao
    {
        return OtelHelper::spanBiz('jana.acao.aprovar', function () use ($acaoKey, $businessId, $userId) {
            $p = $this->previa($acaoKey, $businessId);

            // `previa` é GRAVADA a partir do servidor, nunca recebida do request:
            // aceitar o texto do cliente deixaria o front reescrever o recibo.
            return AcaoAprovacao::create([
                'business_id' => $businessId,
                'user_id'     => $userId,
                'acao_key'    => $acaoKey,
                'status'      => 'aprovada',
                'previa'      => $p['previa'],
                'contexto'    => $p['contexto'],
                'aprovada_em' => Carbon::now(),
            ]);
        });
    }
}
