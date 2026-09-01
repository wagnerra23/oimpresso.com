---
date: "2026-09-01"
topic: "Fiscal PR-F0 — medir screen-coverage das 7 telas e publicar o numero (3 correcoes ao plano de 7 ondas)"
authors: ["C"]
outcomes:
  - "PR-F1 encolhe: baseline de pixel 7/7 ja commitado; falta so o E2E de fumaca"
  - "PR-B3 vira o item de maior alavancagem: 15 de 21 testes do Fiscal skipam"
  - "Gate fiscal.nfe.view JA tem teste (UC-FNFE-08 com controle negativo)"
---

# 2026-09-01 — Fiscal PR-F0: medir `screen-coverage` e publicar o número

> **Escopo:** só medição (PR-F0 da Onda 0 do plano de 7 ondas colado pelo [W] em 2026-09-01).
> Zero mudança de produto. Base medida: `origin/main` = `d85c003ade`.
> **Comando:** `node scripts/qa/screen-coverage-map.mjs` (+ `--screen Fiscal/<Tela>`).

## O número (o aceite do PR-F0)

```
Módulo   Telas  Charter  E2E  Score   VRT   L2
Fiscal       7        7    7      7     7    0
```

Mas a coluna `E2E` do agregado é **união** (`Browser ∪ VRT`) — o docblock do próprio script
avisa: *"14 dos 18 eram crédito de visreg lido como E2E"*. O detalhe por tela desfaz a leitura:

| Eixo | Fiscal | Recibo |
|---|---|---|
| Trio (tsx+charter+casos) | **7/7** | `--screen` em cada tela |
| Baseline de pixel (VRT) | **7/7** | 7 entradas em `tests/Browser/visreg-screens.json` (de 39 do repo) **e** os 7 `.snap` em disco |
| **Pest Browser de fato** | **1/7** | só `Cockpit`, via `tests/Browser/CoreScreens/AuthBridgeSmokeTest.php` (smoke genérico, não E2E do Fiscal) |
| `proto-baseline` | **0/7** | `✗ ausente` nas 7 |
| VISREG L2 (estados) | **0/7** | coluna L2 do agregado |

## Três correções ao plano

**1. `PR-F1` encolhe — a metade cara já existe.** O plano põe *"baseline VRT das 7 telas"* como
pré-requisito da Onda 1 (*"sem isto, 'não mudei layout' é opinião"*). Essa rede **está commitada**:
7/7 no manifesto + 7 `.snap`. Sobra a metade real: **1 E2E de fumaça** que abra as 7 com
`fiscal.*.view` — hoje 6 telas não têm nenhum teste Browser citando o path.
⇒ **A Onda 1 (A1→A4) está desbloqueada agora.**

**2. `PR-B3` não é o 4º da Onda 2 — é o item de maior alavancagem do plano.**
O plano o descreve como *"destravar 1 UC Tier 0 (`UC-FNFE-01`) + criar o teste do gate"*. Medido:
**15 de 21** arquivos de teste do Fiscal chamam `markTestSkipped`, por falta de `nfe_emissoes`,
`nfse_emissoes`, `nfe_eventos`, `nfe_dfe_recebidos`, ou por SQLite-incompatibilidade (ADR 0101).
⇒ a lane Fiscal hoje dá verde medindo uma fração do módulo — o formato de `0 failed` numa suíte
que não rodou (LC-13). **Ondas 1, 2 e 3 seriam validadas contra essa lane cega.**

**3. O gate `fiscal.nfe.view` TEM teste** — o plano diz *"sem nenhum teste"*. Existe
`UC-FNFE-08` em `Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php:72`, **com controle
negativo** (`:79`, superadmin não recebe 403), e mais 3 gates cobertos no mesmo arquivo
(`UC-FDFE-05`, `UC-FEVT-04`, `UC-FCFG-03`). Só que o arquivo inteiro skipa (`:50` e `:53`).
⇒ não falta escrever o teste; falta a lane rodá-lo. Colapsa em (2).

## O que o plano acertou (conferido contra `origin/main`)

- 7 telas × trio completo · 12 componentes · 7 scorecards · 11 controllers · 21 testes — batem.
- **0 contrato `fiscal-*`** em `prototipo-ui/contrato/` (15 contratos no total) — bate; `PR-F2` é real.
- Os **8 CU sem UC** (`CU-FISC-02·03·08·09·10·11·15·16`) — batem, linha a linha, com
  `memory/requisitos/Fiscal/_STATUS-GENERATED.md:30-37`.
- `US-FISCAL-022` × `CertHealthCheckCommand.php` + `CertHealthCheckCommandTest.php` — os dois
  existem; a divergência SPEC×código que o `PR-B4` nomeia é real.
- A base do plano (`49e6e333a057`) é ancestral de `origin/main` a **1 commit** — era atual.

## Limites honestos desta medição

- **Não rodei Pest.** Proibição Tier 0 (CT 100 only). O `15/21` vem de leitura contada de
  `markTestSkipped`, não de execução — quantos casos de fato pulam, só a lane responde.
- **`--check` da catraca não foi rodado** (só o relatório). Nada foi promovido nem regravado.
- Não medi a11y por tela: o agregado dá `5/215` no repo, sem coluna por módulo.
