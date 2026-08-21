<?php

declare(strict_types=1);

namespace Modules\Superadmin\Support;

use Carbon\Carbon;

/**
 * Dono ÚNICO da tradução `subscriptions.status` (enum) → rótulo PT-BR de tela.
 *
 * Por que existir: o mapa vivia COPIADO e idêntico em dois controllers
 * (`SuperadminController::rotuloAssinatura` e `BusinessController::rotuloDeAssinatura`).
 * A onda SA-O4a precisava dele numa terceira tela, e uma terceira cópia é a divergência
 * garantida — §5 proibicoes 2026-08-02 ("corrigir UMA de N implementações duplicadas:
 * o fix pousou na cópia que o consumidor não usa"). Os dois controllers agora delegam aqui.
 *
 * O que o mapa esconde, e é o motivo de ele ser delicado:
 *
 *   - `trial` NÃO É UM STATUS. No protótipo do F1 é; no banco, período de teste é a coluna
 *     `trial_end_date`. "Em trial" é DERIVADO de data, nunca lido de `status`.
 *   - `approved` é ambíguo sozinho: com `end_date` no futuro é Ativa, no passado é Vencida.
 *     Quem chamar sem a data recebe "Ativa" para assinatura morta.
 *   - `declined` (Bloqueada) é gravado por `OnCobrancaVencidaBloqueaSubscription` quando a
 *     cobrança vence. NÃO é cancelamento: bloqueio por inadimplência e cancelamento a pedido
 *     são eventos comerciais diferentes e não se fundem num rótulo só.
 *
 * @see memory/requisitos/Superadmin/RUNBOOK-assinaturas.md §1 (a tabela completa)
 * @see Modules\Superadmin\Services\SubscriptionLifecycleService (quem grava expired/cancelled)
 */
final class RotuloAssinatura
{
    /** Negócio sem nenhuma assinatura — só a tela de Negócios chega a mostrar. */
    public const SEM_ASSINATURA = 'Sem assinatura';

    /**
     * Rótulos aceitos pelo filtro de status da tela de Assinaturas.
     *
     * São de TELA, não do banco: quem monta a URL é o front, e o front não conhece
     * `declined`. Valor fora desta lista não chega à query (o chamador usa `opcaoValida`).
     */
    public const FILTROS = ['ativa', 'trial', 'pendente', 'vencida', 'cancelada', 'bloqueada'];

    /**
     * Enum → rótulo. `approved` fica de fora de propósito: ele depende da data (ver `de()`).
     *
     * @var array<string, string>
     */
    private const DIRETO = [
        'waiting' => 'Pendente',
        'declined' => 'Bloqueada',
        'expired' => 'Vencida',
        'cancelled' => 'Cancelada',
    ];

    /**
     * Rótulo de uma assinatura.
     *
     * @param  string|null  $status  valor cru de `subscriptions.status`; `null` = sem assinatura.
     * @param  mixed  $fim  `end_date` (Carbon, string ou null). Só pesa quando o status é
     *                      `approved` — é o que separa Ativa de Vencida.
     */
    public static function de(?string $status, $fim = null): string
    {
        if ($status === null) {
            return self::SEM_ASSINATURA;
        }

        if ($status === 'approved') {
            return ($fim && Carbon::parse($fim)->isPast()) ? 'Vencida' : 'Ativa';
        }

        return self::DIRETO[$status] ?? self::SEM_ASSINATURA;
    }

    /**
     * Aplica o filtro de status da tela sobre um query builder.
     *
     * Fica AQUI, junto do mapa de leitura, porque são os dois sentidos da mesma tradução —
     * separados, eles divergem no primeiro status novo.
     *
     * As colunas são parâmetro (e não constante) porque o alias depende do join de cada tela;
     * o default é o desta onda. Nada aqui interpola entrada do usuário: `$rotulo` já veio
     * validado contra `FILTROS` e as colunas são literais do chamador.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $rotulo  um de `FILTROS`.
     * @param  string  $hoje  data de referência (`Y-m-d`) — injetada para o teste não depender do relógio.
     */
    public static function filtro($query, string $rotulo, string $hoje, string $colStatus = 'subscriptions.status', string $colFim = 'subscriptions.end_date', string $colTrial = 'subscriptions.trial_end_date'): void
    {
        switch ($rotulo) {
            case 'ativa':
                // Viva: aprovada e ainda dentro da vigência. `end_date` nula conta como viva —
                // é assinatura sem fim marcado, não assinatura vencida.
                $query->where($colStatus, 'approved')
                    ->where(function ($w) use ($colFim, $hoje) {
                        $w->whereNull($colFim)->orWhereDate($colFim, '>=', $hoje);
                    });
                break;

            case 'trial':
                // Derivado de DATA, não de status (ver docblock da classe). Uma assinatura em
                // trial também é "ativa" — os dois filtros se sobrepõem de propósito, porque
                // são perguntas diferentes ("está valendo?" e "ainda é teste?").
                $query->where($colStatus, 'approved')
                    ->whereNotNull($colTrial)
                    ->whereDate($colTrial, '>=', $hoje);
                break;

            case 'pendente':
                $query->where($colStatus, 'waiting');
                break;

            case 'vencida':
                // Duas origens: o sweep marcou `expired`, ou a data passou e ninguém marcou
                // nada. A tela precisa das duas, senão a fila de cobrança nasce incompleta.
                $query->where(function ($w) use ($colStatus, $colFim, $hoje) {
                    $w->where($colStatus, 'expired')
                        ->orWhere(function ($x) use ($colStatus, $colFim, $hoje) {
                            $x->where($colStatus, 'approved')->whereDate($colFim, '<', $hoje);
                        });
                });
                break;

            case 'cancelada':
                $query->where($colStatus, 'cancelled');
                break;

            case 'bloqueada':
                $query->where($colStatus, 'declined');
                break;
        }
    }
}
