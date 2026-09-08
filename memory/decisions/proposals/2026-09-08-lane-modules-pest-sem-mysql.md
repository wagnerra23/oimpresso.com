---
title: "A lane `modules-pest` roda SQLite — 9 módulos, e os testes de contrato ou pulam ou estouram"
status: proposed
date: "2026-09-08"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
origem: "Autofix acusou `Pest Repair` vermelho no PR #7047. Medido: falha no `main` também, com os mesmos UCs. Investigando a causa, o achado cresceu — não é o Repair, é a lane."
---

# A lane `modules-pest` roda SQLite — 9 módulos, e os testes de contrato ou pulam ou estouram

## O que foi medido

A lane `modules-pest.yml` roda **SQLite in-memory** (`:141` e `:157`, `DB_CONNECTION=sqlite`)
e sua matrix (`:97-106`) cobre **9 módulos**: Arquivos · AssetManagement · ComunicacaoVisual ·
Connector · Fiscal · Manufacturing · NfeBrasil · Repair · Vestuario.

Os testes de contrato desses módulos precisam do schema MySQL do UltimatePOS. Sem ele, cada
teste cai num de **dois desfechos, e nenhum dos dois é cobertura**:

| desfecho | quando | o que o CI mostra |
|---|---|---|
| **pula** | o teste tem guard `if (driver === 'sqlite') markTestSkipped(...)` | job **verde**, e o UC nunca rodou |
| **estoura** | o teste **não** tem o guard | job **vermelho**, `no such table: …` |

O Repair é o segundo caso; o AssetManagement é o primeiro. **Os dois são o mesmo defeito.**

### Recibos

**Repair, no `main` (`4f70a09460`) — estoura:**

```
SQLSTATE[HY000]: General error: 1 no such table: repair_statuses
  (Connection: sqlite, Database: :memory:,
   SQL: delete from "repair_statuses" where "name" like %[rst-contrato]%)

Tests: 20 failed, 119 skipped, 83 passed (287 assertions)
```

Os 20 são `RepairStatusContratoTest` (UC-RSTIDX-01..06) e `DeviceModelsContratoTest`
(UC-DMIDX / UC-DMCRE / UC-DMEDT). Falham no `main` e em todo PR, desde antes da frente do
Patrimônio — nenhum PR de Patrimônio tocou `Modules/Repair`.

**AssetManagement, no PR #7047 — pula, e o job fica verde:**

```
- it UC-BENS-01 … → SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL
- it UC-BENS-02 … → SQLite-incompatível
- it UC-BENS-03 … → SQLite-incompatível
- it UC-BENS-01 (espelho) … → SQLite-incompatível

Tests: 21 skipped, 58 passed (136 assertions)        job: success
```

**Este é o caso que assusta mais**, e é o meu: os 4 UCs da tela de Bens satisfazem o G-2 do
`casos-gate` (cada um é citado por um `it()`), o job sai `success`, e **nenhum deles jamais
rodou no CI**. A única execução real foi manual, no CT 100. É a [LC-13](../../LICOES_CODE.md)
literal: *skip sai exit 0; `0 failed` nunca prova execução — leia assertions*.

### O padrão que o projeto já tem, e que esta lane não usa

```
lanes que usam .github/actions/pest-mysql-setup ..... 20
a modules-pest.yml usa ..............................  0
```

A action existe, é usada por 20 workflows (`compras-pest`, `arquivos-pest`, `acessos-pest`,
`backup-pest`, `dashboard-pest`, …) e semeia baseline + migrations + tenants. As lanes
`PHP / Pest (<Mod> · MySQL)` que aparecem em `governance/required-checks-baseline.json` são
justamente as que a usam. A `modules-pest` ficou de fora.

## Por que isto importa mais do que 20 testes vermelhos

1. **O vermelho do Repair não bloqueia** (`Pest Repair` não é required) — é vermelho que se
   aprende a ignorar, e já está assim há tempo suficiente para ninguém notar.
2. **O verde do AssetManagement é pior que o vermelho**, porque afirma cobertura que não existe.
   O `casos-gate` mede *citação* (o UC tem um teste que o nomeia); ele não mede *execução*.
   As duas réguas juntas dão a impressão de contrato defendido.
3. **A frente do Patrimônio vai multiplicar isso.** As 4 telas recém-escritas (Painel,
   Alocações, Manutenções, Configurações) trazem ~20 UCs novos, todos com o mesmo guard de
   SQLite. Se entrarem como estão, entram com cobertura nominal e execução zero.

## O que se propõe

**Dar MySQL à `modules-pest`, reusando a action que já existe** — não inventar um terceiro
jeito de semear (o `pest-mysql-setup` já é o dono desse tema, e é o mesmo caminho que o
CT 100 usou para provisionar o staging em 2026-07-28).

Consequência esperada, e ela **não é confortável**: os testes hoje pulados passam a **rodar**,
e alguns vão ficar vermelhos de verdade. Isso é o ponto — o vermelho revelado é informação;
o verde atual é ruído. Recomendo tratar a leva revelada como dívida a triar, não como
regressão a reverter.

**O que NÃO se propõe:** remover o guard de SQLite dos testes. Ele está correto — é ele que
impede o teste de estourar num ambiente sem schema. O que está errado é o ambiente.

## Decisões que são de [W]

1. **Ligar MySQL na `modules-pest`?** Custo: a lane fica mais lenta (9 módulos × setup de
   banco). Ganho: os testes de contrato desses 9 módulos passam a existir de fato no CI.
2. **Se ligar, o que fazer com a leva revelada?** Triar módulo a módulo, ou segurar a lane
   como advisory até limpar.
3. **Os 20 do Repair têm dono?** Eles são anteriores a esta frente e não têm thread.

## Como reproduzir

```bash
# o vermelho do Repair, no main
gh api repos/wagnerra23/oimpresso.com/commits/4f70a09460/check-runs?per_page=100 --paginate \
  --jq '.check_runs[] | select(.name=="Pest Repair") | .conclusion'

# o skip do AssetManagement (leia as linhas com "→ SQLite-incompatível")
gh api repos/wagnerra23/oimpresso.com/actions/jobs/<id>/logs | grep -E "UC-BENS|Tests:"

# a lane e o banco dela
grep -n "DB_CONNECTION\|matrix" .github/workflows/modules-pest.yml

# o padrão que ela não usa
grep -rl "pest-mysql-setup" .github/workflows/ | wc -l    # 20
grep -c "pest-mysql-setup" .github/workflows/modules-pest.yml   # 0
```
