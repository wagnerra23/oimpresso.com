---
date: "2026-08-11"
time: "18:58 BRT"
slug: dossie-da-grade-cego-a-80-por-cento-das-maquinas
tldr: "O dossiê da grade reguas-do-sistema lia só fontes curadas à mão (mapa-dos-níveis + doutrina + §5) e era cego a 373 das 466 máquinas do inventário derivado (80%) — os pesquisadores declaravam ausência sobre capacidade que o sistema tem. Conserto de escopo mínimo no prompt da Fase 0, com bite-test por mutação. PR #5607 aberto, 99 checks verdes, merge pendente [W]."
prs: [5607]
decided_by: [W]
next_steps:
  - "Mergear o PR #5607 (ato [W]) — CI 99 pass, 0 fail"
  - "Na PRÓXIMA rodada da grade, conferir se o dossiê passou a marcar fraquezas como [já coberta por <arquivo>] — é o sinal de que o conserto pegou"
---

# Dossiê da grade era cego a 80% das máquinas

## Estado MCP no momento do fechamento

| consulta | resultado |
|---|---|
| `cycles-active` | **Nenhum cycle ATIVO em COPI** |
| `my-work` | 8 tasks, **todas em `review`** — nenhuma relacionada a esta sessão (o trabalho veio de chip transversal, não de US) |
| handoffs irmãos (hoje) | **8** — o mais recente `2026-08-11-1810-scope-sai-de-modules-e-o-append-only-suspenso` |
| base | `HEAD` 1 à frente / **5 atrás** de `origin/main` no fechamento |

## O que aconteceu

Chip transversal da rodada de réguas de 2026-08-11: a dimensão `memoria-conhecimento` registrou que **5 das 8 fraquezas levantadas já tinham máquina viva que a pesquisa não achou**. Fui atrás da causa e ela é **estrutural, não azar**.

O prompt da Fase 0 (`.claude/workflows/reguas-do-sistema.js`) montava o dossiê de três fontes — `memory/decisions/` (mapa-dos-níveis, **curado à mão**), a doutrina, e `proibicoes.md §5`. Medido:

```bash
rg -c "hook-replay|hook-bites" memory/decisions/*mapa-dos-niveis* \
   memory/decisions/*doutrina-documentacao* memory/proibicoes.md   # → 0 hits
rg -n  "hook-replay|hook-bites" memory/reference/MAQUINAS-INVENTARIO.md  # → :389 :390
```

Contado sobre o inventário inteiro: **373 das 466 máquinas (80%) não apareciam em nenhuma fonte que o dossiê lia**. Logo os 12 pesquisadores concluíam *"o oimpresso não mede isso"* sobre coisa que ele mede — mesmo padrão do 7/9 de 2026-07-09 (regra 4 da SKILL.md).

**Conserto (escopo mínimo — só o texto do prompt):** `MAQUINAS-INVENTARIO.md` (censo **derivado da árvore**, `authority: generated`) vira fonte obrigatória, rotulada lista anti-falso-negativo, com 3 instruções — não enumerar (teto de 500 palavras), marcar cada fraqueza confessa `[ainda aberta]`/`[já coberta por <arquivo>]`, e fechar o dossiê com a linha que **repassa a regra aos pesquisadores** (o dossiê é embutido em `COMUM`, então viaja de graça pro consumidor real). Zero mudança de arquitetura: mesmas fases, mesmo cap, 12 pesquisadores, 1 dossiê.

## Artefatos gerados

| arquivo | Δ | o quê |
|---|---|---|
| `.claude/workflows/reguas-do-sistema.js` | +6/−1 | prompt da Fase 0 (a mudança) |
| `scripts/governance/reguas-workflow.test.mjs` | +27 | bloco `[9]` — 7 asserts + 2 controles negativos |
| `.claude/skills/reguas-do-sistema/SKILL.md` | +8/−1 | regra 1 (enumera as fontes; ficaria contradizendo o código) |

## Persistência

- **git:** [PR #5607](https://github.com/wagnerra23/oimpresso.com/pull/5607) — **99 pass · 0 fail · 2 skipping** (os 2 são jobs `scheduled`-only, não rodam em PR por desenho). **Merge pendente [W]** (R10).
- **MCP:** nada a atualizar — trabalho veio de chip, não de US; nenhuma task tocada.
- **BRIEFING:** n/a (nenhum `Modules/<X>/` tocado).

## Mordida provada (mutação no arquivo vivo, restauro byte-idêntico)

| mutação | resultado |
|---|---|
| remove a fonte do inventário | **rc=1** (2 asserts) |
| remove a linha repassada aos pesquisadores | **rc=1** |
| remove o rótulo `ANTI-FALSO-NEGATIVO` | **rc=1** |
| restaurado | **rc=0**, byte-idêntico |

Selftest **52/52** (era 45/45), zero agentes, invocado em `governance-script-tests.yml:625` (advisory) — passou no CI em 1m3s.

## Lições catalogadas

1. **Número atemporal barrado no próprio PR.** O `466` apareceu 3× na 1ª redação; duas sem data, e uma delas seria propagada **12×** aos pesquisadores como fato. Sobrou uma, com data e sistema medido (§5 2026-07-17). O valor de hoje vem do dono: `maquinas-inventario.mjs --check`.
2. **Contagem derivada, nunca hardcoded.** O assert de "12 pesquisadores" lê o `DIMS_DEFAULT` vivo — número escrito à mão apodreceria no dia em que alguém adicionar uma dimensão, e o teste passaria a medir o passado.
3. **`in_progress=0` era truncagem, não pool parado.** Diagnosticando a fila do CI, quase reportei "runners travados" a partir de `gh run list --limit 100` — as 100 vagas estavam tomadas por runs recém-enfileirados de 3 branches paralelas, e os que rodavam são mais velhos, fora da janela. O que provou vida foi a **taxa de conclusão** (36/h), não o contador. Família do `head_limit` que corta varredura e devolve número plausível — **LC-08**.
4. **`grep -F` casa substring.** Ao checar se os 2 checks pendentes eram required, o `grep -qF` teria aceitado um nome mais longo; refeito com comparação string-exata contra a união `classic_protection ∪ rulesets` (43 contexts). Os dois eram required de verdade.
5. **O "5 de 8" não é meu** e o PR diz isso. Vem do relatório da rodada de 08-11, que **não está versionado** (`retratos.json` para em 08-08). O que eu medi é a *causa* — os 80%. Vestir número herdado de recibo próprio seria LC-08.

## Ficou aberto

- **Merge do #5607** — ato [W].
- **Retratos da grade parados em 2026-08-08.** A rodada de 08-11 (origem do chip) não persistiu no ledger. Não investiguei — pode ser rodada morta no meio (o workflow é retomável desde 07-26) ou rodada que não chegou à fase Persistir. **Quem for rodar a grade de novo: leia `retratos.json`/`claims.json` antes**, o que fechou já está lá.
- **Efeito do conserto é observável só na próxima rodada.** O bite-test prova que o prompt carrega a fonte; **não prova** que o dossiê vai de fato marcar as fraquezas cobertas — isso é comportamento de agente, e a evidência é o próximo dossiê (§5 2026-07-19: reformular *habilita* o negativo, não *prova* que dispara).

## Pointers detalhados

- PR com o recibo completo + tabela de mordida: [#5607](https://github.com/wagnerra23/oimpresso.com/pull/5607)
- Método da grade: [`.claude/skills/reguas-do-sistema/SKILL.md`](../../.claude/skills/reguas-do-sistema/SKILL.md) (regra 1 e regra 4)
- Dono do inventário: [`scripts/governance/maquinas-inventario.mjs`](../../scripts/governance/maquinas-inventario.mjs)
- Lei da máquina de evolução: [ADR 0353](../decisions/0353-maquina-evolucao-reguas-looping.md)
