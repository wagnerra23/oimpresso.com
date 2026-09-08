---
slug: 0391-regime-de-evolucao-por-loops-blade-fora
number: 391
title: "Regime de evolução por loops vale para tudo que não é Blade; Blade fica fora e morre na migração; programa por área com etapas MEDIDAS (não afirmadas)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-07"
module: governance
quarter: 2026-Q3
tags: [governanca, evolucao, loops, blade, mwart, programa, etapas, medicao, presence-gate, two-strikes]
supersedes: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0344-two-strikes-cobre-processo
  - 0234-automation-registry-mcp
pii: false
review_triggers:
  - "uma etapa do programa fechar (alvo atingido) — decidir se sai do manifesto ou vira catraca perene"
  - "o contador Blade (E10) chegar a 0 — E10 e a decisao 'Blade fora' perdem objeto"
  - "[W] mudar um alvo do manifesto (alvo e decisao do dono, nao do agente)"
---

# ADR 0391 — Regime de evolução por loops: universal fora do Blade; Blade morre na migração; etapas medidas

> Nasce `proposto`. [W] autorizou em 2026-09-07 (*"blade fica fora. ele vai morrer depois de migrar. pode fazer isso tudo"* e *"crie o plano e torne todas as etapas válidas com teste"*). O merge de uma ratificação própria segue sendo o ato formal (ADR 0257).

## Contexto

[W] perguntou em 2026-09-07 *"como fazer o sistema evoluir com o tempo?"* e depois *"vai servir para todas as máquinas do sistema ou só para algumas partes?"*. A resposta exigiu medir, não afirmar (LC-08). Medido contra `origin/main` em 2026-09-07:

| Loop de evolução | Alcance | Recibo |
|---|---|---|
| Sinal de entrada (0105/0382) · ADR por sucessão · lápide+ledger (0344) · derivado>escrito (0256) · memória em git+MCP (0061) · governança da governança | **universal** — qualquer arquivo, qualquer área | canon always-on |
| Trio por tela (charter/casos/teste) · design Cowork (0114/0282) · catracas por tela | **só telas Inertia** | `npm run casos:report`: 218 telas, 67 sem casos · `screen-coverage:report`: charter 218/218, E2E 54/218, A11Y 20/218, scorecard 186/218 |
| Pest required · PHPStan ratchet | **7 lanes required** (Sells, Compras, Estoque, Financeiro, KB, NfeBrasil, Ponto) por desenho da 0314 | `governance/required-checks-baseline.json` (45 contexts) |
| SDD por módulo (0275) | 11 de 32 módulos com `SDD-*.md`; `anchor_coverage` 86,1% | `governance/sdd-scorecard.json` |
| Blade legado | **nenhum loop de tela** | `git ls-files "*.blade.php"`: 1.085 views (Essentials 87, Crm 68, Repair 52, Superadmin 46, Cms 45) |
| Infra CT 100 / Hostinger | "mexeu, registra" + `cron-watchdog` | sem gate de CI por construção |

Onze módulos não são disparados por lane Pest alguma (grep `Modules/<X>/` em `.github/workflows/*-pest.yml`): Auditoria, Cms, ConsultaOs, Crm, OficinaAuto, PaymentGateway, ProductCatalogue, RecurringBilling, Spreadsheet, VozDoCliente, Woocommerce.

O ledger alarma 12 classes LC-* com ≥2 ocorrências sem gate (`licoes-code-two-strikes.mjs`), a maior LC-08 com 126.

## Decisão

**D1 — Blade fica fora do regime.** Nenhuma view Blade recebe charter, casos, contrato visual, regressão visual ou scorecard. A tela entra no regime **quando vira Inertia** pelo MWART (ADR 0104, F5 deleta a Blade). A única etapa Blade do programa é o **contador da morte** (E10), que só desce.

**D2 — O regime completo vale para tudo que não é Blade.** As seis famílias universais já valem. As seis por área (trio · design · teste · SDD · ledger · infra) passam a ter **alvo declarado = a população inteira** de cada área, com a catraca existente garantindo "não regride" e este programa medindo "quanto falta".

**D3 — O programa é um manifesto com etapas MEDIDAS**, `.claude/regime-evolucao.json`, lido pelo **mesmo hook** do loop IA-OS (`loop-fechar-check.mjs --manifest …`). Estender o dono em vez de autorar máquina paralela (§5 2026-08-03, LC-19). Registrado no `_automation_registry` (ADR 0234).

| Etapa | Área | Porta viva | Alvo | Medido 2026-09-07 |
|---|---|---|---|---|
| E1 | Telas — casos | `casos-coverage-guard --json` · `missing_casos` | 0 | 67 |
| E2 | Telas — scorecard | `screen-coverage-map` · SCORECARD | todas | 186/218 |
| E3 | Telas — E2E | `screen-coverage-map` · E2E | todas | 54/218 |
| E4 | Telas — A11Y | `screen-coverage-map` · A11Y | todas | 20/218 |
| E5 | Módulos — SDD | `governance/sdd-scorecard.json` · `anchor_coverage` | 100% | 86,1% |
| E6 | Backend — lane Pest | grep de `Modules/<X>/` nas `*-pest.yml` | 0 sem lane | 11 |
| E7 | Ledger | `licoes-code-two-strikes.mjs` · classes ≥2 sem gate | 0 | 12 |
| E8 | Design — espelho | `cowork-mirror-freshness --sla` | exit 0 | exit 1 (desde 2026-08-13) |
| E9 | Infra — crons | `cron-watchdog` (sob demanda, exige `gh`) | exit 0 | exit 0 |
| E10 | Blade — morte | `git ls-files "*.blade.php"` | 0 | 1.085 |

**D4 — Detect por COMPORTAMENTO, tri-estado.** O detect `comando` roda a porta viva e compara o número com o alvo. `feito | pendente | nao_medido` são três estados: exit inesperado, regex que não casa ou comando ausente é **NÃO MEDIDO**, nunca pendente nem feito (§5 2026-07-29). Alvo fracionário é **derivado da própria saída** (`alvo_grupo`), não número escrito à mão que apodrece quando a população muda (§5 2026-07-17). `done: false` explícito veta um feito medido (veto manual de 2026-07-27, agora também para `comando`). Isto é a resposta da LC-11 (presence-gate) dentro do próprio hook que a cometeu.

**D5 — Cada etapa é válida por teste.** O selftest do hook (`loop-fechar-check.test.mjs`, lane `governance-script-tests`) tem bite-tests do mecanismo (alvo fora → pendente · exit≠0 → nao_medido · rc 127 → nao_medido · veto · cache vencido → remede · memo) **e roda o detect real de cada etapa do manifesto**, falhando se alguma não medir. Etapa `sob_demanda` só escapa declarando `dependencia_externa`. Manifesto novo ou etapa nova sem detect que meça **quebra o selftest**.

**D6 — Custo de sessão controlado.** Cache datado em `.claude/run/` (gitignored) com validade de 24h por etapa; vencido, mede de novo; `--medir` força; comandos idênticos rodam uma vez. O banner mostra a data do retrato quando é cache. Medição completa a frio: ~65s; com cache, zero comandos.

**D7 — O que NÃO vira etapa.** Required continua só Tier-0 (ADR 0314; §5 2026-07-01). Cadência automática da grade de réguas não entra (ADR 0353 D2; §5 2026-08-08). US sem dono é do MCP e fica no brief. Mudar alvo é decisão [W] no manifesto.

## Consequências

- O SessionStart passa a mostrar, ao lado do loop IA-OS, **quanto falta em cada área** com o número da porta viva e a data da medição. "Não medido" é visível e distinto de pendente.
- A execução das etapas é trabalho normal por PR (1 tela, 1 módulo, 1 lane por vez), com os executores nomeados no manifesto (`sdd-from-source`, `screen-qa-specialist`, `mwart-process`, `testador-de-maquinas`, `aplicar-prototipo`). Esta ADR não executa etapa nenhuma.
- E7 não fecha só por gate: parte das 12 classes tem gate óbvio **medido e reprovado**, e apagar o alarme é decisão [W] (`Gate: advisory-terminal`). O banner vai ficar vermelho ali até essa decisão — é o desenho, não defeito.
- E8 fica pendente até alguém autenticado rodar a rotina do espelho; o CI não consegue (§5 2026-08-27). O programa torna essa medição órfã **visível** em toda sessão.

## Alternativas rejeitadas

- **Script novo de "cobertura por área"** — LC-19 (máquina paralela) e §5 2026-07-23/07-25 (mapa/painel/índice novo do sistema morreu 2×). O hook existente é o dono; foi estendido.
- **Detect por existência de arquivo** (`file_any`) para SDD, lane Pest, casos — LC-11; o mesmo hook já pagou esse erro no item #6 do IA-OS.
- **Alvo intermediário por etapa** (ex.: "E2E 50% até outubro") — a catraca já garante não-regressão; marco de calendário em manifesto vira número escrito que apodrece. Alvo é o todo; ritmo é do cycle.
- **Rodar tudo a cada SessionStart sem cache** — 65s por sessão é custo que faria o banner ser desligado; cache datado preserva honestidade com custo zero.
- **Investir charter/casos em Blade "enquanto não migra"** — [W] decidiu o contrário; o custo iria para telas que serão deletadas.

## pre-adr-introspect — relatório

- Tema: regime de evolução por área + Blade fora + programa medido.
- Número: 0391 (`next-id.mjs adr` em 2026-09-07 ✅).
- Patterns canon reusados: `loop-fechar-check.mjs` + manifesto `_automation_registry` (dono do tema "etapas + detect", ADR 0234) — **EXTEND**; portas vivas `casos-coverage-guard`, `screen-coverage-map`, `sdd-scorecard`, `licoes-code-two-strikes`, `cowork-mirror-freshness`, `cron-watchdog` — **REUSAR** como oráculos; ADR 0104 (Blade morre na F5) — reafirmada, não superseded.
- Prior art externa (item 6): "definition of done por métrica observável" e *fitness functions* (Ford/Parsons/Kua, *Building Evolutionary Architectures*, 2017) — o programa é uma lista de fitness functions com alvo; o vocabulário `feito/pendente/nao_medido` fica porque o tri-estado é o ponto que a literatura não enfatiza e o §5 já cobra. Ferramenta madura na stack: nenhuma cobre o eixo "manifesto lido em SessionStart"; o custo de adotar (ex.: ArchUnit-like) seria maior que 60 linhas no dono.
- Decisão: **EXTEND** o hook + **REUSAR** as portas vivas. Zero script novo.
