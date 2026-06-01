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
