<?php

declare(strict_types=1);

namespace Modules\Jana\Contracts;

/**
 * Contrato: toda tool exposta ao LLM declara a permissão que o dado dela exige.
 *
 * Nasce do §Automation Anti-hooks do `Chat.charter.md` — regra 7, "não roda tool
 * sem auth check do tool registry (cada tool declara permission required)" — e é
 * o que o UC-JCHAT-09 mede (`ChatAntiHooksAcaoTest`). Até 2026-08-26 nenhuma das
 * 5 tools do chat declarava nada: o gate era só a flag `copiloto.chat_tools.enabled`
 * + o `business_id` do constructor (ambos já cobertos por R-COPI-141).
 *
 * ⚠️ ESTE CONTRATO **DECLARA**, NÃO **ENFORÇA** — e a distinção é deliberada,
 * não descuido. Um `permission()` que ninguém consulta seria presence-gate
 * (LC-11): declaração com cara de controle. Então fica escrito aqui de quem é
 * cada metade:
 *
 *   - **Declarar** (esta interface): a tool diz qual permissão o dado dela exige.
 *     Serve de fonte única pra quem for enforçar, e torna auditável — hoje por
 *     `git grep`, amanhã por gate — qual tool toca qual domínio.
 *
 *   - **Enforçar** (NÃO implementado aqui): checar `can()` dentro de `handle()`
 *     **quebraria o brief diário**. Medido em 2026-08-26: as 5 tools são
 *     instanciadas por DOIS agentes — `ChatCopilotoAgent` (request HTTP, tem
 *     `auth()->user()`) e `BriefDiarioAgent` (cron `brief:generate`, headless,
 *     **sem** usuário autenticado). Um `auth()->user()->can()` no `handle()`
 *     estouraria no cron. Enforçar exige separar contexto-de-usuário de
 *     contexto-de-sistema, o que é mudança de desenho e decisão [W].
 *
 * O `business_id` do constructor continua sendo o isolamento Tier 0 real e
 * NÃO é substituído por isto ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
 *
 * @see resources/js/Pages/Jana/Chat.charter.md §Automation Anti-hooks
 * @see Modules/Jana/Tests/Feature/Chat/ChatAntiHooksAcaoTest.php (UC-JCHAT-09)
 */
interface DeclaraPermissao
{
    /**
     * Permissão Spatie que o dado devolvido por esta tool exige.
     *
     * Regra de escolha: a permissão gateia a **TABELA que a tool lê**, não o
     * módulo que dá nome a ela — é o que se pode defender sem inventar. Ex:
     * `OportunidadesTool` fala de CRM no nome mas lê `transaction_sell_lines`,
     * então declara `sell.view`.
     *
     * Nunca vazio — string vazia reprova o UC-JCHAT-09 igual a não declarar.
     */
    public function permission(): string;
}
