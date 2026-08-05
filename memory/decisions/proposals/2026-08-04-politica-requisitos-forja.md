---
status: proposal
title: "Política de requisitos da Forja — o que fazer com os existentes, como nascem os novos, onde vivem os propostos"
proposed_by: Claude (especialista de arquitetura de requisitos) — decisão [W]
proposed_at: 2026-08-04
relates_to:
  - 0070-jira-style-task-management-current-md-removed
  - 0089-capterra-driven-module-evolution
  - 0273-spec-anchor-format-v1
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0303-anchor-gate-entrada-covers
  - 0314-poda-gates-onda-2-lei-fusoes
---

# PROPOSAL — Política de requisitos da Forja

> **Resumo em 1 linha:** as três perguntas têm a mesma resposta de fundo — **o mecanismo já existe e está certo; o que está errado é um default e um nome de arquivo**. A recomendação é majoritariamente **subtração**, não construção.

---

## 0. Como reproduzir tudo que está aqui

Medido em **2026-08-04**, worktree `forja-req`, branch `claude/forja-requisitos-formato-novo`, `git rev-list --count HEAD..origin/main` = **0** (em dia), `git rev-parse --is-shallow-repository` = **false** (clone completo — datas de git sustentam conclusão, §5 2026-07-24).

⚠️ **Condição de medição declarada:** `memory/requisitos/Forja/SPEC.md` estava **sendo editado por outra sessão no mesmo worktree** durante estas medições (`git status` = `M`). Onde o número da baseline importa, medi contra `origin/main` via `git show origin/main:<path>` e digo qual dos dois estou citando. Números de execução de gate (`anchor-lint`) foram tirados do estado **em voo** e estão rotulados como tal.

---

## 1. Contexto — Pergunta 1: os requisitos que já existem

### 1.1 A causa da invisibilidade dos `PMG-*` NÃO é o `us_list`

O enunciado do problema atribui a invisibilidade dos `PMG-*` ao padrão `us_list` do `spec.schema.json`. **Medi e a cadeia causal é outra.**

| Fato | Comando | Resultado |
|---|---|---|
| `us_list` é **opcional** | `sed -n '1,80p' scripts/memory-schemas/spec.schema.json` | `"required": ["module","version","last_updated"]` — `us_list` fora |
| `us_list` **não tem consumidor de código** | `git grep -l "us_list" -- .` (repo inteiro, tracked, **7 de 7**) | `spec.schema.json` · `_TEMPLATE_SPEC.md` · `Cliente/SPEC.md` · `Forja/SPEC.md` (em voo) · 1 CYCLE · 1 audit · 1 session. **Zero scripts, zero workflows, zero PHP.** |
| `SPEC.md` de `origin/main` **nunca teve `us_list`** | `git show origin/main:memory/requisitos/Forja/SPEC.md \| sed -n '1,12p'` | frontmatter: `id/module/owner/version/last_updated/project/default_component/na_justified` |
| O que o `anchor-lint` realmente lê | `sed -n '630,660p' scripts/governance/anchor-lint.mjs` | `US_HEAD_RE = /^(#{2,4})\s+.*\bUS-[A-Z][A-Za-z0-9]*-\d/` — descobre US por **heading**, nunca por frontmatter |

**Conclusão:** `PMG-001` é invisível porque o **heading** `#### PMG-001 · …` não casa `US_HEAD_RE`. O `us_list` nunca reprovou nada porque nunca esteve lá e ninguém o lê. Corolário prático: **preencher `us_list` não compra nada** — é superfície de validação sem consumidor (a mesma família de "campo auto-declarado" das lápides §5 2026-07-01/07-09, aqui na variante inofensiva mas inútil).

### 1.2 O que existe de fato (contagem, não impressão)

Contra `origin/main`:

| Coisa | Comando | Resultado |
|---|---|---|
| ids `PMG-*` citados | `grep -oE '\bPMG-[0-9]{3,4}\b' \| sort -u \| wc -l` | **25** |
| ...mas **headings** `PMG-*` | `grep -cE '^#{2,4}\s+.*PMG-[0-9]'` | **15** |
| ...desses, blocos de US reais | leitura das linhas 36–190 | **12** (`PMG-001..012`) |
| ...e seções de **risco** | linhas 281/292/302 | **3** (`R-PMG-001`, `R-PMG-002`, `R-PMG-005`) |
| `PMG-013..025` | linhas 193–205 | **13 bullets numa lista de backlog** — não são US, não têm bloco, não têm âncora |
| headings `US-TR-*` | `grep -cE '^#{2,4}\s+.*\bUS-[A-Z]…'` | **9** |
| campos `**Implementado em:**` | `grep -c` | **12** (3 em `PMG-001/002/003`, formato antigo com link markdown e **sem** `verificado@sha`) |
| marcadores de aceite (`DoD`/`Aceite`/…) | `grep -cE '^\s*(>\s*)?\*\*(Definition of Done\|DoD\|Aceite\|…)'` | **0** |

### 1.3 Quanto custa renomear — medido, não estimado

A emenda §5 **2026-07-27** exige enumerar **todos** os gates diff-aware que o arquivo casa, não um subconjunto. Varredura: `grep -rlE "requisitos/\*/SPEC\.md" .github/workflows/` → **4 workflows**.

| Eixo | Job | Required? | Morde no rename? | Por quê (medido) |
|---|---|---|---|---|
| âncora | `anchor-lint ADR 0273` | ✅ | **não** | `--check` só sai 1 em `anchored_dead`/`anchored_zombie`/`dead_tests`/`v1_violations` (L1000). Os 3 paths de `PMG-001/002/003` **existem** (conferido um a um). `sem_campo` não reprova. ⚠️ passaria a morder **se** alguém adicionasse `anchor_format: v1` ao frontmatter — os 3 campos antigos não casam a `GRAMMAR_RE`. |
| entrada | `anchor entry/covers gate` | ✅ | **SIM — 6 violações** | O gate só olha US com estado `anchored_ok`/`parcial` (L716-720). Renomeados, `PMG-001/002/003` viram `anchored_ok` → exigem DoD (**há 0 no arquivo**) e `@covers-us` (nenhum teste declara). O grandfather é keyed **por US-ID** (`entry-aceite:<ID>`, 655 entradas) e `PMG-` aparece **0 vezes** lá → id novo = não isento. |
| ratchet | `SDD scorecard ratchet` | ✅ | **não** | `anchor_coverage` é a única métrica armada tocada: baseline **55.2**, direção *up*. Medido full-tree agora: **88% (830/943)**. Cenário A (rename seco): 833/955 = **87,2%** (−0,8pp). Cenário B (rename + `_pendente_` nos 9 sem campo): 842/955 = **88,2%** (+0,2pp). Os dois ficam ~32pp acima da baseline. |
| destilação | `distiller_freshness` (armada, 0, *down*) | ✅ | **não** | `Forja/BRIEFING.md` **não tem `distilled_at:`** → cai no `continue` de "sem carimbo", não em "stale" (mesmo code path medido na emenda §5 2026-07-27). |
| schema | `Schema SPEC.md` | ✅ | **não** | `required` mínimo já satisfeito; `additionalProperties: true`. |

**Ou seja: o custo real do rename são 3 US × (1 bloco de DoD + 1 teste com `@covers-us`).** Não é catastrófico — mas também não é "grátis", e é trabalho de conteúdo, não de renomeação.

### 1.4 A armadilha que um rename cego cria (provada em `node`)

```
US_HEAD_RE.test("### R-PMG-001 · Permission gate")      → false
US_HEAD_RE.test("### R-US-FORJA-001 · Permission gate") → true, id extraído = "US-FORJA-001"
```

Renomear as **3 seções de risco** junto (`R-PMG-001/002/005 → R-US-FORJA-…`) cria **US fantasma com id DUPLICADO** — `US-FORJA-001` existiria como US real *e* como seção de risco. `memory-health` Check N trata US-ID como **único global** (comentário L180 do `anchor-lint`). Um `sed` global sobre `PMG-` faz exatamente isso.

### 1.5 Os "órfãos": TaskRegistry, SPEC-COMPLEMENTO, TeamMcp

| Alegação do enunciado | Medição | Veredito |
|---|---|---|
| "TaskRegistry tem SPEC duplicado/ambíguo" | `anchor-lint --check memory/requisitos/TaskRegistry/SPEC.md` → **0 US detectadas**, coverage `null`, exit 0. Frontmatter já é `status: historical`. Os `US-TR-*` de lá vivem em prosa/tabela, não em heading. | **Não há duplicata mecânica.** O arquivo contribui zero para a máquina. Ambiguidade é só de leitura humana, e o cabeçalho já a resolve em prosa. |
| "Por que dois SPECs (SPEC + SPEC-COMPLEMENTO)?" | `git ls-files ':(glob)memory/requisitos/*/SPEC-*.md'` → **8 arquivos, 7 módulos** (ADS, Forja, Jana×2, Mcp, NfeBrasil, Repair, TaskRegistry). O `anchor-lint` (L810/L813) e o `memory-schema-gate` (glob `memory/requisitos/*/SPEC.md`) só enxergam **`SPEC.md` exato**. | **Padrão de fato, não anomalia da Forja.** `SPEC-COMPLEMENTO` carrega uma 3ª família (`US-PROJ-001..008`) invisível **por nome de arquivo**, não por id — renomear os ids ali não muda nada. |
| "TeamMcp: requisitos ficaram órfãos do módulo deletado" | `ls -d Modules/TeamMcp` → **não existe**. Mas: `anchor-lint --check memory/requisitos/TeamMcp/SPEC.md` → **100% coverage, 6 ok + 1 parcial, 0 dead, 0 zombie, exit 0**; as 7 âncoras apontam para `Modules/Forja/**`; `grep -rn "@covers-us" Modules/Forja/` → **6 marcadores, todos `US-TEAM-*`**. Entry/covers com baseline → exit 0 (2 isentos). | **O vínculo requisito↔código está VIVO e verde.** O que ficou órfão é a **pasta**, não o requisito. Mover = risco de quebrar 7 âncoras verdes em troca de estética de diretório. |

⚠️ **A varredura acima usou `:(glob)`** porque o hook `block-instrumento-sem-porta-viva` interceptou o pathspec cru e provou divergência real (9 vs 8 — §5 2026-07-28). O número correto é **8**.

---

## 2. Contexto — Pergunta 2: como os requisitos novos devem nascer

Pedido literal de [W]: *"as tarefas criadas devem ter aprovação (triagem, backlog, changelog), ou seja status para não sair fazendo tudo sempre"*.

### 2.1 O mecanismo pedido **já existe inteiro**

| Peça | Onde | Estado medido |
|---|---|---|
| Estado "aguardando aprovação" | `McpTask::STATUSES` (L88) | **`backlog` existe** |
| Só pode aprovar ou rejeitar | `McpTask::TRANSITIONS` (L151) | `'backlog' => ['todo','cancelled']` — **literalmente um gate de 2 saídas** |
| Aparece na triagem | `McpTask::scopeTriage()` | `owner IS NULL **OR** priority IS NULL **OR** status='backlog'` — a perna de status **já basta sozinha** |
| Botão aprovar | `ForjaController::aprovar` (L372) | → `todo`, **422 se faltar owner ou priority** (força o enriquecimento antes) |
| Botão rejeitar / fundir | `ForjaController::rejeitar` (L397) / `fundir` (L413) | → `cancelled` (+ evento na fusão) |
| Rotas | `Modules/Forja/Http/routes.php` L346-349 | `POST /forja/{taskId}/aprovar` · `/rejeitar` — **live, não placeholder** |
| Intenção original documentada | migration `2026_05_04_180015_extend_mcp_tasks_for_jira_style.php` L20 | *"adiciona **'backlog' como state inicial**"* |

### 2.2 O defeito é **um default**, não um enum faltando

| Fato | Recibo |
|---|---|
| A tool não expõe `status` | `TasksCreateTool::schema()` — params: `module`, `title`, `owner`, `sprint`, `priority`, `estimate_h`, `blocked_by`, `description`, `author`. **Sem `status`.** |
| O serviço grava `todo` | `TaskCrudService.php:481` → `'status' => $data['status'] ?? 'todo'` |
| E escreve `todo` no SPEC | `TaskCrudService.php:430` → `$fmParts[] = "status: todo";` |
| A coluna nasce `todo` | `ALTER TABLE mcp_tasks MODIFY status ENUM('backlog','todo',…) NOT NULL **DEFAULT 'todo'**` |
| `priority` nunca é null | `TaskCrudService.php:486` → default `'p2'` |

**Consequência exata:** hoje uma task criada por agente nasce `status=todo`, `owner=null`, `priority=p2`. Ela *aparece* na triagem — mas pela perna **`owner IS NULL`**, não pela perna de status. Então: (a) o registro **afirma "aprovada"** sem nunca ter sido; (b) no instante em que alguém atribui um dono, ela **sai da triagem em silêncio, sem jamais passar pelo botão `aprovar`**. O funil existe; a porta de entrada passa por fora dele.

### 2.3 Se mesmo assim se quisesse um enum novo (`proposto`), o que quebra

Levantado para responder o que foi perguntado — **não é a recomendação**:

1. `mcp_tasks.status` é **MySQL `ENUM`** → exige migration DDL `ALTER TABLE … MODIFY`.
2. `McpTask::STATUSES` + `TRANSITIONS` (entrada nova + arestas de/para) + fail-closed de `canTransition`.
3. `scopeTriage`, `scopeOverdue`, `CLOSED_STATUSES`, `isClosed()`, `statusMapFor`/`openBlockers`.
4. `TaskCrudService`: default, writer de frontmatter (L430) e o parser que lê `status:` de volta do SPEC.
5. `TasksCreateTool` + demais tools MCP (`tasks-update`, `tasks-list`, `triage`, `brief`).
6. Colunas do Kanban (`ForjaQuadroService`/Board) e da aba Backlog.
7. Seeds, fixtures e testes que enumeram status.

Sete superfícies **para comprar um comportamento que `backlog` já dá**. Isso é o oposto da fase de subtração declarada nas ADRs 0271/0314.

---

## 3. Contexto — Pergunta 3: onde vivem as propostas de benchmark

### 3.1 O apodrecimento é real e está provado *dentro do próprio arquivo*

Em `origin/main`, `memory/requisitos/Forja/INVENTARIO.md` declara **"Atualizado 2026-05-08"** e **se contradiz na mesma página**:

- linha de resumo: `✅ APROVADO | 13 | 54% | +7 (Fase 1+2 fecharam drag-drop, **Cmd+K**, Tests, …)`
- linha de detalhe #6: `Search global Cmd+K | P0 | **❌** | cmdk lib em package.json, sem uso visível`
- linha de detalhe #12: `Atalhos keyboard completos | **❌** | NADA implementado apesar de doc`

Enquanto isso o `HEAD` de hoje é `ec2d7f852ba feat(forja): atalho '?' abre overlay de ajuda e Enter abre o detalhe no Board`. O doc marca `❌` no que já embarcou — e o desmentido estava **três parágrafos acima, no mesmo arquivo**.

### 3.2 O dono do tema existe — e a Forja saiu dele por um **nome de arquivo**

| Fato | Recibo |
|---|---|
| O anti-podridão canônico é **regeneração**, não gate | ADR 0089 §57: *"`CAPTERRA-INVENTARIO.md` \| **Skill regenera, Wagner não edita à mão** \| a cada execução da skill"*; §71: *"Escreve … (sobrescreve, git diff é o histórico)"* |
| A skill escreve num alvo fixo | `.claude/commands/comparativo.md:39` → `Sobrescreve memory/requisitos/{Modulo}/CAPTERRA-INVENTARIO.md` |
| Existe sentinela de frescor cobrindo esse alvo | `knowledge-drift.mjs:104` → `TRUTH_RE = /^(SPEC\|README\|ARCHITECTURE\|BRIEFING\|**CAPTERRA.***\|CAPTERRA-INVENTARIO\|AUDIT.*\|AUDITORIA.*)\.md$/i` |
| …mas **`INVENTARIO.md` (sem prefixo) não casa** | testado em `node`: `TRUTH_RE.test("INVENTARIO.md")` → **false** |
| …e é **1 de 1 no repo inteiro** | `git ls-files ':(glob)memory/requisitos/*/INVENTARIO.md'` → **1** (só Forja). `CAPTERRA-INVENTARIO.md` → **11 módulos** |
| A Forja tem **os dois** | `ls memory/requisitos/Forja/` → `INVENTARIO.md` (11.500 B) **e** `CAPTERRA-INVENTARIO.md` (15.896 B, frontmatter `generated_by: audit-constituicao`, `generated_at: 2026-05-09`) |

**Diagnóstico:** a Forja tem **dois inventários para o mesmo tema**. O canônico (`CAPTERRA-INVENTARIO.md`) é gerado e vigiado. O órfão (`INVENTARIO.md`) é escrito à mão, **nenhuma skill o regenera e nenhum gate o enxerga** — e foi exatamente ele que apodreceu. É a lápide §5 2026-06-05 ("doc paralelo ao dono do tema") em forma de inventário.

### 3.3 Por que um staleness checker novo **não** resolveria (medido)

Tentador dizer "põe `INVENTARIO.md` no `TRUTH_RE`". Medi e **não teria pego este caso**:

```
git log -1 --format=%ad --date=short origin/main -- memory/requisitos/Forja/INVENTARIO.md
→ 2026-07-30
git log -1 --format='%h %s'  → 6229fb4238d refactor(forja): renomeia Modules/ProjectMgmt -> Modules/Forja (#5089)
```

A data-git diz **2026-07-30**; o conteúdo é de **2026-05-08**. O rename em massa carimbou o arquivo. Staleness por data-git daria **"fresco"** — falso-negativo. É textualmente o incidente **#3714** que motivou o `declaredDoorDate` do `briefing-code-staleness.mjs` (*"a data-git do BRIEFING do Compras foi carimbada em 06-08 por commits [de rename]"*).

E um gate que verificasse se um bucket `❌` está **correto** é predicado **semântico** → advisory por [ADR 0224](../0224-hooks-block-vs-advisory-claude-4.8-aware.md), e cairia na família de guard sintático já morta 4× no §5 (allowlist-de-pasta 06-30 · `@scope` 07-09 · vocabulário 130 FP 07-16 · `toHaveKey` 100% FP 07-26).

---

## 4. Decisão proposta

### Pergunta 1 — requisitos existentes

**D1. NÃO renomear os `PMG-*` em big-bang.** Forward-only + oportunístico: um `PMG-*` vira `US-FORJA-*` **só quando trabalho real já for tocar aquela US**, e a dívida dela (DoD + teste com `@covers-us`) é paga **no mesmo PR**. Base: §5 2026-07-12 + o custo medido em §1.3 (o bloqueio é o `anchor entry/covers gate`, não o ratchet).

**D2. Se o rename for autorizado, na ordem barata → cara:** (a) os **9** sem âncora (`PMG-004..012`) custam **zero** de gate — e ganham `+0,2pp` de coverage se o rename vier com `**Implementado em:** _pendente_` junto; (b) os **3** ancorados (`PMG-001/002/003`) custam **1 DoD + 1 teste com `@covers-us` cada** e só devem ser tocados quando alguém for escrever esse teste.

**D3. As 3 seções `R-PMG-*` NÃO recebem prefixo `US-`.** Renomear para `R-FORJA-001/002/005` (ou manter). Um `sed` global cria US fantasma com id duplicado — provado em §1.4. **Qualquer rename tem que ser cirúrgico, nunca `sed`.**

**D4. `PMG-013..025` não são US e não devem virar US.** São 13 bullets de backlog de Fase 3/4. Não têm bloco, aceite nem âncora. O destino correto delas é o funil da Pergunta 2 (`mcp_tasks` em `status=backlog`, visíveis na Triagem), **não** ids de US no SPEC. Enquanto forem prosa, ficam como prosa honesta — vira US quando ganhar dono, aceite e âncora.

**D5. `TaskRegistry/` e `TeamMcp/`: NÃO MEXER.** Medido: TaskRegistry contribui **0 US** e já é `status: historical`; TeamMcp está **100% verde** com âncoras apontando para `Modules/Forja/**` e 6 `@covers-us` vivos. Tocar é assumir risco de gate por ganho de organização de diretório. Se um dia [W] quiser reconciliar, é ADR própria com o trio (mover US + mover âncora + mover teste) num PR só — nunca `git mv`.

**D6. `SPEC-COMPLEMENTO.md`: não fundir e não proibir.** São **8 satélites em 7 módulos** — padrão de fato. Fundir na Forja acorda os gates (§1.3) e cria inconsistência com os outros 6 módulos. O que **fica registrado** é o fato de que satélite é invisível **por nome de arquivo**: quem quiser que uma US seja medida, escreve em `SPEC.md`.

**D7. NÃO preencher `us_list`.** Zero consumidores em 7 de 7 ocorrências no repo. Preencher compra apenas superfície de validação — e, pior, o dia em que alguém puser um `PMG-*` lá, o schema reprova por um motivo que não tem nada a ver com a invisibilidade real. Se um dia [W] quiser que `us_list` valha, o caminho é dar-lhe um consumidor primeiro, não populá-lo antes.

### Pergunta 2 — requisitos novos

**D8. NÃO criar status novo.** `backlog` **é** o "proposto, aguardando aprovação [W]": já entra na triagem sozinho (`scopeTriage`), já só sai por `todo` ou `cancelled` (`TRANSITIONS`), e o botão que faz isso já existe e já exige dono+prioridade antes de aprovar.

**D9. Mudar o default do caminho de criação para `backlog`** — a mudança mínima que entrega o pedido:
- `TaskCrudService.php:481` → `'status' => $data['status'] ?? 'backlog'` (o campo já é `fillable`);
- `TaskCrudService.php:430` → o frontmatter escrito no SPEC acompanha (`status: backlog`);
- opcionalmente expor `status` no `TasksCreateTool` **restrito a `backlog|todo`**, para que um caminho legítimo de "já aprovado" (ex.: [W] criando direto) continue existindo.

**Isto é mudança de comportamento em caminho vivo → decisão [W]** (§4 de "o que é soberania").

**D10. Prova de mordida (obrigatória antes de mergear D9), sem presence-gate.** O teste não pode medir "o campo status existe"; tem que medir **comportamento**:
- **fixture ruim:** `tasks-create` sem `status` → asserção `status === 'backlog'` **e** a task **aparece** em `McpTask::triage()`; hoje esse teste **falha** (nasce `todo`) — é o failing-first;
- **fixture boa:** após `POST /forja/{id}/aprovar` com dono+prioridade → `status === 'todo'` **e** a task **sai** de `triage()`;
- **controle negativo:** `aprovar` sem dono → **422** e status **inalterado** (prova que o gate morde, não que o campo existe).
Roda no CT 100, nunca local.

### Pergunta 3 — propostas de benchmark

**D11. Um inventário por módulo, com o nome canônico.** `memory/requisitos/Forja/INVENTARIO.md` é o **único** do repo com esse nome (1 de 1) e é o que apodreceu. Ou ele vira `CAPTERRA-INVENTARIO.md` (entra na regeneração da skill **e** no `TRUTH_RE` do `knowledge-drift` de graça), ou é aposentado com lápide apontando para o canônico. **Não manter os dois.**

**D12. NÃO criar staleness checker novo** — duplicaria régua consolidada (§5 2026-07-09) **e** foi medido que não pegaria este caso (§3.3: rename em massa carimbou a data-git).

**D13. A regra de forma para o INVENTARIO: recibo ou ponteiro, nunca afirmação atemporal.** Cada bucket `✅🟡❌` carrega **o comando que o produziu + a data + o que foi medido**, ou aponta para o dono da resposta (`anchor-lint`, `screen-coverage:report`, `casos:report`). É a lápide §5 2026-07-17 aplicada ao inventário. Isto é **política de redação**, não gate — e é exatamente o que a reauditoria em voo desta mesma data já fez ("Como reproduzir esta auditoria").

**D14. Onde a proposta vive entre "benchmarkei" e "[W] aprovou": em `mcp_tasks`, `status=backlog`, `project=FORJA`, visível na aba Triagem** — não em prosa dentro do INVENTARIO. O INVENTARIO é **diagnóstico regenerável e sobrescrevível** (ADR 0089 §71); proposta viva é **task**. Isso fecha o loop com D9: o passo 8 do ADR 0089 (`tasks-create` para os aprovados) passa a nascer no funil correto em vez de nascer dizendo "aprovado".

---

## 5. Alternativas consideradas e por que caíram

| # | Alternativa | Por que caiu |
|---|---|---|
| A1 | Renomear os 25 `PMG-*` em big-bang | §5 **2026-07-12**: tocar legado tira do grandfather. Medido aqui: 6 violações no `anchor entry/covers gate` (required), grandfather é keyed por US-ID e `PMG-` tem **0 entradas** nas 655. |
| A2 | Gate exigindo que `us_list` liste todas as US | Presence-gate (§5 2026-07-01/09) **e** sobre campo com **zero consumidores** (7 de 7 ocorrências medidas). Mediria a presença de uma string. |
| A3 | Enum `proposto` novo | 7 superfícies quebradas (§2.3), incl. DDL em `ENUM` MySQL, para comprar comportamento que `backlog` já dá. Rema contra ADR 0271/0314 (subtração). |
| A4 | Mover as `US-TEAM-*` para `Forja/SPEC.md` | Hoje **100% coverage, 0 dead, 0 zombie, exit 0** (medido). Ganho é estético; risco é quebrar 7 âncoras verdes + 6 `@covers-us`. |
| A5 | Fundir `SPEC-COMPLEMENTO.md` em `SPEC.md` | 8 satélites em 7 módulos = padrão de fato; fundir só na Forja acorda gates e desalinha dos outros 6. |
| A6 | Gate proibindo `SPEC-*.md` satélite | Gate por **nome de arquivo** — família morta 4× no §5; e reprovaria 8 arquivos legítimos. |
| A7 | Staleness checker novo para INVENTARIO/FICHA | Duplica `knowledge-drift`+`briefing-code-staleness` (§5 2026-07-09) **e** medido que daria falso-negativo (data-git carimbada pelo rename #5089). |
| A8 | Gate validando bucket `❌` contra o código | Predicado **semântico** → advisory por ADR 0224. E "capacidade X está ausente" não é decidível sinteticamente. |
| A9 | Doc/índice novo mapeando "qual requisito vive onde" | §5 **2026-07-23** (não criar mapa/painel/índice novo — os donos existem) + §5 **2026-07-25** (índice-por-pergunta foi medido e **não preveniu** o erro que deveria prevenir). |
| A10 | Renomear `R-PMG-*` junto, por `sed` | Cria US fantasma com **id duplicado** — provado em `node` (§1.4). |

---

## 6. Consequências — incluindo o que fica **pior**

**Melhora**
- O pedido de [W] passa a ser cumprido por **1 default**, sem enum novo, sem migration, sem gate novo.
- A Forja para de ter dois inventários competindo, e o sobrevivente entra de graça na regeneração + no sentinela existente.
- Nenhum arquivo legado é tocado em massa → nenhum gate diff-aware é acordado sem trabalho real por trás.

**Piora (assumido conscientemente)**
1. **Cinco famílias de id convivem por tempo indeterminado** na Forja: `PMG-*`, `US-TR-*`, `US-PROJ-*`, `US-TEAM-*`, `US-FORJA-*`. Para o humano isso é feio e confunde. A alternativa (uniformizar) custa o que está em §1.3 e §5/A1. **Escolho a feiura sobre o risco** — mas é feiura real, não neutra.
2. **Doze US da Forja seguem invisíveis à máquina** enquanto não forem tocadas. O `anchor_coverage` global fica ~0,2pp mais otimista do que a realidade da Forja, porque mede um denominador que não as inclui. Não é mentira do medidor (ele mede o que enxerga), mas é ponto cego declarado.
3. **Com D9, o backlog "parece esvaziar"**: toda task criada por agente deixa de nascer pronta-para-pegar. A fila de Triagem cresce e passa a exigir passada humana. Se ninguém triar, o funil entope — e entupimento é *mais visível* que o problema atual, o que é bom, mas é atrito novo.
4. **Aposentar `INVENTARIO.md`** perde a prosa histórica dele. Mitigado pelo próprio ADR 0089 (*"git diff é o histórico"*), mas é perda.
5. **D13 é política de redação, não máquina.** Depende de disciplina — e o §5 é um cemitério de coisas que dependiam de disciplina. Assumo: aqui a alternativa mecânica foi medida e reprovada (§3.3), então disciplina é o teto honesto, não a preferência.

---

## 7. Gate de reversão — como saber que esta decisão foi ruim

| Decisão | Sinal de que caiu | Ação |
|---|---|---|
| **D9** (default `backlog`) | Após **30 dias**: tasks `project=FORJA` em `backlog` crescem monotonicamente e o nº de transições `backlog→todo` via `aprovar` é **0**. Funil virou entupimento, não triagem. | Reverter default para `todo`; o problema é de capacidade humana de triar, não de status. |
| **D9** | Alguma automação legítima quebra por assumir que task nova está `todo` (ex.: cron, brief, seed). | Expor `status` no `TasksCreateTool` restrito a `backlog\|todo` e deixar o chamador declarar. |
| **D1/D2** (rename oportunístico) | Um PR que renomeia **um único** `PMG-*` já pagando DoD+teste ainda assim avermelha um gate não previsto em §1.3. | A enumeração dos 4 workflows estava incompleta → refazer a varredura e **corrigir esta proposta** (não o gate). |
| **D3** | Aparece `US-FORJA-NNN` duplicado no `memory-health` Check N. | Alguém usou `sed`. Reverter o rename daquele bloco. |
| **D11** (um inventário só) | O inventário unificado volta a divergir do código em **< 90 dias** mesmo carregando recibo. | O recibo não basta: aí sim discutir **regeneração agendada** (`/comparativo` em cadência), que é estender o dono do tema — nunca gate novo. |
| **D13** | Dois PRs seguidos adicionam bucket sem recibo e ninguém nota na revisão. | Vira candidato a **advisory** (nunca required, ADR 0224/0314), com FP medido **antes** de instalar. |

---

## 8. O que é decisão **[W]** (soberania — não decido nada disto)

1. **Renomear ou não `PMG-*` → `US-FORJA-*`.** Recomendo forward-only/oportunístico (D1). É renomear identificador de escopo — soberania.
2. **Se renomear: fazer os 9 baratos agora em lote, ou esperar trabalho real em cada um.** Recomendo esperar; a exceção defensável é o lote dos 9 com `_pendente_`, porque tem custo de gate **zero medido** e melhora a cobertura.
3. **Aposentar `PMG-013..025` como ids** e movê-los para `mcp_tasks status=backlog` (D4). É apagar 13 identificadores de um doc canônico — soberania.
4. **Mudar o default de `tasks-create` para `backlog`** (D9). Muda o comportamento de um caminho vivo que o time inteiro usa — soberania, e pareia com a regra de aprovação humana.
5. **Expor ou não `status` no `TasksCreateTool`** (e com quais valores permitidos).
6. **Aposentar `memory/requisitos/Forja/INVENTARIO.md`** (renomear para o canônico ou lápide) — D11. É apagar/renomear capacidade documental — soberania.
7. **Mexer ou não em `TaskRegistry/` e `TeamMcp/`.** Recomendo **não mexer** (D5), com medição a favor. Se [W] quiser reconciliar por organização, é ADR própria.
8. **Promover qualquer coisa daqui a gate.** Nada nesta proposta pede gate novo; se um dia pedir, é flip [W] com mordida provada (ADR 0336).

---

## 9. Resposta honesta: o que **não deve virar mudança nenhuma**

Três das coisas que o enunciado tratava como problema **não são problema**, e o registro disso é parte da entrega:

- **`TaskRegistry/SPEC.md`** — 0 US para a máquina, já `historical`, cabeçalho já desambigua em prosa. **Não mexer.**
- **`TeamMcp/SPEC.md`** — 100% coverage, 0 dead, 0 zombie, `@covers-us` vivos apontando para `Modules/Forja`. A pasta está órfã; **o requisito não está.** Não mexer.
- **`us_list`** — não é a causa da invisibilidade dos `PMG-*` (a causa é o heading), e não tem consumidor. **Não preencher.**

E uma que **é** problema, mas cuja solução é subtração e não construção: o `INVENTARIO.md` órfão — 1 de 1 no repo, fora de toda skill e de todo gate. Não precisa de máquina nova; precisa de deixar de existir em paralelo ao dono do tema.

---

> **Ratificação:** merge desta proposta **não** implementa nada. Cada item de §8 é um ato separado de [W]. Enquanto não houver ratificação, o estado vigente é o medido em §1–§3.
