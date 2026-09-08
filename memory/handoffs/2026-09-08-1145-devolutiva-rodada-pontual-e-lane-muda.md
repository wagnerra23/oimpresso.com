---
date: "2026-09-08"
time: "11:45 BRT"
slug: "devolutiva-rodada-pontual-e-lane-muda"
tldr: "Rodada pontual do --export-from apagava a devolutiva de 54 recusados; a 1a forma sugerida foi medida e e insatisfazivel por construcao. Ficou preservar + --limpar-devolutiva, com bite provado por mutacao. 2o achado: o bite nascia MUDO (arquivos sob teste fora do paths: da lane)."
prs: [6992]
decided_by: ["W"]
next_steps:
  - "Merge do #6992 e decisao [W] (2 commits, 0 falhas de CI)"
  - "O gatilho novo da lane so vale pra PR ABERTO daqui em diante — o on: nao tem synchronize"
---

# Devolutiva da recusa: rodada pontual não apaga retrato de outra rodada

## Estado MCP no momento do fechamento

⚠️ **MCP não conectado neste worktree filho** — usei o fallback filesystem, que é o caminho
legítimo documentado em [`how-trabalhar.md` §Fallback](../how-trabalhar.md). O snapshot abaixo vem
do `brief-fetch` do SessionStart (brief #619, gerado há ~1h no início) + git:

- Cycle: — · HITL pending [W]: 5 · Brain B: 0% (0/50)
- Handoffs de 08/09 em `origin/main` no momento da escrita: **nenhum** (sem colisão de índice)
- Session logs de 08/09: **nenhum** (sem duplicação)
- Base: `origin/main` = `11eff17f13` no início; +5 commits durante a sessão, rebaseado, sem colisão

## O que aconteceu

O bloco `--export-from` removia `prototipo-ui/CODE_NOTES.recusados-canon.md` em toda rodada sem
recusa. **Reproduzido em sandbox antes de tocar em código:** 1 JSON avulso (zero canon de tela)
apaga o retrato de **54 recusados de 07/09 (12.913 B)** imprimindo *"nenhuma recusa nesta rodada"*.

A causa não é o `rmSync` — é a premissa do autor, verdadeira para rodada COMPLETA e falsa para
PONTUAL, sendo que o painel classifica o `--export-from` como **a rota do caso pontual**.

**A 1ª forma sugerida foi medida e é insatisfazível:** *"só remover quando a rodada cobre o
universo do retrato"* — o retrato só contém paths que passam por `RE_CANON_DE_TELA`, logo incluir
um deles **o recusa de novo**; "cobriu o universo" e "zero recusa" são mutuamente exclusivos por
construção. Seria um ramo de remoção que nunca dispara. Limiar também caiu (denominador inventado).

**Ficou:** preserva, diz de quando o retrato é, e oferece `--limpar-devolutiva` — declaração do
operador, nomeada pelo efeito e não por propriedade que o script não checa.

## O 2º achado (fora do pedido, no escopo do "verificável")

O bite-test nascia **MUDO**: os arquivos sob teste não estavam no `paths:` do
`design-memory-gate.yml` e `gh pr checks | grep -ci design-memory` = **0**. Commit 2 corrige
(29 → 31 paths). ⚠️ **Ressalva:** o `on:` da lane é `types: [opened, reopened, ready_for_review]`,
**sem `synchronize`** — o gatilho vale pra PR aberto daqui em diante, não se re-dispara por push.

## Artefatos gerados

| arquivo | o quê |
|---|---|
| `scripts/governance/cowork-mirror-freshness.mjs` | +59/−5 · `lerRetratoDevolutiva` + ramo condicional + 3ª entrada no HISTÓRICO |
| `scripts/governance/cowork-mirror-freshness.test.mjs` | +65 · seção 13, 7 asserts pelo CLI de fora |
| `.github/workflows/design-memory-gate.yml` | +5 · os 2 arquivos sob teste no `paths:` |

## Persistência

- **git:** PR [#6992](https://github.com/wagnerra23/oimpresso.com/pull/6992) (2 commits) — aberto, merge é [W]
- **MCP:** webhook propaga este handoff + session log após merge
- **BRIEFING:** não aplicável (governança, não módulo)

## Próximos passos pra retomar

```bash
gh pr view 6992 --json statusCheckRollup,mergeStateStatus
```

## Lições catalogadas

- **Recibo LC-08 (near-miss meu, registrado):** montei o baseline dos modos de CI copiando o script
  para `__base-tmp.mjs`; saiu **rc=0 com zero output** e eu ia ler como *"baseline limpo"*. Eram
  **não-execuções** — o guard de entry-point exige que o `argv[1]` termine em
  `cowork-mirror-freshness.mjs`. Só um modo discordando expôs; se o defeito fosse uniforme, os 7
  teriam "concordado" e eu publicaria uma comparação de nada. §5 2026-07-29 + o controle positivo
  de §5 2026-08-01 — as duas lápides existiam, faltou aplicá-las **antes** da tabela.
- **Sem lápide §5 nova para o defeito consertado:** classe já catalogada 2× (2026-08-10 ·
  2026-08-04). Este trabalho é a **execução** que §5 2026-08-02 pedia.

## Pointers detalhados

- Session log: [`2026-09-08-devolutiva-recusados-rodada-pontual.md`](../sessions/2026-09-08-devolutiva-recusados-rodada-pontual.md)
- Origem da devolutiva: #6945 · descoberta do defeito: #6990
