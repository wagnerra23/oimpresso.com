---
slug: 0374-scope-md-sai-de-modules-para-memory-requisitos
number: 374
title: "SCOPE.md e LICOES-OPERACAO.md saem de Modules/ para memory/requisitos/"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-10"
accepted_via: "Regra do dono [W] 2026-08-10: 'nao pode existir arquivos de memory fora da pasta de memoria', reafirmada como 'nao deve ter nada fora'. O agente MEDIU o custo (42 consumidores, 2 gates required, leitura em runtime de producao) e apresentou; [W] manteve a decisao. Esta ADR registra o raio ANTES do git mv — o merge dela e o ato de ratificacao, o agente nao ratifica."
module: governance
quarter: 2026-Q3
tags: [memoria, governanca, contrato-de-path, tier-0, scope]
supersedes: []
supersedes_partially: []
superseded_by: []
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
---

# ADR 0374 — `SCOPE.md` e `LICOES-OPERACAO.md` saem de `Modules/` para `memory/requisitos/`

## Contexto

Regra do dono ([W], 2026-08-10): **"não pode existir arquivos de memory fora da pasta de memória"**, reafirmada como **"não deve ter nada fora"**.

As fases 1 e 2 ([#5547](https://github.com/wagnerra23/oimpresso.com/pull/5547) · [#5548](https://github.com/wagnerra23/oimpresso.com/pull/5548)) moveram **45 docs** (`CHANGELOG` · `README` · `CONTRACTS`) para `memory/requisitos/<X>/`. Restam **33**: `SCOPE.md` (32) e `LICOES-OPERACAO.md` (1, Jana).

Esta ADR existe porque este lote **não é da mesma natureza dos 45 anteriores**, e o raio precisa estar escrito antes do `git mv` — não descoberto por acidente depois.

## O raio, medido (2026-08-10, `origin/main`)

| eixo | número | como foi medido |
|---|---:|---|
| arquivos movidos | 33 | `git ls-files ':(glob)Modules/*/*.md'` |
| consumidores não-`.md` | **42** | `rg --hidden -g '!.git/**' -g '!*.md' -l "SCOPE\.md"` (repo inteiro) |
| gates **`required`** derivados | **2** | `catalog.json == SCOPEs + Classes B` · `Governance Gate` |
| leitura em **runtime de produção** | sim | `DriftAlertService:65` → alimenta `/governance/drift` + cron |
| hook local do time | sim | `.githooks/pre-commit` roda `check-scope` na máquina de cada dev |

Distribuição dos 42: 4 workflows de CI · 2 binários (`bin/check-*.php`) · 7 arquivos do `Modules/Governance` (incl. 3 de tradução) · `app/Console/Kernel.php` · `GenerateModuleSpecsCommand` · 8 scripts de `scripts/governance/` · 1 componente React (`DriftAlerts.tsx`) · 5 JSONs derivados · 1 migration · testes Pest · `.githooks/pre-commit`.

**O que torna este lote diferente:** os 45 eram documento — ninguém os lê em runtime. `SCOPE.md` é **contrato lido por máquina**, e a leitura é por **construção de path**:

```php
// bin/check-scope.php:319 e :393 — padrão que se repete nos consumidores
foreach (glob('Modules/*', GLOB_ONLYDIR) as $moduleDir) { … }
$scopePath = $moduleDir . '/SCOPE.md';
```

Mover exige alterar a **lógica de descoberta de módulos**, não substituir texto. Um `sed` sobre `Modules/<X>/SCOPE.md` **não resolve** — a maioria dos consumidores nunca escreve esse literal.

## Decisão

Mover os 33 para `memory/requisitos/<Modulo>/`, em **PR próprio e isolado**, com a reescrita dos 42 consumidores no mesmo PR.

A decisão é do [W] e está registrada. Esta ADR **não a reabre** — registra o custo para que a execução seja auditável e para que quem vier depois entenda por que 42 arquivos de código mudaram junto com um "move de documentação".

## Consequências

**Aceitas.** `SCOPE.md` deixa de ser vizinho do código que descreve; o pré-flight da [`.claude/rules/modules.md`](../../.claude/rules/modules.md) passa a apontar para `memory/requisitos/<X>/SCOPE.md`. Um PR que mexa em módulo passa a tocar duas árvores.

**Risco principal, declarado.** Os 2 gates `required` derivam do `SCOPE.md`. Erro na reescrita não produz "PR vermelho": produz **merge travado para o time inteiro** até o conserto. Por isso o PR é isolado — para que reverter seja um `git revert` de um commit, não arqueologia.

**Produção.** `DriftAlertService` roda scan em runtime; a tela `/governance/drift` e o cron mudam de fonte no mesmo deploy — sem janela em que metade do sistema leia o path antigo.

## Alternativas consideradas

1. **Manter `SCOPE.md` em `Modules/`** — tratá-lo como metadado (igual `module.json`/`composer.json`), não como memória. **Recusada por [W]**: a regra é "nada fora", sem exceção.
2. **Symlink ou arquivo-ponteiro no lugar antigo** — descartada: duplica a fonte de verdade, que é o problema que a regra quer eliminar.

## Verificação (DoD)

Rodados **no modo que o CI roda** — lição desta série: o job `deadlink-gate (ratchet · …)` executa `--check`, não `--ratchet`; nome do job ≠ modo do comando:

- [ ] `php bin/check-scope.php --strict` · `--declared` · `--selftest`
- [ ] `node scripts/governance/catalog-graph.mjs --check` (freshness + aresta pendurada)
- [ ] `node scripts/governance/module-surface.mjs --all --check`
- [ ] `node scripts/governance/deadlink-gate.mjs --check`
- [ ] `node scripts/governance/knowledge-drift.mjs --check`
- [ ] zero referência órfã: varredura contada em `.php`/`.mjs`/`.js`/`.yml`/`.json` **e** `.md`
- [ ] `DriftAlertService` lê o novo caminho — provado por **teste**, não por leitura de código

## Registro honesto da série

As fases 1 e 2 moveram **documentação pura** e ainda assim exigiram: 5 mecanismos do repo corrigindo o agente, 3 absorções de baseline (todas em commit isolado com `BASELINE-ABSORB`), 2 refutadores GT-G5 que acharam 2 erros reais, e **4 eixos distintos** da mesma classe "mover arquivo quebra referência" — path do arquivo movido, quem aponta para ele, prefixo com alvo já morto, e link dentro de arquivo append-only. Cada um invisível ao conserto do anterior.

Este lote tem 42 consumidores e produção do outro lado. A expectativa realista **não** é que saia limpo de primeira.
