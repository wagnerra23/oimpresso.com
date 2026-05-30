<?php

declare(strict_types=1);

namespace Modules\Jana\Contracts;

use Modules\Jana\Services\Reconcile\ReconcileResult;

/**
 * Interface canônica Reconciler — ADR 0237 (jana:reconcile loop único).
 *
 * Cada Reconciler garante a sincronia de UMA faceta do estado entre o git
 * (fonte da verdade, ADR 0061) e o estado vivo (índice / DB / settings / SHA
 * deployado). Espelha o padrão classe-A já provado em prod do
 * `ChannelsReconcilerCommand` (WhatsApp) + o `DriftChecker` do Governance Drift
 * Framework (ADR 0216) — `jana:reconcile` orquestra via `config('jana.reconcilers')`.
 *
 * Contrato mental (cada implementação faz internamente):
 *   desired()  — estado desejado, derivado do git.
 *   observed() — estado vivo.
 *   diff()     — drift semântico desired × observed.
 *   heal()     — cura idempotente o seguro (append-only — nunca rebaixa/deleta).
 *   alert()    — alerta idempotente o que não é seguro curar.
 * O método público é `reconcile()`, que orquestra os 5 acima.
 *
 * Invariantes (ADR 0237 / 0230):
 * - Idempotência: reconcile() 2× = mesmo resultado.
 * - Rastreabilidade (RTM): todo drift cita a fonte (path/ADR/doc).
 * - Cura segura só: drift com fonte-de-verdade clara cura; ambíguo alerta humano (R10).
 */
interface Reconciler
{
    /**
     * Identificador canônico, snake_case, único: 'index', 'settings', 'content',
     * 'deploy', 'eval'. Usado pelo filtro `--only=index,settings`.
     */
    public function name(): string;

    /** Descrição humana 1-line pra tabela CLI + dashboard. */
    public function description(): string;

    /**
     * Tags pra filtragem (`--tag`): tier + domínio.
     *
     * @return array<int, string>
     */
    public function tags(): array;

    /**
     * Reconcilia a faceta. Idempotente.
     *
     * @param array{heal?: bool, dry_run?: bool} $opts
     *   - heal=false (default): só DETECTA + reporta drift (modo --check).
     *   - heal=true: CURA o que é seguro (idempotente, append-only); alerta o resto.
     *   - dry_run=true: com heal, mostra o que CURARIA sem aplicar.
     */
    public function reconcile(array $opts = []): ReconcileResult;
}
