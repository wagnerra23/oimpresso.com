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
- **Conteúdo:** R1–R8 (always-read · 3 planos · anéis fonte-única · charter+register irmãos · defesa-que-dispara · medir+gatilho · LICOES append-only · soberania [W]). Consolida 0114/0236/0238/0239/0241/0242 + UI-0013; `supersedes: []`.
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
