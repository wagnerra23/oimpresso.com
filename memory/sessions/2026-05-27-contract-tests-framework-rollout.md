---
title: "Contract tests autosave — framework canon + 8 telas cobertas em paralelo (ADR 0205)"
type: session
date: 2026-05-27
author: Claude (Opus 4.7) sob direção Wagner — 6 sub-agents paralelos
status: complete
audience: Wagner + Felipe + Maiara (próximas iterações contract tests)
related_adrs:
  - 0205
  - 0179
  - 0093
  - 0094
  - 0061
source_files:
  - "tests/Contract/AutosaveContractRunner.php"
  - "tests/Contract/Fixtures/*.php (7 fixtures)"
  - "tests/Feature/Contract/*.php (7 tests)"
  - "tests/Contract/README.md"
  - "memory/decisions/0205-contract-tests-autosave-padrao-canonico.md"
---

# Contract Tests Framework Rollout — 2026-05-27

> **Continuação da sessão 2026-05-27 drawer Cliente.** Wagner reagiu pós-bateria exaustiva ("podia ter criado regra pra TODAS as telas") → canonizei framework + 1 sub-agent serial (drawer Cliente) + 6 sub-agents paralelos (5 telas).

## Entregas — 9 PRs merged em ~3h

| # | PR | Tela | Campos | Aliases | Pattern endpoint |
|---|---|---|---|---|---|
| 1 | #1791 | **Drawer Cliente** | 32 (5 abas) | 6 (nome/doc/tel/site/canal/contato) | PATCH per-field |
| 2 | #1795 | **ServiceOrder/Edit** | 7 cadastrais | ❌ canon EN nativo | PUT form + DB roundtrip |
| 3 | #1797 | **Sells/Create** companheiros | 7 (2 endpoints) | `first_name→name` + nested shape | POST + payload nested |
| 4 | #1799 | **ServiceOrderItem CRUD** | 7 | ❌ | POST 201 |
| 5 | #1800 | **Vehicles/Edit** | 14 | ❌ | PUT |
| 6 | #1801 | **Produto/Edit** | 11 | ❌ | PUT + DB roundtrip |
| 7 | #1802 | **Compras/Create** supplier | 5 | ❌ explícito (mesmo endpoint Sells PF) | POST |
| 8 | #1803 | **NFe/Config** | 9 (3 endpoints) | **`session['business.id']` vs `user.business_id`** | POST 302 + DB roundtrip |

**Total: ~92 campos auto-testados em CI a cada PR.**

ADR 0205 (canonizado) + Runner Pest reusável + README com receita 2-arquivos pra adicionar nova tela.

## Patterns arquiteturais descobertos

### P1 — 3 tipos de endpoint
1. **PATCH per-field autosave** (Cliente drawer) — request `{key: value}`, response `{contact: {...}}` shape padrão
2. **PUT form submit + DB roundtrip** (ServiceOrder/Vehicles/Produto) — request full payload, response 302 redirect, validar via `Vehicle::find($id)` direto no DB
3. **POST 201/302** (Sells/Compras/Items/NFe) — pode retornar JSON (201) ou redirect (302)

Runner extensível pra todos via opções: `method`/`responseRoot`/`payloadShape`/`baseFields`/`expectStatus`.

### P2 — Aliases PT-BR↔EN só no Cliente
Outras telas (ServiceOrder/Vehicles/Produto/Sells/Compras/NFe) nasceram canon EN nativo. Cliente foi o único módulo com fase legacy PT-BR (drawer Cowork). Heurística pra próximos PRs: **assumir canon EN; explicitar alias só quando confirmado**.

### P3 — Session multi-tenant não é uniforme
NFe usa `session['business.id']` (não `user.business_id` como resto). **Bug silencioso esperando acontecer** se dev assumir uniformidade. Solução: contract test seta ambas session keys (`setupContext` ampliado).

### P4 — FSM Pipeline transitions FORA do contract test
ServiceOrder/Vehicles têm `current_status`/`current_stage_id` FSM. Contract test cobre só campos cadastrais. FSM tem test próprio via `ExecuteStageActionService` (ADR 0143).

### P5 — PII LGPD: valores sintéticos no fixture
Pattern obrigatório: `'TEST-{stamp}'`, CNPJ fake mod-11 (`'11.222.333/0001-44'`), placa `'TEST-{stamp}'`. Cert .pfx + senha + CSC NFC-e ficam em Vaultwarden (NÃO no fixture).

## Bugs descobertos pelos sub-agents (catalogados pra próxima iteração)

| Bug | Sub-agent | PR | Status |
|---|---|---|---|
| Drawer Cliente Tags display (backend OK, chips não destacam) | Wagner manual | — | ❌ pendente — suspeito cache stale rows Inertia |
| Bug #4 cache stale generalizado (`useEffect([contact.id])` em 4 tabs) | catalogado | — | ❌ pendente |
| `responseShape: raw_body` faltando no runner (`check_ref_number` Compras) | Compras | bloqueado | ❌ pendente extensão runner |
| Multi-placeholder `{order}+{item}` faltando (ServiceOrderItem PUT/DELETE) | ServiceOrderItem | bloqueado | ❌ pendente extensão runner |

## Pendências priorizadas pra próxima sessão

### A — Extender runner Contract Tests (~80 linhas, 1 PR rápido)
- `responseShape: raw_body` (Compras `check_ref_number`)
- Multi-placeholder `{order}+{item}` (ServiceOrderItem PUT/DELETE)
- Multipart support (NFe cert .pfx upload)
- Reativa 3 endpoints DESATIVADOS nos fixtures existentes

### B — Bugs UI restantes drawer Cliente
- Tags display chips (backend OK, frontend não destaca)
- Bug #4 cache stale generalizado:
  - Adicionar `onContactUpdated` callback em IdentificacaoTab/ContatoTab/ComercialTab/ClassificacaoTab (pattern do EnderecoTab fix #1786)
  - OU pattern global `key={contact.id + lastUpdated}` no parent
- Inspecionar via JS browser pós-PATCH se tag chip lê de prop ou state

### C — Próximas telas pendentes (paralelizar com sub-agents)
- **DviInspection CRUD** (OficinaAuto Wave 3 — wedge competitivo vs RepairShopr · sugerido por sub-agent ServiceOrder)
- **Stock adjustment** Produto (endpoint separado `/stock-adjustments`)
- **Variations pricing** Produto (sub_sku/single_dpp/single_dsp roundtrip)
- **Bulk edit** Produto (POST `/products/bulk-update`)
- **Image upload** Produto (multipart — depende runner extension A)
- **Cert upload .pfx** NFe (multipart — depende runner extension A)
- **Sells/Edit shipping modal** (PATCH `/sells/update-shipping/{id}`)
- **CRUD NCM rules** NFe (`/nfe-brasil/tributacao/regras`)

### D — Tier 2 Browser smoke (Q3 2026)
- Pest Browser/Dusk pra capturar bugs de cache stale frontend
- Plano: implementar após Tier 1 cobrir 10+ telas

## Lições de processo (vale replicar)

### L1 — Sub-agents paralelos via `isolation: worktree` funcionam
6 agents paralelos hoje, todos abriram PR + admin merge autônomo. Pegadinhas catalogadas pelos próprios agents:
- **Branch race condition** quando 2+ agents tentam `git checkout` no main repo shared. Solução: trabalhar 100% dentro do worktree próprio (`pwd` confirmar `agent-<id>`), commit + push do worktree, merge via `gh api` (evita `gh pr merge` que tenta local checkout).
- **README.md conflict** quando 5 agents adicionam linha simultaneamente na tabela de coverage. Resolução: último agent que mergeou herdou conflito, rebase + resolve manual.

### L2 — Naming `-v2`/`-v3` em branches paralelas
Sub-agent Compras teve que usar `feat/contract-fixture-compras-create-v3` porque outros agents stomparam `compras-create` e `compras-create-v2`. Padrão a evitar: numerar branches pra evitar colisão se sub-agents paralelos forem retriggered.

### L3 — Sub-agents documentam achados úteis no PR body
Cada PR teve sub-seção "Próximos endpoints sugeridos" — backlog auto-gerado. Wagner pode revisar listas e priorizar.

### L4 — `gh api` mais resiliente que `gh pr merge` em multi-agent
`gh pr merge` tenta checkout local da branch — falha em multi-agent paralelo. `gh api repos/{owner}/{repo}/pulls/{num}/merge` faz merge server-side, sem race condition.

## Custo cognitivo da sessão

- ~3h totais (parent + 6 sub-agents paralelos)
- 9 PRs merged (sem rejeição)
- 1 conflito de merge resolvido (NFe README)
- ~92 campos auto-testados em CI a cada PR
- Padrão ADR 0205 ativo

## Prompt sugerido pra próxima sessão

```
Continuar contract tests rollout (sessão 2026-05-27).

Próximo trabalho — escolher A/B/C:

A — Extender runner Contract Tests (~80 linhas, 1 PR)
   - responseShape: raw_body (Compras check_ref_number)
   - Multi-placeholder {order}+{item} (ServiceOrderItem PUT/DELETE)
   - Multipart support (NFe cert .pfx)
   - Reativa 3 endpoints DESATIVADOS

B — Bugs UI drawer Cliente restantes
   - Tags display chips
   - Bug #4 cache stale generalizado (4 tabs sem onContactUpdated)

C — Próximas telas via sub-agents paralelos
   - DviInspection CRUD (wedge OficinaAuto)
   - Stock adjustment Produto
   - Sells/Edit shipping modal
   - CRUD NCM rules NFe

Ler: memory/sessions/2026-05-27-contract-tests-framework-rollout.md
     memory/decisions/0205-contract-tests-autosave-padrao-canonico.md
     tests/Contract/README.md
```

---

**Última atualização:** 2026-05-27 — sessão fechada. Padrão ADR 0205 ativo em escala produtiva (7 telas cobertas). Próxima sessão começa pelo prompt sugerido acima.
