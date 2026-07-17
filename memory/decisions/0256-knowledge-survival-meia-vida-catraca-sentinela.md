---
slug: 0256-knowledge-survival-meia-vida-catraca-sentinela
number: 256
title: "Knowledge Survival — conhecimento tem meia-vida; sobrevive por catraca + sentinela + gate + cadência"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-07"
module: governance
related:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0180-drift-numero-adr-0178-conflito-paralelo
  - 0215-secrets-governance-5-camadas-automaticas
  - 0250-screen-qa-specialist-sustentavel
---

# ADR 0256 — Knowledge Survival

## Contexto

Auditoria 2026-06-07 (`memory/governance/AUDITORIA-CONFLITOS-MEMORIA-2026-06-07.md`) achou, na base de conhecimento canônica, a mesma classe de podridão que já tínhamos combatido em **código** e **telas**: notas fantasma (46 "telas baixas" → 5 reais), 2 camadas de memória legada contradizendo o canon, 13 colisões de número de ADR, fatos `reference/` divergindo, **10 arquivos com segredo em claro no git**, e um cron (`memcofre:sync-memories`) que ressuscitava o legado e vazava credenciais toda noite.

**Diagnóstico-raiz:** os mecanismos certos JÁ EXISTIAM e foram **burlados ou ficaram incompletos** — `secrets:scan` com regex cego pra senha simples; ADR 0061 (zero auto-mem) sem dente contra o cron; registro de colisão ADR manual que furou (0236/0246); scorecards sem gatilho de re-grade. O conhecimento não foi escrito errado — ele **apodreceu sozinho** porque ninguém apontou pra ele a máquina de manutenção que o resto do projeto usa.

## Decisão

**Tratar conhecimento como produto com meia-vida.** Todo artefato canônico (ADR, reference, scorecard, charter, índice) está sujeito aos mesmos 6 princípios de sobrevivência abaixo. Não basta escrever certo uma vez; tem que ser **mecanicamente** protegido de regressão e **continuamente** podado.

### Os 6 princípios

1. **Fonte única por fato.** Cópia derivada é *gerada* ou marcada *histórica/congelada* — nunca duplicata mantida à mão (ex: board MD/HTML do screen-grade são derivados do JSON; os 222 YAMLs são a fonte viva). Template: `memory/governance/scorecards/FONTE-UNICA-QUALIDADE-TELAS.md`.
2. **Catraca.** Todo ganho trava; regredir exige override consciente (espelha `module-grades-gate` ADR 0155 + `screen-grades-ratchet` ADR 0250).
3. **Sentinela de frescor.** Conhecimento tem validade. Stale = flag automático (Daily Brief / health-check), não confiança silenciosa.
4. **Gate na porta.** Impedir o ruim de *entrar* no CI (segredo, canon concorrente, colisão ADR não-registrada) — não limpar depois.
5. **Self-healing.** Quando a fonte muda, o derivado se regenera (ex: re-grade automático quando `.tsx` muda), em vez de quebrar e esperar humano.
6. **Cadência de evolução.** Revisão periódica barata (mensal/no Brief) que poda/funde/atualiza — limpeza contínua, nunca heroica.

### Mapa podridão → mecanismo

| Classe de podridão | Mecanismo de sobrevivência | Estado |
|---|---|---|
| Segredo em git | `secrets:scan` endurecido (pega senha PT-BR/alta-entropia/UUID) + check no `memory-health` | 🟡 a construir |
| Auto-mem ressuscitando | cron `memcofre:sync-memories` DESATIVADO (PR purga); só volta via ADR que reverta 0061 | ✅ feito (PR #2383) |
| Nota/scorecard fantasma | sentinela "scorecard stale" (`.tsx` mudou após `graded_at`) → flag + re-grade | 🟡 a construir |
| Colisão de número ADR | `AdrNumberCollisionTest` + check no `memory-health` + **referenciar por slug, nunca número** | 🟡 reforçar |
| Docs legados acumulando | `lifecycle:` + `reviewed_at:` obrigatórios + sentinela de stale | 🟡 a construir |
| Canons concorrentes | regra "fonte única" + flag de 2+ docs reivindicando canon no mesmo tópico | 🟡 a construir |

### A cadência ("Memory Health" — o batimento cardíaco)

Um sentinela `memory-health` (espelho do `jana:health-check` pra dados) roda as checagens mecânicas da auditoria de 2026-06-07 de forma **automática** — no CI (PRs que tocam `memory/**`) e no Daily Brief. O que apodreceu vira flag/PR, não descoberta acidental daqui 3 meses. A skill `consolidate-memory` é o motor de poda manual complementar.

## Roadmap de implementação (ondas)

- **Onda 0 (feita nesta sessão):** consolidação fonte-única telas (PR #2378), auditoria (PR #2379), P2/P3/P1b/P0-P1a (PRs #2380-2383), cron desativado.
- **Onda 1:** `scripts/governance/memory-health.mjs` (checks: colisão ADR não-registrada · scorecard fantasma · segredo em `memory/**` · doc stale) + workflow CI + linha no Daily Brief.
- **Onda 2:** endurecer `SecretsScanCommand` (patterns PT-BR senha / alta-entropia / token UUID) — validar no CT 100.
- **Onda 3:** `reviewed_at:`/`lifecycle:` obrigatórios em docs canon (schema-gate) + sentinela de frescor por idade.
- **Onda 4:** self-healing — re-grade automático no `screen-smoke-after-merge` quando `.tsx` muda.

## Consequências

- ✅ A auditoria de hoje deixa de ser evento e vira processo. Regressão de conhecimento exige decisão consciente (catraca) ou é flagada (sentinela).
- ✅ Princípios reusáveis pra qualquer artefato futuro (não só os achados de hoje).
- ⚠️ Custo: manter os gates/sentinela. Mitigado por serem determinísticos e baratos (Node, sem LLM no caminho crítico).
- ⚠️ `reviewed_at` obrigatório adiciona fricção a docs novos — aceitável (é o "preço da frescura").

## Refs
- Auditoria: `memory/governance/AUDITORIA-CONFLITOS-MEMORIA-2026-06-07.md`
- Fonte-única template: `memory/governance/scorecards/FONTE-UNICA-QUALIDADE-TELAS.md`
- Espelha: ADR 0155 (module-grade gate) · ADR 0250 (screen-qa sustentável) · ADR 0215 (secrets governance) · ADR 0061 (zero auto-mem) · ADR 0180 (colisão ADR)
