# PROTOCOL.md — protocolo formal do loop Claude Design ↔ Claude Code

> **Versão:** 1.1 — reconciliada com o modelo **autônomo** de 2026-05-31 (ver overlay no §2 + [AUTOMACAO-LOOP-AUTONOMO.md](AUTOMACAO-LOOP-AUTONOMO.md)). Formalizado em [ADR 0241](../memory/decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md).
> **Documento mãe:** [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
> **Última revisão:** 2026-05-31 (reconciliação [CL] — conteúdo base 1.0 = 2026-05-09)

## 1. Os 6 papéis

| Sigla | Quem | Onde vive | O que faz |
|---|---|---|---|
| **[W]** | Wagner | local + chat | escreve pedido em `COWORK_NOTES.md`, aprova screenshot, aprova merge |
| **[CC]** | Claude Cowork | Anthropic Cowork web app | gera protótipo visual `<tela>/page.tsx` |
| **[CD]** | Claude Design | Cowork OU plugin local | roda `design:design-critique`, `design:design-system`, `design:ux-copy` |
| **[CL]** | Claude Code | repo Laravel (este) | traduz protótipo aprovado pra Inertia/React real |
| **[CA]** | Claude Accessibility | plugin local | roda `design:accessibility-review` (WCAG 2.1 AA) |
| **[W2]** | Wagner | local | aprova SCREENSHOT real (não tabela) antes de mergear |

`[CD]` e `[CA]` podem ser a mesma instância de Claude Code rodando skills do plugin Anthropic. `[CC]` é separado (Cowork web app).

## 2. As 5 fases (na verdade 7 com 1.5 e 3.5)

```
F0 BRIEF       [W]   pedido em COWORK_NOTES.md
                     ↓
F1 DESIGN      [CC]  protótipo visual em prototipos/<tela>/page.tsx
                     ↓
F1.5 CRITIQUE  [CD]  score ≥80 ok / 70-79 1 round refator / <70 discussão
                     ↓
F2 SCREENSHOT  [W2]  aprovação visual síncrona (não tabela)
                     ↓
F3 CODE        [CL]  refator/criação Inertia em <Tela>.tsx + .charter.md
                     (detalhe cirúrgico: ver PROTOCOL-F3-COWORK-CODE.md — 7 sub-fases + agente cowork-to-inertia)
                     ↓
F3.5 A11Y      [CA]  accessibility-review WCAG 2.1 AA
                     ↓
F4 MERGE       [W2]  PR merge se F3.5 passou
```

Sem fase pulada. Mesmo princípio do MWART process ([ADR 0104](../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)).

> **⚙️ Overlay autônomo (2026-05-31 — supersede os gates humanos abaixo · [ADR 0241](../memory/decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md)).**
> Wagner adotou **0 intervenção humana** no loop ([AUTOMACAO-LOOP-AUTONOMO.md](AUTOMACAO-LOOP-AUTONOMO.md) · `SYNC_LOG` 2026-05-31 00:45). As 7 fases continuam válidas como **lentes**, mas 3 gates humanos viraram automáticos:
> - **F1.5 [CD] crítica + F3.5 [CA] a11y** → **auto-check de quem produz** ([CC] roda a crítica, [CL] roda a a11y) antes de entregar — não fases-ferry separadas. Trava objetiva mantida: critique **≥80** + **WCAG AA**. Nota <70 ou a11y crítica → escala revisão dedicada.
> - **F2 [W2] screenshot** → **gates CI**: *PR UI Judge (Claude Sonnet 4.5)* + *visual-regression*. Sem aprovação síncrona de screenshot.
> - **F4 [W2] merge** → **merge autônomo `gh --admin` quando todos os checks *required* verdes** (interim; alvo = bot `grokwr2`). CI verde **é** o gate.
> - **FICA humano (Tier 0):** ADR novo · mudança multi-tenant · segredos/Vaultwarden · lógica de lint/tooling · decisão de produto.
>
> **Cadeia efetiva (0-humano):** `F0 [W] brief` → `F1 [CC] design + auto-crítica + auto-a11y` → `gates CI (UI Judge + visual-regression + lint + Pest)` → `F3 [CL] aplica no repo + PR` → `merge autônomo se CI verde`. O 7→4-hop proposto no `COWORK_NOTES` (Cowork, com [W] ainda no merge) está **superado** por este modelo.

## 3. Critérios de transição entre fases

| De → Pra | Critério obrigatório |
|---|---|
| F0 → F1 | `COWORK_NOTES.md` tem entrada com tela + prioridade + contexto + restrições |
| F1 → F1.5 | `prototipos/<tela>/page.tsx` existe + commitado |
| F1.5 → F2 | `prototipos/<tela>/critique-score.json` com `score: ≥80` (ou exceção justificada) |
| F2 → F3 | Wagner aprovou screenshot (registrado em `SYNC_LOG.md` com `[W2]: approved`) |
| F3 → F3.5 | `resources/js/Pages/<Mod>/<Tela>.tsx` editado/criado + `npm run build` OK |
| F3.5 → F4 | `prototipos/<tela>/a11y-report.md` sem `severity: critical` |
| F4 → done | PR merged + `SYNC_LOG.md` com `[W2]: merged` + `HANDOFF.md` atualizado |

> **Entrada via bundle estruturado (Claude Design oficial):** quando o insumo de F1/F3 chega como bundle do Claude Design (spec+tokens+layout+assets) em vez de protótipo HTML, ele entra como **proposta** validada pelo gate — ver **§10.5**. Não pula fase nem o overlay autônomo.

## 4. Onde cada fase escreve

| Fase | Arquivo escrito | Responsável |
|---|---|---|
| F0 | `COWORK_NOTES.md` (append) | [W] |
| F1 | `prototipos/<tela>/page.tsx`, `prototipos/<tela>/COMPARISON.md` | [CC] |
| F1.5 | `prototipos/<tela>/critique-score.json` | [CD] |
| F2 | `SYNC_LOG.md` (1 linha aprovação) + `HANDOFF.md` (atualizar estado) | [W2] |
| F3 | `resources/js/Pages/<Mod>/<Tela>.tsx`, `*.charter.md`, `CODE_NOTES.md` (append) | [CL] |
| F3.5 | `prototipos/<tela>/a11y-report.md` | [CA] |
| F4 | `SYNC_LOG.md` (1 linha merge) + `HANDOFF.md` (limpar estado, próxima da queue) | [W2] |

## 5. Override autorizado

Wagner pode pular gates via comentário em `COWORK_NOTES.md`:

| Comando | Pula | Quando usar |
|---|---|---|
| `/design-override <razão>` | F1.5 critique | Tela trivial, copy-only, fix de bug |
| `/screenshot-override <razão>` | F2 aprovação | Wagner já viu protótipo Cowork direto |
| `/a11y-override <razão>` | F3.5 a11y | Protótipo interno superadmin (não-cliente-facing) |

**Formato canônico:** `/X-override <razão curta> --tela=<nome-kebab>`
**Exemplo:** `/screenshot-override Wagner aprovou no Cowork --tela=sells-create`

Cada override gera ADR per-tela `lifecycle: historical` em `memory/decisions/<NNNN>-design-excecao-<tela>.md`.

Sem override, gates não cedem. Mesmo loop pra todos: `[W]`, `[F]`, `[M]`, `[L]`, `[E]`.

## 6. Métricas de saúde do loop

A adicionar em `php artisan jana:health-check`:

| Check | SQL/lógica | Falha quando |
|---|---|---|
| `design_loop_stuck` | conta telas em F3 há mais de 7 dias | ≥1 |
| `design_critique_skipped` | conta `prototipos/*/critique-score.json` ausentes | ≥1 protótipo sem critique |
| `design_a11y_skipped` | conta merges sem `a11y-report.md` no path | ≥1 merge sem a11y |
| `design_loop_active_count` | conta entradas `HANDOFF.md` em fase ≠ done | informativo |
| `design_review_missing` | tela charter `status: live` sem `<Tela>.review.md` **fora** do `review-freshness-baseline.json` (ratchet) — `node prototipo-ui/audit/review-freshness.mjs` | ≥1 (tela nova nasceu sem review) |
| `design_review_stale` | `<Tela>.review.md` cujo `measured_against_sha` ≠ sha do último commit que tocou o `.tsx` | informativo na v1 (advisory) · vira ≥1 ao hardenizar o ratchet |

Falha → ALERT em `storage/logs/laravel.log`.

> Os 2 checks `design_review_*` (charter page viva — o `<Tela>.review.md` é o relatório de tarefas por tela ao lado do `.charter.md`) são gerados por `prototipo-ui/audit/review-gen.mjs` (`npm run design:review <Mod/Tela>`) e auditados por `review-freshness.mjs` (`npm run design:review:check`) + `tests/Feature/Design/DesignReviewFreshnessTest.php`. Ratchet = `review-freshness-baseline.json` (espelha `config/eslint-baseline.json`, ADR 0209): só FALHA por tela live nova fora do baseline; o baseline só encolhe. Ver proposta `memory/decisions/proposals/design-review-por-tela-charter-page.md` (evolução do loop · mãe 0114/0236/0239).

## 7. Como lidar com batch (várias telas relacionadas)

Telas que fazem sentido juntas (ex: `Repair/Dashboard` + `JobSheet` + `Status`) podem rodar em **batch single F0**:

- 1 entrada em `COWORK_NOTES.md` listando as 3 telas
- 1 protótipo Cowork por tela em `prototipos/<tela1>/`, `<tela2>/`, `<tela3>/`
- 1 critique score por tela
- 1 screenshot approval por tela (Wagner aprova as 3 em sequência)
- 3 PRs separados em F3 (1 por tela) — preserva commit-discipline (1 PR = 1 intent)

## 8. Anti-padrões

- ❌ Editar `prototipos/<tela>/page.tsx` direto no repo — re-exporta do Cowork
- ❌ Pular F1.5 sem `/design-override` — quebra disciplina
- ❌ `[CL]` mergeia sem `[W]` aprovar screenshot — quebra confiança
- ❌ Tabela markdown vira "screenshot" — F2 exige imagem real
- ❌ Loopar F1 → F1.5 → F1 mais de 3x sem ADR — sinal que protocolo é insuficiente, abrir reflexão
- ❌ Mais de 2 telas simultâneas em F3 (`[CL]` perde foco) — backpressure obrigatório
- ❌ Editar `SYNC_LOG.md` no meio (não-append) — log é imutável, só append

## 9. Ciclo de vida de um arquivo `prototipos/<tela>/`

```
[CC] export zip → unzipa em prototipos/<tela>/
                  ↓
[CL] commit em PR de F1 (label: cowork-export, sem build)
                  ↓
[CD] adiciona critique-score.json em PR de F1.5
                  ↓
[W2] aprova screenshot (PR mergeado em main)
                  ↓
[CL] consome em F3 — translate pra <Tela>.tsx (PR separado)
                  ↓
[CA] adiciona a11y-report.md em PR de F3.5
                  ↓
PR de F3 mergeia → tela ativa em prod
                  ↓
prototipos/<tela>/ permanece read-only como histórico
```

## 10. Gatilho canônico de entrada + canal de retorno (loop fechado)

> **Origem:** 2026-05-30 — o roadmap "DS até zero" chegou via 2 snippets genéricos do Claude Design
> ("Fetch this design file…" / "Implement the designs") que **não carregam este protocolo** e **não
> abrem retorno** `[CL]`→`[CC]`. Resultado: `HANDOFF.md` ficou 15d stale, `SYNC_LOG.md` parou, `CODE_NOTES.md`
> morreu, e o Wagner virou carteiro manual de status. Esta seção mata isso.
> Ancora em [ADR 0239](../memory/decisions/0239-governanca-design-system-git-ssot-regressao-ia.md) (git = SSOT · fluxo Cowork→Code→git).

O loop tem **dois** gatilhos, não um. O genérico do Claude Design é só IDA — e ainda incompleto.

### 10.1 Gatilho de IDA — `[CC]`/`[W]` → `[CL]` (substitui os snippets genéricos)

```
<roadmap/tarefa> — executar via protocolo prototipo-ui (ADR 0114 + ADR 0239).

ANTES de tocar código:
1. Ler prototipo-ui/PROTOCOL.md + ADR 0239 (git = fonte única).
2. Salvar os prompts recebidos em prototipo-ui/PROMPT_PARA_CODE_*.md e commitar
   (as URLs claudeusercontent.com expiram em ~1h — git é o SSOT).

EXECUTAR: 1 unidade = 1 branch = 1 PR · lint:baseline:check verde ·
gate visual = CI (PR UI Judge Sonnet 4.5 + visual-regression), NÃO [W2] síncrono ·
merge autônomo `gh --admin` quando todos os checks required verdes (modelo 2026-05-31 · interim, alvo bot `grokwr2`) ·
[W] só entra em Tier 0 (ADR / multi-tenant / segredo / tooling). Ver AUTOMACAO-LOOP-AUTONOMO.md.

REPORTAR DE VOLTA a cada PR mergeado → §10.2.
```

### 10.2 Gatilho de RETORNO — `[CL]` → `[CC]` (o que faltava)

A cada PR mergeado, `[CL]` escreve nos **3 canais que `[CC]` lê via MCP** (webhook GitHub→MCP, ~2min):

| # | Canal | Ação | Conteúdo |
|---|---|---|---|
| 1 | `DS_ADOCAO_INDICE.md` | **`npm run ds:report:write`** | regenera o **placar de tarefas** (checklist ✅/☐ por módulo, entre marcadores `ds:worklist`) — derivado do `ds/*` real, é como `[CC]` sabe **o que `[CL]` já executou e o que falta**, sem regerar o já-feito |
| 2 | `SYNC_LOG.md` | **append** | `YYYY-MM-DD HH:MM [CL] <fase> <módulo> merged · ds/*: <antes>→<depois> · PR #N` |
| 3 | `HANDOFF.md` | **sobrescreve** | "agora": fase/módulo corrente · próximo da fila · `ds/*` total restante |

Placar canônico: **`npm run ds:report`** (`scripts/ds-report.mjs`) — quebra `ds/*` por **regra × módulo** (o baseline agrega tudo sob `no-restricted-syntax`; este separa). Modos:
- **`npm run ds:report:write`** (= `-- --write`) — regenera o **checklist da fila** no `DS_ADOCAO_INDICE.md` (**✅ = `ds/*`=0 concluído · ☐ = pendente**), derivado do estado real. **Rodar a cada PR** — é o "tarefa concluída" que `[CC]` lê (Sync now) pra não regerar o já-feito.
- `-- --worklist` mostra só o checklist no stdout · `-- --json` alimenta a dimensão "Adoção DS" do GovernanceV4.

`[CC]` lê esses 3 via MCP e solta a próxima fila **só do que está ☐** — sem o Wagner copiar status na mão, sem regerar tarefa já concluída.

### 10.3 Por que git, não chat

`[CC]` (Claude Design web) não enxerga o repo direto; enxerga via **MCP** (`mcp.oimpresso.com`), que sincroniza por **webhook GitHub→MCP no merge**. Logo: status que não está **commitado** é invisível pro `[CC]`. Handoff em `memory/handoffs/` (fora de `prototipo-ui/`) **não** fecha o loop — `[CC]` não lê de lá. Os 3 canais de §10.2 são o contrato.

### 10.4 Gate de validação `[CL]` do prompt do `[CC]` — obrigatório, NÃO depende de `[W]`

> **Origem:** 2026-05-30 — Wagner: *"se eu responder eu posso errar? isso não pode depender de mim"*. Um prompt stale do `[CC]` (sync da faxina) mandava re-numerar **ADR 0238 já existente** + renomear colisões 0235/0236 (viola append-only). Se `[W]` aprovasse no automático, quebrava o canon. **A proteção não pode ser `[W]` revisar certo** — é o `[CL]` que valida.

**Passo 0 — ancorar em `origin/main` FRESCO (mecânico, ANTES de qualquer checagem).** "O main" deste gate = `origin/main` **após `git fetch`**, **nunca** o working tree do branch atual (pode estar stale). Primeiras linhas de QUALQUER aplicação do §10.4:

```bash
git fetch origin +refs/heads/main:refs/remotes/origin/main --quiet
git rev-list --left-right --count origin/main...HEAD   # 1º número (esquerda) > 0 = base STALE
```

Se a base está **atrás** de `origin/main`: **toda** validação de existência/canon ("ADR/arquivo/script X existe?", comparar com SPEC/decisions/prototipo-ui) usa `git show origin/main:<path>` / `git ls-tree origin/main` — **nunca** `Read`/`ls`/`Grep` do working copy. Pra produzir/mergear, trabalhe **a partir de** `origin/main` (`git worktree add -b <branch> <path> origin/main`), não do branch stale.

> **Origem do Passo 0:** 2026-05-31 — Wagner: *"isso nunca pode acontecer ... não pode depender de mim"*. O F0 "rotinas-design" rodou o **próprio gate §10.4** lendo um checkout **−47 vs `origin/main`** (`feat/staging-ct100`) → 3 achados factualmente errados (`ds:report` "não existe", canais "stale", G3 "gap") + edits que corromperiam os canais. Pego **por sorte** no "merge", **não pelo gate**. Enforcement automático (não depende de `[W]` nem de `[CL]` lembrar): hook **`git-base-freshness-guard.mjs`** (SessionStart) dá o choque "BASE STALE" sozinho.

Todo `PROMPT_PARA_CODE` / comando de sync do `[CC]` é **proposta, não ordem**. Antes de executar, `[CL]` valida contra o git (`origin/main` fresco, Passo 0) — **sozinho, sem escalar pra `[W]` decidir**:

| Checagem | Como | Se falhar |
|---|---|---|
| **Base do checkout está atrás de `origin/main`** (Passo 0) | `git rev-list --left-right --count origin/main...HEAD` (esquerda > 0) | **refaz** — re-ancora em `origin/main`; descarta achados feitos sobre disco stale (incidente 2026-05-31) |
| Manda criar/numerar ADR que **já existe** | `git ls-tree origin/main \| grep decisions/<nº\|slug>` | **bloqueia** — não duplica |
| Manda **renomear/mutar/renumerar ADR aceito** | append-only é **Tier 0** | **bloqueia** — colisão se *documenta* (gate #1997), não muta |
| Cita **número de ADR** que não bate | comparar com `decisions/` real | **alerta** — provável alucinação/stale |
| Manda trazer `_PROPOSTA-*` / `*.proposto` / faxina-local pro **canon git** | é rascunho do Cowork | **rejeita** — rascunho fica no Cowork, não polui canon |
| Contradiz **decisão canon recente** | `decisions-search` / handoffs | **alerta** — Cowork provavelmente stale |

**Regra de ouro:** se a checagem tem resposta no git, `[CL]` **decide e age — só informa `[W]`**. Escala pra `[W]` **apenas o subjetivo** (estético / estratégico / prioridade / dinheiro). O gate não espera `[W]`.

**Backstop no repo (2ª linha, também sem `[W]`):** hook **`git-base-freshness-guard.mjs`** (SessionStart — choque "BASE STALE" automático, Passo 0) + `AdrNumberCollisionTest` (#1997) + invariante append-only pegam se algo stale chegar a commitar. Lição canon: [feedback-cowork-sync-now-prompt-stale](../memory/reference/feedback-cowork-sync-now-prompt-stale.md).

### 10.5 Handoff bundle estruturado (Claude Design oficial) — entra como PROPOSTA, nunca autoridade

> **Origem:** 2026-06-06 — pesquisa profunda do **Claude Design (Anthropic Labs, 17/abr/2026)** e seu handoff Claude Design→Claude Code. Dossiê: [memory/sessions/2026-06-06-arte-claude-design-handoff.md](../memory/sessions/2026-06-06-arte-claude-design-handoff.md).

O Claude Design oficial empacota o design num **bundle estruturado** (spec machine-readable: estrutura de componentes + design tokens usados no canvas + hierarquia de layout + assets) que o Claude Code lê direto, **sem inferir de pixels** (mesma família de modelo). O **formato** é superior ao nosso export HTML (`visual-source.html`) + mapeamento manual CSS→Tailwind (F3 do RUNBOOK-replicar-prototipo-cowork) — é **ali** que nasce perda de tradução / regressão visual.

**MAS o protocolo oficial é só IDA** — não tem retorno `[CL]→[CC]`, não tem gate de validação, não usa git=SSOT, o spec **não foi publicado** ("muda antes do GA"), o preview **não tem audit log nem versionamento**, e o auto-DS tem **drift não resolvido**. Nossa governança (§10.1–10.4 + ADR 0239) **cobre todos esses buracos** — desde que o bundle entre **pelo §10**, não pelos snippets genéricos que originaram esta seção.

**Regra (Tier 0 deste loop):**

| Aspecto do bundle oficial | Como entra no nosso loop |
|---|---|
| O bundle (spec + tokens + layout + assets) | **Proposta**, igual ao `PROMPT_PARA_CODE_*`: salvar no git ANTES de agir (URLs `claudeusercontent.com` expiram ~1h · §10.1) — git é o SSOT |
| Validação antes de aplicar | passa pelo **gate §10.4** (ancorar `origin/main` fresco · não duplicar/renumerar ADR · não trazer rascunho pro canon) — **sozinho, sem esperar `[W]`** |
| Reporte pós-merge | os **3 canais §10.2** (DS_ADOCAO/SYNC_LOG/HANDOFF) — o oficial não fecha o loop, nós fechamos |
| Auto-DS do codebase (Claude Design lê nosso código → monta DS) | apontar pro **nosso DS v6 canon** (tokens oklch + `REGISTRY_DS_COMPONENTES` + `Components/layout` ADR 0253). O DS que ele gerar é **proposta** validada contra o canon — append-only, **sem** renumerar/mutar token aceito (mesma regra §10.4) |
| **Drift de token do bundle** (token não-canon que o bundle/auto-DS trouxe) | **já coberto** por `foundation-guard.mjs` (ratchet: definição de token só na allowlist `foundations.css`/`cockpit.css` — token novo fora da fundação → conta sobe → bloqueia) + `conformance-gate.mjs` (cor crua/`--accent`). **Não criar gate novo** — os tokens do bundle, ao serem escritos, passam por esses gates existentes |

**O que NÃO muda:** o bundle é insumo de **F1/F3**, não pula F1.5/F2/F3.5 nem o overlay autônomo (gates CI). Adotar o **formato** do bundle (alto impacto, mata o mapeamento manual) depende do "Send to Claude Code" real existir — até lá, esta seção **blinda** o canon contra lock-in/stale quando o bundle chegar.

> **Roadmap reativo (dispara só com o bundle REAL — [ADR 0105](../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) "sinal antes de feature"):** **F-B** (drift de token) = **já coberto** por `foundation-guard` + `conformance` acima · **F-C** (parser do tar estruturado → casa layout no DS v6, mata o mapeamento manual CSS→Tailwind) = **especulativo até o formato sair** (Anthropic não publicou — "muda antes do GA"); estender `cowork-to-inertia` F3.1 **quando o bundle real chegar** · **F-D** (versionar `BUNDLE_<tela>_<sha>`) = §10.1 já manda salvar no git. **Não construir contra formato fantasma.** Dossiê: [2026-06-06-arte-claude-design-handoff](../memory/sessions/2026-06-06-arte-claude-design-handoff.md).

## 11. Links

- [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — mãe
- [ADR 0107](../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — gate F1.5 visual
- [ADR 0109](../memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md) — Claude Design plugin
- [ADR 0104](../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART process
- [ADR 0110](../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — Cockpit V2
- [Skill `mwart-comparative` V4](../.claude/skills/mwart-comparative/SKILL.md) — orquestrador
