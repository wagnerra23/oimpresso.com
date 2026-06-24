---
title: Eixos canônicos de detecção de órfão/ponteiro-morto — 1 detector por eixo, registro central anti-redundância
status: proposed
date: 2026-06-24
deciders: [Wagner]
related_adrs: [0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0298-teto-de-governanca-anti-proliferacao-gates, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0263-identidade-cor-gate-bloqueante]
---

# ADR (proposta) — Eixos canônicos de detecção de órfão/ponteiro-morto

> **Status: proposta.** Número e aceite são do Wagner (soberania ADR). Nenhum código que roda
> cita esta proposta ainda (evita o Check L "proposto vs realizado" do `memory-health`).

## Contexto

A auditoria das máquinas (2026-06-24, [session log](../../sessions/2026-06-24-audit-maquinas-planos-runbooks-skills.md) §4) constatou que **"tela/ponteiro órfão" é detectado hoje em ≥3 lugares**, com inputs e camadas diferentes (teste Pest runtime, script node CI, serviço PHP de health), **sem um mapa** que diga qual eixo cada um cobre. Risco concreto: alguém (humano ou IA) **reinventa um detector redundante** — exatamente o que o método combate (PROCESSO_MEMORIA_CC §5 + teto anti-proliferação [ADR 0298](../0298-teto-de-governanca-anti-proliferacao-gates.md)). O próprio incidente que abriu esta sessão (`detectar-telas` falhando 2× por prosa antes de virar mecanismo) é o sintoma.

Lendo **cada fonte**, confirmou-se que existem na verdade **7 eixos distintos** de "X aponta pra Y, e Y não existe no disco OU existe mas está desligado" (não 5):

| # | Eixo | Detector (dono) | Detecta | Enforcement (fonte: `governance/required-checks-baseline.json`) |
|---|---|---|---|---|
| **E1** | `Inertia::render → page viva` | `tests/Feature/Architecture/OrphanRenderGateTest.php` | render aponta pra `Pages/X/Y.tsx` inexistente → 500 runtime / dead code | roda **todo PR sem continue-on-error** (intenção [ADR 0263](../0263-identidade-cor-gate-bloqueante.md)), mas o host `ui-architecture-gate.yml` **NÃO** está no `required-checks-baseline` → **não-enforced-como-required** |
| **E2** | `charter → protótipo` | `scripts/governance/charter-blueprint-pointers.mjs` | `blueprint_cowork`/Refs do charter → dir/arquivo de protótipo no vácuo | **advisory** (`reconcile-triplet.yml`, chamado **sem** `--strict`) |
| **E3** | `bundle → alvo no repo` (import-time) | `prototipo-ui/detectar-telas.mjs` | mockup do bundle Cowork sem alvo no repo (ORFAO) / >1 (AMBIGUO) | **script + `--selftest` advisory** (`design-memory-gates.yml`). É o passo ANTES dos outros — não duplica |
| **E4** | `charter ↔ .tsx` | `Modules/Jana/Services/CharterHealthChecker.php` (runtime) espelhado por `scripts/governance/charter-refs.mjs` (catraca) | `.tsx` roteada sem `.charter.md` / ref do charter quebrada | `charter_refs_broken ≤ teto` é **REQUIRED-ENFORCED** (está no `required-checks-baseline`); `charter_missing` advisory |
| **E5** | `US ↔ código` | `scripts/governance/anchor-lint.mjs` | âncora `**Implementado em:**` → path morto (`anchored_dead`) / desligado (`anchored_zombie`) | **advisory diff-aware** (`anchor-drift.yml`), [ADR 0273](../0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) + 0303 |
| **E6** | `charter visual_source → arquivo vivo` | `tests/Feature/Architecture/CharterVisualSourceGateTest.php` | `visual_source:` → arquivo de design morto/arquivado | roda **todo PR** (intenção ADR 0263), **NÃO** em `required-checks-baseline` → não-enforced-como-required |
| **E7** | `registry bloco → componente React` | `scripts/governance/component-registry-check.mjs` | entrada `mapped` → file/import/export inexistente | **advisory** (`component-registry.yml`, `continue-on-error`) |

> **Fonte de gate_status:** enforcement de branch-protection = só o que está em `governance/required-checks-baseline.json` (22 contextos congelados). O `scripts/governance/gates-registry.json` é fonte de **intenção/classe** (`terminal`/`promote_by`), não de enforced. Não confundir os dois — foi o erro que o adversário pegou no rascunho desta proposta (E1/E6 não são required-enforced; E4 é).

**NÃO são "órfão"** (são cobertura/correspondência/drift de outra natureza — registrados aqui pra evitar que alguém os "conserte" como se fossem o mesmo problema, ou os trate como um 8º eixo):
- `charter-us-lint.mjs` — charter↔US: mede **ausência** de `related_us` (cobertura do join), não ponteiro-morto.
- `reuse-index.mjs` — símbolo↔arquivo: índice anti-duplicação ("reusa ou cria"), não detecta órfão.
- `uc-derive.mjs` — UC↔teste: rastreabilidade.
- `reconcile-triplet.mjs` — 3-way charter×protótipo×produção: **reusa** o detector do E2 (`proto.missing`), é **consumidor**, não eixo novo.
- `charter-live-signal.mjs` — `status:live`↔sinal de prod: honestidade de estado.
- `plan-health.mjs` — planos órfãos/podres: staleness de plano (conceito diferente).

## Decisão

Nomear formalmente os **7 eixos canônicos** acima, com as regras duras:

- **R1 — 1 detector por eixo.** Cada eixo tem exatamente uma máquina-dona (E4 = par PHP-runtime + node-catraca, um detector lógico). Proibido um 2º detector pro mesmo par X→Y.
- **R2 — quem cria detector de órfão registra aqui primeiro.** Ou prova que é um eixo genuinamente novo (input/alvo que nenhum dos 7 cobre) e adiciona uma linha, ou reusa/estende o detector-dono (como `reconcile-triplet` já faz certo, reusando `proto.missing` do E2).
- **R3 — gate_status vem do `required-checks-baseline.json`** (enforced), não do `gates-registry.json` (intenção). Promoção advisory→required segue o calendário [ADR 0275](../0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5 (1 armamento/semana), nunca editada aqui direto.
- **R4 — sem índice paralelo.** A referência cruzada vive nesta ADR + na coluna "scripts-correspondência" da auditoria. Não se cria um 2º índice (gate T6 do método: estender o que existe; um gerador estilo `adr-index-generate` pode consumir esta ADR no futuro — ADR 0239).

## Consequências

**Positivo:** (a) nenhum detector redundante nasce sem decisão consciente — fecha o risco do "4º/5º detector" da auditoria; (b) 1 tabela diz qual máquina morde qual par X→Y e com que força; (c) corrige in-loco 2 erros factuais herdados (detectar-telas não é "o 4º eixo" — são 7; e o ADR do anchor é 0273+0303, não 0297, que é exceção append-only de frontmatter); (d) distingue **órfão** de cobertura/drift — evita fusões erradas.

**Custo:** (a) a ADR vira ponto de manutenção (1 linha por detector novo — é o objetivo); (b) risco de a tabela drifar do `required-checks-baseline.json` — mitigado citando-o como fonte, sem duplicar thresholds; (c) os eixos com enforcement misto (E4 advisory+catraca, E5 advisory diff-aware) continuam podendo deixar passar mentira **legada** por grandfather — decisão consciente (no-new-lie ADR 0275), não furo desta ADR.

## Alternativas consideradas

- **(A) Deixar em prosa na sessão de auditoria** — rejeitado: prosa na cabeça do agente foi a raiz que fez `detectar-telas` falhar 2×; o próprio §4 da auditoria pede "nomear num ADR".
- **(B) Fundir os 7 num mega-detector** — rejeitado: viola SoC brutal (Constituição v2 §5) e [ADR 0298](../0298-teto-de-governanca-anti-proliferacao-gates.md) (inputs/runtime/linguagem diferentes: Pest PHP vs node CI vs serviço health; required vs advisory; runtime vs filesystem-puro). 1 concern por máquina é a força do desenho.
- **(C) Índice gerado** (varre os scripts, monta a tabela) — **adiável**: bom como evolução (derivado>escrito, ADR 0239), mas hoje os 7 não têm um campo comum legível de "eixo". A ADR Nygard manual é o passo barato que trava a decisão agora; um gerador a consome depois.
- **(D) Tratar charter-us-lint/reuse-index/uc-derive/plan-health/charter-live-signal como eixos de órfão** — rejeitado após leitura: são cobertura, índice, rastreabilidade, staleness e honestidade de status — nenhum é "ponteiro aponta pra alvo inexistente/desligado". Incluí-los inflaria o conceito e convidaria fusões erradas.
