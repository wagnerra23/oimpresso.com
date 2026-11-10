<?php

namespace Modules\Auditoria\Services;

use App\Domain\Fsm\Concerns\GuardsFsmTransitions;
use App\User;
use App\Util\OtelHelper;
use App\Observers\ActivityCauserKindObserver;
use App\Support\Privacy\PiiRedactor;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\LogBatch;
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
     * Whitelist de Models/condicoes UNREVERTIBLE.
     * Append-only: adicionar Model novo via PR + comentario; remover = ADR amendada.
     *
     * As chaves sao as classes REAIS que gravam em activity_log. Ate 2026-11 tres
     * chaves apontavam para classes inexistentes (PontoWr2 Marcacao, NfeTransaction,
     * Repair Models OS): `instanceof` de classe inexistente da false sem erro, e a
     * trava ficava inerte. RevertServiceTest prova cada uma pelo canRevert.
     *
     * Documento fiscal bloqueia em TODOS os estados, nao so nos autorizados: os campos
     * logados (status, cstat, numero, chave_44) pertencem ao fluxo da SEFAZ/prefeitura.
     */
    public function unrevertibleRegistry(): array
    {
        $sefaz = 'Documento fiscal da SEFAZ nao pode ser revertido via undo. Use o fluxo fiscal apropriado (cancelamento/inutilizacao/CC-e).';
        $nfse = 'NFSe segue o fluxo da prefeitura e nao pode ser revertida via undo — cancele a nota na prefeitura.';

        return [
            \Modules\Ponto\Entities\Marcacao::class => [
                'reason'    => 'Portaria MTP 671/2021 — registro de ponto e append-only por forca de lei. Use Marcacao::anular() (cria marcacao de anulacao, nao deleta original).',
                'condition' => null, // sempre bloqueado
            ],
            \Modules\Ponto\Entities\BancoHorasMovimento::class => [
                'reason'    => 'Portaria MTP 671/2021 — movimento de banco de horas e append-only (o model recusa UPDATE). Lance um movimento de ajuste.',
                'condition' => null,
            ],
            \Modules\NfeBrasil\Models\NfeEmissao::class      => ['reason' => $sefaz, 'condition' => null],
            \Modules\NfeBrasil\Models\NfeEvento::class       => ['reason' => $sefaz, 'condition' => null],
            \Modules\NfeBrasil\Models\NfeInutilizacao::class => ['reason' => $sefaz, 'condition' => null],
            \Modules\NFSe\Models\NfseEmissao::class          => ['reason' => $nfse, 'condition' => null],
            \Modules\NfeBrasil\Models\NfseEmissao::class     => ['reason' => $nfse, 'condition' => null],
            // fin_titulo_baixas nao tem coluna que identifique o Asaas (a condicao antiga
            // lia `origem`, que nunca existiu). A baixa e imutavel por contrato do model:
            // estorno e nova linha com estorno_de_id. Por isso bloqueia sempre.
            \Modules\Financeiro\Models\TituloBaixa::class => [
                'reason'    => 'Baixa financeira e imutavel (inclui boleto pago via Asaas) — o estorno e uma nova linha, feito pela acao Estornar do Financeiro, nao via undo de auditoria.',
                'condition' => null,
            ],
            \Modules\Repair\Entities\JobSheet::class => [
                'reason'    => 'OS com NFSe emitida nao pode ser revertida — cancele NFSe na prefeitura primeiro.',
                // A OS nao guarda a nota: o elo e transactions.repair_job_sheet_id ->
                // nfse_emissoes.transaction_id. A tabela nasce de dois modulos, por isso
                // os dois vocabularios de status. Em transmissao tambem bloqueia.
                'condition' => fn ($model) => DB::table('nfse_emissoes as n')
                    ->join('transactions as t', 't.id', '=', 'n.transaction_id')
                    ->where('t.repair_job_sheet_id', $model->getKey())
                    ->where('n.business_id', $model->business_id)
                    ->whereNull('n.deleted_at')
                    ->whereIn('n.status', ['emitida', 'processando', 'authorized', 'sent'])
                    ->exists(),
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
    public function canRevert(Activity $log, User $by, bool $travarRegistro = false): RevertCheck
    {
        // Multi-tenant Tier 0 ([ADR 0093])
        $userBizId = (int) ($by->business_id ?? 0);
        if ((int) $log->business_id !== $userBizId) {
            return RevertCheck::deny('Activity de outro business — nao acessivel (Tier 0).');
        }

        if ($log->getAttribute('reverted_at') !== null) {
            return RevertCheck::deny('Esta alteracao ja foi revertida.');
        }

        // Subject ainda existe?
        if (! $log->subject_type || ! $log->subject_id) {
            return RevertCheck::deny('Activity sem subject — nao ha o que reverter.');
        }

        $subject = null;
        try {
            $modelClass = $log->subject_type;
            if (! class_exists($modelClass)) {
                return RevertCheck::deny('Subject_type nao e classe valida ('.$modelClass.').');
            }
            $subject = $this->carregarSubject($modelClass, $log->subject_id, $travarRegistro);
        } catch (\Throwable $e) {
            return RevertCheck::deny('Erro ao carregar subject: '.$e->getMessage());
        }

        if (! $subject) {
            return RevertCheck::deny('Registro original nao existe mais — possivel hard-delete posterior.');
        }

        // Tier 0: o tenant que vale e o do REGISTRO. Antes do PR #8384 o log era gravado
        // com a empresa da sessao, entao a linha de log sozinha nao prova nada.
        if (ActivityCauserKindObserver::businessIdDoSubject($subject) !== $userBizId) {
            return RevertCheck::deny('Registro pertence a outro business — nao acessivel (Tier 0).');
        }

        // Whitelist UNREVERTIBLE
        foreach ($this->unrevertibleRegistry() as $blockedClass => $rule) {
            if ($subject instanceof $blockedClass || $log->subject_type === $blockedClass) {
                $cond = $rule['condition'];
                try {
                    $blocked = $cond === null
                        ? true
                        : (bool) $cond($subject, $log);
                } catch (\Throwable $e) {
                    // Sem conseguir medir a trava, nao libera (fail-closed).
                    return RevertCheck::deny('Nao foi possivel verificar a trava de irreversibilidade.', $blockedClass);
                }

                if ($blocked) {
                    return RevertCheck::deny($rule['reason'], $blockedClass);
                }
            }
        }

        // Etapa do pipeline FSM so anda por ExecuteStageActionService: o trait recusa o
        // UPDATE direto, entao liberar aqui viraria excecao no meio do revert.
        if (array_key_exists('current_stage_id', (array) ($log->properties['old'] ?? []))
            && in_array(GuardsFsmTransitions::class, class_uses_recursive($subject), true)) {
            return RevertCheck::deny(
                'Mudanca de etapa do pipeline nao pode ser revertida via undo — use as acoes do proprio pipeline.',
                GuardsFsmTransitions::class,
            );
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
        // D9.a OTel: span hot-path do revert (transaction + 3 writes).
        // Zero-cost quando OTel disabled (config('otel.enabled')=false default).
        // Attributes: NO PII — apenas IDs + classe (Tier 0 ADR 0093).
        return OtelHelper::spanBiz('auditoria.revert.execute', function () use ($log, $by, $reason): Activity {
            return DB::transaction(fn () => LogBatch::withinBatch(function (string $batchUuid) use ($log, $by, $reason) {
            // Tudo que decide e relido aqui, sob lock: duas reversoes simultaneas da
            // mesma Activity passam a ser uma de cada vez, e a segunda ve reverted_at.
            $log = Activity::query()->whereKey($log->getKey())->lockForUpdate()->first()
                ?? throw new \DomainException('Activity nao existe mais.');

            $check = $this->canRevert($log, $by, travarRegistro: true);
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

            $oldAttrs = $log->properties['old'] ?? [];
            if (empty($oldAttrs)) {
                throw new \DomainException('Activity nao tem properties.old — nao ha snapshot pra restaurar.');
            }

            $subject = $this->carregarSubject($log->subject_type, $log->subject_id);

            // Aplica old attrs no Model (campos logged anyway)
            foreach ($oldAttrs as $field => $value) {
                $subject->{$field} = $value;
            }

            // A entry 'updated' que o LogsActivity grava neste save herda o batch aberto.
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
                    'restored_attributes'       => app(PiiRedactor::class)->redactArray($oldAttrs, 'placeholder'),
                ],
                'business_id' => $log->business_id,
            ]);

                return $revertEntry;
            }));
        }, [
            'module'              => 'Auditoria',
            'activity_id'         => $log->id,
            'subject_type'        => $log->subject_type,
            'subject_id'          => (int) $log->subject_id,
            'restored_attrs_count' => count($log->properties['old'] ?? []),
            'has_reason'          => $reason !== '',
        ]);
    }

    /**
     * Carrega o registro auditado sem o escopo de sessao: quem decide o tenant e a
     * comparacao explicita em canRevert. Registro na lixeira conta como inexistente.
     */
    private function carregarSubject(string $modelClass, mixed $id, bool $travar = false): mixed
    {
        $query = $modelClass::withoutGlobalScopes()->whereKey($id); // SUPERADMIN: tenant conferido em canRevert (ADR 0093)
        if ($travar) {
            $query->lockForUpdate();
        }
        $subject = $query->first();

        return $subject && method_exists($subject, 'trashed') && $subject->trashed() ? null : $subject;
    }
}
