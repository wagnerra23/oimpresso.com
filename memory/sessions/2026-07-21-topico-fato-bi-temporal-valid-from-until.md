---
date: "2026-07-21"
topic: "Fato bi-temporal no tópico vivo: valid_from/valid_until ancorados em supersessor verificável (gap Zep da sub-dimensão Memória viva)"
authors: [W, C]
prs: []
related_adrs:
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# Session log 2026-07-21 — fato bi-temporal no tópico (valid_from/valid_until)

## TL;DR

A grade de réguas nomeou um gap na sub-dimensão **Memória viva** (barra = Letta/Zep/Swimm, 7,5): o **fato bi-temporal do Zep** — o tópico deveria carregar `valid_from`/`valid_until` pra comportamento superado **auto-expirar**, em vez de valer até alguém notar. Estendi o `topico.schema.json` (ADR 0345, grace/forward-only) com os dois campos + as âncoras de supersessão, atualizei template e piloto, e validei em AJV com fixtures adversariais. **O ponto de projeto** — e o que diferencia do anti-padrão §5 — é que `valid_until` **não pode ser data solta escrita à mão**: o schema exige nomear o que superou o fato (ADR supersessor ou tópico sucessor), e a expiração real é decidida pelo leitor resolvendo essa âncora, não pela data.

## Contexto

O Zep (Graphiti) modela cada aresta como bi-temporal: tempo-de-domínio (`valid_at`/`invalid_at` — quando o fato é/foi verdadeiro no mundo) separado do tempo-de-transação (`created_at`/`expired_at` — quando o sistema aprendeu/esqueceu). Quando um fato novo contradiz um velho, o velho ganha `invalid_at` = o `valid_at` do novo. O tópico já tinha `updated_at` (transação); faltava o eixo de domínio.

O risco a evitar é o catalogado em `proibicoes.md §5` — `last_validated`/`verificado_em`: **campo de data auto-declarado que apodrece**. Uma `valid_until` nua ("este tópico expira em X") escrita à mão é exatamente isso: uma promessa sem fato por trás. O SessionStart desta sessão ainda flagrou a classe **LC-08** ("afirmar/derivar/medir a partir da fonte errada, sem provar") como reincidente. A trava tinha que ancorar num **fato verificável**.

## Decisão de projeto

`valid_until` é **inseparável de um supersessor nomeado**. A expiração carrega o motivo; o motivo é resolvível a um arquivo versionado real.

- **Schema (`topico.schema.json`)** — 4 campos novos top-level, todos opcionais (forward-only):
  - `valid_from` — data de domínio (evento verificável: incidente/fix/ADR). Histórica, não apodrece.
  - `valid_until` — data de domínio em que o fato deixou de valer. Presença = superado.
  - `superseded_by_adr` — slug do ADR supersessor (pattern `^[0-9]{4}-...`).
  - `superseded_by_topic` — id do tópico sucessor (pattern kebab).
  - **`dependentSchemas.valid_until` → `anyOf[superseded_by_adr | superseded_by_topic]`**: se há `valid_until`, o tópico **DEVE** nomear o supersessor. Data solta = inválida.
  - **`dependentRequired`**: nomear supersessor **exige** `valid_until` (sem supersessor pendurado). Acoplamento bidirecional: `valid_until ⟺ supersessor`.
- **Isto NÃO é presence-gate.** O schema garante a *forma coerente da afirmação* (uma expiração carrega seu motivo), como já exige `evidence` não-vazia num `claim`. A *correção* (o ADR existe mesmo? é mesmo supersessor?) é verificada fora do schema — pelo leitor.

## Semântica de expiração (o passo 3 da tarefa) — ADVISORY, ancorada em fato, NÃO gate

- **Leitor (recall/brief)** trata um tópico como "comportamento superado" quando `valid_until` está no passado **E** a âncora resolve (o arquivo `memory/decisions/<slug>.md` ou o tópico sucessor existe). É o **fato verificável** (o supersessor existe) que dispara — não a data nua. Advisory: marca superado e aponta o sucessor; **não bloqueia nada**.
- **Não criei script/gate novo.** O piloto está em grace/forward-only (ADR 0345 §Implantação); promover qualquer coisa a required exige FP=0 no piloto + decisão [W] (ADR 0314). Um presence-gate de campo seria teatro (§5).
- **Se [W] promover a check no futuro**, o lar legítimo é o `fact-anchor` (Check T do `memory-health.mjs`) estendido ao corpus de tópicos — resolve a âncora e flagra **contradição** (ex: `status: ativo` + `valid_until` no passado com supersessor existente). É extensão de régua consolidada, nunca gate novo (ecoa a lápide §5 de 2026-07-17 sobre o fact-anchor).

## Entregas

- `scripts/memory-schemas/topico.schema.json` — +4 propriedades + `dependentSchemas` + `dependentRequired` (35 linhas, aditivo).
- `memory/requisitos/_Governanca/TOPICO-TEMPLATE.md` — bloco comentado no frontmatter + seção `## Validade (bi-temporal)` (a maioria dos tópicos vivos tem só `valid_from`; `valid_until` é a exceção do superado).
- `memory/requisitos/Produto/topicos/calculo-total-fatura.md` — piloto ganhou `valid_from: "2026-06-05"` (ancorado ao fix #2279 do incidente num_uf, coberto pelo golden `CalculoValorSellsTest` + `IncidentValorInfladoNumUfTest`) e seção explicando por que `valid_until` está **ausente**: `status: contestado` (parecer disputado) ≠ superado.

## Validações

- **Schema compila** sob `Ajv2020 + ajv-formats (strict:false)` — igual ao `memory-schema-gate.yml` (mesmo `require('ajv/dist/2020')` + `addFormats`). `format: date` é enforçado (o CI instala `ajv-formats@^3`).
- **8 fixtures adversariais** (via gray-matter, espelhando o CI) — todas verdes:

  | Caso | Esperado | Bateu |
  |---|---|---|
  | piloto real (`valid_from` só) | válido | ✅ |
  | `valid_from` sozinho | válido | ✅ |
  | `valid_until` **sem** supersessor | **inválido** | ✅ (dependentSchemas mordeu) |
  | `valid_until` + `superseded_by_adr` | válido | ✅ |
  | `valid_until` + `superseded_by_topic` | válido | ✅ |
  | `superseded_by_adr` **sem** `valid_until` | **inválido** | ✅ (dependentRequired mordeu) |
  | `valid_until` com data quebrada | **inválido** | ✅ (format+pattern) |
  | `superseded_by_adr` fora do pattern | **inválido** | ✅ |

- `git diff --check` — sem erro; 3 arquivos, +54 linhas, 100% aditivo (nenhum tópico legado tocado).
- Pest/PHPStan não rodaram localmente (regra CT 100 preservada; nada de PHP aqui — só schema JSON + AJV Node).

## Aprendizados / pegadinhas

- Data de expiração escrita à mão é a mesma família do `last_validated`/`verificado_em` reprovada no §5: apodrece calada. A trava correta não é "campo presente", é "campo **ancorado** num fato que uma máquina consegue resolver". `dependentSchemas` força o motivo a viajar junto com a afirmação sem virar presence-gate.
- `contestado ≠ superado`. O piloto prova a distinção: parecer disputado mantém o fato **vivo** (sem `valid_until`); só um supersessor real o mata.
- Extensão rides o gate consolidado: como `topico` já está na matriz do `memory-schema-gate` (grace, diff-aware), os campos novos são validados de graça — sem workflow novo, sem fork.

## Próximos passos

- [ ] Medir o piloto mais algumas PRs antes de qualquer promoção grace→required (ADR 0345/0314).
- [ ] **Se** [W] quiser a expiração como sinal ativo: estender o `fact-anchor` (Check T do `memory-health.mjs`) pra resolver `superseded_by_*` no corpus de tópicos e flagrar contradição — advisory primeiro (ADR 0275), nunca presence-gate.

## Referências

- ADR 0345: `memory/decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md`
- Schema: `scripts/memory-schemas/topico.schema.json` · Gate: `.github/workflows/memory-schema-gate.yml`
- §5 (data auto-declarada que apodrece): `memory/proibicoes.md` (lápides 2026-07-01/2026-07-09 `last_validated`/`verificado_em`; 2026-07-17 fact-anchor)
