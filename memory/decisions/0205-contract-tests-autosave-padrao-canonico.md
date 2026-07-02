---
slug: 0205-contract-tests-autosave-padrao-canonico
number: 205
title: "Contract tests autosave como padrão canônico pra toda tela com endpoint PATCH"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-27"
module: Infra
quarter: 2026-Q2
tags: [testes, ci, autosave, drawer, contract-test, anti-regressao, ADR-0179]
supersedes: []
related:
  - 0179-cliente-drawer-760px-substitui-show-fullpage
  - 0093-multi-tenant-isolation-tier-0
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
pii: false
review_triggers:
  - "Algum bug de aliases PT-BR/canon EN passar pra prod apesar do contract test (ajustar runner)"
  - "Tier 2 browser smoke implementado (cobre cache stale frontend) — atualizar este ADR"
  - "Padrão extendido pra >5 telas — promover regra de criar fixture obrigatório em PR de tela nova"
---

# ADR 0205 — Contract tests autosave padrão canônico

## Contexto

Em **2026-05-27**, sessão Wagner com auditoria exaustiva do drawer 760 Cliente (ADR 0179) descobriu **5 bugs silenciosos** em produção que **passaram por testes unitários convencionais** mas geraram reclamações reais de cliente piloto (Daniela @ Martinho — cadastro Heinig Pre-Moldados):

| Bug | Sintoma | Causa raiz | Test convencional cobriu? |
|---|---|---|---|
| Aliases PT-BR (`nome→name`, `doc→tax_number`, `tel→mobile`, `site→site_url`, `canal→canal_preferido`) | Razão social/CNPJ/Tel principal NÃO SALVAVAM, badge "Salvo" verde aparecia | Validator backend filtra `validated()` chaves desconhecidas → `Eloquent::update([])` silent no-op | ❌ Test fazia `assertJsonPath('contact.fantasia', X)` (que funcionava). Não testava `nome` (que era o quebrado). |
| Campo `contato` órfão sem coluna destino | "Contato principal" → badge "Salvo" mas dado some | Validator não tinha `contato`, sem coluna no schema | ❌ Idem |
| `contact_status` vs `status` mismatch | Dropdown Status sempre vazio mesmo após PATCH OK | Frontend lê `contact.status` (alias PT-BR), backend envia `contact_status` (canon EN UPOS) | ❌ Test verificava PATCH 200 mas não validava chave consumida pelo frontend |
| Coluna IE duplicada (`ie` vs `inscricao_estadual`) | IE preenchida via autosave, sumia ao reabrir | Backend autosave gravava em `ie` (Wave drawer), payload rows lia de `inscricao_estadual` (Wave canon BR) | ❌ Testes separados pra cada coluna, sem teste cruzado endpoint→payload |
| Cache stale rows Inertia pós lookup CEP | Endereço preenchido pelo ViaCEP sumia ao fechar+reabrir drawer | `EnderecoTab` sem `onContactUpdated` callback (que `IdentificacaoTab` tem) | ❌ Test PATCH validava resposta mas não simulava reabertura |

**Comum a todos:** chave que frontend envia ≠ chave que validator aceita OU chave que backend retorna ≠ chave que frontend lê. **Test convencional (`assertJsonPath`) não pega** — só pega se backend de fato retornar a chave esperada **com o valor enviado pelo frontend usando o alias canon que a UI consome**.

Bateria manual via JavaScript no browser (32 PATCHes contra Cervejaria Lupulada biz=1) descobriu os 5 bugs em ~30 minutos. Wagner reagiu: **"podia ter criado uma regra dessas pra TODAS as telas — porque não criou? cria uma estrutura de teste, isso economiza muito tempo de desenvolvimento"**.

## Decisão

**Adotar contract tests autosave como padrão canônico do projeto.** Toda tela com endpoint(s) PATCH autosave **DEVE** ter fixture contract test associado. Runner Pest genérico itera fixture, faz PATCH HTTP TestCase, valida `status 200 + response.contact.{key} === sent` pra cada campo. CI Pest roda em todo PR — falha bloqueia merge.

### Estrutura canônica

```
tests/Contract/
├── README.md                           ← receita de adicionar nova tela
├── AutosaveContractRunner.php          ← runner reusável (1 arquivo)
└── Fixtures/
    ├── cliente_drawer.php              ← drawer Cliente (5 abas, 32 campos)
    ├── sells_create.php                ← futuro
    ├── service_order_edit.php          ← futuro
    └── ...                             ← 1 por tela com autosave

tests/Feature/Contract/
├── ClienteDrawerAutosaveContractTest.php  ← invoca runner com fixture
├── SellsCreateAutosaveContractTest.php    ← futuro
└── ...
```

### Match modes suportados (extensível)

| Modo | Quando usar |
|---|---|
| `equals` (default) | Valor exato — strings, enums |
| `partial` | `str_contains` — mask CPF/CNPJ/telefones formatados |
| `bool` | Cast bool — toggle/checkbox |
| `int` | Cast int — quando backend retorna string mas frontend espera number |
| `array_eq` | JSON serialize ambos — tags, multi-select |

### Multi-tenant (Tier 0 ADR 0093 IRREVOGÁVEL)

`AutosaveContractRunner::setupContext($testCase)` cuida:
- `Business::first()` + `User` do biz alvo
- `actingAs($user)` + `session(['user.business_id' => $business->id])`
- Cria contact base scopo correto

Skips graceful em ambiente sem schema (sqlite memory) — não quebra CI runners.

### Quando criar fixture (obrigatório vs opcional)

| Cenário | Obrigatório? |
|---|---|
| Tela nova com 2+ endpoints PATCH autosave | ✅ obrigatório (PR rejeitado sem fixture) |
| Tela existente sem fixture mas modificada (Controller PATCH novo) | ✅ obrigatório (PR adiciona fixture cobrindo endpoints novos) |
| Modificação em validator existente (campo novo, alias novo) | ✅ atualizar fixture do tela impactada |
| Tela CRUD tradicional (form full-page Save+Redirect, não autosave) | ⚪ opcional (não causa bug silencioso pq usuário vê erro de validação) |
| Tela read-only (lista/show sem PATCH) | ❌ não aplicável |

## Princípios derivados

### P1 — Contract test = fonte da verdade do contrato frontend↔backend
Fixture documenta o que frontend ENVIA + o que backend RETORNA + match mode. Dev mexendo controller/validator deve atualizar fixture no mesmo PR. CI bloqueia se desincronizado.

### P2 — Aliases PT-BR↔EN são pegadinha conhecida — fixture nomeia ambos
Tabela `send` vs `recv` separadas no fixture FORÇA dev a explicitar quando frontend envia chave diferente do que backend retorna. Bug Daniela (`nome→name`) seria evitado se fixture existisse desde criação do drawer.

### P3 — Match `partial` evita brittleness com mascaramento PII
PATCH envia CPF/CNPJ plain (`'11.222.333/0001-44'`), backend retorna mascarado (`'11.222.***/0001-**'`). Match `partial` (substring incluso) cobre sem precisar simular máscara exata no test.

### P4 — Runner genérico ≠ test específico
Runner roda fixture cega. Tests específicos (`tests/Feature/Cliente/Cliente*Test.php`) seguem existindo pra cobrir comportamento de negócio (FSM, permissions matriciais, side effects, race conditions). Contract test cobre só "chave bate?".

### P5 — Tier 1 PHP primeiro, Tier 2 browser depois
- **Tier 1** (este ADR): contract test PHP via HTTP TestCase — roda em ~10s, cobre 90% dos bugs (mismatch chave/valor)
- **Tier 2** (futuro): browser smoke via Pest Browser/Dusk — abre drawer, preenche, fecha+reabre, screenshot + assert text visible. Cobre bugs de **cache stale frontend** (tipo bug CEP #1786). Implementar quando Tier 1 cobre 5+ telas.

## Consequências

### Positivas
- **Bugs silenciosos pegos em CI em vez de prod** (estimate: 3-5 bugs/mês prevenidos baseado na sessão 2026-05-27)
- **Tempo dev economizado:** ~30min manual + smoke browser → ~10s CI automático por PR
- **Documentação viva:** fixture é registro do contrato real entre camadas
- **Refactor seguro:** mudar validator backend → CI quebra imediato se frontend ainda envia alias antigo
- **Onboarding rápido:** novo dev lê fixture pra entender campos de uma tela

### Negativas
- **Custo manutenção fixture:** dev tem que atualizar quando adicionar campo. Mitigado: 1 linha por campo, copy-paste de campo similar.
- **Multi-tenant setup overhead:** cada test cria contact base. Mitigado: `DatabaseTransactions` reverte após cada test.
- **Não cobre bugs de UX/visual:** botão funcionando mas com label errado, layout quebrado, etc. Tier 2 cobre isso.
- **Race conditions paralelas não cobertas:** runner é sequencial. Bug bateria 32 PATCHes paralelos (1/32 retornou 500) NÃO seria pego — exige test específico de concurrency.

### Neutras
- **CI tempo:** ~10s adicional por fixture × 7 telas potenciais = ~70s. Aceitável.
- **Pode coexistir** com test específico `tests/Feature/Cliente/ClienteAutosaveAliasesPtBrTest.php` que já cobre mesma área via assertions PHP nativas (não-runner). Eles complementam: runner = breadth, específico = depth.

## Roadmap de adoção (próximas 4 semanas)

1. **Sprint atual** (2026-05-27): drawer Cliente fixture ✅ (este PR — 32 campos)
2. **+1 semana** (Sells/Create + ServiceOrder/Edit — top-2 telas com autosave): 2 fixtures
3. **+2 semanas:** Compras/Create + Vehicles/Edit + Produto/Edit: 3 fixtures
4. **+4 semanas:** todas as ~10 telas com autosave do oimpresso cobertas
5. **Q3 2026:** Tier 2 browser smoke pra 3-5 telas top-priority (drawer Cliente, Sells/Create, ServiceOrder)

## Riscos Tier 0 mitigados

- **❌ Multi-tenant ADR 0093:** `AutosaveContractRunner::setupContext` força `business_id` session — contract test NUNCA vaza cross-tenant
- **❌ PII LGPD:** valores `value` no fixture devem ser **sintéticos** (`'CT-{stamp}'`, `'11.222.333/0001-44'` que é CNPJ fake). PR review rejeita fixture com CPF/CNPJ real
- **❌ FSM Pipeline:** contract test NÃO mexe em transação FSM `current_stage_id` — só PATCH cadastrais. Negócio FSM continua coberto por tests específicos (ADR 0143)

## Refs

- [ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) — Drawer Cliente 760
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (preservado)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio "Loop fechado por métrica" aplicado aqui)
- [Session 2026-05-27](../sessions/2026-05-27-drawer-cliente-reorg-end-to-end.md) — origem do problema (bugs Daniela)
- `tests/Contract/README.md` — receita prática de adicionar nova tela
