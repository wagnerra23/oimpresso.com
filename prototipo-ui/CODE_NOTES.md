# CODE_NOTES.md — OUTBOX: Claude Code → Wagner

> Claude Code [CL] escreve aqui. Wagner [W] lê pra acompanhar tradução protótipo → Inertia.
> **Append-only.** Não edita entradas antigas.
> Formato em [PROTOCOL.md §4](PROTOCOL.md).

---

## 2026-05-09 — Setup inicial + auto-check passou

[CL] leu [CLAUDE_CODE_BRIEFING.md](CLAUDE_CODE_BRIEFING.md) seção 5. Respostas auto-check:

1. **Quem aprova merge final?** → `[W]` Wagner. `[CL]` sozinho NUNCA mergeia.
2. **Onde vive o protótipo Cowork?** → `prototipos/<tela-kebab>/page.tsx` (read-only no repo, vem do export Cowork).
3. **Qual skill orquestra este loop?** → `mwart-comparative` V4 (Tier A always-on).

Auto-check OK. [CL] entendeu protocolo.

---

## 2026-05-30 09:43 [CL] → [W] — Sync memória Cowork→Git (espinha + índice temático + ADRs)

**Branch:** `feat/memoria-espinha-indice-tematico` (base `main`). PR aberto pra sua revisão — **não mergeei** (publication-policy). Link do PR no fim desta entrada.

Processei a ponte `PROMPT_PARA_CODE_MEMORIA.md`. Tudo num PR só.

### ✅ Sincronizado
- `prototipo-ui/STATUS.md` — espinha viva (fiel ao Cowork; ver ⚠️ drift DS abaixo)
- `prototipo-ui/CARTA_DESIGN_CC.md` — carta subordinada (ref ADR ajustada → 0247)
- `memory/INDEX_TEMATICO.md` — índice temático (renomeado de `MEMORY_INDEX.md`; **backfill completo 0042–0235**)
- `memory/LICOES_CC.md` · `memory/sessions/2026-05-30-ds-harmonizacao-espinha.md`
- `memory/decisions/0246-…` + `0247-…` (as 2 ADRs, renumeradas) + `README.md` (2 linhas + ponteiro)

### ⚠️ AÇÃO PRA VOCÊ NO COWORK — renumeração de ADR
Os números `0200`/`0201` **já estavam ocupados no git** por ADRs reais e DIFERENTES:
- **0200 real** = `contacts` adota canon sync bidirecional
- **0201 real** = Receita Federal + SEFAZ ConsultaCadastro

Por isso (ADR 0028, monotônica) renumerei as ADRs da sessão pros próximos livres:
- **ADR 0200 (Cowork) → `0246`** (era `0236`, recolidiu) — Harmonização DS + v4.2
- **ADR 0201 (Cowork) → `0247`** (era `0237`, recolidiu) — Carta de Design [CC] subordinada

👉 **No Cowork, atualize STATUS.md / MEMORY_INDEX.md / sessão / CARTA pra citar `0246` e `0247`** (não 0200/0201).

### ℹ️ Escopo estendido (além do que o prompt pediu)
Seed dizia "faltam 0042–0189". O git já tem **239 ADRs (até 0235)** — completei o índice temático até **0235**, senão nasceria defasado. Colisões históricas de número (0101, 0102, 0119, 0141, 0170×3, 0178, 0180, 0195, 0216, 0235) marcadas `(colisão)`; resolvê-las é trabalho à parte.

### ⚠️ Possível drift de DS pra você decidir (NÃO mexi)
`STATUS.md` diz "DS canônico = v4.1 / v4.2 proposto". Mas o git aceitou **ADR 0235 — DS v4 roxo universal + Claude Design como owner da UI** (2026-05-29). Vale reconciliar a numeração (v4.1/v4.2 Cowork × "DS v4" git) pelo loop F0. Deixei STATUS fiel ao Cowork.

### 🔗 PR
https://github.com/wagnerra23/oimpresso.com/pull/1990

---

## Template entradas futuras (copiar e preencher)

```markdown
## YYYY-MM-DD HH:MM [CL] → [W]

### Tela: <Modulo/Tela>
### Status: traduzido | aguardando | bloqueado
### Diff: <link PR | branch local>
### Build: passou | falhou (motivo)
### Charter atualizado: sim | não (motivo)

### Decisões de tradução:
- <protótipo usava X, Inertia usa Y porque...>
- <copy "Sales" virou "Vendas">
- ...

### Pendências:
- [ ] <a11y review F3.5>
- [ ] <screenshot final pra Wagner aprovar merge>

### Notas pra Wagner:
<qualquer coisa que precisa atenção dele>
```

---

## 2026-05-31 [CL] → [W] — Fila COWORK_NOTES processada pelo gate §10.4 (bundle Cowork `ssTxBh7X8X`)

Wagner trouxe 2 design files do Cowork e pediu *"implementar COWORK_NOTES.md / reduzir os loops / automatize, não pergunta, resolve"*. Apliquei o **gate §10.4** (`PROTOCOL.md` — valido contra `main` sozinho; só escalo o subjetivo). **Veredito: a fila do bundle está stale vs `main` — `main` já passou de tudo.** Item a item:

| Item COWORK_NOTES (bundle) | Veredito §10.4 vs `main` | Ação [CL] |
|---|---|---|
| **#1 — cadeia 7→4 hops (com [W] no merge F4)** | **SUPERADO** — `main` já é **0-humano** desde hoje 00:45 (`SYNC_LOG` + `AUTOMACAO-LOOP-AUTONOMO.md`): merge autônomo `--admin`, gate visual = PR UI Judge + visual-regression. O 4-hop com [W]-merge é *menos* enxuto que o adotado. | **NÃO implementei** (seria regressão). |
| **#2 — Regra de Ouro / gate de validação** | **JÁ É CANON** — `PROTOCOL.md §10.4` + ADR 0239 (2026-05-30) cobrem o lado [CL]. O pré-flight 4-gates do lado [CC] é comportamento **Cowork-local** (`LICOES_CC`/`STATUS`, jurisdição [CC]), não `proibicoes.md` do repo. | **NÃO dupliquei** (anti L-01/L-11). |
| **#3 — guards de lint anti-drift** | **PARCIAL** — TAREFA 1 (eslint `ds/*`) **já ativa** (ratchet; baseline 1373→1348 hoje). TAREFA 2 (stylelint `.css`) **não existe** (sem config). Ligar = "lógica de tooling" = FICA humano (AUTOMACAO §4); o pedido pedia *"devolver contagem"*. | Devolvi o drift count (↓). **NÃO liguei guard.** |
| **#4 — Vendas A+ (≥9.5)** | Fora do escopo [CL] — é **F1 design ([CC])**, não F3. | Não processei. |

### Drift count item #3 (devolvido sem ligar guard) — mapa P0 da migração `.css`
- `resources/css/cowork-financeiro-bundle.css`: **188 literais hex** + `--bubble-me: oklch(0.58 0.09 220)` (azul; canon = roxo `oklch(0.55 0.15 295)` — ADR 0235) + `--bubble-me-fg: #ffffff` + `--origin-CRM-*` em hue 220. **P0.**
- `resources/js/Components/cockpit/Sidebar.tsx:1147`: `vibeAccent('workspace')` retorna `oklch(0.58 0.09 220)` (azul). Pode ser intencional (atmosfera ADR UI-0008) — **decisão sua**, não toquei.

### Achado NOVO (o valor real desta passada) — `PROTOCOL.md` drifted vs prática
A "lei" (`PROTOCOL.md`) ainda dizia **7-hop + [W2] screenshot manual + "--admin proibido"** (§2, §10.1) — contradizendo o modelo **0-humano** que você adotou hoje 00:45 (`HANDOFF` + `SYNC_LOG` + `AUTOMACAO-LOOP-AUTONOMO.md`). Loop-cause clássico: o próximo Claude lê a lei stale e re-introduz os gates manuais / recusa `--admin`. **Reconciliei** `PROTOCOL.md` §2 (overlay autônomo) + §10.1 (gate CI, merge `--admin`) — atribuído à *sua* decisão (§10.4: "se a resposta está no git, [CL] decide e age").

### FICA pra você (Tier 0 — não fiz, de propósito)
- **ADR formal do shift 0-humano** (supersede o gate [W2]-manual de ADR 0114/0107). "ADR novo" = FICA humano (AUTOMACAO §4). Não cunho número (colisão `0235` já documentada confirma a regra). Quando quiser, você abre — o overlay no §2 já tem o texto pronto.
- **Nada foi mergeado.** PR `docs/cowork-loop-protocol-10-4` aguarda seu merge — mexe na lei/governança = Tier 0, **não** auto-merge.

### Drift de canal (informo, não corrijo — é decisão sua)
`COWORK_NOTES.md` do repo = *"INBOX: Wagner → Claude Design"*; o do bundle = *"Cowork → Claude Code"*. Definições contraditórias do **mesmo arquivo** = loop-cause. Reconciliar a semântica é subjetivo (§10.4 escala pra [W]).

---

## 2026-05-31 ~07:20 [CL] → [W] — F0 "otimizar rotinas de design" (complementa a fila §10.4 das 07:00)

Chegou OUTRA proposta [CC] (amendment `otimizar-rotinas-design`, URL diferente do bundle `ssTxBh7X8X`). É mais rica que a fila das 07:00: foca **consolidação dos motores de score** (G1 "um motor, não 4" · G2 "um schema" · G6 "não regenerar"), além de repetir hops (G3) e lint (G5). Entreguei o **F0** (medir antes de mexer) e apliquei o **gate §10.4**.

> ⚠️ **Honestidade de processo:** a 1ª versão saiu numa base **stale** (`feat/staging-ct100`, −46 vs `main`) e teve 3 achados ERRADOS. **Refiz contra `origin/main`** (este é o resultado correto). Foi exatamente o modo-de-falha que o §10.4 existe pra pegar — peguei via o próprio gate (cruzar `git show origin/main:` em vez do working tree).

### Gate §10.4 (vs `origin/main` `e443c2ea4`): PASSA, mas a maioria dos G já está feita/superada
| Item | Realidade no main | Veredito |
|---|---|---|
| G4 `ds:report`/`ds-report.mjs` | **já existe** (criado 05-30) | não recriar ✅ |
| Canais stale / [W] carteiro (premissa G4) | **reancorados 05-30**, vivos hoje | premissa histórica, consertada |
| G3 7→4 hops | loop **0-humano** desde 00:45 + ADR 0241 | **superado** (já ~0 hop) |
| G5 ESLint `ds/*` + `REGRAS_DS_LINT` | já ativos (ADR 0209) | metade DONE; não recriar |
| G5 Stylelint `.css` + `REGRAS_STYLELINT_CSS.md` | **não existe** | trabalho novo 🔴 |
| G1 "não duplicar score engine" | **6 motores** já existem | não cunhar `design-score` do zero ✅ |

### Achado NOVO (o valor desta passada — não coberto pela fila das 07:00)
**6 motores de score, em 2 camadas:** a **cara** (LLM `design:*`: `mwart-comparative` dormante desde 05-17 · `design-deep-analysis` **0 disparos na história** · gates F1.5/F3.5 com artefato morto) **morreu de custo**; a **barata/estática** (`screen-grade` 222 telas · `module:grade` D1-D9 · ESLint `ds/*`) **escalou** — e o `PROTOCOL.md` descreve **só a camada morta**. → G1 já aconteceu de fato na camada barata; o caminho não é skill nova, é **religar `mwart-comparative` como aprofundamento sob demanda do `screen-grade`** (as 44 telas <70 do PLANO-DESIGN-TELAS) + aposentar/fundir `design-deep-analysis`.

### FICA pra você [W]
- **Único bloqueador:** ordem de consolidação. Sobra real = **G1/G2/G6** (motores) + **G5-`.css`** (Stylelint). Minha leitura: G5-css e G2 (agregar `screen-grades-baseline.json` + `DS_ADOCAO_INDICE` num `design-report.json`) têm o melhor ROI; G1 é o mais estratégico (mata 3 dicionários de dimensão duplicados).
- **Nada de código mergeado.** Este PR é **só docs** (`prototipo-ui/*.md`) — você autorizou o merge enxuto. ADR de evolução do loop (se quiser) nasce rascunho mãe 0114, sem número.

---

## 2026-05-31 ~21:15 [CL] → [W]/[CC] — Handoff "implementar o Diagnóstico" processado: item 3 (já-canon) + item 2 (health-check de charter) MERGED

Wagner trouxe o bundle Cowork `ZxcA47…` ("implementar o Diagnóstico"). O Diagnóstico é **relatório de saúde** (placar 6.7/10), não tela. Apliquei o gate §10.4 contra `main` na fila COWORK_NOTES (4 itens):

| Item | Veredito vs `main` | Ação [CL] |
|---|---|---|
| #1 ADR peer-review + override ≥98% | **já-canon** (ADR 0238/0241) | NÃO fiz (regressão) |
| #2 health-check de charter (tooling) | **novo** | ✅ **PR #2055 MERGED** |
| #3 oficializar `Financeiro.charter.md` | **stale/já-canon** — tela real = `Unificado/Index.tsx` charter **v9 live**; `Financeiro/Index.tsx` não existe | ✅ dobrei só o feedback novo → **PR #2053 MERGED** |
| #4 auditoria read-only → `design-report.json` | infra pronta no main | não feito (aguarda go) |

### O que mergeou
- **PR #2053** — `Unificado/Index.charter.md` **v9→v10**: +4 anti-patterns de densidade do header (05-31: "7 botões apertado" · sub-páginas→sidebar · "não foi fiel ao domínio" · mock-como-pronto) + **US-FIN-029** (direção "3 lentes + ··· + sidebar" como **intenção PENDENTE**, não-live — registrada assim de propósito pra não cometer o anti-pattern "mock como pronto"). A charter Cowork `Financeiro.charter.md` v1 fica **superada** por este v10 canônico.
- **PR #2055** — `jana:health-check` ganhou 5 checks **advisory** de charter (`CharterHealthChecker`): charter_missing · charter_stale (>90d) · charter_refs_broken · charter_method_missing · readme_handoff_block_missing. 9 testes Pest.

### Achado pro [CC] (o check já se provou na 1ª execução)
`readme_handoff_block_missing` flag um **gap real**: o `prototipo-ui/README.md` canônico **não tem** o marcador `<!-- HANDOFF-ENTRY -->` que o STATUS.md (L-18) exige. Sem ele, um Share→Handoff entrega o projeto mas o Code não acha a fila. **Fix é conteúdo do loop [CC]** — sugiro [CC] regenerar o README preservando o bloco "🤖 Claude Code — COMECE AQUI" + o marcador.

### new_design_memories (CARTA §6.2)
- tipo: doc-novo · ref: `resources/js/Pages/Financeiro/Unificado/Index.charter.md` v10 · resumo: feedback header-densidade 05-31 dobrado na charter canônica (charter Cowork v1 superada).
- tipo: tooling · ref: `Modules/Jana/Services/CharterHealthChecker.php` · resumo: jana:health-check passa a cobrar charter (missing/stale/refs/method/readme-handoff), advisory.
- tipo: anti-padrao · ref: gotcha charter frontmatter · resumo: tocar `.charter.md` acorda o memory-schema-gate — `last_validated` quoted string, `related_adrs` integer/slug (sem `ui/`/`arq/` namespaced).

### FICA [W]/[CC]
- README handoff-entry marker (conteúdo [CC]).
- Item 4 (auditoria read-only → `design-report.json`) — aguarda go.

---

## 2026-06-01 — fila Cowork "Diagnostico de Projeto" (handoff P6u6) · #2 + #3

Wagner reenviou o bundle Cowork (open-file `Diagnostico de Projeto - CC.html`). Pelo `project/README.md`: **o open-file e ponto de entrada, NAO a tarefa** — a fila vive em `COWORK_NOTES -> 📥 Pendentes`. Processei pelo §10.4 (base estava -71 vs origin/main -> worktree isolado off origin/main fresco).

| Item | Veredito | Acao [CL] |
|---|---|---|
| #2 Charters de papel | NOVO (nao no main) | ✅ **PR #2061 MERGED** ([W] 01:33) — ADR 0242 + CHARTER_GOVERNANCA_W + CHARTER_CHAMPION_AGENTES (prototipo-ui/). Tier 0. |
| #3 README HANDOFF-ENTRY | gap real (readme_handoff_block_missing) | ✅ **PR #2062 MERGED** (autonomo, nao-Tier-0). |
| #1 G4 retorno automatico | em andamento | PR Tier 0 (design_return_skipped + workflow pos-merge). |
| #4 auditoria read-only | aguarda go | — |

Detalhes: ADR 0242 e evolucao/aplicacao de 0079/0094/0238/0241 (nao reescrita). Charters em prototipo-ui/ (nao `_DesignSystem/` -> evita design-index-gate "orfao"). Tela de diagnostico construida e **descartada** (era ponto-de-entrada, nao tarefa — confirma o CODE_NOTES anterior "o Diagnostico e relatorio, nao tela").

### Gotchas (new_design_memories)
- `persona: [CC] ...` em frontmatter = YAML invalido (`[CC]` vira flow-seq) -> quotar.
- charter-gate so valida `resources/js/Pages/**/*.charter.md`; design-index-gate so `_DesignSystem/**` -> `CHARTER_*.md` em prototipo-ui/ passa livre.

### FICA [W]
- #1 G4: PR Tier 0 aguarda merge [W]. #4 auditoria aguarda go.

---

## 2026-06-01 [CL] → [W] — Handoff `metricas.html` (Cowork→Code): regras de design no git

### Origem: bundle Cowork "oimpresso-erp-comunicação-visual" (chat33 · `metricas.html`). [W]: "implemente os aspectos relevantes do design".
### Status: Regras 1+2 traduzidas · Regra 3 N/A (justificado) · **aguarda merge [W]** (Tier 0)
### Diff: branch `docs/design-no-dup-trilha` → PR

**Passo 0 (vs `origin/main` fresco):** bundle estava stale — Jana Pro `#2069` e o prep dos 3 Tier 0 de IA `#2073` JÁ em `main` (o bundle dizia "⏸️ não disparar"). Cada item batido contra o `main` antes de agir (L-09).

- **Regra 1 — no-duplicação de design (L-21) → ✅** bullet em `CLAUDE_DESIGN_BRIEFING §7.1`, referenciando o pai em `proibicoes.md` (não duplica).
- **Regra 2 — trilha do tempo / lápide (L-22) → ✅** bullet em `§7.1` + 1 forward-ref em `proibicoes.md` (Memória/governança).
- **Regra 3 — rename shell `Oimpresso ERP - Chat.html` → `oimpresso.com.html` → ⏭️ N/A.** Não há shell vivo no repo: esse HTML só existe em pastas-snapshot (`_arquivo/`, `cowork-2026-05-26-…/`) — o repo guarda bundles Cowork como snapshots datados, não 1 HTML vivo. `metricas.html` é Cowork-local (não vai pro repo, por decisão sua no chat33). Nada a renomear.

### Adaptação §10.4 (não copiei literal): o bullet usa "layout único do shell" em vez de citar `Oimpresso ERP - Chat.html` (inexistente no repo).

### FICA [W]
- Proibição/design = Tier 0 = **seu merge**. Não auto-mergeei.

---

## 2026-06-01 10:45 [CL] → [W]

### Tela: Jana/Pro (+ infra de review por tela)
### Status: traduzido (MERGED)
### Diff: PR #2078 (squash · main 98566bfb4)
### Build: passou (Pest 1m55s · 21/22 CI verde)
### Charter atualizado: n/a (review novo ao lado do charter existente)

Gerador `design:review` (fila COWORK #2) MERGED:
- `review-gen.mjs` (`npm run design:review <tela>`) → `<Tela>.review.md` append-only ancorado por `measured_against_sha`; `review-freshness.mjs` + `review-freshness-baseline.json` (ratchet, espelha eslint-baseline) + Pest `DesignReviewFreshnessTest` + `PROTOCOL §6` (+2 checks).
- 1a exec = `Jana/Pro.review.md` (nota 88) — fecha o gap do #2069 (tela sem review).
- ADR = **proposta sem numero** (`proposals/design-review-por-tela-charter-page.md`) — [W] cunha pra promover a canon (soberania, ADR 0238).

### FICA [W] / proximo
- Fase 2 (juiz-LLM R5/R8/R10 + nota holistica + best_of_class) = custo/cadencia real-mode → espera [W].
- `ui:lint` vermelho **PRE-EXISTENTE** (nao do #2078): `Pro.tsx` (#2069) 2 R1 cor-crua fora do `ui-lint-baseline.json`. Fix = PR separado tokenizando o card dark (decisao de tokens dark). Ja no backlog do `Pro.review.md`.
- Fila restante (Tier 0, top→down): #1 Metodo Migration→Tela · #3 ADR peer-review · #4 IA ENABLE.

---

## 2026-06-02 [CL] → [W] — Arquitetura de Memória/Evolução [CC] formalizada (handoff `ALwoVssQOY…`)

### Origem: bundle Cowork (Share→Handoff) · prompt `prototipo-ui-patch/PROMPT_PARA_CODE_ARQUITETURA-MEMORIA-CC.md` (PROPOSTA §10.4)
### Status: 4 arquivos transportados **verbatim** · **aguarda merge [W]** (docs podem mergear; ratificação ADR é separada)
### Diff: branch `docs/arquitetura-memoria-cc` (base `origin/main` `1e4bb33c4`) → **PR #2106**

**Passo 0 §10.4 (ancorei em `origin/main` FRESCO):** worktree novo off `origin/main`. O `git-base-freshness-guard` se confirmou na prática — minha 1ª checada de cross-refs rodou contra o working tree `feat/staging-ct100` (parado em ADR 0236) e marcou ADR 0238/0239 como "phantom". **Errado.** Contra `origin/main` (até 0242) ambos EXISTEM e batem com o texto. Exatamente o modo-de-falha que o §10.4 Passo 0 descreve — pego pelo gate, não por sorte.

### Paths finais (todos net-new vs `main` — nenhum sobrescrito)
| Artefato | Path no repo | Ação |
|---|---|---|
| Método/raiz do processo | `prototipo-ui/PROCESSO_MEMORIA_CC.md` | criado (vizinho de `PROTOCOL.md`/`CLAUDE_DESIGN_BRIEFING.md`) |
| Charter Produção/Oficina | `prototipo-ui/prototipos/producao-oficina/charter.md` | criado (convenção pasta-por-tela, ex `clientes/`) |
| Register Produção/Oficina | `prototipo-ui/prototipos/producao-oficina/decisoes.md` | criado (irmão do charter → IT2 do PROCESSO passa) |
| Lições [CC] | `memory/LICOES_CC.md` | criado (1ª vez versionado no `main`) |

### Validação §10.4 (passei sozinho, sem [W])
- **Não cunhei número.** PROCESSO referencia ADR **0238** (`soberania-constituicao-wagner`) + **0239** (`governanca-design-system-git-ssot-regressao-ia`) — **verificados reais em `origin/main`**, não inventados aqui. PROTOCOL §10.3/§10.4 reais. Charter referencia ADR 0194/0129/0143 — reais.
- **Nenhum arquivo divergente sobrescrito** — os 4 são net-new (não existiam em `origin/main`).
- **`LICOES_CC` append-only:** não havia base em `main` → commitei o arquivo inteiro (L-01…L-23). **Contíguo, sem buraco/duplicata → o próprio IT4 do PROCESSO passa.** O único erro novo desta sessão Cowork é o **L-23** ("construí a tela de venda FORA do sistema" — reincidência L-02 paleta inventada + L-21 `.html` na raiz).
- **Baseline intacto:** só `.md`, sem código/css/tsx → lint/Pest/stylelint-baseline não mexem.

### Follow-up Tier 0 (NÃO fiz, de propósito)
`PROCESSO_MEMORIA_CC` é **método que governa [CC]**. Ratificação formal = vira ADR → **[W] cunha o próximo número livre (0243) sob seu OK**; não cunho. Docs mergeiam antes; a ratificação é separada e não bloqueia.

### Nada mergeado
Só docs, mas mexe na família de governança do loop (`prototipo-ui/*` + `memory/LICOES_CC`) → **não auto-mergeei**; seu merge.

---

## 2026-06-02 (b) [CL] → [W] — ADR 0243 cunhado (ratificação, sob OK "pode fazer")

[W] autorizou ("pode fazer") → minei **ADR 0243** `memory/decisions/0243-processo-memoria-evolucao-design-cowork.md` na mesma PR #2106 (a ADR ratifica o `PROCESSO_MEMORIA_CC` que vive nela — evita forward-dependency).

- **Número:** 0243 (próximo livre vs `origin/main`, que ia até 0242). Confirmei contra `origin/main`, não o working tree stale.
- **Schema:** valida contra `scripts/memory-schemas/adr.schema.json` (gray-matter + Ajv 2020, mesmo validador do `memory-schema-gate`) — ✅ passa. `status: aceito` + `decided_by: [W]`, padrão dos irmãos 0238/0239/0242 (merge de [W] = ratificação formal).
- **Conteúdo:** R1–R8 (always-read · 3 planos · anéis fonte-única · charter+register irmãos · defesa-que-dispara · medir+gatilho · LICOES append-only · soberania [W]). Consolida 0114/0246/0238/0239/0241/0242 + UI-0013; `supersedes: []`.
- **Índice (ADR 0239 R5):** adicionei 0243 em `INDEX-DESIGN-MEMORIAS.md` (tabela de governança + changelog) — link relativo resolve, `DesignIndexSingleSourceTest` segue verde.
- **Soberania respeitada:** numerei só porque [W] deu o OK explícito (ADR 0238). Continua **sem merge** — ratificação = seu merge da PR #2106.

---

## 2026-06-02 17:16 [CL] → [W]/[CD]

### Tela: shell global (AppShellV2 + cockpit) + 4 telas-núcleo
### Status: handoff Cowork PROCESSADO → main
### Diff: PR #2119 (`5407072ed`) + PR #2121 (`9ba9d8944`) — ambos MERGED `--admin`
### Build: passou (14/14 + 12/12 CI verde)
### Charter atualizado: charters Vendas/Compras mirrorados em `prototipo-ui/prototipos/`

### O que landou (prompts v3 `REFORCO-APPSHELL-TESTES` + `SESSAO-2026-06-02`):
- **A2** accent default **220 azul → 295 roxo canon** — `AppShellV2` escrevia `--accent` inline (de `accentHue=220`) vencendo o cascade sobre `cockpit.css` (ADR 0190); `Sidebar.vibeAccent('workspace')` idem. Guard `CockpitAccentCanonTest`.
- **A1** gate "toda tela Inertia usa AppShellV2" — ground-truth `Inertia::render`→tsx; 224 alvos → 0 violação (allowlist `Site/*` + `AprovacaoPublica`).
- **B** smoke — `CoreScreensIntegrityTest` (roda sempre) + `tests/Browser/CoreScreens` (skip-guarded, CI chromium): Financeiro/Unificado, Compras, Cliente, OficinaAuto/ServiceOrders.
- **C1** CSS — `background:#fff`→`var(--surface)` 30× no `cowork-financeiro-bundle.css` (ratchet stylelint −30; subset seguro só-background).
- **docs** — charters Vendas/Compras + `INVENTARIO_CLASSES.md` mirrorados.
- **CI** — `ui-architecture-gate.yml` + `multi-tenant-gate.yml` (os gates novos rodam de verdade; `ci.yml` antes só rodava `tests/Feature/Form`).
- **guard business_id** recalibrado (#2121) — exempta `=== 0` (no-tenant), biz=1 SaaS → `config('app.saas_owner_business_id')`.

### NÃO feito (de propósito):
- **ADR _PROPOSTA-0245 NÃO mirrorado** — numeração de ADR = soberania [W] (ADR 0238). Cowork numera no git quando [W] aprovar.
- **COMPAT de tokens em `app.css` / `@media`→`@container` em `fin-cowork.css`** — alvos que o próprio Cowork retratou (repo já roxo+warm; `app.css` = manifesto vazio).
- **de-drift `os-*` do `styles.css` Cowork** — shell de protótipo, não existe no repo.
- **CRM** — ainda Blade legado (sem Inertia page), L-26. Migração = programa MWART Tier 0.
- **C1 só o subset seguro** — restam ~158 hex (semânticos/chart/texto) que precisam regressão visual.

### Pendências (pro Cowork/[W]):
- [ ] Numerar ADR _PROPOSTA-0245 no git (soberania [W]).
- [ ] Decidir migração CRM Blade→Inertia (cliente-como-sinal).
- [ ] Fase 2 `os-*` dedup + restante do hex drift (com regressão visual).

---

## 2026-06-02 18:05 [CL] → [W]/[CD]

### Tela: bundle CSS Financeiro (`.fin-cowork`) — dedupe de infra, sem tela específica
### Status: prompt `PROMPT_PARA_CODE_DEDUPE-FINANCEIRO-BUNDLE.md` PROCESSADO → main
### Diff: PR #2127 (`7dff54968`) — squash MERGED `--admin`, 13/13 CI verde
### Build: passou (Vite build + Stylelint ratchet + UI gates)

### O que landou:
- **Removido** `@import "./cowork-financeiro-bundle.css"` do `inertia.css` + **`git rm`** do arquivo (Onda 8 antigo, vencia o cascade). **−327KB / −8658 LOC.**
- README `_cowork-bundle` 2 refs → canon. Rebaseline stylelint **1065→820** (3 entradas do arquivo morto).

### Validação da paridade (re-rodada por [CL], não confiei nos números do prompt):
- Parser CSS brace-aware próprio contra `origin/main`: **2309 regras idênticas · 0 seletor real só-no-antigo** (a "perda de 4" do prompt eram só linhas de **comentário** de header).
- **29 body-diff, TODAS o mesmo único delta** `background: var(--surface)` → `#fff`.
- **No-op visual provado**: `--surface` só vira escuro sob `[data-theme="dark"]`, e **não existe toggler de dark theme em `resources/js`** → `--surface`==`#fff` sempre.

### Correção do prompt (achado [CL]):
- **`.rec-paper` (recibo) está nos 29 diffs mas FALTAVA na lista de 30 do Cowork.** Mesmo delta/no-op. Lista completa = os 30 do prompt **+ `.rec-paper`**.

### ⚠️ Nuance que conecta com o C1 de 17:16:
- O **C1** desta manhã ratcheou `#fff`→`var(--surface)` nos 30 selectors **DENTRO do `cowork-financeiro-bundle.css`** — o bundle que este dedupe **apagou**. Logo o canon volta a `#fff` hardcoded nesses 30 (no-op visual, mas perde o ratchet de token).
- **Trabalho de token-discipline deve mirar o CANON, não o bundle deprecado.** Fica como Fase 2.

### Pendências (pro Cowork/[W]):
- [ ] **Fase 2 hex drift**: portar `var(--surface)` pros 30 selectors `os-*`/`vd-*`/`rec-paper`/etc **NO CANON** (`cowork-canon-financeiro-bundle.css`) + ~158 hex semânticos restantes — com regressão visual.

### new_design_memories
- **golden**: dedupe bundle duplo Financeiro (−327KB; paridade validada por [CL]: 2309 idênticas, 0 seletor real só-no-antigo, 30 OLD→CANON + `.rec-paper` eram todos `var(--surface)`→`#fff` no-op porque dark-theme nunca ativa).
- **conflito**: 2 bundles Financeiro ~327KB ambos `@import`, antigo vence cascade — resolver adotando canon (feito #2127).
- **lição**: token-discipline ratchet (C1) num bundle slated-for-delete vira trabalho perdido — mirar sempre o canon.

---

## 2026-06-02 (c) [CL] → [W]/[CC] — Jana ganha ledger de auto-reflexão de erros de OPERAÇÃO (Reflexion runtime)

### Handoff: `PROMPT_PARA_CODE_JANA-LICOES-REFLEXION.md` (Cowork→Code) · racional `rep-cc-vs-jana` do `metricas.html`
### Natureza: peer-review (L-17) · **Tier 0** (governança de módulo) → PR aberto, **NÃO mergeei**, espera [W]
### Status: PR aberto · branch `feat/jana-ledger-licoes-operacao` · base `main` fresco (`de72198ae`)

### Veredito do peer-review: **procede** (aditivo, ROI alto, espelha 5ª camada já aceita pro [CC])
A Jana já pega erro de **saída** (golden 30Q + RAGAS + drift sentinel) mas não tinha o ledger dos próprios erros de **operação** que **gradua** cada um — lacuna #1 (Aprendizado ~6.5 vs [CC] ~9.0). Não é mecanismo novo: `jana:health-check` já é o harness; é só +1 check.

### Passo 0 contra `origin/main` (não dupliquei nada):
- `LICOES_CC`/`APRENDER-COM-ERRO` **não estão no canon** (só em `_BACKUP-NAO-USAR`, design/[CC]) → ledger da Jana é o **gêmeo runtime**, novo.
- `proibicoes.md` = proibição global, não lição de operação → não toquei.
- `incident-done-checklist` + `feedback-capture` + `jana:health-check` **estendidos**, não recriados.
- **Achado que ancora**: `mcp_webhook_5xx_2h` e `profile_distiller_drift` **já são checks** em `main` → viraram seed (L-OP-001/002) → ledger nasce verde.

### O que entrou (tudo aditivo):
- `Modules/Jana/LICOES-OPERACAO.md` — ledger append-only · formato `### L-OP-NNN` · Erro·Sintoma·Regra·Ref·**Graduação** (MEC→check / JULG→regra) · 3 lições seed reais.
- `jana:health-check` → check **advisory** `jana_lesson_ledger_graduation` (parser `parseLessonLedger()` puro/estático) — acende amarelo se lição malformada/`pendente`; não derruba cron.
- Pest: 4 testes do parser (verde local, incl. ledger canônico) + presença no smoke. `php -l` limpo. Parser validado isolado: **ALL GREEN**.
- `Modules/Jana/SCOPE.md` (+2 linhas) · `incident-done-checklist` **Bloco D** (gatilho) · `feedback-capture` nota de fronteira.
- Proposta §10.4: `memory/decisions/proposals/jana-ledger-licoes-operacao-reflexion.md`.

### Tier 0 respeitado:
- **Não cunhei nº de ADR** (soberania [W], 0238) — é proposal slug-only. [W] numera se promover.
- **Não mergeei** (publication-policy). PR espera [W].

### Decisão aberta pra [W]:
- [ ] Aprovar o ledger como mecanismo canônico da Jana (vira canon ao mergear) ou ajustar o home.
- [ ] Numerar ADR se quiser elevar proposal → decisão.
- [ ] Confirmar check **advisory** (recomendo sim — drift de processo não pagina à noite).

### NÃO reprocessei (a comparação só confirma): guard higiene Cowork L-07/11/21/22 · collector CT100/OTel/LGPD #2073 · `design:review` #2078.

---

## 2026-06-02 (d) [CL] → [W] — TRAVA-SEGUNDA Martinho (biz=164) · Onda 1: net de smoke do núcleo-6

### Handoff: `PROMPT_PARA_CODE_TRAVA-SEGUNDA-MARTINHO.md` (canário Delphi→nuvem · deadline segunda · retenção Kamila)
### Natureza: **Tier 0** (cliente real) → PR aberto, **NÃO mergeei**, espera [W]
### Status: PR aberto · branch `feat/trava-segunda-martinho` · base `origin/main` fresco (`2e9f5881e`)

### Passo 0 §10.4 (confirmadíssimo): **as 6 do núcleo JÁ existem e estão maduras em `origin/main`.** O loop **CU-3→4→5 já encadeia no backend e está testado** (Observer `TransactionObserver`→`TituloAutoService` cria título receber +30d idempotente; emissão NF-e/NFS-e via `NfeEmissaoController`/`NfseController` aceitam `transaction_id`, homolog default, SEFAZ stubável). Job real = **estender + wire-up + estabilizar**, não construir.

### Esta PR (Onda 1 — net de segurança, zero código de produção):
Estende o canon de smoke do núcleo (criado ontem em PR #2119 §B) das **4 telas** pro **núcleo-6 de retenção**:
- `tests/Feature/Architecture/CoreScreensIntegrityTest.php` (Tier 1, roda **sempre**, sem DB/chromium) → +Produto, +Sells (Index+Create), +Fiscal (Cockpit/NF-e/NFS-e). **Verificado local: PASS** (1 assertion, todas as 6 têm `.tsx`+`AppShellV2`+charter). É o net "falha alto" #6 + no-regression #4 do worklist.
- `tests/Browser/CoreScreens/SmokeTest.php` (Tier 2, opt-in chromium) → mesmas telas, âncoras = substrings reais do PageHeader, slug best-effort (CI-tunável, idioma já existente do arquivo).

### Discrepância de dado importante (FICA [W]): "já migrado" vale só pra **Oficina** (91 veículos+SO, biz=164, ADR 0171). O tracker `_pipeline-migracao-legacy.md` diz **clientes/produtos/preços/títulos da Martinho ainda PENDENTES**. Por isso os smokes do loop **semeiam fixture determinística** (canon Pest = DB dev real, padrão `TransactionObserverIntegrationTest`); validação com dados reais biz=164 = passo de prod/staging seu.

### Onda 2 (próxima PR, **gated na sua decisão**):
1. **CU-4 wire-up frontend** — `Sells/Show.tsx` modais `VdNfeEmitModal`/`VdNfseEmitModal` hoje são stub `setTimeout`; backend real existe. Wire pros endpoints (emite em **homologação**). Era marcado "próximo PR" por decisão anterior (KB-9.75) → **preciso do seu OK** pra reativar a partir da venda, ou deixo só backend+smoke.
2. **CU-6 demo seeder** — OficinaAuto idempotente (1 veículo+OS+FSM) p/ demo limpa sem depender de prod.
3. **CU-3→4→5 chain E2E** — 1 teste do encadeamento venda→título→emite NF-e+NFS-e (SEFAZ stub), o "1 teste E2E" do worklist.
4. **1 build limpo** — `npm run build` verificado (parto de origin/main já limpo).

### Congelado (roadmap, NÃO entra segunda): auto-boleto-on-finalize · estoque avançado · BOM · comissão · manifestação entrada · MDF-e/NFC-e · migração real venda/fin Martinho (depende de fonte Firebird).

### Tier 0 respeitado: não cunhei ADR (worklist proíbe 0238/já-tomado); **não mergeei** (publication-policy); base off `origin/main` (não a branch suja).

---

## 2026-06-02 (e) [CL] → [W] — TRAVA-SEGUNDA Onda 2: wire-up CU-4 + demo CU-6 + chain E2E (você disse "conclui tudo, melhor caminho")

### Decisão que tomei (você delegou): **CU-4 = reativar emissão NF-e/NFS-e a partir da venda** (em homologação — seguro). É o coração literal do worklist.
### Status: implementado + **verificado contra o DB dev real** · mesma PR #2135 · **não mergeei** (Tier 0)

### O que entrou (Onda 2):
1. **CU-4 NF-e wire-up** — `VdNfeEmitModal.tsx`: removido o mock `setTimeout`/`Math.random`; `handleTransmit` agora faz `POST /nfe-brasil/transactions/{id}/emitir {modelo:'55'}` (endpoint real que já existia). NF-e B2B (produto); NFC-e 65 fora do núcleo.
2. **CU-4 NFS-e wire-up** — `VdNfseEmitModal.tsx` → `POST /nfse/transactions/{id}/emitir` (**endpoint novo, simétrico**). Adicionei `NfseController::emitirParaTransaction` + rota — reusa o serviço REAL (`NfseEmissaoService::montarPayload`+`despacharEmissaoAsync`), payload no mesmo formato do `StoreNfseRequest`, defaults LC116/ISS do `NfseProviderConfig`. Zero invenção de Model/Service (LICOES_F3).
3. **CU-6 demo seeder** — `OficinaAutoDemoSeeder` idempotente (veículo + OS aberta + itens peça/mão-obra + DVI). NÃO entra no DatabaseSeeder (roda explícito; `OFICINA_DEMO_BUSINESS_ID` ou 1º business). Demo limpa reproduzível sem depender de prod biz=164.
4. **CU-6 smoke** — `DemoOsSmokeTest` (seeder monta documento-vivo + idempotência + stepper check-in→execução).
5. **Chain E2E CU-3→4→5** — `tests/Feature/TravaSegunda/RetencaoLoopE2ETest` — o "vende→fatura→recebe" inteiro.

### Verificação REAL (rodei contra o MySQL dev `oimpresso`, não confiei no papel):
- **Chain E2E: 3 passed / 14 assertions** ✅ — venda a prazo gera título a receber +30d (valor certo) · recebimento baixa o título (quitado + CaixaMovimento) · os 2 endpoints fiscais que a venda dispara existem.
- **php -l limpo** nos arquivos PHP novos. CU-6 smoke roda em CI (módulo OficinaAuto não está migrado no DB dev local → skip resiliente aqui, como o `FsmTransitionTest` existente também faz).
- **Build/tsc:** `npm run typecheck` (resultado anexado no commit/PR).

### Coração da Kamila ✅: o loop financeiro+venda (CU-3→CU-5) está **provado ponta-a-ponta** com observer/título/baixa reais. Oficina (CU-6) tem demo limpa semeável.

### Ainda congelado (roadmap): auto-boleto-on-finalize · NFS-e inline 100% síncrona (hoje async/processando — honesto p/ homologação) · migração real dos dados venda/fin da Martinho (depende da fonte Firebird sua) · estoque/BOM/comissão/manifestação/MDF-e/NFC-e.

---

## 2026-06-02 — [CL] → [W]: Jana "Modo Consultor" (Advisor) — Metade A (clarify reativo)

### Handoff: `PROMPT_PARA_CODE_JANA-ADVISOR-MODE.md` (Cowork→Code) · insight [W]: *"as melhores respostas vêm quando eu pergunto que pergunta eu deveria fazer"*
### Natureza: peer-review (L-17) · **Tier 0** (produto + custo) → PR aberto, **NÃO mergeei**, espera [W]
### Status: PR aberto · branch `feat/jana-advisor-clarify` · base `origin/main` fresco (`2e9f5881e`)

### Veredito do peer-review: **procede** (andaime de raciocínio, não troca de modelo)
Bate com o estado-da-arte: Active Task Disambiguation (ICLR 2025 Spotlight) + INTENT-SIM (NAACL 2025 — decoupla ambiguidade-de-intenção de falta-de-dado). A Jana hoje **chuta** quando é ambíguo e **pergunta** quando é só falta de dado — o erro nº1. Esta capacidade conserta o pior hábito primeiro (Metade A, a mais barata), como você pediu.

### §10.4 Passo 0 contra `origin/main` (estendi, NÃO recriei):
- Chat resolve hoje: `ChatController::send/sendStream` → `LaravelAiSdkDriver::responderChat[Stream]` → `recallMemoria`(MemoriaContrato) + `snapshotContexto`(ContextoNegocio) → `ChatCopilotoAgent` (laravel/ai).
- Precedente de interceptação: `BriefDiarioChatTrigger` já pré-empta o chat por intent — a cascata clarify pluga na MESMA forma, antes do recall/LLM.
- Os 4 Agents (ChatCopiloto/BriefDiario/Sugestoes/Briefing), o brief diário (ADR 0091), a `MemoriaContrato` e o roteamento `laravel/ai` ficaram **intactos**. `ContextoNegocio` é **reusado** (snapshot único serve cascata E chat — zero consulta a mais).

### O que entrou (tudo aditivo, default-OFF):
- **`ClarificadorAgent`** (5º agente) — disambiguador `HasStructuredOutput` que decide `claro|falta_dado|ambiguo` e, se ambíguo, dá a **pergunta de maior ganho de informação**. Roteamento de modelo seletivo via `provider()`/`model()` (config) — **difícil → frontier** (default `gpt-4o` vs `gpt-4o-mini` do chat), mas só dispara no ~20% cinza.
- **`ClarifyCascadeService`** — cascata por latência: **1a heurística local (zero LLM)** resolve ~80% direto; **1b disambiguador frontier** só no cinza. Honestidade (não inventa pergunta), **fail-open** (qualquer erro → responde), **anti-loop** (não pergunta 2× seguidas), **medição** (`clarify_event` no log `copiloto-ai`).
- **`ClarifyResult`** (DTO) + guard `talvezClarificar()` em `LaravelAiSdkDriver` (blocking + stream).
- **Config** `copiloto.clarify.*` (flag `JANA_CLARIFY_ENABLED` default-OFF — mesma postura de `contextual_retrieval`/`peso_real`; com OFF o pipeline é byte-idêntico ao legado).
- **RUNBOOK** `memory/requisitos/Jana/RUNBOOK-jana-advisor-clarify.md` (como ligar/medir).
- Proposta §10.4: `memory/decisions/proposals/jana-advisor-modo-consultor.md`.

### Build / testes:
- **14/14 Pest verdes** (54 assertions): `ClarifyCascadeServiceTest` (9 — heurística, flag-off no-op, curto-circuito, clarifica, honestidade ×2, anti false-clarify, anti-loop, fail-open) + `ClarificadorAgentTest` (5 — routing frontier, instructions INTENT-SIM, grounding não-PII, messages). `php -l` limpo nos 7 arquivos.
- Regressão: suite `Jana/Tests/Feature/Ai` + brief trigger = **50 passaram** com o driver editado. As 2 falhas restantes são **pré-existentes** (`BriefDiarioChatTriggerTest` sem `activity_log` em run isolado — gap do `tests/Pest.php`, não coberto pra Jana), **não-relacionadas** a este PR.

### Tier 0 respeitado:
- **Não cunhei nº de ADR** (soberania [W], 0238) — proposal slug-only. [W] numera se promover.
- **Não mergeei** (publication-policy). PR espera [W].
- Histórico/contexto vão **PII-redigidos** pro disambiguador (defense-in-depth, reusa `PiiRedactor`).

### Decisão aberta pra [W]:
- [ ] Aprovar a Metade A como caminho (vira canon ao mergear) ou ajustar.
- [ ] Numerar ADR se quiser elevar proposal → decisão.
- [ ] Ligar em homolog (`JANA_CLARIFY_ENABLED=true` + escolher o modelo frontier) p/ medir `clarify_event` antes de prod.
- [ ] **Metade B** (próxima-melhor-pergunta proativa, estende o brief por persona) — próxima na fila, spec à parte, como você sequenciou.

---

## 2026-06-02 20:15 [CL] → [W]

### Tela: Fiscal — status unificado (NFC-e/NF-e/NFS-e) · handoff `PROMPT_PARA_CODE_FISCAL-STATUS-UNIFICADO.md`
### Status: traduzido (branch `feat/fiscal-status-unificado`, PR aberto — NÃO mergeado, aguarda F2 [W])
### Diff: branch `feat/fiscal-status-unificado` (off `origin/main` fresco · §10.4 Passo 0)
### Build: vitest 7/7 verde · eslint baseline Δ−60 (sem regressão) · tsc sem erro nos arquivos tocados
### Charter atualizado: n/a (mudança de Componente, não de Page)

### O que landou (reuse-first, só APRESENTAÇÃO — backend SEFAZ intocado):
- **NOVO** `Components/NfeBrasil/FiscalStatusBadge.tsx` — componente ÚNICO de status fiscal. Cobre os 3 documentos (NFC-e 65 · NF-e 55 · NFS-e), 7 estados semânticos (emitting/waiting/authorized/rejected/denied/cancelled/inutilized), 2 variantes (`banner` card + `pill` chip). Fonte única das cores oklch de status (R-DS-002).
- **NOVO** `Components/NfeBrasil/fiscalStatus.ts` — modelo de domínio + helpers (`docLabel`, `emissaoStatusToKind`) separados do .tsx (react-refresh).
- **REFACTOR** `NfceStatusBadge.tsx` → vira wrapper reativo fino: mantém o polling `useNfceStatus` + a nuance "aguardando SEFAZ" (hasGivenUp) e DELEGA a renderização pro FiscalStatusBadge. Backward-compatible (mesma API).
- **WIRE** `Sells/_components/FiscalSection.tsx` → o `StatusBadge` local (emerald/amber/rose Tailwind cru) foi DELETADO; cada linha de emissão agora usa `<FiscalStatusBadge variant="pill">`. Larissa vê o MESMO status do mesmo jeito.
- **TEST** `tests/fiscal-status-badge.test.tsx` (7 casos: helpers + 3 docs autorizada + rejeição + pill + override).

### ⚠️ Correção do achado [CC] (validado contra `main` NESTA sessão — §10.4, não confiei no prompt):
O prompt dizia "4 implementações" — só **2** procedem; corrijo as outras 2 pra não causar regressão:
1. ✅ **`NfceStatusBadge`** era o "bom padrão" — MAS estava **órfão** (importado em lugar nenhum, grep). Agora é a base do componente único.
2. ✅ **Vendas `FiscalSection`** rolava status próprio — REAL duplicata, agora consome o componente. (Os modais `VdNfeEmitModal`/`VdNfseEmitModal` são wizards de EMISSÃO mock — fora de escopo, como o próprio prompt diz.)
3. ❌ **Oficina `ServiceOrderRichSheet`** — NÃO tem badge fiscal NF-e/NFS-e. O `StatusBadge` dele (linha 647) é o status da ORDEM DE SERVIÇO (locação/order_type). Nada a unificar lá.
4. ❌ **NotaDrawer V1 vs V2 — NÃO deletei "o legado".** É o OPOSTO do prompt: **V1 (`Nfe.tsx`) é o FUNCIONAL** (router.post real p/ cancelar/cce/retransmitir); **V2 (`Cockpit.tsx`) é o protótipo** (todos botões `disabled title="Em breve"`, dados mock). Deletar V1 = REGRESSÃO (some cancelar/CC-e/retransmitir reais). Além disso os drawers usam outro paradigma de apresentação (`fx-sefaz` CSS + cstat numérico), não o badge de polling — unificar isso é refactor separado e arriscado.

### Pendências (pro Cowork/[W] decidir — NÃO fiz por serem outro intent/risco):
- [ ] **NotaDrawer V1↔V2:** a resolução correta é MERGER as ações reais do V1 dentro do shell mais rico do V2 (ou migrar `Nfe.tsx`→V2 só depois de portar as 3 ações), não deletar. Precisa decisão de [W] (toca tela fiscal viva).
- [ ] Higiene repo (fora do escopo, achado de passagem): há **arquivos com colisão de caixa** no git que quebram no Windows — `RecurringBilling/.../pt-BR` vs `pt-br/recurringbilling.php` e `Fiscal/Nfe-` vs `nfe-visual-comparison.md`.

### new_design_memories
- **golden**: status fiscal = 1 componente `FiscalStatusBadge` (NFC-e/NF-e/NFS-e · 7 kinds · banner+pill · oklch único); NfceStatusBadge vira wrapper de polling que delega; FiscalSection consome o pill.
- **conflito (corrigido)**: o achado "4 implementações iguais" era ~50% — Oficina não tem badge fiscal e NotaDrawer V1 é o funcional (não legado). Só NfceStatusBadge(órfão)+FiscalSection eram a duplicata real do MODELO de polling.
- **lição**: "deletar o legado" sem ler quem-está-wired pode apagar o funcional — V1 tinha as ações reais; V2 era o protótipo bonito-porém-mock.

---

## 2026-06-02 [CL] → [W]

### Tela: OficinaAuto/ServiceOrders/Board (Quadro Kanban de OS de Mecânica)
### Status: traduzido (build reversível autônomo — CI verde; deploy prod + fiscal real aguardam [W])
### Diff: branch `feat/oficina-kanban-carro-board` (worktree oficina-kanban-carro, base origin/main 84e8cb1a3)
### Build: passou — `tsc` (arquivos novos sem erro), `eslint` (0 erro/0 warning nos novos), `lint:baseline:check` delta −60 (sem regressão), `php -l` ok em todos os PHP.
### Charter atualizado: sim — `Board.charter.md` (schema-compliant) + `Board.review.md` + RUNBOOK.

### Contexto (correção de domínio confirmada por [W] nesta sessão):
- Martinho NÃO é locação de caçamba — é **oficina de mecânica pesada de caminhão** (entra pra reparo/troca de peça). O "caçamba" dos nomes legados (`cacamba_*`, `ProducaoOficina`) é equívoco já corrigido pela ADR 0194. Este port é o **fluxo real do carro**, distinto do board de caçamba.
- [W] confirmou o fluxo de 6 etapas + ancorar num **processo FSM novo** `oficina_mecanica_os` (sem "caçamba"), sem mexer no legado.

### O que foi feito (estender, não recriar — §10.4):
- **Backend**: 3º processo FSM `oficina_mecanica_os` no `OficinaAutoFsmSeeder` (Recepção→Diagnóstico→Aguardando aprovação→Aguardando peças→Em execução→Pronto p/ retirar + terminais Entregue/Cancelado/Garantia). Transições puras (side_effect null — sem estoque ainda). `order_type='mecanica'` mapeado pro processo no `ServiceOrderFsmActionController`. Migration **reversível/idempotente** estende o enum `service_orders.order_type` (não remapeia OS legadas).
- **Controller**: `ServiceOrderController@board` agrupa OS por etapa real do FSM em colunas data-driven (+ KPIs derivados, zero query extra) + rota `/oficina-auto/ordens-servico/board`.
- **Frontend**: `Board.tsx` REUSA `KanbanDndProvider` (generalizado backward-compat com `renderPreview`), `DragConfirmDialog` (+`subjectLabel`), `ServiceOrderRichSheet`, `MercosulPlate`. Card novo `ServiceOrderKanbanCard` (mods [W]). Toggle Quadro|Lista na Index. Create oferece tipo "Mecânica".
- **Drag canon (GUARD)**: arrastar → confirmar → `POST /fsm/execute` → `ExecuteStageActionService` (grava `sale_stage_history`). NUNCA UPDATE direto em `current_stage_id`.

### Modificações [W]-aceitas aplicadas (a crítica [CC]):
1. **Foto REAL no card** (1ª foto de item DVI via Arquivos) — sem foto **esconde o thumb** (ícone câmera discreto; sem placeholder de texto "inacabado").
2. **Contador DVI x/y** com ícone de **checklist** (não cadeado) + tooltip ("x de y itens decididos pelo cliente · N críticos").
3. **Densidade @1280** via **@container** (Tailwind v4 nativo) — KPIs compactos como base, expandem em telas largas. NÃO @media (lição Financeiro F3).
4. **"N OS"** (não "boxes") + colunas **Aguardando aprovação** (âmbar · OK do cliente) distinta de **Aguardando peças** (violeta · peça física).

### Decisões de tradução:
- Colunas NÃO hardcoded — vêm das etapas reais do processo (board se adapta ao seeder).
- Etapas terminais (Entregue/Cancelado/Garantia) saem pelo drawer (FsmActionPanel), não pelo drag.
- OS 'mecanica' sem pipeline cai na coluna Recepção com `in_pipeline=false` (drag off) → abrir card e iniciar pipeline.
- Cores de status via tokens DS (`text-destructive`/`text-success`), não rose/emerald cru (DS-GUARD).

### Pendências (follow-ups — NÃO bloqueiam o build):
- [ ] **[W] aprovar SCREENSHOT** do quadro antes do merge (gate visual F3 · ADR 0107).
- [ ] **Deploy prod + emissão fiscal real** — aguardam [W] (build é reversível/autônomo).
- [ ] P2: derivar `STAGE_TRANSITIONS` de `/fsm/actions` (hoje espelha o seeder, mesma estratégia do Kanban de caçamba).
- [ ] P2: avaliar trait `GuardsFsmTransitions` no `ServiceOrder` (enforcement defense-in-depth do GUARD) — toca model LIVE, decisão [W].
- [ ] P3: smoke browser (Claude in Chrome) usando data-testid `so-card-*`/`board-column-*` pós-deploy.

### new_design_memories
- **doc-novo**: o Kanban do carro (wow) virou tela real em `ServiceOrders/Board` reusando DnD/MercosulPlate/DviPhotoGrid/RichSheet; roda no processo FSM novo `oficina_mecanica_os`. `ProducaoOficina`=caçamba (vertical legado — não confundir).
- **anti-padrao**: thumbnail placeholder de TEXTO ("frente/painel/OBD" listrado) lê "inacabado" na frente do cliente → usar foto real (Arquivos) ou esconder o thumb.
- **golden**: board de FSM = colunas data-driven das etapas reais (não hardcode) + drag dispara `ExecuteStageActionService` (nunca UPDATE direto) + reusar o provider DnD canon generalizado (renderPreview/subjectLabel) em vez de forkar.

---

## 2026-06-03 [CL] → [W] — LIGAR FISCAL REAL Martinho (biz=164): Passo 0 + fix per-business (MERGED #2147)

Resposta ao export design `PROMPT_PARA_CODE_LIGAR-FISCAL-REAL-MARTINHO.md`. **NÃO flipei nada, NÃO emiti nota, NÃO toquei `.env` de prod.** Fiz Passo 0 + um fix de prep que é **pré-requisito de segurança** do cutover.

### Passo 0 — ambiente/credenciais são por-business (não global)?
| Documento | Ambiente (homolog/prod) | Per-business? | Flip biz=164 vaza p/ ROTA LIVRE? |
|---|---|---|---|
| **NF-e 55** | coluna `business.ambiente` → `tpAmb` em `NfeService::criarTools` | ✅ Sim | ✅ Não vaza |
| **NFS-e** | era **GLOBAL** `env('NFSE_AMBIENTE')` no bind do `SnNfseAdapter` | ❌ Era global | ❌ **Vazaria** → corrigido |

O furo (exatamente o que o "não assumir" pega): o NFS-e resolvia ambiente pelo bind global `config('nfse.ambiente')`; o campo per-business `nfse_provider_configs.ambiente` existia mas estava **morto** (só na UI). Flipar NFS-e pra prod = mudar `.env` global = emitir nota real de TODOS os tenants.

### Fix (PR #2147 MERGED, merge `77ced51`, 13 checks verdes)
- `NfseEmissaoService::montarPayload` popula `ambiente`/`municipioIbge` do tenant; fail-safe → `homologacao`.
- `SnNfseAdapter::emitir/buildDps` derivam endpoint+`tpAmb` de `$payload->ambiente` (per-call), não do bind global.
- `AmbientePorBusinessTest` (4 testes DB-free) provando isolamento.
- Fix de ciclo: PHPStan ratchet pediu `@property` no `NfseProviderConfig` (Larastan não via as colunas) → resolvido.

### Pendências
- [ ] **[W]** subir **certificado A1** da Martinho (sem stub: até homologação bate na SEFAZ real e exige o cert).
- [ ] **[W]** regime/CRT + tributação + série/numeração NF-e + município SN-NFSe/ISS (biz=164).
- [ ] **[W]** flip `business.ambiente=1` + `nfse_provider_configs.ambiente='producao'` **só biz=164** (irreversível).
- [ ] **[W]** checkpoint da 1ª nota real no portal SEFAZ → só então abrir gate `auto_emission_enabled` biz=164.
- [ ] Follow-up rastreado: **US-NFSE-015** (eliana, p2) — per-business em `consultar()`/`cancelar()` (escopo "PR separado").

⚠️ **ZERO fiscal real tocado.** Continua 100% homologação até [W] executar o checklist acima. Emissão manual da nota-teste tem que ir com `modelo:'55'` (default do endpoint é `'65'`/NFC-e, sem CSC, fora de escopo).

### new_design_memories
- **golden**: emissão fiscal de produção = cutover controlado **por-business** (homolog → 1 nota teste → checkpoint [W] no portal SEFAZ → abre gate), nunca flip global; credenciais + flip prod = humano (irreversível).
- **anti-padrao**: resolver ambiente fiscal por bind global de container (`config()`/`env()`) num app multi-tenant — vaza emissão real cross-tenant. Ambiente fiscal SEMPRE per-business (coluna do tenant no payload), igual `business.ambiente` do NF-e.

---

## 2026-06-03 [CC] → [W] — Charter Governança [CC]×Jana commitado verbatim (proposta §10.4)

[CL] commitou **verbatim** o charter `prototipo-ui/CHARTER_GOVERNANCA_CC_JANA.md` (export Cowork, irmão de `CHARTER_GOVERNANCA_W.md` + `CHARTER_CHAMPION_AGENTES.md`). Espelha pro git a **conclusão** do report `rep-cc-vs-jana` (`metricas.html`) — pro raciocínio rumo a 9.7 ficar durável e re-derivável do `main`.

- **Frescor validado vs origin/main fresco:** branch `docs/charter-governanca-cc-jana` cortado de `origin/main` @ `74bc2ea` (mesmo SHA que o charter cita no header). Worktree limpo, sem WIP do `feat/staging-ct100` contaminando o PR (1 arquivo, 1 intent — commit-discipline).
- **NÃO numerei ADR** (instrução [W] + o próprio charter: "[W] numera/ratifica se promover a ADR — soberania 0238").
- **PR aberto, NÃO mergeado** — merge é Tier 0 = [W] (publication-policy).

### new_design_memories
- **golden**: export de governança da Cowork (conclusão/raciocínio, não só o número do scorecard) vai pro git **verbatim** como charter irmão, cortado de `origin/main` fresco — durabilidade + re-derivação por sessão futura ([CL]/[CC]).
- **anti-padrao**: commitar doc de governança numa branch poluída (WIP de outro intent) — fura commit-discipline (1 PR = 1 intent). Worktree novo de `origin/main` isola o arquivo.

---

## 2026-06-03 [CL] → [W] — W28 importer Firebird + reconciliação domínio "Caçamba"→caminhão (Martinho biz=164)

### Tarefa: §10.4 PROPOSTA (Tarefa A do prompt `W28-FIREBIRD-DOMINIO-MARTINHO`)
### Status: traduzido · branch `feat/oficina-w28-firebird-dominio` · PR aberto · **NÃO mergeado (Tier 0 = [W])**
### Validação vs `origin/main` (74bc2ea77 · worktree off origin/main, não o working tree):
- `ImportFirebirdMartinhoCommand` confirmado **ESQUELETO W27** (mapping fino não existia) — não duplicquei, completei.
- `scripts/firebird/export-martinho-os.py` **não existia** — criado do zero (template `export-customers.py`).
- Achado que ancora confirmado @main: o importer hardcodava `'vehicle_type' => 'cacamba'` — e **`cacamba` nem é valor válido** do enum `vehicles.vehicle_type` (whitelist real: `caminhao, cavalo, semi_reboque, cacamba_estacionaria, cacamba_avulsa, cacamba_caminhao, recapagem, automovel, motocicleta, outros, outro`). Rodar como estava etiquetava os caminhões da Martinho como caçamba (drift pré-ADR 0194).

### Decisões de tradução:
- **vehicle_type default `cacamba` → `caminhao`** (ADR 0194 · valor canônico de caminhão na whitelist real). `normalizeVehicleType()` mapeia sinônimos de basculante (`cacamba`/`basculante`/`caminhao_basculante`/`cacamba_basculante`) → `caminhao`; valor já-whitelisted é preservado; default `caminhao`. **NÃO inventei** valor fora do enum (o `caminhao_basculante` do docblock e `cacamba_basculante` do README/E2E **não** estão no enum — só funcionavam em SQLite de test).
- **status legacy→FSM** (`normalizeStatus`, accent-fold PT): ABERTA/orçamento→`aberta`, andamento/execução/serviço→`em_servico`, finalizada/fechado/concluída→`concluida`, cancel*→`cancelada`; histórico vazio→`concluida`.
- **order_type** (`normalizeOrderType`): default `manutencao` (bucket do legado — migration `2026_06_02_000001` "novo processo mecanica não mexe no legado"); respeita `mecanica`/`locacao` se vier do JSON.
- **item tipo** → `peca|mao_obra|servico_terceiro`. Idempotência `FB_LEGACY_ID` preservada.
- **Dry-run virou o PADRÃO**: grava só com `--commit` (`--dry-run` vence por segurança se vier junto). "Commit real só com diff aprovado" passou a ser enforced por default, não por disciplina.
- **Docs curados** (append, não reescreve história · L-22): `CHANGELOG.md` ganhou entrada W28 + lápide ADR 0194 ("Caçambas" = nome comercial preservado; *domínio* reclassificado p/ mecânica de caminhão); `README.md` journey passo 2 `cacamba_basculante`→`caminhao` (valor válido).

### Build / Tests:
- `ImportFirebirdMartinhoW28Test` — **7 passed (41 assertions)**, reflection-only (pattern Wave 25/27 do módulo, zero-DB): cobre todos os mappings + contrato "default caminhao, nunca cacamba" + source-grep anti-regressão.
- `php -l` ok nos 2 PHP; `python -m ast` ok no `.py`.
- ⚠️ **Não rodei o caminho DB-real local**: o dev DB local não tem as tabelas OficinaAuto e o suite de migration completo não re-roda do zero localmente (migration pré-existente `ALTER TABLE transactions MODIFY ...` quebra em SQLite; e FK `fin_contas_bancarias→rb_boleto_credentials` fora de ordem em MySQL fresco). Não é do meu diff — roda em CI/MySQL.

### Tarefa B (preflight de cutover observável): **PULADA** (escopo · 1 PR = 1 intent). Reportada como follow-up [W].

### Pendências / fica de [W]:
- [ ] **[W]** decidir se OS históricas devem entrar como `order_type='mecanica'` (domínio real ADR 0194) em vez de `manutencao` (default conservador atual). É decisão de domínio.
- [ ] **[W]** rodar `oficina:migration-report {biz} --detail` + `oficina:sanity-check` no fixture/staging pós-import (prova: vendas órfãs / OS sem NFe / pendentes) — exige DB migrado (CI/staging).
- [ ] **[W]** ajustar o SCHEMA MAP do `export-martinho-os.py` aos nomes REAIS das tabelas do FDB (rodar `--dump-schema` no Windows + Firebird local) antes do export de verdade.
- [ ] **[W]** drift residual a decidir: README/E2E ainda têm `cacamba_basculante` (não-whitelisted, passa só em SQLite) + docblock do Vehicle recomenda `caminhao_basculante` (não-whitelisted) — opção [W]: adicionar valor de enum dedicado via migration OU consolidar tudo em `caminhao`/`cacamba_caminhao`. Não inventei migration de schema.
- [ ] **[W]** import real só contra staging/prod biz=164 (NÃO toquei dado real; fixture/dry-run only).
- [ ] **[W]** mergear (Tier 0 = soberania [W]).

### new_design_memories
- **anti-padrao**: default `vehicle_type='cacamba'` + docs "Caçambas" eram pré-ADR-0194 (domínio = mecânica pesada de caminhão basculante). Pior: `cacamba` nem era valor do enum — rodar o import etiquetava caminhão errado. Reconciliado no W28 (default `caminhao` + normalização contra a whitelist real).
- **golden**: cutover/import irreversível-ish = **dry-run por padrão, grava só com `--commit`** ("interceptar a ação", não confiar na disciplina de lembrar `--dry-run`).

---

## 2026-06-03 [CC] → [W] — Charter Governança [CC]×Jana commitado verbatim (proposta §10.4)

[CL] commitou **verbatim** o charter `prototipo-ui/CHARTER_GOVERNANCA_CC_JANA.md` (export Cowork, irmão de `CHARTER_GOVERNANCA_W.md` + `CHARTER_CHAMPION_AGENTES.md`). Espelha pro git a **conclusão** do report `rep-cc-vs-jana` (`metricas.html`) — pro raciocínio rumo a 9.7 ficar durável e re-derivável do `main`.

- **Frescor validado vs origin/main fresco:** branch `docs/charter-governanca-cc-jana` cortado de `origin/main` @ `74bc2ea` (mesmo SHA que o charter cita no header). Worktree limpo, sem WIP do `feat/staging-ct100` contaminando o PR (1 arquivo, 1 intent — commit-discipline).
- **NÃO numerei ADR** (instrução [W] + o próprio charter: "[W] numera/ratifica se promover a ADR — soberania 0238").
- **PR aberto, NÃO mergeado** — merge é Tier 0 = [W] (publication-policy).

### new_design_memories
- **golden**: export de governança da Cowork (conclusão/raciocínio, não só o número do scorecard) vai pro git **verbatim** como charter irmão, cortado de `origin/main` fresco — durabilidade + re-derivação por sessão futura ([CL]/[CC]).
- **anti-padrao**: commitar doc de governança numa branch poluída (WIP de outro intent) — fura commit-discipline (1 PR = 1 intent). Worktree novo de `origin/main` isola o arquivo.

---

## 2026-06-03 [CL] → [W] — `governanca:scorecard` (camada 3) · placar [CC]×Jana mecanizado

### Tarefa: §10.4 PROPOSTA (prompt `GOVERNANCA-SCORECARD`)
### Status: traduzido · branch `feat/governanca-scorecard` · PR aberto · **NÃO mergeado (Tier 0 = [W])**
### Validação vs `origin/main` (74bc2ea77 · worktree off origin/main):
- `HealthCheckCommand::parseLessonLedger()` + check `jana_lesson_ledger_graduation` confirmados @main — **reusei o parser** (não escrevi outro).
- Os dois ledgers existem: `Modules/Jana/LICOES-OPERACAO.md` + `memory/LICOES_CC.md` (#2106).
- `governanca:scorecard` **não existia** (os arquivos `*scorecard*` no repo são o motor Governance scoped — engine diferente; **não recriei** 7º motor, agreguei · anti-G1).

### Decisões de tradução:
- **`parseLessonLedger($content, $headerPattern)`** — generalizado com 2º arg = regex de header (default `### L-OP-NNN` → backward-compat; ledger [CC] usa `## L-NN`). Grupo 1 = ID.
- **`HealthCheckCommand::ledgerGraduationStats($abs, $header)`** (helper público estático) — `graduadas` (Graduação válida + status:done) / `pendentes` (resto: pendente, malformada OU sem linha de graduação) / `graduation_ratio` (vazio = 1.0).
- **Check `governanca_graduation_ratio`** (ADVISORY) adicionado ao `jana:health-check` — espelha `jana_lesson_ledger_graduation` pros DOIS ledgers; amarelo se algum ratio < 1.0. **Não derruba cron/exit** (drift de processo não pagina à noite).
- **`php artisan governanca:scorecard {--json}`** (Modules/Governance, registrado no provider) escreve `storage/reports/governanca-scorecard.json`: por-ledger total/graduadas/pendentes/ratio + `enforcement_score` (derivado da razão, não digitado) + `health_checks_count` (reflection) + baselines + `measured_against_sha` + timestamp + `condicao_9_7`.
- **Honestidade de escopo**: eixos subjetivos (Tiering de risco etc.) marcados `source: "estimativa [CC]"` no JSON — não finjo objetividade onde não há.

### Build / Tests:
- `GovernancaScorecardCommandTest` — **7 passed (31 assertions)** (parser nos 2 headers + ratio + comando escreve JSON + condição 9.7).
- `JanaHealthCheckTest` (existente) — **8 passed (114 assertions)**, sem regressão (parser default intacto, +1 check, `>=10` mantido).

### 📊 Números REAIS do `main` hoje (74bc2ea77):
- **`operacao` (LICOES-OPERACAO.md): graduation_ratio = 1.0** (3/3 graduadas, 0 pendentes).
- **`cc` (LICOES_CC.md): graduation_ratio = 0.0** (0/25 graduadas — L-01…L-25, nenhuma tem linha `Graduação: …status:done` canônica; L-23 usa `**MEC**` sem binding `check:`/`status:done` → conta pendente).
- `enforcement_score` derivado = **5.0/10** (ratio médio 0.5) · `health_checks_count` = 12 · `condicao_9_7.atingido` = **false** (cc não está 100% + pipe único não setado).
- JSON de exemplo em `storage/reports/governanca-scorecard.json` (gitignored — artefato runtime, não commitado).

### Pendências / fica de [W]:
- [ ] **[W]** o número que move o 9.7 está claro: graduar as 25 lições do `LICOES_CC.md` (formato `- **Graduação:** MEC|JULG · check:\`x\`|regra:\`y\` · status:done`). Hoje 0/25.
- [ ] **[W]** flag `--pipe-unico` é manual por enquanto (pipe único [CC]+Jana). Quando o pipe existir de fato, virar detecção automática.
- [ ] **[W]** promover ADR (slug-only até lá · soberania [W], 0238) se quiser canonizar o scorecard como mecanismo.
- [ ] **[W]** (opcional) `metricas.html` do Cowork passar a LER `storage/reports/governanca-scorecard.json` no re-sync (fecha o loop de frescor sem digitar).
- [ ] **[W]** mergear (Tier 0 = soberania [W]).

### new_design_memories
- **golden**: placar de governança [CC]×Jana deixa de ser prosa digitada e vira saída de check (`governanca_graduation_ratio`) + comando que escreve JSON que o report LÊ — frescor por mecanismo (o ProfileDistiller da governança).
- **regra**: métrica do 9.7 = lições graduadas em check rodável ÷ total, nos dois ledgers; 9.7 exige ambos 100% + pipe único. Hoje: operação 1.0, CC 0.0.

---

## 2026-06-03 [CL] → [W] · Lote design [CC] §10.4 (3 tarefas) — validação vs `origin/main` fresco + entregas

Worktrees off `origin/main` (@ `7a60eddbb`). Validei cada tarefa ANTES de codar (não duplicar). Não numerei ADR (soberania [W], 0238). Não mergeei nada (Tier 0 = [W]). Ordem de merge importa: **1 → 2 → 3**.

### Tarefa 1 — `governanca:scorecard` → **JÁ EM `main` (#2151)**. NÃO dupliquei.
A ponte inteira já landou (é a própria tip do `main`): `GovernancaScorecardCommand` + check `governanca_graduation_ratio` + `parseLessonLedger`/`ledgerGraduationStats` generalizados pros 2 ledgers. Revalidei os números pela **lógica pura do comando** contra os ledgers reais do `main`:
- **`operacao` (LICOES-OPERACAO.md): graduation_ratio = 1.0** (3/3).
- **`cc` (LICOES_CC.md): graduation_ratio = 0.0** (0/25 — as L-01…L-25 usam o formato antigo `Erro·Sintoma·Regra·Ref`, sem linha `**Graduação:**`).
- `enforcement_score` = **5.0/10** (ratio médio 0.5) · `condicao_9_7.atingido` = **false**.
→ Sem PR novo (já mergeado). O número que move o 9.7 segue: graduar as 25 lições do `LICOES_CC.md`.

### Tarefa 2 — `governanca:ciclo-diario` → **PR #2152** (branch `feat/governanca-ciclo-diario`)
Orquestrador diário advisory (06:50 BRT, após health-check/grade/audit/smoke). Regenera estado (reusa scorecard #2151) → `storage/reports/governanca-state.json`; roda frescor (graduation_ratio + charter coverage + review-freshness baseline + protocol_freshness se presente); gradua o inbox `COWORK_NOTES.md`; emite 1 digest/dia. Cron sem `--notify`/ALERT; append a CODE_NOTES só via `--code-notes` (manual, idempotente) — cron não suja arquivo git.

**1º digest real do `main` hoje** (lógica pura do comando · boot Laravel local bloqueado pelo autoload do vendor pinado a worktree removido — Pest roda no CI/CT100, #2076):
```
# Governança — Digest diário (2026-06-03)
> measured_against_sha: 7a60eddbb · enforcement_score: 5/10
- Graduou: operacao 3/3 (100%) · cc 0/25 (0%)
- Acendeu (advisory): graduacao:cc 0% · review-freshness: 21 missing (baseline) · protocol_freshness: ponte pendente (vira "12 gaps" após #2153)
- Inbox [W]: 0 graduada(s) · 5 pendente(s) sem `Graduação:`  (entradas [W]→[CC]: 16:45 2026-05-09, Amendment #316 avatar, Amendment #316 block-renderer, Amendment Cockpit V2.1, F0 PaymentGateway)
- Espera [W] (Tier 0): nada
```

### Tarefa 3 — UC-guards + `protocol_freshness` → **PR #2153** (branch `feat/uc-guards-protocol-freshness`)
Dos 2 docs de Casos de Uso (Vendas UC-V/R/C · Oficina UC-01..10) gerei, **só nas telas canon**, `PRECISA TER` na charter + GUARD Pest `uc-<id>` + check `protocol_freshness` (advisory no health-check, espelha `review-freshness` #2078, ratchet baseline). Fonte única: `prototipo-ui/audit/uc-registry.json`.

**Números reais do `main` hoje** (`node protocol-freshness.mjs` + simulação das asserções Pest):
- **8 UC cobertos** (GUARD verde, todos PASS): UC-V01, UC-V02, UC-V03 (Sells/Create) · UC-01 (Oficina/Create) · UC-03, UC-05, UC-09 (Oficina/Show) · UC-02 (Oficina/Board).
- **12 sem cobertura** (gaps → baseline, acendem advisory): UC-V04, UC-R01, UC-C01, UC-V04S, UC-V05, UC-V06, UC-V07 · UC-04, UC-06, UC-07, UC-08, UC-10.
- **0** guard quebrado · **0** charter ausente · **0** UC morto · **0 regressão**.
- Markers usam **nome de componente** (CustomerSearchAutocomplete, EntryCheckinFields, DviBudgetSection, ApprovalGateCard, FiscalSplitCard, drag) — `data-testid` é esparso no canon; componente é mais estável que i18n.

### Fica de [W] (Tier 0):
- [ ] mergear na ordem **#2151 (já) → #2152 → #2153** (Tier 0 = soberania [W]).
- [ ] graduar as 25 lições do `LICOES_CC.md` (move o 9.7 de 0.5 → 1.0 no lado [CC]).
- [ ] cobrir os 12 gaps de UC (vira `guard:true` no registro + GUARD `uc-<id>` + `--write-baseline`).
- [ ] promover ADR (slug-only até lá) se quiser canonizar ciclo-diário/protocol_freshness.

### new_design_memories
- **golden**: a governança se mantém por um **ciclo diário** que regenera estado + roda frescor + gradua o inbox de [W] + emite 1 digest — tira [W] **e** [CL] do caminho recorrente.
- **golden**: cada UC ("A tela precisa:") vira PRECISA TER (o porquê) + GUARD Pest `uc-<id>` (a trava) — some o elemento = build vermelho; o doc de casos para de defasar (amarrado ao teste, `protocol_freshness` acende o que falta).

---

## 2026-06-03 [CL] → [W] · DS v6 PR1 — tokens de fundação `--stage-*` (PR #2170)

Worktree off `origin/main` (@ `4b3b742e8`). Single-intent: **só `resources/css/cockpit.css`**.

**O que landou:** paleta categórica `--stage-{slate,indigo,rose,emerald,green}` nos blocos light (`.cockpit`) e dark (`[data-theme="dark"]`). Valores idênticos ao `ds-v6/gabarito-vendas.html` e ao `PROMPT_PARA_CODE_DS-V6-TOKEN-DELTA`.

**Garantias:** aditivo / não-Tier-0 (nomes de token novos) · `--accent` roxo 295 **não** redeclarado (ADR 0235/0190 intocado, count=1) · zero hex novo · stylelint passa (oklch permitido/esperado no arquivo de fundação; gate anti-hex/anti-redeclare-accent #2054 não dispara).

**Fora do PR (decisões):**
- de-TODO do `Norte.tsx`: **não entra** — `Norte.tsx` é staging-only (não existe em `main`). A troca do fallback fica em `feat/staging-ct100`. ⚠️ `.nx-root` é ilha dark FIXA fora de `[data-theme="dark"]`: NÃO remover os `--stage-*` locais (cascataria valores light dentro do dark). Mantidos como override de escopo; comentário reescrito (TODO morto).
- Part 2 (`+chroma` nos `-soft`): `cockpit.css` não define `--pos/neg/warn-soft` (usa classes escopadas) → gated, deferido a delta próprio.

**Fica de [W] (Tier 0):** numerar ADR do DS v6 (soberania [W], 0238) se quiser canonizar. PR2 (kit de componentes, ref #2165) e PR3 (port `Sells/Index.tsx`, gate screenshot) seguem depois.

### new_design_memories
- **golden**: buraco do DS vira token na fundação primeiro (PR1 aditivo isolado), tela consome depois — `--stage-*` nasceu assim (receita DS v6, passo 5).
- **gotcha**: token com variante dark só resolve o dark sob `[data-theme="dark"]`; ilha dark fixa (`position:fixed` fora do seletor) precisa redeclarar local, senão `var(--token)` puxa o light.

---

## 2026-06-03 [CL] → [W] · DS v6 PR2 — reuse-mapping do kit `c-*` (PR #2181)

Branch `feat/ds-v6-kit-reuse-map` (base PR1 `2520c8a56`). Single-intent: **só docs** (`REUSE_MAPPING.md` novo + ponteiros em `REGISTRY_DS_COMPONENTES.md` e `DS_ADOCAO_INDICE.md`).

**Passo 0 reuse-first concluído:** mapeei os 11 `c-*` do `showcase.html` → React no repo.
- **8/11 reusam** (Button cowork-primary · Badge/StatusBadge · OsStageBadge · KpiCard · Segmented · MercosulPlate · LinkedApps · +DviPhotoGrid). Régua valida o existente; **nada recriado**.
- **3 gaps = Tier-0, NÃO criados:** `c-id` (ficha 360), `c-tl` (timeline unificada cross-módulo), `c-nba` (próxima-melhor-ação Jana). Catalogados como buraco do DS — nascem na 1ª tela que os consome via MWART (0104) + gate visual (0107/0114) + [W] aprova screenshot. `c-asset` genérico deferido (composição já existe nos Kanbans).
- **Dívida catalogada (não deste PR):** `MercosulPlate.tsx` usa `oklch` cru pro azul Mercosul (cor institucional, não semântica) → tokenizar/exceção = decisão [W] futura.

**Processo:** o trabalho de análise foi feito por um agente background que **travou ~1h15 pós-análise sem commitar**. [W] mandou "assumir e mergear" → finalizei e abri PR. **Não cunhei ADR** (soberania [W], 0238).

**Fica de [W] (Tier 0):** criar (quando portar a 1ª tela de ficha/CRM 360) os 3 componentes `c-id`/`c-tl`-unificada/`c-nba` no `@/Components/ui` consumindo só token, e registrá-los (Onda nova no REGISTRY). PR3 (port `Sells/Index.tsx`) segue separado, com teu screenshot.

### new_design_memories
- **golden**: o reuse-map é o filtro anti-recriação — a maioria do "kit novo" já existe sob outro nome (shadcn/CVA/bespoke); a régua valida, não duplica.
- **gotcha**: componente que vira padrão visual do app é Tier-0 mesmo sendo "só um card" — não nasce solto; nasce amarrado à 1ª tela real, via MWART + gate + [W].

---

## 2026-06-03 [CL] → [W] · DS v6 PR3 kickoff — tokens semânticas + gate /sells (PR #2184)

Branch `feat/ds-v6-semantic-tokens` (worktree off `origin/main`). [W] aprovou "abre o PR3" via AskUserQuestion.

**Entregue (aditivo/não-Tier-0):**
1. `cockpit.css` — `--pos/--neg/--warn` (+`-soft`) light+dark, valores do gabarito DS v6. É a "Part 2" que o PR #2170 adiou; pré-requisito pra `/sells` consumir cor por token em vez de `oklch` escopado. `--accent` 295 intocado; stylelint = baseline 54 (zero delta).
2. `sells-index-dsv6-visual-comparison.md` — gate visual **aprovado [W]** (15 dim, status approved).

**ACHADO DE ESCOPO (importante):** o port real de `/sells` é **campanha multi-slice**, não single-PR. `sells-cowork.css` = **7530 linhas / 559 oklch crus** (muitos com par light+dark manual). Meu comparativo dizia "single-intent, baixo-médio" — **subestimou**. Correção registrada: cada slice (status pills · FSM/stage · KPIs · toolbar · drawer · ageing) entra sozinha, ≤300 LOC, **screenshot-gated** (ADR 0107/0114). `.vd-*`/`.os-*` são escolha deliberada do charter (UX Targets) — migração é cuidadosa, não blast.

**Gotcha CSS:** comentário com `.vd-*/.os-*` contém `*/` → fecha o comentário → `CssSyntaxError`. Corrigido pra `classes .vd- e .os-`. (Lição: nunca por `*/` literal em comentário CSS.)

**Fica de [W]:** decidir ordem/ritmo das slices da tela e dar OK no screenshot de cada uma pós-impl. Os 3 componentes Tier-0 (c-id/c-tl/c-nba) não aparecem em `/sells`.

### new_design_memories
- **gotcha**: `*/` dentro de comentário CSS (ex: `.vd-*/.os-*`) fecha o comentário e quebra o parse — use ` e ` ou espaço.
- **golden**: antes de prometer "single-intent re-skin", medir o CSS alvo (`wc -l` + `grep -c oklch`) — 559 oklch vira campanha, não PR.

---

## 2026-06-03 [CL] → [W] · DS v6 PR3 — /sells re-skin por token (4 slices MERGED)

Gate `sells-index-dsv6-visual-comparison.md` aprovado [W] ("tudo do gabarito" + auto). Campanha em slices ≤300 LOC, cada uma CI-verde → merge `--admin`.

| slice | PR | o que | delta visível |
|---|---|---|---|
| 1 | #2186 | status pills (PILL_STYLE) → --pos/warn/neg | cancelada cinza→**vermelho** |
| 2 | #2187 | camada --vd-ok/warn/bad/neutral → tokens canon | ~neutro + flip dark |
| 3 | #2190 | origem --vd-src-* → --origin-* | balcão→azul · oficina→âmbar · online→verde |
| 4 | #2191 | pipeline FSM dots → --stage-emerald/green | dots na escala de etapas (fecha loop PR1) |

**Estado:** todos os elementos cromáticos que **definem** o gabarito no list view estão em token canônico (flip claro/escuro de fábrica). Restam ~544 `oklch` crus **interiores** (stepper FSM completo, cards do drawer, AI panel `--vd-ai`, hero `--vd-green`, Sparkline) que **não** fazem parte da comparação visível com o gabarito — long tail de diminishing returns.

**Recomendação:** considerar `/sells` list **"batido com o gabarito"** e parar a campanha de auto-merge aqui; o interior do drawer/stepper vira slice sob demanda (com screenshot real no staging) se [W] quiser. Verificação visual real precisa do app de pé (Chrome MCP no staging).

### new_design_memories
- **golden**: re-skin de tela madura por token = redirecionar a camada semântica local (--vd-*/--vd-src-*) na DEFINIÇÃO, não caçar cada oklch — cobertura ampla, diff mínimo, CI-verde.

---

## 2026-06-10 [CL] → [W] · PACOTE-Q9 — execução dos 4 PRs (validados contra main)

Origem: PROMPT_PARA_CODE_PACOTE-QUALIDADE-9-OS ([CC] 2026-06-10). Validação §10.4 mostrou o cache Cowork ATRÁS do repo — PR-1 itens 1-3 e PR-2 item 1 já estavam landados (#2482/#2484/#2485 + dedup bundles 2026-06-02). Executado só o gap real de cada PR.

| PR do pacote | PR GitHub | O que sobrou de real |
|---|---|---|
| PR-1 OS funcional | #2488 | E2E UC-11 caminho Larissa + **bug real pego**: store() não vinculava OS ao veículo (card "sem OS") → fix + 2 Pest |
| PR-3 gates | #2489 | token-role invariante (Camada 1+META 22/22 ✅) + probes G2/G3/G4 browser c/ controle-negativo |
| PR-2 css | #2490 | família morta .os-art* (13 regras, 10 hex) + catraca desce 661→337 / 487→427 / 20213→20204 |
| PR-4 régua | (este) | scorer 242 telas média 87 · fila bottom-16 nomeada · W2=Financeiro (9/16) · accent fora roxo = zero |

### new_design_memories
- **decisão** · [W] 2026-06-10: piso de qualidade = 9; CSS 1-arquivo-por-superfície; duplicata estrutural proibida (espelho/snapshot) — Cowork já deletou 575 e criou IT8. Lado Code: dedup bundles ✅ (2026-06-02), dead-CSS 1-família/PR em curso (fila: vd-drawer 9).
- **anti-padrão** · token `-fg` como superfície / `-bg` como texto → gate PR-3 (#2489): invariante absoluto no conformance-gate.mjs (repo medido 0) + probe DOM-matched no browser. Caso real: barra de progresso marrom com `--origin-MFG-fg` de fill.
- **golden** · probe G1–G6 (classes genéricas, controle-negativo embutido) = espelho Cowork da camada-2 portado por SEMÂNTICA (não arquivo): estático onde dá (G3 regex), browser onde precisa (G2 computed accent-color, G4 overflow com estado "adicionando" ABERTO).

---

## 2026-06-10 [CL] → [CC]/[W] · PACOTE-FINANCEIRO-F2 — 4 PRs MERGED (+1 re-land)

| PR | o que | nota |
|---|---|---|
| #2493 | Type ramp `--fs-1..9` (fundacao + gate) | `foundations.css` NASCE (Camada Fundacoes ADR UI-0013, ja previsto no foundation-guard); `Text` (ADR 0253) consome o ramp 1:1 xs→fs-1…5xl→fs-9; ratchet `fontramp` no conformance-gate (espelho G8), 814 dividas congeladas; SEM sweep global |
| #2494 | US-FIN-029 · 3 lentes no Unificado | `?lente=` clamp caixa · chips refinam DENTRO · KPI-click seta lente · charter v14 · MWART commitado · `UnificadoLentesGuardTest` 6 GUARDs. Menu ··· e topnav JA estavam live (FinanceiroSubNav) — nao refeito |
| #2497 | Drawer 3 camadas (re-land do #2495) | hero FIXO fora do scroll (fs-9 mono + urgencia em palavras + FSM compacto 4 etapas) · DrawerLens primary/10 · conciliada = box discreto · Lente Fiscal ISS 5%/DAS ≈6% + link /financeiro/impostos · 2 white→var(--accent-fg) |
| #2496 | Impostos & obrigacoes | tela nova /financeiro/impostos 100%% derivada (zero tabela): DAS ≈6%% s/ RECEBIDO (regime caixa, espelha kpisCore) · Lancar a pagar idempotente (`metadata.guia`, valor server-side) · NF↔titulo · charter v1 + casos 7 UCs + `ImpostosGuardTest` |

**Divergencias da spec (registradas nos PRs):** contadores por lente FORA (1280px ja carrega ghosts+lentes+primary; qtd vive nos KPIs/chips) · `<FinModuleTopnav>` nao criado (FinanceiroSubNav ja e o shared em uso) · FGTS/DCTFWeb sem folha no sistema = so historico de titulos lancados (honesto, zero mock).

**Gates que morderam no ciclo (e como resolvemos):** ui:lint R1 (+3 stone no segmented → tokens semanticos) · layout-primitives (Δ+8 drawer / +8 Impostos → Inline/Stack/Grid ADR 0253; `grid place-items-center` e idioma permitido) · css-size (comentario de 4 linhas no bundle → 1 linha inline, delta 0) · casos-coverage (frontmatter owner+last_run + `Status:` por UC) · check-scope strict (ImpostosController no SCOPE.md) · layout test do Text atualizado pro ramp (GUARD nunca deletado).

**Fica de [W]:** screenshots F1.5 @1280/@1440 das telas (lentes/drawer/impostos) no staging quando subir — mergeado por ordem explicita "merge" com CI verde; visual fica validavel no staging.

### new_design_memories
- **gotcha**: PR stacked + fila de merge com `--delete-branch` = squash pode entrar NA BASE deletada (conteudo some do main sem erro). Confirmar `baseRefName=main` ANTES de mergear o filho.
- **golden**: gate novo (fontramp) nasce como RATCHET com baseline congelada — adoção tela-a-tela depois, fundacao nunca força sweep.

---

## 2026-06-11 [CL] → [W] · ONDA Q1 — G-3 E2E Playwright vira gate de PR (mandato ONDAS-QUALIDADE-GOVERNANCA)

Origem: PROMPT_PARA_CODE_ONDAS-QUALIDADE-GOVERNANCA ([CC] 2026-06-11, proposta §10.4). Validação Passo 0 contra origin/main 642836124 ANTES de agir.

### Validação §10.4 (estado real vs premissas do prompt)
| Premissa [CC] | Estado real @main | Veredito |
|---|---|---|
| e2e-gate workflow_dispatch não-required | confirmado | EXECUTADO Q1 |
| harness estável (2 verdes 06-10) | runs 27277134411 + 27277247743 ✓ | mas main MUDOU (ver bug abaixo) |
| visual-regression "ainda stub" | DESATUALIZADO: já required (15 contexts, #2553) e roda Pest Browser real | Q4 re-escopado: falta pixel-baseline núcleo-6, não o gate |
| casos:check não marca unverified | DESATUALIZADO: já marca (8 `status:unverified` no baseline) | Q2.1 SUPERADO |
| governance-drift/memory-health não-auditados | auditados agora: ADR 0216 (DriftCheckers+daily) + ADR 0256 (6 checks A-F) substantivos | Q5 re-escopado: faltam registry+frescor+licao_sem_assercao |

### Placar Q1
| passo | prova |
|---|---|
| Re-validação em main ANTES do flip | run 27364711144 🔴 — **3/5 UCs quebrados**: workspace unificado #2551 matou a tela ProducaoOficina (virou redirect 301 pro Board) e os specs ancoravam nela. O gate fez o trabalho dele ANTES de nascer required. |
| Conserto de CAUSA (nunca retry-até-passar, ADR 0261) | PR #2561 MERGED — specs re-ancorados no Board canônico (busca `placa ou cliente`; UC-06: veredito preditivo de arrasto não existe no Board → gate opina no DROP, toast `Transição não permitida`/`OS sem pipeline` + mouse.up no helper) |
| 2 runs verdes seguidos em main pós-fix | 27365585787 ✓ + 27365775694 ✓ |
| Flip workflow_dispatch → pull_request | PR #2560 MERGED — always-run + skip-as-pass dorny/paths-filter (padrão required-readiness ADR 0271 onda 2, idêntico visual-regression #2553). **Desvio consciente do prompt**: `paths:` no trigger criaria deadlock "Expected — waiting" quando required; o repo já tem o padrão provado. workflow_dispatch mantido pra re-validação manual. Context novo: `E2E Playwright · UCs críticos`. |
| Prova sensibilidade (lado 🔴) | PR #2563 (DRAFT, NÃO-MERGEAR): aria-label `Coluna→Etapa` sintético → e2e-gate run 27365999451 🔴, fechado após prova |
| Prova especificidade (lado 🟢) | este PR (docs-only) → e2e-gate skip-as-pass 🟢 em ~1-2min sem pagar boot |
| Required (1 clique [W]) | PREPARADO, aguardando clique — comando no fim desta entrada. Não-bloqueante: segui pra Q2 (regra do mandato). |

### Clique do [W] — promover a required (16º context)
```
gh api -X POST "repos/wagnerra23/oimpresso.com/branches/main/protection/required_status_checks/contexts" -f "contexts[]=E2E Playwright · UCs críticos"
```
(ou Settings → Branches → main → Require status checks → adicionar `E2E Playwright · UCs críticos`)

### new_design_memories
- **gotcha**: tela substituída por redirect (workspace #2551) quebra E2E silenciosamente ENQUANTO o gate é manual — exatamente o intervalo que o flip pra `pull_request` fecha. Specs ancorados em tela morta = a 1ª coisa que um gate de comportamento pega.
- **golden**: required-readiness = `pull_request` SEM `paths:` + dorny/paths-filter skip-as-pass interno (3ª aplicação: visual-regression #2553, governance-drift, agora e2e-gate). `paths:` no trigger de check required = deadlock.

## 2026-06-11 [CL] -> [W] · ONDAS-FINANCEIRO (FA-1..FA-4) — tempero do Financeiro + achados deferidos

Origem: PROMPT_PARA_CODE_ONDAS-FINANCEIRO ([CC] 2026-06-11, §10.4). [W]: "execute na integra ... validando tudo contra origin/main fresco antes". 4 ondas, 1 PR cada, merge autonomo com CI verde. Worktree dedicado off origin/main fresco a cada onda (fin-ondas-fa).

### O que landou
- **FA-1 #2569**: §TEMPERO na fundacao (--sh-1/2, --ease, --t-1/2, --atmo + atmosfera no .cockpit).
- **FA-2 #2572**: snap tipografico (479 font-size px -> var(--fs-1..9); .fontramp-baseline desce).
- **FA-3 #2574**: 18 sombras de elevacao + 58 transicoes -> tokens; text-wrap:balance nos titulos.
- **FA-4 (este PR)**: 7 background:#fff -> var(--surface); desce stylelint+cor baseline do bundle.

### Achados FA-4 DEFERIDOS — sessao com QA visual (mexem em LOGICA/LAYOUT do Unificado/Index.tsx, que e charter-gated; F3 — nao em token de css)
| # | Achado (print live 06-11) | Conserto recomendado | Restricao |
|---|---|---|---|
| FX-1 | Segmented de lente colado no FinSubNav -> le-se "Caixa ... Caixa" (ambiguo) | separar visual: segmented a direita do header (intencao do os-page-h-r) OU gap+divisor. NAO renomear a lente (charter v14 [W]) | layout no Unificado, precisa QA visual |
| FX-2 | Hero "SALDO PREVISTO · MAIO" com a pagina em "Junho 2026" | periodo do hero da MESMA fonte do subtitulo (fonte unica); verificar se e filtro ativo ou label stale | logica frontend Unificado |
| FX-3 | KPI A pagar "prox. 5 jun" (data ja vencida, hoje 11/06) | "prox." = proxima obrigacao FUTURA; vencida vira "vencida ha Nd" (tom destructive) | logica KPI Unificado |
| FX-4 | Linha "-0,00" (FELIPE — COMISSAO) | brl(0) sem sinal + investigar titulo zerado na origem | **SOBREPOE sessao paralela "Financial discrepancy adjustment"** — coordenar, nao duplicar |
| FX-5 | DeltaBadge "↓-100.0%" / "↑+505.8%" gigantes com valor R$ 0,00 | suprimir delta quando valor=0 ou sem base comparavel (ruido) | logica DeltaBadge |

### Outros gaps documentados (NAO implementados as cegas — regra do prompt)
- **Breadcrumb**: "voltar" VERDE cru fora da identidade + telas Fluxo/Conciliacao sem o padrao de breadcrumb do modulo. NAO esta no css fin (e Page-level/Tailwind) -> unificar via token, conferir Fluxo/Conciliacao. Cross-page.
- **Costura venda->titulo** (dominio backend; [CC] nao verificou): confirmar no live que venda faturada aparece no Unificado com vinculo navegavel; se nao, e gap real de pipeline. Verificacao pendente.
- **bg opaco .fin-cowork** (fin-cowork.css:575 `background:#ffffff !important`): superficie solida que (a) COBRE o --atmo da FA-1 e (b) deixa o Financeiro BRANCO no dark (nao vira escuro). E o "bug .fin-body do prototipo" citado na FA-1 ("superficies de tela transparentes"). Conserto = transparent / surface semi-transparente, MAS muda muito o visual -> QA-gated.
- **divida hex congelada**: ~28 hex coloridos (gold/terracota/navy/red) + 30 `color:#fff` (texto branco — sem token "sempre branco" limpo no DS; --accent-fg inverte no dark) ficam congelados; semantico por elemento + QA, sem sweep cego.

### new_design_memories
- **gotcha**: comentario CSS com `*/` (ex "--sh-*/--atmo") FECHA o comentario -> parse error. 2a ocorrencia (1a = DS v6 PR3 ".vd-*/.os-*"). Em comentario use "--sh-1/--sh-2", nunca glob com `*`.
- **gotcha**: snap de box-shadow as cegas e PERIGOSO — `0 0 0 Npx var(--accent-soft)` e anel de FOCO, nao elevacao; virar var(--sh-1) = sombra cinza = regressao a11y. Tokenizar SO sombras de elevacao reais (offset+blur em elemento flutuante/assentado), nunca aneis/insets/hairlines.
- **golden**: descer ratchet por-onda sem sweep — rodar `--all --update` e reverter (git checkout) os baselines/entries fora de escopo, deixando so o que a onda tocou (fontramp na FA-2; cor+stylelint do bundle na FA-4).
- **gotcha**: superficie de tela opaca (`.fin-cowork{background:#fff !important}`) anula a atmosfera da fundacao E quebra o dark mode — atmosfera de shell exige telas transparentes por cima.

---

## 2026-06-11 [CL] → [W] · ONDAS Q2–Q5 — mandato ONDAS-QUALIDADE-GOVERNANCA fechado (12 PRs no dia)

Continuação da entrada Q1 acima (mesma sessão). Validação §10.4 contra main em CADA item; SUPERADOS pulados.

### ONDA Q2 — G-7 honesto + ratchet de cobertura
| item | veredito | prova |
|---|---|---|
| `casos:check` marca ✅-sem-prova como unverified | **SUPERADO** — já marcava (8 no baseline) + meta-teste 2 lados já no CI | tests/casosGuard.spec.ts:213-255 |
| Ratchet SÓ-DESCE do baseline | #2565 — `--check-baseline-shrink` (git-free) + step no casos-gate; escape consciente = label `casos-baseline-grow-approved`; 4 meta-testes | provas vivas: "caiu −16", "caiu −2", "caiu −1" nos PRs seguintes |
| Board P0 re-ancorado | #2566 — `git mv` casos → `ServiceOrders/Board.casos.md` + **4 UCs ganham e2e** (UC-04/05/07/09) + statuses honestos (UC-04=🧪 prova parcial; ex-08/10 → Backlog SEM token) | run verde 27367534698 |
| Coletor merge per-UC | #2567 — runner parcial (Pest) não apaga prova alheia (Playwright); `--no-merge` = reset consciente; 3 meta-testes | prova viva no #2568: "10 preservado(s)" |
| Espelho Financeiro + Sells/Index | #2568 — **DESCOBERTA: RetencaoLoopE2ETest (a prova do fio vende→fatura→recebe) NÃO RODAVA EM NENHUM CI** (fora da allowlist, skip em sqlite). Agora: UC-F01..03 nos títulos + allowlist + seed location/contact + JUnit artifact. +UC-S10 lista de vendas | runs verdes e2e 27368509966 + pest 27368511483 |
| Venda balcão a prazo NA TELA | #2570 — UC-S01 (produto→carrinho→saldo devedor→salva) + produto E2E-0001 no VisregTenantSeeder. 2 causas reais no caminho: H1 vs botão `Salvar venda` disabled; `filterProduct->ForLocation` exigia product_locations | run verde 27368689134 |
| **PROVA Q2** | manifesto **14 UCs / 14 pass** (≥10 ✓) · baseline **433 → 414** · **zero ✅ não-verificado nas 4 P0** | scripts/casos-test-results.json |

### ONDA Q3 — dicionários de domínio ANTES das telas de estoque/faturamento
| item | veredito | prova |
|---|---|---|
| Guard alcança o core | #2571 — `migrations_paths` + `tables_scope` (não cobra tabela alheia em dir compartilhado) + `code_paths` estreitos + **last-write-wins CRONOLÓGICO por basename** (cross-dir determinístico) | 27 meta-testes |
| 5 dicionários grounded | #2573 — vendas/estoque/financeiro/fiscal-faturamento/compras extraídos de 378 migrations core + módulos + squash. **+`vocab`**: transactions.type/status/source são **VARCHAR na física** (BD não constrange; o dicionário é a única lei, Salto #3 o único enforcement) | gate verde nos 6 (0 divergência enum) · 29/29 meta |
| **2 drifts REAIS catalogados (decisão [W] pendente)** | (a) `StoreTransactionRequest` valida `origem in:manual,sells,repair,assinatura,boleto` mas o enum `fin_titulos.origem` = {manual,venda,compra,…} — MySQL non-strict coage inválido pra `''` (classe locação!); (b) `nfse_emissoes` tem **2 vocabulários**: NFSe criou (05/01, rascunho/processando/emitida), NfeBrasil RE-criou (05/11, pending/sent/authorized), 2 models `NfseEmissao`, código NFSe ainda ramifica no antigo | baseline dominio 96→121 (26 débitos de código fotografados, ratchet trava novos) |

### ONDA Q4 — gate visual de pixel deixa de ser stub
| item | veredito | prova |
|---|---|---|
| Passo 0 | premissa "visual-regression stub" DESATUALIZADA: check já required (#2553). O que faltava: **diff de pixel com baseline commitada** | — |
| PixelBaselineTest núcleo-6 | #2575 — pixelmatch NATIVO do pest-plugin-browser (threshold 0.3 · maxDiffPixels 300 · ratio 1% · AA) via auth-bridge; step ADVISORY dentro do job required (`continue-on-error` só nele — promover = remover o flag, SEM clique de protection) | baselines .snap commitadas (115–204KB) |
| Flakiness CAÇADA na causa (3 iterações com diff-views artifact) | (a) baseline pré-paint (2KB, "?" de fonte) — networkidle do plugin não basta → settle 1.5s; (b) variância subpixel de CONTROLES NATIVOS (selects/inputs date) + valor vivo "Data da venda" → CSS visibility:hidden preserva layout e zera variância | artifacts pixel-diff-views runs 27370651063/27370956421 |
| `visreg:update` | npm script — update NUNCA automático (aprovação [W] F1.5) | package.json |
| **PROVA Q4 (2 lados)** | 🟢 especificidade: run 27371559873 verde vs baseline · 🟡 sensibilidade: PR #2580 sintético (h1 vermelho, mudança SÓ-visual — e2e textual verde, pixel acusou) run 27371822166, fechado pós-prova | #2575 MERGED |

### ONDA Q5 — meta-gates (o processo se autocobra)
| item | veredito | prova |
|---|---|---|
| Passo 0 | governance-drift (ADR 0216) + memory-health (ADR 0256 A–F) + 2 meta-gates JÁ substantivos — nada recriado | — |
| Registry canônico de gates | #2578 — `scripts/governance/gates-registry.json` (54 workflows, nome+classe). Check G **fail-class**: workflow novo fora do censo = 🔴 mecânico; entrada órfã = 🟡 | roda em TODO PR (umbrella) + daily |
| Frescor doc-cache | Check H: `✓lido @main <data>` >14d = 🟡 | idem |
| licao_sem_assercao | Check I: lição sem gate/G#/IT# nem `não-mecanizável:` = 🟡 (14 atuais sinalizadas) | idem |
| **PROVA Q5** | 9/9 meta-testes físicos 2 lados (tests/memoryHealth.spec.ts) no umbrella | — |

### Pendências [W] (decisões, não trabalho)
1. **1 clique required Q1**: `gh api -X POST "repos/wagnerra23/oimpresso.com/branches/main/protection/required_status_checks/contexts" -f "contexts[]=E2E Playwright · UCs críticos"`
2. Drift `origem` do StoreTransactionRequest (alinhar request ↔ enum fin_titulos) — catalogado em memory/dominio/financeiro.md
3. Consolidação NfeBrasil×NFSe (2 vocabulários da mesma tabela) — catalogado em memory/dominio/fiscal-faturamento.md
4. Pixel-diff: após 2 verdes pós-merge, remover `continue-on-error` (advisory → enforcing, sem clique)

### new_design_memories
- **golden**: artifact de DIFF VIEW antes de chutar máscara — 3 iterações de pixel-flakiness resolvidas na CAUSA (pré-paint + controles nativos), nunca por retry.
- **gotcha**: `Carbon::setTestNow` no processo de teste NÃO congela o relógio do `artisan serve` (cross-process) — conteúdo dinâmico de servidor se neutraliza no DOM (CSS), não no clock do test runner.
- **golden**: vocabulário sem constraint física (coluna varchar) = `vocab` no dicionário — o gate vira a ÚNICA lei quando o BD não constrange.
- **golden (FA-5)**: F3 de protótipo Cowork = SEMPRE mapear token Cowork→live ANTES de colar CSS. O gabarito FA-5 referenciava `--text-2/--sunken/--pos-soft/--hairline` (vocabulário do protótipo) que não existe no live; e cor semântica no drawer vai por Tailwind `@theme`, não `var(--pos)` (o drawer é portal FORA de `.cockpit`, onde `--pos` de cockpit.css mora). Sintoma se ignorado: CSS "verde" que renderiza INCOLOR (var indefinida = sem cor). Resolve: tokens neutros do escopo `.fin-cowork` + Fundação (`--sh-2/--ease/--fs-*`); semântico via classe utilitária.
- **gotcha (FA-5)**: colisão de atalho `R` — `R` era global "novo recebimento"; o 9.75 quer `R`=liquida no drawer. Resolvido por PRECEDÊNCIA (drawer aberto + título liquidável → `openBaixa`; senão cai no novo-lançamento). Mesmo guard de foco (INPUT/TEXTAREA/SELECT/contentEditable + meta/ctrl/alt).
- **gotcha (FA-5)**: inline-edit (R2 KVEdit) precisa de rota de save POR CAMPO — `Canal` não tem (`UpdateTituloRequest` só aceita categoria_id/plano/venc/valor/forma/conta). Deferido em vez de inventar PATCH (T-AP-10). Regra: KVEdit inline só onde o campo já tem rota de update provada.

---

## TAREFA 1 — PageHeader canon rollout (telas inline → componente)
> **Reconciliação · sessões paralelas (2026-06-16):** esta sessão migrou Dashboard (#2863) por decisão [W] "Full canon" + "Keep #2863"; a entrada ACIMA pulou TAREFA 1 alegando `os-page-h` = Tier 0 Cowork-canon. **Verificado e resolvido a favor de #2863:** (1) o doc citado `feedback-cowork-bundle-aplicar-inteiro` é sobre ESTRATÉGIA de cópia de bundle CSS (copiar `styles.css` inteiro vs cherry-pick), **não** sobre proteger `os-page-h` de migração; (2) `ContasPagar` e `Dre` já migraram OFF `os-page-h` pra `<PageHeader>` (Wave 4, 25/mai) — direção estabelecida; (3) Dashboard não tem charter. Só **Unificado** é charter-locked em `os-page-h` (corretamente HELD nesta sessão). #2863 procede.
_handoff 2026-06-16 · [CL] · 1 tela = 1 PR · verificado vs main @4d9726142_

### Onda 0 — inventário (CORRIGE o handoff)
| item | veredito | prova |
|---|---|---|
| Lista do handoff "Unificado/Dashboard/Dre pendentes" | **STALE** — `Dre` já migrado (Wave 4, 25/mai; o hit `fin-page-h` era só COMENTÁRIO, não markup vivo); `ContasPagar` done; `Unificado` = **HOLD** (rewrite staged na branch governance, −123/+26 não-header → migrar em paralelo = colisão); `Dashboard` = único pendente limpo vs main | grep os-page-h/fin-page-h (74 arquivos) + `git diff main` |
| Escopo | módulo `Ponto/` inteiro tem header inline — FORA do escopo Financeiro do handoff (recipe importa `FinanceiroSubNav`) | grep |

### Dashboard `/financeiro` → PageHeader canon — PR #2863
| item | veredito | prova |
|---|---|---|
| Header inline → canon | `<header os-page-h fin-page-h>` → `<PageHeader>` v3.8 (ADR 0189), mesmo pattern de ContasPagar/Dre | +17/−9 · zero os-page-h/fin-page-h no arquivo |
| Botão morto → honesto | primary "Novo título" (`.os-btn.primary` sem handler) → `<PageHeaderPrimary>` roxo (ADR 0190) wired `/financeiro/unificado/novo` (rota real dos irmãos) | diff |
| Sem subnav (consciente) | Dashboard é o root `/financeiro`, sem ghost tab no DataController (ghosts: unificado/contas-pagar/fluxo/dre/…) — adicionar tab = mudança de IA no backend, fora de escopo | DataController.php:188-199 |
| guard | `pageheader-migration-guard.mjs` **verde** (102/104; neutro a este PR — Dashboard nunca importou o shared antigo) | run local |
| **Pendência [W]** | screenshot pra aprovação (gate MWART / PR UI Judge) antes do merge | — |

### new_design_memories
- **gotcha**: o handoff listou Dre como pendente, mas o único sinal (`grep fin-page-h`) batia num COMENTÁRIO, não no markup vivo — `fin-page-h` num arquivo ≠ header inline. Confirmar lendo o bloco, não só o grep count. (É exatamente o caso **C4 "ref morta"** que a TAREFA-2 vai mecanizar.)
- **golden**: antes de migrar o header de uma tela, `git diff main -- <tela>` — se a tela está staged-reescrita noutra branch (Unificado), migrar em paralelo é colisão garantida; HOLD é o caminho. (Caso **C5 "carimbo vs-main"**.)

---

## 2026-06-16 [CL] → [W] — Integridade do handoff (TAREFA 2) · TAREFA 1 pulada (premissa stale)

**Origem:** prompt colado [W]/Cowork (2 tarefas). Gate §10.4 contra `origin/main` fresco ANTES de codar mudou tudo. Worktree dedicado off `origin/main` (`D:/oimpresso-handoff`), 1 PR por onda.

### TAREFA 1 — migrar headers `os-page-h` inline → `<PageHeader>`: **NÃO FEITO** ([W] confirmou "pular")
Onda 0 (inventário) revelou que o pedido confundia 2 coisas distintas:
- `os-page-h`/`fin-page-h` = **CSS canon** do bundle Cowork (Tier 0 `feedback-cowork-bundle-aplicar-inteiro`), **não** alvo de migração.
- `@/Components/shared/PageHeader` = o **componente** que o `pageheader:guard` ratcheteia (104 baseline) — **verde**, não toca essas telas.

Estado real no `main`: **Dre** e **ContasPagar** já migrados pra `<PageHeader>` (Wave 4, 25/mai) — "pendente" no prompt era **ref morta** (a doença que a TAREFA 2 cura). **Unificado** charter v15 (10/jun) re-afirma `os-page-h` como "Markup canon EXATO" + hero "3 lentes" aprovado [W]; **Dashboard** foi DELIBERADAMENTE movido PRA `os-page-h` (19/mai, "paridade Unificado"). Migrar = regressão Tier 0 charter-protegida. [W] decidiu **pular**. Caixa Unificada dark + H1 600×700 não tocados (já no main / espera [W]).

### TAREFA 2 — gate de integridade do handoff: **FEITO** (2 ondas, 1 PR cada)
A fila `COWORK_NOTES.md` apodrecia invisível (refs mortas pra `PROMPT_PARA_CODE_*` inexistentes + prompts órfãos) e nada travava.
- **Onda 1 — regra (doc):** PR **#2864** — `PROCESSO_MEMORIA_CC.md` §16 (5 regras: sem órfão · auto-contido · linha d'água · "pousou" só pós-`main` · ondas) + IT8.
- **Onda 2 — gate (CI):** PR **#2865** — `scripts/handoff-integrity-guard.mjs` (catraca acima da linha d'água `<!-- LINHA-DAGUA-HANDOFF -->`) + auto-teste controle-negativo (8 casos: órfão/ref-morta injetados → vermelho) + baseline 0/0 + workflow advisory (ADR 0271/0275, `paths:` na fila + dir handoffs) + npm scripts. **Home confirmado antes (Regra 7):** `cowork-inbox.py` é mover-de-conteúdo (não validador) → estendi a família `*-guard.mjs`, não dupliquei.

**Status:** PRs **#2864** + **#2865** abertos, **aguardando merge [W]** (publication-policy). "Pousou" só vira `PROCESSADO → main` quando estiver no `main` (regra §16.4 deste próprio PR). Se a §16 virar ADR formal = Tier 0 = número é [W] (não cunhei).

---

## TAREFA 2 — C3 salvage (extensão do guard do main) — PR #2869
_handoff 2026-06-16 · [CL] · verificado vs main @f17072b86 · reconcilia colisão de sessões paralelas_

Uma sessão paralela mergeou #2864 (§16) + #2865 (handoff-integrity-guard = C4 órfão/ref-morta) DURANTE a minha. Fechei minhas duplicatas (#2866 §16, #2868 guard) e salvei só o **C3** (único valor único), **estendendo** o guard do main — não paralelo (Regra 7).

| item | veredito | prova |
|---|---|---|
| C3 no `handoff-integrity-guard.mjs` | `:** > **` na fila ativa + `PROMPT_PARA_CODE_*`; baseline `fused_headers` (0); abaixo-da-linha ignorado | self-test 18/18 |
| §16 6ª regra "Sem cabeçalho fundido" | doc + nota de mecanização atualizadas | `integrity-check` §15 verde |
| C4/C5 | C4 já do #2865 (não dupliquei); C5 vs-main não-mecanizável (fila Cowork-only) | — |

### new_design_memories
- **golden**: re-validar `origin/main` ANTES de cada novo intent, não só no início — a sessão paralela mergeou o mesmo trabalho no meio da minha. Fechei a duplicata e salvei só o delta (C3), estendendo o canon.
- **gotcha**: a paralela alegou "Dashboard charter-protegido" pra pular TAREFA 1 — FALSO (não há charter). Conclusão de skip ≠ fato; conferir o disco antes de pular.

---

## 2026-06-16 [CL] → [W] — Prompt "Header / Reincidência / Caixa" (3 tarefas) — Wave 0 + entregas
_sessão nova · verificado vs main @92adb692d · trabalhei em worktree `D:/oimpresso-cl` (branches off `main`), não na branch governance da cwd_

Re-rodei o prompt validando **tudo** contra `origin/main` antes de codar (Regra 5/§16). Achado: ~70% já estava no main (sessões paralelas, hoje paradas) — não refiz. Refs `prototipo-ui-patch/PROMPT_PARA_CODE_*` do prompt **não existem** no checkout → trabalhei do texto do prompt + código + `inbox-page.jsx`.

### Task 1 — `os-page-h` → `<PageHeader>`: **PULADO** ([W] confirmou 16/jun)
Não é migração. `os-page-h`/`fin-page-h` = **markup canon do bundle Cowork**, não header inline: o `<PageHeader>` canon **nem emite** essas classes (grep) e o `pageheader:guard` ratcheteia outro eixo (`shared/PageHeader`→`Components/PageHeader`). O **charter v15 do Unificado** declara `os-page-h fin-page-h` como "Markup canon EXATO" + hero "3 lentes" aprovado [W]. Migrar Unificado/Dre = **regressão Tier 0 charter-protegida**. Dashboard já foi pro PageHeader via #2863.

### Task 2 — Guard da Reincidência: **FECHADO (doc)**
C3/C4 já no main (#2864/#2865/#2869). Faltava o **C5** → **#2872 (MERGEADO):** §16 Regra 7 (carimbo `verificado vs main @<SHA>`) + tabela "Caçador de reincidência" (git-gates C3/C4/C5 com condição de morte; C1/C2/C6 referenciadas **sem inventar** — Tier 0, def. canônica no handoff Cowork não-versionado). **Catraca do C5 pendente** — a fila real usa bullets `- **…**`, não `> … → [CL]` → mecanizar precisa de [W] confirmar o que é "item ativo" (+ o código-ref mora no patch Cowork ausente).

### Task 3 — Caixa Unificada · filtros em 2 botões (ondas 1+2 aprovadas [W])
- **Onda 1 #2875 (MERGEADO):** removida a faixa de canais (`ChannelChipsRow` + dead-code); `availableChannels/Accounts` + URL-sync `?channel=`/`?account_id=` intactos. Charter v14.
- **Onda 2 #2879 (ABERTO):** `ConversationListV4` header vira **Status** (DropdownMenu 7-valor `?tab=`) + **Filtros** (Popover flutuante, não empurra a lista) com 9 grupos (Canal/Conta/Fila/Tags/Ordenar/Esperando há/Sem CRM/Janela 24h/Mídia 24h + Limpar). **Atribuição omitida** — sem param no `CaixaUnificadaController` (só a tab "Minhas" + picker da sidebar) → não inventei grupo morto (anti M-AP-2). Contrato backend intacto; `buildQuery` agora carrega channel/account_id/queue (persistem na navegação). Charter v15. **Verificado local:** `npm ci` + `tsc --noEmit` limpo nos 2 arquivos (restam só erros **pré-existentes** de `preserveScroll`) + `vite build:inertia` verde (4431 módulos). **Sem screenshot** (worktree sem dev server) → visual-regression CI + revisão [W].

### new_design_memories
- **golden**: `os-page-h` é canon (charter "Markup canon EXATO"), NÃO header a migrar — confirmar o charter antes de "migrar header", senão vira regressão Tier 0 (Task 1).
- **golden**: mudança de design que toca um Goal de charter (Task 3 faixa de canais) = atualizar o charter **no mesmo PR** (v14/v15), pra charter ≡ realidade — o mesmo drift que a catraca §16 combate.
- **gotcha**: grupo de filtro só entra se houver param backend real — "Atribuição" não tinha (anti M-AP-2). Conferir o controller antes de listar grupos.

---

## 2026-06-17 [CL] → [W] — Caixa Unificada · sidebar mobile + scrollbar (handoff Cowork, ondas 1 & 3)
_sessão nova · base `origin/main` @cb1a5467a · worktree `D:/oimpresso-shell-mobile` (branches off `main`), NÃO na branch governance (`feat/governance-ds-rollout-ledger`, ~1017 dirty) da cwd `frosty-greider-83ab2f` (dir órfão). Handoff colado por [W] (v1) — return aqui (v2 F3)._

Handoff `[CC]→[CL]` "Sidebar flutuante no mobile + 3 ajustes da Caixa Unificada" (4 ondas). Validei **cada onda contra o app real** (§10.4): o handoff trazia código espelho do protótipo (`.app`/`.sb`/`hidden`-mode/`SidebarReopenHandle`/`.om-*`), que **não existe** no shell/tela reais (`.cockpit`/`.sb`/`data-sidebar`; Caixa é Tailwind+shadcn). Repo venceu onde divergiu.

### Onda 1 — Sidebar flutuante no mobile (≤768px): **#2887 (ABERTO)**
`AppShellV2.tsx` + `cockpit.css`. Em ≤768px a `.sb` sai do grid (260px) e vira drawer off-canvas (`position:fixed` + `translateX`) com hambúrguer fixo→✕ (desliza pra borda do drawer), backdrop `.42`, conteúdo full-width, fecha ao navegar (`page.url`), trava scroll do body. **Desktop ≥769px provadamente intocado:** todo CSS novo sob `@media (max-width:768px)`; no React `isMobile=false` ⇒ `renderSidebarMode===sidebarMode`, sem hambúrguer/backdrop no DOM, `data-mobile-menu="closed"` inerte, grid 260/rail 56 + ⌘\ preservados. Banda de 48px só em telas `hideTopbar` (default). `.apps` (LinkedApps) escondido no mobile.

### Onda 3 — Scrollbar visível na lista e thread: **#2888 (ABERTO)**
Não havia utilitário de scroll nem plugin `tailwind-scrollbar`; o repo tokeniza scrollbar por elemento em `cockpit.css` (`.sb-body`/`.linked-body`). Criei o utilitário reusável **`.cw-scroll-thin`** (`scrollbar-width:thin` + thumb webkit arredondado, cor por token `--text-mute`/hover `--text-dim` → flip claro/escuro automático) e apliquei no `<ul role=listbox>` (`ConversationListV4`) e no container de mensagens (`ConversationThreadV4`). `.om-list`/`.om-msgs` do protótipo não existem → containers reais.

### Ondas 2 & 4 — NÃO se aplicam ([W] decidiu "Pular 2 & 4")
Descrevem UI **só do protótipo Cowork**: (2) strip colapsável de Contexto a 44px no preto — `ContextSidebarV4` real não colapsa (sempre cheio; mobile = coluna inteira via `InboxMobileTabs`); (4) caixa de comentário inline na mensagem com botão "Comentar" — não existe (nota = toggle do composer; feedback = Sheet "Capturar feedback"). Aplicá-las seria CSS morto pra classe inexistente → **não fiz** (anti M-AP / regressão).

### Verificação
Toolchain JS **não instalado** nesta máquina (`node_modules` vazio — modelo CT100/CI), então `typecheck`/`eslint`/`stylelint`/`vite`/preview **não rodaram** local (diferente da entrada 06-16, que tinha `npm ci`). Mudanças mínimas, revisadas à mão + argumento de não-regressão desktop. Gates do CI de cada PR validam; **screenshot-gate [W]** mobile+desktop antes do merge dos 2 PRs de feature. Este return é doc → a11y-axe short-circuita verde (v2).

### new_design_memories
- **golden**: handoff Cowork mira classes do protótipo (`.om-*`/`.app`/`.sb`/`hidden`-mode) que NÃO existem no app real (Tailwind+shadcn / `.cockpit`). Traduzir intenção→containers reais e deixar o repo vencer (§10.4); nunca colar CSS de classe inexistente (vira morto).
- **golden**: feature mobile no shell = todo CSS sob `@media (max-width:768px)` + gate `isMobile` no React ⇒ desktop provadamente intocado, sem re-testar o desktop inteiro.
- **gotcha**: parte de um handoff pode descrever UI que só vive no protótipo (Caixa: strip de Contexto colapsável, comentário inline na bolha). Verificar contra o componente real ANTES de implementar; se não existe, reportar — não inventar nem encher de CSS morto.
- **gotcha**: a cwd `frosty-greider-83ab2f` é dir órfão (não-worktree; `rev-parse` cai no repo na branch governance suja). Trabalhar em worktree limpo off `origin/main`, nunca na cwd.

---

## 2026-06-17 [CL] → [W] — Loop de handoff zero-paste · sync Cowork→repo (PR-6, ADR 0283) · **PROCESSADO → main**
_worktree `D:/oimpresso-handoff-pr6` off `origin/main` @`92ed49e8d`. Handoff colado por [W] via URL Cowork (`PROMPT_PARA_CODE_HANDOFF-SYNC-PR6.md`). **PR #2921 MERGEADO → main** (squash `c4b31cb11`, 2026-06-17 18:43Z, por wagnerra23)._

Fecha o "primeiro hop" do loop zero-paste: um `.md` em `prototipo-ui/handoffs/` é assinado por uma Action e vira `pending` via tool MCP, sem o [W] colar nada nem computar HMAC. Auditei contra o `main` fresco (§10.4): a Fase 0/1 já estava no `main` — faltava só o que **assina e dispara**.

### Entregue (#2921, mergeado)
- **PR-6a `handoff-submit`**: tool MCP de mutação (`Modules/TeamMcp/Mcp/Tools/HandoffSubmitTool.php`) que recebe o handoff assinado por HTTP e cria `pending`. **Reusa `HandoffIngestService`** (extraí de `HandoffIngestCommand` — HMAC/`source_hash`/append-only viram fonte única). Scope `jana.mcp.handoff.submit` (A7), `sig` inválida→recusa (A1), `source_hash` igual→no-op, revisão de `applied`→supersede. Audita + pulsa `mcp_ingest_heartbeat`. Sem auto-merge.
- **PR-6b transporte**: `bin/sign-handoff.php` (`--self-test`) + `.github/workflows/handoff-sign-submit.yml` (on-push assina + POST stateless no `Mcp::web /api/mcp`; skip-as-pass sem secrets).
- Pest `HandoffSubmitToolTest` (6 provas) + `submit`/`ingest` no `ci-sqlite-pest.list` + gate no `gates-registry.json`.

### Pendente (do [W], UMA VEZ) — transporte fica skip-as-pass até lá
Secret `HANDOFF_SECRET` (= `.env` servidor) · secret `HANDOFF_SUBMIT_TOKEN` (token scope `jana.mcp.handoff.submit` via admin Team MCP) · (opc) var `MCP_ENDPOINT_URL`.

### Resíduos → chips
Gap 3 levers · Gap 2 badge `conflito` · publisher Cowork→repo (zero-toque real).

### new_design_memories
- **golden**: handoff de INFRA (não-UI) também exige auditar o `main` fresco (§10.4) — a fundação (PR-1..5) vivia só no `main`, ausente da branch da cwd; abrir branch off `origin/main`.
- **golden**: validação compartilhada por 2 caminhos (ingest por arquivo + por HTTP) vira **Service extraído**, não cópia (`HandoffIngestService`) — uma fonte de verdade pro HMAC/append-only.
- **golden**: `Mcp::web` (laravel/mcp ^0.7) é JSON-RPC **síncrono/stateless** — `tools/call` num POST sem handshake `initialize` (`vendor/.../Server.php:198`); por isso uma GitHub Action chama tool MCP por `curl`.
- **gotcha**: teste de `Modules/TeamMcp` SÓ roda em CI se estiver no `.github/ci-sqlite-pest.list` (não há lane TeamMcp; `modules-pest.yml` não cobre). PR-1/2 não estavam → não mordiam.
- **gotcha**: workflow novo SEM registro em `scripts/governance/gates-registry.json` no MESMO PR → `memory-health` (enforce) 🔴 bloqueia ("censo de gates").
- **gotcha**: **`--auto` merge durante lag do GitHub squasha o head que o *PR-object* enxerga, NÃO a ref real da branch.** O PR-object ficou preso 1 commit atrás (lag de minutos); o auto-merge squashou o head defasado e o último commit recém-pushado (este doc) ficou de fora. Pós-merge, conferir `git show --stat <mergeCommit>` e re-landar o que faltou. Esta entrada é o re-land.
