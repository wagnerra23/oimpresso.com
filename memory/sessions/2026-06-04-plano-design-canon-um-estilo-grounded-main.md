---
slug: 2026-06-04-plano-design-canon-um-estilo-grounded-main
title: "Plano completo: UM estilo só (ERP profissional) — estado REAL do main + 5 gaps + sequência DEFINIR→POLICIAR→VARRER"
type: session
status: proposta
authority: pending
decided_by: pending-[W]
date: "2026-06-04"
authors: [claude-cowork]
grounded_in_main: "21416a6eb (HEAD) · 44a561802415 (tree)"
related: [INDEX-DESIGN-MEMORIAS.md, _INDEX-LIFECYCLE.md, "ADR UI-0013", "ADR 0235", "ADR 0236", "ADR 0239"]
---

# Plano — "um estilo só, ERP profissional" (grounded no `main`)

> [W] (m0034): "quero um sistema com único estilo, profissional. o DS ancorado no Linear/Stripe/Carbon. tudo que for diferente está errado. disposto a sacrificar pra definir o padrão, depois aumentar qualidade. consulte o git, faça o plano, quero ter certeza."
> **Tudo abaixo foi LIDO no `main` nesta sessão** (`✓ lido`) ou está marcado `⚠ inferido`. Não inferi estrutura de repo (L-26/L-27).

## TL;DR (a verdade, pra ter certeza)

**O sistema de "um estilo só" JÁ EXISTE e JÁ é cobrado por máquina no `main`. Você está a ~90%, não no zero.** O que falta são **5 buracos** específicos. Qualidade = fechar os 5 — não reescrever, não merge big-bang.

## O que existe HOJE no main (✓ lido)

| Peça | Arquivo @main | O que já garante |
|---|---|---|
| **Constituição UI** = o DS que você adorou | `…/adr/ui/0013-constituicao-ui-v2-camadas.md` (`accepted`, [W] "eu aporvo" 2026-05-24) | 4 camadas **Fundações→Shell→Padrão→Módulo**; "camada de cima herda e NUNCA contradiz". Ancorada em Atomic Design + **Stripe/Linear/Carbon**. |
| **Cor canon** | ADR 0235a (`DS v4 — roxo primary oklch(0.55 0.15 295)`, amends 0190) | roxo universal; azul = **débito a migrar**, nunca padrão novo |
| **Reconciliação de memórias (JÁ FEITA)** | `_DesignSystem/INDEX-DESIGN-MEMORIAS.md` (PR #1991) | Regra de Ouro §0 · **conflitos reconciliados §4** · hierarquia §5 · lista de stale §6 — enforçado por `DesignIndexSingleSourceTest` (CI falha se regra de design não está no índice) |
| **Lifecycle de ADR (filtra morto)** | `memory/decisions/_INDEX-LIFECYCLE.md` (aprovado [W] 2026-05-06) | estados accepted/accepted-historical/**superseded**/deprecated + `superseded_by`. Tool MCP **`decisions-search` NÃO retorna superseded por padrão** → já é o conserto do "índice puxa ADR morto" |
| **Gate de colisão de número** | `tests/Feature/Memory/AdrNumberCollisionTest.php` | falha se número colide sem registro; 11 colisões já documentadas |
| **Fundação real** | `resources/css/cockpit.css` (82KB) + `inertia.css` (14KB) | os tokens vivem aqui. **`foundations.css` NÃO existe no main** |

> Conclusão: a "lei do estilo único" está escrita (UI-0013) **e** tem polícia (DesignIndexSingleSourceTest, AdrNumberCollisionTest, ui:lint, ds/*). Faltam 5 buracos.

## Os 5 buracos (o que falta pra "diferente = errado" ser físico)

- **GAP-A · DS v6 sem ADR + naming inconsistente.** Os tokens semânticos (`--pos/--neg/--warn/--stage-*/--origin-*`) entraram no main via PRs #2128–#2200 (⚠ por Claude Code, não re-verifiquei os PRs), mas **não há ADR** e o `INDEX-DESIGN-MEMORIAS §4/§5 ainda diz "DS v4"**. Pior: o Cowork chama "v6", o repo canon chama "v4". → **1 nome, 1 ADR.**
- **GAP-B · `foundations.css` do branch = 4ª fundação paralela.** Por UI-0013 a Fundação já é `inertia.css`+`cockpit.css`. Introduzir `foundations.css` é literalmente criar um **2º estilo** — a doença que você quer matar. (Branch `refactor/css-fundacao-unica`, +111k/−3.9k; ⚠ não li o branch, só o relato do [CL].)
- **GAP-C · memórias conflitantes stale sem lápide forte.** `INDEX-DESIGN-MEMORIAS §6` já lista: `BRIEFING_CLAUDE_DESIGN.md` (sidebar dark + hue 220), `CLAUDE_DESIGN_BRIEFING §2/§4` ("só comunicação visual" + azul), `CODE_DESIGN_CONTRACT.md` (para em v3.1), `CATALOGO_ACABAMENTOS.md` (hue 220), `SPEC R-DS-002` (`bg-blue-500`), `GUIA-SIDEBAR-V3`. → **lápide forte** (o que você pediu).
- **GAP-D · housekeeping do lifecycle (ADR 0120).** 9 ADRs `superseded` **sem `superseded_by` no frontmatter** (0008/0010/0031/0032/0033/0042/0073/0075/0077) + 11 colisões registradas no índice mas não no frontmatter. É **isto** que ainda deixa um retrieval que lê frontmatter (não o índice) servir morto. → **backfill mecânico do índice → frontmatter.**
- **GAP-E · gates só no branch.** `conformance-gate.mjs`/`foundation-guard.mjs` estão no branch, calibrados pro CSS do branch (não do main). → extrair, **re-baselinar contra o main real**, landar como CI.

## Plano — sua estratégia, traduzida (DEFINIR → POLICIAR → VARRER)

### 1. DEFINIR (crava o padrão — onde você decide)
- **ADR DS v6** (amends 0235, **não** supersedes — append-only; roxo 0235 continua a âncora): ratifica a camada de tokens semânticos + **resolve o nome** (v6 vs "v4.x semantic layer"). Atualiza `INDEX-DESIGN-MEMORIAS §4/§5` pra apontar a versão nova.
- **Decisão das 5 origins** (OS/CRM/FIN/PNT/MFG → 11 hues): UI-0013 lista como lacuna explícita ("abrir ADR se decidido"). Sim/não é seu.
- **Decisão do branch**: rebase supervisionado (extrair gates + deletar stubs órfãos, re-derivar contra DS v6 do main) **em vez de** merge big-bang do +111k. **NÃO** introduzir `foundations.css`.

### 2. POLICIAR (já existe — só ligar o que falta)
- Extrair `conformance-gate`/`foundation-guard` do branch → re-baselinar contra o main → CI bloqueante. A partir daí, **ninguém introduz 2º estilo sem o merge travar.**
- `DesignIndexSingleSourceTest` + `AdrNumberCollisionTest` + `ui:lint` + `ds/*` **já rodam** — confirmam que a polícia existe.

### 3. VARRER (qualidade depois do padrão cravado)
- **Lápide forte** nos docs do §6 (GAP-C) + **backfill `superseded_by`** (GAP-D) → memória conflitante para de ser servida.
- Fila de telas fora do gabarito = o **screen-grade ratchet** que já existe (board "44 telas <70"). Aponta pro DS v6 ratificado; cada tela sobe uma a uma.

## Formato da LÁPIDE FORTE (o que você pediu — alto p/ humano + legível p/ máquina)
```
> # ⚰️ SUPERSEDED — NÃO USE
> Morto: 2026-06-04 · Canon vivo: INDEX-DESIGN-MEMORIAS.md
> Substituído por: ADR UI-0013 + DS v6 (amends 0235) + roxo 295 + IBM Plex
> Por que morreu: <1 linha> (ex.: "accent hue 220 azul; canon é roxo 295")
> Conteúdo abaixo preservado só como histórico (append-only · ADR 0003/0236).
```
+ frontmatter: `status: superseded` · `superseded_by: [...]`. Append-only — **lápide marca, não deleta** (Constituição Art. 3; renumber/delete = bloqueado pelo gate, provado no PR #1995).

## As 3 decisões que são SÓ suas (Tier 0 — respondem os checkboxes do Claude Code)
1. **Nome do DS**: "v6" oficial **ou** "DS v4 — camada de tokens semânticos"? (eu recomendo nomear v6 e o ADR amends 0235, pra parar a divergência Cowork×repo).
2. **5 origins → 11 hues**: sim (abre ADR) ou fica nas 5 atuais?
3. **Branch**: rebase supervisionado (recomendado) **ou** merge big-bang? (o big-bang conflita com o DS v6 que já está no main — risco de shipar visual errado).

## Quem faz o quê
- **[CL]** (já no branch): rebase supervisionado → PR limpo; ratificar ADR DS v6; aplicar lápides §6; backfill `superseded_by` (ADR 0120) — **tudo EXTENDENDO os índices que já existem** (`INDEX-DESIGN-MEMORIAS` + `_INDEX-LIFECYCLE`), **nunca recriando** (L-11).
- **[W]**: as 3 decisões acima.
- **[CC] (eu)**: este plano + corrigir minha ponte de ontem (reinventava o lifecycle) + espelhar no Cowork.

## Auto-correção honesta (L-26/L-27/L-11)
Minha ponte de ontem (`ADR-LIFECYCLE-JANA-RETRIEVAL`) propunha **CRIAR** status/`superseded_by`/filtro de retrieval. O `main` **JÁ TEM** (`_INDEX-LIFECYCLE` + `decisions-search`). O trabalho real é **FINALIZAR o housekeeping (GAP-D / ADR 0120) + backfill**, não construir do zero. Ponte corrigida nesta sessão.

## Trilha do tempo
- 2026-06-04 · [CC] · li o main (CSS + lifecycle + design canon + UI-0013) → "estilo único" já existe e é cobrado; 5 gaps reais (A-E). Recomendação: fechar os 5, rebase supervisionado, não foundations.css, não big-bang. 3 decisões Tier 0 pro [W]. Corrigi a ponte de ontem (não reinventar lifecycle).
