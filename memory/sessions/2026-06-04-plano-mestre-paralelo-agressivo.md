---
slug: 2026-06-04-plano-mestre-paralelo-agressivo
title: "Plano-mestre PARALELO/AGRESSIVO — 4 raias concorrentes, [W] só nos gates (visual + switch + 3 Tier-0)"
type: plano
status: ativo
date: "2026-06-04"
authors: [claude-cowork]
---

# Plano-mestre — paralelo e agressivo (tudo que é reversível corre JÁ, concorrente)

> [W]: "faça em paralelo se possível, agressivo." **Por que é seguro ser agressivo:** o aparato (37 workflows CI + visual-regression + module-grades + ui:lint) é a **rede de segurança** — se um PR regredir o visual/contrato, o gate trava ANTES do main. Velocidade pela paralelização, segurança pelo gate. Autorização permanente [W] 2026-06-02 já cobre: **PRs paralelos autônomos, merge em CI verde; [W] só no irreversível.**
> **Regra de ouro:** abrir TODAS as raias concorrentes hoje. **1 PR = 1 intent**, muitos PRs ao mesmo tempo. Nada de big-bang (≠ paralelo). Merge autônomo em CI verde.

## As 4 raias (correm ao MESMO tempo)

### 🟦 RAIA 1 — As 44 telas → gate visual ([CL] autônomo; 1 gate [W])
Ponte: `prototipo-ui-patch/PROMPT_PARA_CODE_APROVAR-44-STAGING-SCREENSHOTS.md`
- **1a** frescor + rebase `feat/staging-ct100` vs `main` (read-only→rebase) — **agora**
- **1b** build staging CT100 + **capturar as 44 de uma vez** (claro+escuro, 1280+1440) — paralelo a 1a assim que rebase fecha
- **1c** conflitos do rebase → **sub-agents paralelos** (1 por tela conflitante), não serial
- ⛓️ **GATE [W]:** aprova as 3 ondas (A receita / B segurança / C interno) — pode aprovar **as 3 numa sentada**, rejeição inline 1 linha
- **1d** merge por onda (autônomo CI verde) → **1e** re-roda o board (média nova)

### 🟩 RAIA 2 — Ligar o juiz LLM ([W], 2 min, zero dependência)
`Settings → Variables → PR_UI_JUDGE_ENABLED=true` (+ secret `ANTHROPIC_API_KEY`). **Faz hoje, independente de tudo.** A partir daí todo PR das outras raias **já nasce pontuado** pelo juiz — a máquina cobra a própria operação. ~$3/mês.

### 🟨 RAIA 3 — Higiene de governança ([CL] autônomo, 4 sub-PRs PARALELOS)
Pontes: `PROMPT_PARA_CODE_ADR-LIFECYCLE-JANA-RETRIEVAL.md` + a entrada "UM ESTILO SÓ" do `COWORK_NOTES`. Todos EXTENDEM o que já existe (não recriar — L-11):
- **3a** ADR DS v6 (amends 0235) + atualizar `INDEX-DESIGN-MEMORIAS §4/§5` — **texto pronto:** `prototipo-ui-patch/RASCUNHO_ADR_DS-V6-CAMADA-SEMANTICA.md` ⛓️ *espera só o nome (G3); [CL] transcreve valores reais de token e numera*
- **3b** lápides/banners nos docs stale (`INDEX §6`) — **texto pronto:** `prototipo-ui-patch/PROMPT_PARA_CODE_LAPIDES-DOCS-STALE.md` (banner+spot-fix por-doc; lápide forte só nos inteiros mortos, L-28). Autônomo
- **3c** backfill `superseded_by` dos 9 ADRs + ADR 0120 + teste de coerência índice↔frontmatter — **tabela concreta pronta** em `prototipo-ui-patch/PROMPT_PARA_CODE_ADR-LIFECYCLE-JANA-RETRIEVAL.md` (Fase 2). Autônomo
- **3d** extrair `conformance-gate`/`foundation-guard` do branch +111k, **re-baselinar vs main**, landar como CI — **NÃO introduzir `foundations.css`** (fundação = cockpit/inertia). Autônomo

### 🟥 RAIA 4 — Sidebar (Onda 4) ([CL] faz 1, [W] decide 3)
- **4a** dedup OficinaAuto — já feito (`6abc5d8ff`)
- ⛓️ **GATE [W]:** desinchar SISTEMA · grupos órfãos (ads/MemCofre/kb/ProjectMgmt) · bucket Público — decisão de produto

## As ÚNICAS dependências reais (o resto é tudo paralelo)
1. RAIA 1: rebase (1a) **antes** dos screenshots (1b) — pra [W] aprovar o que vai mesmo pro main.
2. RAIA 1: aprovação visual [W] **antes** do merge (1c→1d) — gate ADR 0107.
3. RAIA 3a: nome do DS (v6 vs "v4 semantic") **antes** de escrever o ADR.
> Fora esses 3 elos, **tudo corre concorrente desde já.**

## Os gates do [W] (junte e responda quando quiser — não bloqueiam o trabalho reversível)
| # | Decisão | Raia | Custo de esperar |
|---|---|---|---|
| G1 | flip `PR_UI_JUDGE_ENABLED=true` | 2 | drift dos PRs novos não é pontuado até ligar |
| G2 | aprovar/rejeitar as 44 (3 ondas) | 1 | as 44 não fecham ratchet / não mergeiam |
| G3 | nome do DS (v6 oficial?) | 3a | ADR DS v6 não escreve |
| G4 | 5 origins → 11 hues? | 3 | fica nas 5 (default ok) |
| G5 | sidebar: desinchar SISTEMA + órfãos | 4 | menu do cliente segue inchado |

## Disciplina (pra não virar bagunça agressiva)
- **1 PR = 1 intent**, muitos em paralelo — nunca um PR gigante (o +111k é o anti-exemplo).
- Cada PR **passa pelos gates** (CI/visual-regression/module-grades) — divergir = trava sozinho.
- [CL] valida vs `origin/main` (§10.4 Passo 0) por PR · não cunha ADR · retorno em `CODE_NOTES.md`.
- **Irreversível continua [W]:** deploy prod Martinho/ROTA LIVRE · ligar fiscal real · migração destrutiva. Staging CT100 = reversível = autônomo.

## Trilha do tempo
- 2026-06-04 · [CC] · plano-mestre paralelo: 4 raias concorrentes, 3 elos de dependência reais, 5 gates [W] agrupados. Pontes: 44-staging-screenshots · ADR-lifecycle · um-estilo-só. Agressivo é seguro porque os gates são a rede.
