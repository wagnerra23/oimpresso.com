---
page: /ia/operacao
component: resources/js/Pages/Jana/Operacao.tsx
owner: wagner
status: draft
last_validated: "2026-07-31"
parent_module: Jana
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
related_adrs: [35, 61, 91, 94, 256, 275, 318, 344, 351]
tier: A
charter_version: 1
---

# Page Charter — Jana/Operacao (DRAFT · carimbado do PT-01)

> Nascida do Padrão de Tela **PT-01 Lista** via `criar-tela.mjs` (UI-0013 — herança de padrão,
> NÃO bespoke). Golden: [PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
> **Gêmea** de [`governance/Operacao`](../governance/Operacao.charter.md): mesmas 5 perguntas,
> mesmo arquétipo, mesmo componente — **corpus diferente**. Aquela cobre o ERP; esta cobre o
> sistema operacional de IA que constrói o ERP.

---

## Por que existe

Duas camadas degradam por razões distintas. O ERP quebra quando um módulo perde teste, tela perde
contrato ou gate fica vermelho. O **IA-OS** quebra quando a memória canônica apodrece, o recall
cai, o custo por PR sobe, um agente reincide numa classe de erro já catalogada, ou uma avaliação
vira teatro (verde que não pode ficar vermelho).

Misturar numa tela só esconde as duas. Por isso são gêmeas, não uma.

E há um fato medido que torna esta a mais urgente: das 12 métricas do processo, **as 4 que nunca
foram medidas são exatamente as da camada de IA e de dados** — `coverage_pct`, `ragas_real_uptime`,
`recall_eval_violations`, `backfill_error_rate`. É onde um defeito não aparece em gate nenhum,
porque não há medida.

### Posição na pilha de governança

Camada **Operacional** do IA-OS (cadência diária). Nome `Operacao` deliberado — deixa `Tatico` e
`Estrategico` livres pra nascerem irmãs. Ver [charter gêmeo §Posição](../governance/Operacao.charter.md)
pra a tradução de premissa do modelo em camadas.

---

## Mission

Responder as **mesmas cinco perguntas** do [W], aplicadas ao sistema operacional de IA:

| Pergunta | Resposta nesta camada |
|---|---|
| **O que está piorando?** | Ranking dos processos do IA-OS em queda (memória, recall, custo, eval, aprendizado). |
| **Quem responde?** | Dono do processo de IA e das peças afetadas. |
| **Por que piorou?** | Gate, métrica RAGAS, drift de memória, reincidência de lição, salto de custo. |
| **O que precisa ser feito?** | Ação com prioridade, prazo e responsável. |
| **Está melhorando?** | Tendência do recall, do custo e da taxa de reincidência. |

---

## Goals — Features (faz)

### 1 · O que está piorando — os processos do IA-OS

Lista ordenada por piora:

- **Memória canônica** — docs sincronizados, ghosts (nome citado que não existe mais), frescor das
  portas por módulo.
- **Recall e qualidade da resposta** — RAGAS real, `context_recall`, fidelidade.
- **Custo** — gasto por PR, por sessão, e a fatia não-atribuível a PR nenhum.
- **Aprendizado do agente** — classes de erro reincidentes e quais já viraram defesa mecânica.
- **Governança do próprio agente** — skills Tier A disparando, hooks vivos, gates do IA-OS.
- **Ancoragem doc↔código** — cobertura de âncora, links mortos, doc que afirma o que o código não faz.

### 2 · Quem responde

- Dono por processo. Pela matriz atual, `Eval / RAGAS / golden set` e `PII redactor BR` são de
  **[F]**; política de eval é de **[W]**. Os demais processos **não têm linha** — mesma dependência
  da gêmea.
- **Bus factor** aplicado ao IA-OS: quantas pessoas distintas tocaram cada área em 180d. Medido em
  2026-07-31 no repo inteiro — 96,7% dos commits numa identidade só, com ~7.000 trailers de
  co-autoria de agente. O conhecimento não está distribuído entre pessoas: está entre uma pessoa e
  um agente que não lembra entre sessões.
- `sem dono` em rose.

### 3 · Por que piorou

- A peça exata: métrica que caiu, eval que rodou e deu quanto, doc que ficou stale, classe de lição
  que reincidiu e com quantas ocorrências.
- **Sinal de instrumento morto** — o destaque mais importante desta tela: métrica cujo valor não
  varia há N execuções é sinalizada como **possível medidor quebrado**, não como saúde. Precedente
  medido: o `jana:drift-sentinel` deu `1.0` em 51 de 51 perguntas porque alimentava o juiz com a
  resposta certa como contexto — verde perfeito, medindo nada
  ([proibicoes.md §5, 2026-07-17](../../../../memory/proibicoes.md)).
- Linka pro detalhe existente: `/ia/admin/qualidade`, `/ia/admin/governanca`, `/ia/admin/custos`,
  `/ia/memoria`, `/copiloto/admin/cc-sessions`.

### 4 · O que precisa ser feito

- Ação derivada do estado, em imperativo, com responsável e prazo — ex.:
  `ragas_real_uptime · nunca medida · [F]: construir o transporte cron→scorecard`.
- Classe de erro reincidente sem defesa mecânica vira ação, com o nº de ocorrências — é o gatilho
  two-strikes que hoje só existe como aviso no início da sessão
  ([ADR 0344](../../../../memory/decisions/0344-two-strikes-cobre-processo.md)).

### 5 · Está melhorando

- Tendência de recall, custo e reincidência.
- **Taxa de conversão do aprendizado**: quantas classes viraram defesa mecânica, e quanto tempo
  entre a 2ª ocorrência e a defesa. É a medida de se o sistema aprende ou só registra.

### Transversal

- Multi-tenant Tier 0 (default biz=1) · degradação graciosa com a razão · PT-BR em tudo.

---

## Fontes de dados — todas derivadas

| Coluna | Fonte única |
|---|---|
| Métricas do processo | [`governance/sdd-scorecard-baseline.json`](../../../../governance/sdd-scorecard-baseline.json) |
| Qualidade real da IA | [`governance/jana-ragas-real-baseline.json`](../../../../governance/jana-ragas-real-baseline.json) + `jana:ragas-real-eval` |
| Saúde diária | `php artisan jana:health-check` — 5 checks ([ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)) |
| Memória e ghosts | `scripts/governance/knowledge-drift.mjs --json` |
| Frescor das portas | `distilled_at` dos `BRIEFING.md` por módulo |
| Custo por PR | `scripts/governance/agent-cost-per-pr.mjs` — advisory, relato, **nunca gate** |
| Aprendizado | [`memory/LICOES_CODE.md`](../../../../memory/LICOES_CODE.md) — classes, ocorrências, campo `Gate:` |
| Bus factor | `git log --since=180.days --format=%an` (autores distintos) |
| Máquinas do IA-OS | [`scripts/governance/gates-registry.json`](../../../../scripts/governance/gates-registry.json) |

**Regra dura:** número vem com data de medição ao lado. Incomodou? Re-roda a fonte — não edita o
número ([proibicoes.md §oráculo errado](../../../../memory/proibicoes.md)).

---

## Dependência bloqueante — decisão [W]

Mesma da gêmea: **não existe dono por processo**. Aqui só dois dos seis processos têm responsável
derivável do `TEAM.md` §3 (eval e PII, ambos [F]). A decisão é uma só e serve às duas telas —
ver [charter gêmeo §Dependência](../governance/Operacao.charter.md).

---

## Non-Goals — Features (NÃO faz)

> ⛔ **Preenchimento é do [W].** Cada item vira Pest GUARD; `charter-write` é proibida de inferir.
> Abaixo são **propostas**.

- _proposta_ ❌ Não avalia pessoa. Vale igual à gêmea, com uma razão a mais: a maior parte do
  trabalho desta camada é feita por agente, e "nota do agente" não se cobra de gente.
- _proposta_ ❌ **Não prevê com probabilidade.** Projeção só como aritmética verificável ("N dias
  no ritmo atual"). Número calibrado que ninguém calibrou é pior que número nenhum.
- _proposta_ ❌ Não roda eval nem dispara comando pela UI. Eval real roda no CT 100, agendado
  ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)). A tela
  mostra o resultado e a idade dele.
- _proposta_ ❌ Não agrega tudo num índice único de "saúde da IA". Somar recall com custo e com
  reincidência aponta pro lado errado — [lápide C9](../../../../memory/proibicoes.md).
- _proposta_ ❌ Não apaga nem esconde alarme. Apagar alarme é soberania [W], nunca resultado de medição.
- _proposta_ ❌ Não guarda estado próprio. Zero tabela nova.
- _proposta_ ❌ Não duplica `/ia/admin/qualidade`, `/ia/admin/custos` nem `/ia/memoria` — linka.

---

## Automation Anti-hooks

> ⛔ **Preenchimento é do [W].** Propostas abaixo.

- _proposta_ ❌ Nenhuma automação regrava baseline, desarma métrica ou "atualiza" carimbo de
  frescor a partir desta tela. Regravar baseline sem provar que o instrumento mede é o erro do
  drift-sentinel ([proibicoes.md §5, 2026-07-17](../../../../memory/proibicoes.md)).
- _proposta_ ❌ Nenhum campo auto-declarado vira semáforo. Frescor se mede contra a data-git do
  código, nunca contra data que alguém escreveu.

---

## UX Targets

- Cabe em **1280px** sem scroll horizontal.
- p95 first-paint < 800ms; payload caro (eval, custo) via `Inertia::defer` + skeleton.
- **Teste dos 10 segundos:** ao abrir, sem clicar, dá pra dizer o que está piorando na IA e quem
  age. Se não der, falhou — mesmo tudo verde.
- Métrica nunca medida lê como **buraco**, não como zero: slate com rótulo `sem medida`, jamais
  `0` nem verde.
- Cores Cockpit V2: rose=piorando/parado · amber=avisando · emerald=melhorando · slate=sem medida.

---

## UX Anti-patterns

- ❌ Número sem data de medição ao lado.
- ❌ Métrica ausente renderizada como `0` ou verde.
- ❌ Índice único de saúde da IA.
- ❌ Série constante exibida como estabilidade sem sinalizar suspeita de instrumento morto.
- ❌ Probabilidade de falha sem calibração declarada.
- ❌ `sessionStorage` · ❌ cor crua `bg-red-100`.

---

## Tests anti-regressão

- `e2e/jana-operacao.spec.ts` — stub carimbado citando o UC.
- Contrato: métrica `not_yet_measured` **não** renderiza como `0` (defende o anti-pattern acima).
- Multi-tenant: `business_id` scopado, biz=1 × biz=99.
- Ver [`Operacao.casos.md`](./Operacao.casos.md).

---

## Refs

- Padrão de Tela: [PT-01 Lista](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)
- Constituição UI v2: [UI-0013](../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- Gêmea (camada ERP): [`governance/Operacao`](../governance/Operacao.charter.md)
- Vizinhas no módulo: [`Admin/Qualidade`](./Admin/Qualidade/Index.charter.md) ·
  [`Admin/Custos`](./Admin/Custos/Index.charter.md) · [`Admin/Governanca`](./Admin/Governanca/Index.charter.md)
- [ADR 0318 — eval RAGAS real](../../../../memory/decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md) ·
  [ADR 0344 — two-strikes cobre processo](../../../../memory/decisions/0344-two-strikes-cobre-processo.md)
