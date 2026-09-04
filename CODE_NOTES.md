# CODE_NOTES — retorno [CL]→[W]/[CC] (canal Code→Cowork)

> Cowork é read-only no git. Este arquivo é o retorno do Code sobre handoffs processados.

---

## [PROCESSADO 2026-06-17] Forja › aba MCP — superfície do handoff (Fase 1 · ADR 0283)

**Handoff:** `prototipo-ui-patch/PROMPT_PARA_CODE_FORJA-HANDOFF-SURFACE.md`
**Branch:** `feat/forja-mcp-handoff-surface` (worktree off `origin/main` @ 70cb5b195 — §10.4 validado)
**Premissa confirmada @main:** Fase 0 mergeada (#2904/2905/2906/2908) — `cowork_handoffs`,
`HandoffPendingTool`/`HandoffAckTool`, `HandoffStaleAlertCommand`, `McpIngestHeartbeat` existem.
Não recriado nada da Fase 0.

### Entregue (PR-A / PR-B / PR-C)

- **PR-A · backend** — `ForjaController@mcp` deixou de ser `renderTab('mcp')` (mock puro) e passou a
  projetar dado REAL via `Inertia::defer` (`handoffs` + `heartbeat`), espelhando `triagem()`/`quadro()`.
  - **Desvio do literal "1 controller method":** a projeção foi pra um **`Services/Forja/ForjaMcpService`**
    novo, porque é o padrão CANÔNICO do controller (backlog/quadro/changelog já delegam a `*Service`;
    §10.4 "main vence"). Isso deixou a lógica **unit-testável** sem HTTP/auth. O método `mcp()` ficou
    fininho. Removi o helper `renderTab()` (ficou órfão — evita dead-code/larastan).
  - **Status REAIS** (não o vocabulário do protótipo): `pending/applied/rejected/stale/superseded`.
  - **`stale` derivado na LEITURA** (`pending` + idade > 3d) — robusto, não espera o cron.
  - **Gate** derivado do `gate_status` com a **MESMA regra verde do `HandoffAckTool`**
    (`conformance && critique_score>=80 && a11y`): `verde/vermelho/rodando(applied sem gate)/na`.
    Nunca pinta verde sem ler.
  - Mais recente por slug (maior `version`), **excluindo `superseded`**, limit 200. Tier 0: repo-wide
    (sem `business_id`), com o marker que o `NoMissingTenantScopeRule` exige.
- **PR-B · frontend** — `ForjaMcp.tsx` ganhou a seção `data-testid="forja-mcp-handoffs"` no TOPO
  (acima de contrato/tokens/auditoria, que seguem MOCKADO). `Deferred` envolve **só** a seção nova →
  o contrato estático continua pintando na hora (sem regressão de 1º paint). Tem: título, filtros por
  status com contagem (`todos/pendente/aplicado/rejeitado/parado`), item com slug `vN` · tela · resumo
  (1ª linha do `body_md`) · ⚿ sig · `N arq` · gate (dot colorido, drill pro PR) · `PR ↗` · idade · autor,
  e **empty-state = heartbeat** ("transporte sem sinal" vira alerta vs "ocioso"). DS v6: só tokens
  semânticos, `tabular-nums`, `inline-flex/grid`, `data-testid`.
- **PR-C · contrato** — 2 linhas no array `TOOLS`: `handoff-pending` (PERMITIDO · assinado) e
  `handoff-ack` (PROPÕE · 422 sem gate verde).
- **Test** — `Modules/TeamMcp/Tests/Feature/ForjaMcpServiceTest.php` (13 casos, tabela sintética como
  `HandoffToolsTest`): exclusão de superseded, maior-version-por-slug, derivação de stale, as 4 saídas
  do gate, serialização (files_count/signed/resumo) e heartbeat (silent/recente/teto).

### Decisões / TODOs (honestidade)

- **Levers (re-disparar / devolver ao [CC] / supersede)** — renderizadas mas `disabled` com tooltip
  "Roteia via tool MCP — Fase 2 (ADR 0283)". **NÃO simulam sucesso** e **SEM botão de merge** (Tier 0:
  o merge é o 1-clique do [W]). Optei por `disabled`+TODO em vez de criar endpoints HTTP/rota stub pra
  manter o PR cirúrgico (não inventei `POST` que não existe). **Fase 2** = wire das levers às tools MCP.
- **Conflito gate×CI real (A3 do adversário)** — deixado como TODO consciente: o gate é lido do
  `gate_status` (verdade do ack), sem cruzar com os required checks do PR via GitHub API. Não inventei
  "verde". Follow-up se quiser fechar 100%.
- **Sinal no Quadro (contador pending na aresta F1→F3)** — NÃO feito aqui (toca `ForjaQuadroService` +
  `ForjaQuadro.tsx`, fora do escopo cirúrgico). Vira task (o handoff permitia "senão vira task").
- **Charter** (`Cockpit.charter.md`) — a bullet do MCP ainda diz "MOCKADO por design"; continua certo
  pra contrato/tokens/auditoria. Atualizar pra citar a seção Handoffs REAL é follow-up (evitei mexer em
  design-memory neste PR).
- **Verificação visual** — não rodei preview local: o app é Laravel/Inertia (sem PHP local) e a seção
  depende de dado real de `cowork_handoffs`. Validação visual fica pro smoke pós-merge contra prod
  (skill `tela-smoke-pos-merge`, rota `/forja/mcp`) + os gates de CI (conformance/foundation/pageheader/
  a11y/visual-regression).

### Pós-push (CI verde)
- **PHPStan**: removidos `is_array($h->files_json)`/`is_string($h->sig)` redundantes (casts/`@property` — larastan resolve como sempre-true).
- **Casos G-6** (ADR 0264): `last_run` de `Cockpit.casos.md` bumpado + UC-FORJA-12.
- **Base atualizada**: merge de `origin/main` (PR #2914 entrou depois do branch — subiu o baseline TeamMcp 75→79 + OTel no GitMainResolver). Minha query repo-wide de `CoworkHandoff` é consistente com o novo `CoworkHandoffCrossTenantTest`.
- **Module Grade D9.a** (ADR 0155): `ForjaMcpService` instrumentado com `OtelHelper::span` (observability real, igual GitMainResolver) — sem isso o service novo diluía a razão `services-com-OTel` e derrubava TeamMcp 79→78.

### Arquivos
- `Modules/TeamMcp/Services/Forja/ForjaMcpService.php` (novo)
- `Modules/TeamMcp/Http/Controllers/ForjaController.php` (mcp() + import; removido renderTab órfão)
- `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaMcp.tsx` (seção Handoffs + props + PR-C)
- `Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.tsx` (props deferidas + repasse)
- `Modules/TeamMcp/Tests/Feature/ForjaMcpServiceTest.php` (novo)

---

## [PROCESSADO 2026-09-04] Crm › pacote de export — pedido de 24/08 RETIRADO + as 3 verificações do passo-a-passo

**Ponte:** `prototipo-ui/design-docs/handoff-crm/PEDIDO-CODE.md` (reescrito pelo Cowork em 2026-09-04; [W] colou 1× — rota sancionada pela ADR 0389).
**Branch:** `claude/crm-pedido-retirado-0301` (worktree repontada pra `origin/main` fresco — a base anterior estava 104 commits atrás, guard `git-base-freshness-guard`).
**Veredito do Cowork:** 0 de 16 telas exportáveis. **Concordo, e a razão dele confere no `main`.**

O pacote não pede código: pede 3 verificações. Todas rodadas contra `origin/main`, com o comando ao lado.

### 1 · ADR 0301 segue `aceito`? — **SIM**

```
git show origin/main:memory/decisions/0301-separar-cliente-deprecar-crm-pipeline.md | sed -n '1,17p'
→ status: aceito · authority: canonical · lifecycle: ativo · decided_by: [W] · decided_at: "2026-06-22"
```

Logo o pedido de 2026-08-24 (criar `resources/js/Pages/Crm/{Painel,Leads,Acompanhamentos,Portal}` com trio)
era mesmo contra a decisão. **Retirado** — o próprio `PEDIDO-CODE.md` é o registro, e nenhum charter foi criado.

### 2 · As premissas do pacote, medidas — **todas conferem**

| afirmação do Cowork | medido no `main` | ✓ |
|---|---|---|
| `resources/js/Pages/Crm/` não existe | `git ls-tree -r origin/main --full-tree` → 0 entradas | ✅ |
| `routes/` (raiz) não tem `Crm` | `git grep -c -i crm origin/main -- 'routes/*.php'` → rc=1 (sem match) | ✅ |
| o React vivo do módulo é o cadastro (A) | `Pages/Cliente/` = **53** arquivos = 7 telas × trio (21) + `_drawer/` 10 + `_show/` 13 + `_form/` 5 + `_components/` 4 (32) | ✅ |
| o dono real das rotas é o módulo | `Modules/Crm/Routes/web.php` — grupo `prefix('crm')` | ✅ |

### 3 · Alguma etapa E1–E6 avançou desde 22/06? — **NENHUMA**

O plano segue `status: planejado`. Não respondi por título de commit (isso mede a citação, não a entrega —
§5 2026-08-08): medi a **consequência** que cada etapa produziria.

| etapa | o que ela mudaria | estado medido no `main` | avançou? |
|---|---|---|---|
| **E1** | ADR aceita + row count por business + confirmar `BrLookupService`=A | ADR feita **em** 22/06 (não *desde*); SQL nunca rodado (o plano diz "não rodei SQL") | ❌ |
| **E2** | `/crm/*` → 404 + nav sem pipeline | **41 rotas ativas** no grupo `prefix('crm')`, nenhuma comentada; `nav.blade.php` com **11** itens de pipeline | ❌ |
| **E3** | dump ARCHIVE por business + PiiRedactor | sem script de archive; `PiiRedactor` só mudou de namespace (#5675) | ❌ |
| **E4** | remover commands + tirar do schedule + Connector 410 | `registerScheduleCommands()` chamado (`CrmServiceProvider:44`); `pos:sendScheduleNotification` **everyMinute** + `pos:createRecursiveFollowup` daily (`:217-218`); `connector/api/crm/*` exposta (`Connector/Routes/api.php:112-117`) | ❌ |
| **E5** | DROP das tabelas `crm_*` (30d após E4) | depende de E4 | ❌ |
| **E6** | SPEC→`descontinuado`, BRIEFING→deprecado, SCOPE limpo | SPEC `status: rascunho`; BRIEFING `status: producao` | ❌ |

**Os 15 commits que tocaram `Modules/Crm/` desde 22/06 são sweeps de docs/infra, não execução** — e um deles
anda na direção **oposta**: `#6302` (26/08) *"declara `crm_module` no catálogo do pacote — salvar a tela apagava
o CRM em silêncio"*, ou seja, conserto para **manter** o módulo instalável.

### Bônus — BLOQUEIO 3 do plano fechado por medição (era read-only, então fiz)

O plano lista 3 bloqueios antes de qualquer DROP; o 3º é *"confirmar que `BrLookupService` pertence a A"*
(Risco 8: removê-lo por engano quebra CEP/CNPJ do cadastro da Larissa). **Varredura contada, repo inteiro:**

```
git grep -n "BrLookupService" origin/main   → 26 arquivos
```

Desses, **código** (não doc/teste) são 3: o próprio `Modules/Crm/Services/BrLookupService.php`,
`Modules/Crm/Http/Controllers/ClienteLookupController.php` (injeção no construtor, `:48`) e
`tests/Feature/Cliente/ClienteLookupCnpjCepTest.php` (instancia direto, `:337`/`:349`).
**Zero consumidor no pipeline B.** `ClienteLookupController` é classe **A** pela própria tabela do plano.

→ **`BrLookupService` pertence a A. Confirmado.** Restam abertos os bloqueios 1 (row count por business,
exige SQL em réplica) e 2 (auditar o consumidor Delphi da API Connector).

### O que NÃO fiz, e por quê

- **Não criei `resources/js/Pages/Crm/`** — é o pedido retirado.
- **Não executei E1–E6.** Cada etapa tem gate [W] explícito no plano; medir é meu, decidir não é.
- **Não regenerei o bundle** — `gerar-payload-partes.mjs` roda do lado que tem os arquivos em disco (Cowork).
- **Não toquei `prototipo-ui/cowork/`** (espelho read-only) nem os `.jsx` do build (são do lado design).

### Fila de decisão que volta para [W] (o RESÍDUO do pacote, sem tradução minha)

1. O pipeline CRM continua em depreciação? Se sim, `crm-blade*` é referência de legado, não alvo de export.
2. Portal do contato (`/contact/*`) fica ou sai? Se fica, sai da zona cinza e há onda de 3 telas.
3. Alvo de toque em 1280 denso: mínimo WCAG 24×24 ou exceção declarada? (mesma pergunta aberta da Forja.)

### Arquivos
- `prototipo-ui/design-docs/handoff-crm/PEDIDO-CODE.md` (reescrito — era o pedido de 24/08)
- `CODE_NOTES.md` (esta entrada)
