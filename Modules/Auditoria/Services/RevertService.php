<?php

namespace Modules\Auditoria\Services;

use App\User;
use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\Privacy\PiiRedactor;
use Spatie\Activitylog\Models\Activity;

/**
 * RevertService — undo de Activity respeitando whitelist UNREVERTIBLE.
 *
 * Per ADR 0127 §princípio 4 + 5:
 *   - 5 categorias bloqueadas (Marcacao append-only, NFe SEFAZ, Asaas paid,
 *     OS com NFSe, Transaction com payment posterior)
 *   - 3 niveis de permissao Spatie:
 *       auditoria.revert.own        - propria <=24h
 *       auditoria.revert.any        - admin <=30d
 *       auditoria.revert.unlimited  - superadmin sem janela
 *
 * Cada revert gera NOVA entry activity_log event='reverted' linkada via
 * batch_uuid + atualiza linha original (reverted_at, reverted_by_user_id,
 * revert_reason). Append-only conceitualmente.
 */
class RevertService
{
    /**
     * Campos que o undo nunca restaura, em qualquer model (motivo => campos).
     * Regra mestre de valor e estoque (memory/proibicoes.md): cada um desses numeros tem
     * dono e recalculo proprios; atribuir direto deixa total, titulo e estoque divergentes.
     * Lista explicita dos campos que a trilha grava (logOnly): campo novo de valor entra aqui.
     */
    private const CAMPOS_PROTEGIDOS = [
        'o total e calculado a partir dos itens, impostos e descontos' => [
            'final_total', 'total_before_tax', 'subtotal', 'total', 'valor_total', 'valor',
            'item_tax', 'line_discount_amount', 'valor_unitario', 'unit_price_inc_tax',
            'preco_unitario_m2', 'valor_estimado', 'valor_recomendado', 'purchase_amount',
        ],
        'o preco ou custo alimenta vendas e margens ja calculadas' => [
            'default_purchase_price', 'default_sell_price', 'sell_price_inc_tax', 'preco', 'preco_base',
            'preco_km', 'preco_m2', 'preco_solda_m', 'preco_custo_m2', 'preco_venda_m2',
            'extra_cost', 'final_price', 'ingredients_cost',
        ],
        'o pagamento so muda por baixa ou estorno no Financeiro' => [
            'amount', 'payment_status', 'valor_aberto', 'valor_baixa', 'valor_centavos', 'valor_pago_centavos',
        ],
        'a comissao depende do total e do vendedor da venda' => [
            'commission_agent', 'commission_split', 'comissao_calculada',
        ],
        'o estoque so muda por movimentacao registrada' => [
            'qty_available', 'quantity', 'total_quantity', 'enable_stock', 'estoque_minimo_m2',
        ],
        'a empresa dona do registro nao muda' => ['business_id'],
    ];

    /** Campos cujo nome so e sensivel neste model (classe => campo => motivo). */
    private const CAMPOS_PROTEGIDOS_POR_CLASSE = [
        \App\Transaction::class => ['status' => 'o status da venda dispara reserva e baixa de estoque'],
        \Modules\Financeiro\Models\Titulo::class => ['status' => 'o status do titulo acompanha o valor em aberto'],
    ];

    /**
     * Whitelist de Models/condicoes UNREVERTIBLE.
     * Append-only: adicionar Model novo via PR + comentario; remover = ADR amendada.
     */
    public function unrevertibleRegistry(): array
    {
        return [
            \Modules\PontoWr2\Models\Marcacao::class => [
                'reason'    => 'Portaria MTP 671/2021 — registro de ponto e append-only por forca de lei. Use Marcacao::anular() (cria marcacao de anulacao, nao deleta original).',
                'condition' => null, // sempre bloqueado
            ],
            \Modules\NfeBrasil\Models\NfeTransaction::class => [
                'reason'    => 'NFe autorizada/cancelada/inutilizada na SEFAZ nao pode ser revertida via undo. Use fluxo SEFAZ apropriado (cancelamento/inutilizacao/CC-e).',
                'condition' => fn ($model) => isset($model->cstat) && in_array($model->cstat, [100, 101, 135]),
            ],
            \Modules\Financeiro\Models\TituloBaixa::class => [
                'reason'    => 'Boleto pago externamente (Asaas) — estorno deve ser feito via fluxo Asaas, nao via undo de auditoria.',
                'condition' => fn ($model) => isset($model->origem) && $model->origem === 'asaas-paid',
            ],
            \Modules\Repair\Models\OS::class => [
                'reason'    => 'OS com NFSe emitida nao pode ser revertida — cancele NFSe na prefeitura primeiro.',
                'condition' => fn ($model) => isset($model->nfse_emitida) && $model->nfse_emitida === true,
            ],
            \App\Transaction::class => [
                'reason'    => 'Esta transacao tem pagamento(s) registrado(s) posteriormente. Reverter quebraria consistencia. Estorne os pagamentos primeiro.',
                'condition' => function ($model, ?Activity $logEntry) {
                    if (! $logEntry) {
                        return false;
                    }
                    $cutoff = $logEntry->created_at;

                    return $model->payment_lines()
                        ->where('created_at', '>', $cutoff)
                        ->exists();
                },
            ],
        ];
    }

    /** Motivo da negacao se o snapshot traz campo de valor ou estoque; null se nao traz. */
    private function motivoCampoProtegido(Activity $log): ?string
    {
        $classe = (array) (self::CAMPOS_PROTEGIDOS_POR_CLASSE[ltrim((string) $log->subject_type, '\\')] ?? []);

        foreach (array_keys((array) ($log->properties['old'] ?? [])) as $campo) {
            $motivo = $classe[$campo] ?? null;
            foreach (self::CAMPOS_PROTEGIDOS as $texto => $campos) {
                $motivo ??= in_array($campo, $campos, true) ? $texto : null;
            }
            if ($motivo !== null) {
                return "O campo {$campo} nao pode ser revertido via undo: {$motivo}.";
            }
        }

        return null;
    }

    /**
     * Verifica se uma Activity pode ser revertida pelo usuario informado.
     *
     * Considera:
     *   1. Whitelist UNREVERTIBLE (Model/condicao)
     *   2. Janela temporal por permissao Spatie
     *   3. Multi-tenant Tier 0 (business_id deve bater)
     *
     * Retorna RevertCheck com allowed bool + reason text.
     */
    public function canRevert(Activity $log, User $by): RevertCheck
    {
        // Multi-tenant Tier 0 ([ADR 0093])
        $userBizId = (int) ($by->business_id ?? 0);
        if ((int) $log->business_id !== $userBizId) {
            return RevertCheck::deny('Activity de outro business — nao acessivel (Tier 0).');
        }

        // Subject ainda existe?
        if (! $log->subject_type || ! $log->subject_id) {
            return RevertCheck::deny('Activity sem subject — nao ha o que reverter.');
        }

        if ($motivo = $this->motivoCampoProtegido($log)) {
            return RevertCheck::deny($motivo);
        }

        $subject = null;
        try {
            $modelClass = $log->subject_type;
            if (! class_exists($modelClass)) {
                return RevertCheck::deny('Subject_type nao e classe valida ('.$modelClass.').');
            }
            $subject = $modelClass::find($log->subject_id);
        } catch (\Throwable $e) {
            return RevertCheck::deny('Erro ao carregar subject: '.$e->getMessage());
        }

        if (! $subject) {
            return RevertCheck::deny('Registro original nao existe mais — possivel hard-delete posterior.');
        }

        // Whitelist UNREVERTIBLE
        foreach ($this->unrevertibleRegistry() as $blockedClass => $rule) {
            if ($subject instanceof $blockedClass || $log->subject_type === $blockedClass) {
                $cond = $rule['condition'];
                $blocked = $cond === null
                    ? true
                    : (bool) $cond($subject, $log);

                if ($blocked) {
                    return RevertCheck::deny($rule['reason'], $blockedClass);
                }
            }
        }

        // Janela temporal por permissao
        if ($by->can('auditoria.revert.unlimited')) {
            return RevertCheck::allow();
        }

        $createdAt = $log->created_at;
        if ($by->can('auditoria.revert.any')) {
            $maxDays = (int) config('auditoria.revert_window_admin_days', 30);
            if ($createdAt && $createdAt->diffInDays(now()) > $maxDays) {
                return RevertCheck::deny("Janela de {$maxDays}d expirada (admin).");
            }

            return RevertCheck::allow();
        }

        if ($by->can('auditoria.revert.own')) {
            // Propria + <= 24h
            if ((int) $log->causer_id !== (int) $by->id) {
                return RevertCheck::deny('Voce nao tem permissao pra reverter acoes de outros usuarios.');
            }
            $maxHours = (int) config('auditoria.revert_window_own_hours', 24);
            if ($createdAt && $createdAt->diffInHours(now()) > $maxHours) {
                return RevertCheck::deny("Janela de {$maxHours}h expirada (own).");
            }

            return RevertCheck::allow();
        }

        return RevertCheck::deny('Sem permissao auditoria.revert.* — fale com admin.');
    }

    /**
     * Reverte uma Activity (undo): restaura properties.old no Model + cria
     * NOVA entry activity_log event='reverted' linkada via batch_uuid +
     * atualiza linha original (reverted_at/by/reason).
     *
     * Joga DomainException se !canRevert.
     */
    public function revert(Activity $log, User $by, string $reason): Activity
    {
        $check = $this->canRevert($log, $by);
        if (! $check->allowed) {
            throw new \DomainException($check->reason ?? 'Revert bloqueado.');
        }

        if (strlen($reason) < 10) {
            throw new \InvalidArgumentException('revert_reason precisa ter no minimo 10 caracteres.');
        }

        // D7.a LGPD: redacta PII (CPF/CNPJ/email/telefone) da justificativa do
        // revert ANTES de persistir em activity_log. Service Jana é a fonte
        // canônica de redaction BR — Auditoria NÃO duplica regex/lógica, apenas
        // consome (ADR 0093 + ADR 0127). Modo placeholder mantém legibilidade.
        $reason = app(PiiRedactor::class)->redact($reason, 'placeholder');

        $modelClass = $log->subject_type;
        $subject = $modelClass::find($log->subject_id);

        $oldAttrs = $log->properties['old'] ?? [];
        if (empty($oldAttrs)) {
            throw new \DomainException('Activity nao tem properties.old — nao ha snapshot pra restaurar.');
        }

        $batchUuid = (string) Str::uuid();

        // D9.a OTel: span hot-path do revert (transaction + 3 writes).
        // Zero-cost quando OTel disabled (config('otel.enabled')=false default).
        // Attributes: NO PII — apenas IDs + classe (Tier 0 ADR 0093).
        return OtelHelper::spanBiz('auditoria.revert.execute', function () use ($log, $by, $reason, $subject, $oldAttrs, $batchUuid): Activity {
            return DB::transaction(function () use ($log, $by, $reason, $subject, $oldAttrs, $batchUuid) {
            // Aplica old attrs no Model (campos logged anyway)
            foreach ($oldAttrs as $field => $value) {
                $subject->{$field} = $value;
            }

            // Mark Model save com batch_uuid pra a entry de reverted aproveitar
            // (Spatie LogsActivity vai criar entry 'updated' automaticamente
            // ao chamar save — vamos usar batch pra agrupar)
            activity()->withProperties([
                'reverted_from_activity_id' => $log->id,
                'revert_reason'             => $reason,
            ])->tap(function ($a) use ($batchUuid) {
                $a->batch_uuid = $batchUuid;
            });
            $subject->save();

            // Atualiza linha original com revert metadata
            $log->reverted_at         = now();
            $log->reverted_by_user_id = $by->id;
            $log->revert_reason       = $reason;
            $log->save();

            // Cria entry explicita 'reverted' no log
            $revertEntry = Activity::create([
                'log_name'    => $log->log_name,
                'description' => 'reverted from activity #'.$log->id,
                'subject_type' => $log->subject_type,
                'subject_id'  => $log->subject_id,
                'causer_type' => get_class($by),
                'causer_id'   => $by->id,
                'event'       => 'reverted',
                'batch_uuid'  => $batchUuid,
                'properties'  => [
                    'reverted_from_activity_id' => $log->id,
                    'revert_reason'             => $reason,
                    'restored_attributes'       => $oldAttrs,
                ],
                'business_id' => $log->business_id,
            ]);

                return $revertEntry;
            });
        }, [
            'module'              => 'Auditoria',
            'activity_id'         => $log->id,
            'subject_type'        => $log->subject_type,
            'subject_id'          => (int) $log->subject_id,
            'restored_attrs_count' => count($oldAttrs),
            'has_reason'          => $reason !== '',
        ]);
    }
}
