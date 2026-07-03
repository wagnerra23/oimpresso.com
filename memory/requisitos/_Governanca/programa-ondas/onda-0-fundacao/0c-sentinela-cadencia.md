---
titulo: Onda 0c — Sentinela de cadência (o vigia que faz durar)
status: proposto
owner: W
criado: '2026-07-02'
etapa: onda-0-fundacao
related: ../PLANO-MESTRE.md
---

# Onda 0c — Sentinela de cadência

> Status vivo do programa: [PLANO-MESTRE.md](../PLANO-MESTRE.md) §Status vivo (1 plano = 1 registro) — execução via tasks MCP `parent_plan=programa-ondas`.

## Objetivo

Responder a pergunta original do Wagner — "como isso **sobrevive ao tempo**?". O diagnóstico
de hoje é uma foto; sem um vigia, ela envelhece e ninguém percebe o débito voltar. A sentinela
é o pilar **cadência** da [ADR 0256](../../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md).

## Passos

### 1. Versionar o censo de exposição

Mover o protótipo desta sessão (`scratchpad/censo-exposicao.mjs`) para
`scripts/qa/exposicao-tier0.mjs`. Ele cruza as 3 camadas por tela/serviço:
- exposição Tier-0 (dinheiro/estoque/PII/fiscal, por conteúdo + módulo);
- cobertura de comportamento (E2E / `casos_coverage`);
- e ranqueia o débito.

Gravar baseline em `memory/governance/exposicao-tier0-baseline.json`.

### 1b. Limpar o universo ANTES de congelar baseline

O protótipo herda ruído da heurística: conta subcomponentes (`_drawer/*`, `_Showcase`) como
telas (universo 279 vs os 242 honestos do inventário) e credita "PII" por palavras genéricas
(`email`). **Antes do primeiro baseline:** alinhar o universo ao do `screen-coverage-map.mjs`
(excluir `_components/`, `Partials/`, pastas `_*`) e revisar os regexes de categoria. Baseline
congelado com ruído = catraca protegendo número errado.

### 2. Cron semanal (cadência)

Agendar como **workflow CI agendado** (`.github/workflows/`, `schedule:`) — **não** em
`app/Console/Kernel.php`: o Kernel roda no Hostinger (runtime de produção, sem o repo de
dev nem node garantido); este script analisa o REPO, e repo-análise mora no CI. O run semanal:
- recomputa o débito das 3 camadas;
- compara com o baseline;
- emite a **tendência** (o conjunto quente está encolhendo ou o débito cresce?);
- alerta se o piso Tier-0 for violado.

### 3. Modo `--check` na catraca

`node scripts/qa/exposicao-tier0.mjs --check` — exit 1 se o conjunto quente regredir abaixo
do piso; exit 0 caso contrário. Advisory no início; promovível a required só para a fatia
Tier-0 (padrão ADR 0271).

## Critério de pronto

- `--check` retorna exit 0 no baseline atual e exit 1 numa regressão simulada.
- Cron agendado + primeira execução emite a tendência.

## Verificação

```bash
node scripts/qa/exposicao-tier0.mjs --json    # grava baseline
node scripts/qa/exposicao-tier0.mjs --check   # exit 0
# simular: remover um teste do conjunto quente → --check exit 1
```

## Por que fecha o loop

Catraca (0b) impede regredir; sentinela (0c) **avisa** quando o débito cresce apesar da
catraca (ex: telas novas nascendo sem teste). Juntas, o conhecimento não depende de alguém
lembrar de checar — é a diferença entre controle manual (a REGRA MESTRE do `num_uf`) e
controle que sobrevive ao tempo.
