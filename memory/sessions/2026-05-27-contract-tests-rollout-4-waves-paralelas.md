---
title: "Contract tests rollout — 4 waves paralelas (DviInspection · Stock adjustment · Sells/Edit shipping · NCM rules)"
type: session
date: 2026-05-27
author: Claude (Opus 4.7) sob direção Wagner
status: complete
audience: Wagner + Felipe + Maiara + Eliana (próximas iterações contract tests)
related_adrs:
  - 0205
  - 0093
  - 0143
  - 0094
  - 0104
  - 0106
source_files:
  - "tests/Contract/AutosaveContractRunner.php"
  - "tests/Contract/Fixtures/dvi_inspection.php"
  - "tests/Contract/Fixtures/sells_edit_shipping.php"
  - "tests/Contract/Fixtures/ncm_rules.php"
  - "tests/Feature/Contract/DviInspectionAutosaveContractTest.php"
  - "tests/Feature/Contract/SellsEditShippingAutosaveContractTest.php"
  - "tests/Feature/Contract/NcmRulesAutosaveContractTest.php"
  - "memory/sessions/2026-05-27-contract-tests-stock-adjustment-decisao.md"
---

# Contract tests rollout — 4 waves paralelas

> **Duração:** ~15min spawn + ~7min agents paralelos · **PRs:** 4 (todos merged) · **Cobertura nova:** 3 fixtures + 1 decisão doc-only

## Resumo executivo

Sessão seguindo ADR 0205 (contract tests autosave canon, aceito 2026-05-27 pós-bugs Daniela). Estado pré-sessão: **8 fixtures já mergeadas** (cliente_drawer, service_order_edit, sells_create, service_order_items, produto_edit, vehicles_edit, compras_create, nfe_config). Wagner aprovou Caminho C — 4 sub-agents general-purpose paralelos em worktree isolado pra cobrir mais 4 telas.

Resultado: **11 fixtures cobertas + 1 doc-only decision justificada**. Pulou direto +2 semanas do roadmap original do ADR 0205 numa única sessão.

## PRs gerados (paralelos)

| # | Tela | Estratégia | Linhas | Status |
|---|---|---|---|---|
| [1810](https://github.com/wagnerra23/oimpresso.com/pull/1810) | Stock adjustment | **Doc-only** — matriz ADR 0205 § "Tela CRUD tradicional opcional" | doc 117 | ✅ merged |
| [1812](https://github.com/wagnerra23/oimpresso.com/pull/1812) | DviInspection (OficinaAuto) | Fixture 7 campos PUT item DVI | ~200 | ✅ merged |
| [1813](https://github.com/wagnerra23/oimpresso.com/pull/1813) | Sells/Edit shipping | Fixture 10 campos PUT shipping (DB roundtrip) | ~250 | ✅ merged |
| [1815](https://github.com/wagnerra23/oimpresso.com/pull/1815) | NCM rules NFe | Fixture POST+PUT 3 tabs (mutex CSOSN/CST) | ~300 | ✅ merged |

## Padrões emergentes catalogados (vale replicar)

### P1 — Multi-placeholder endpoint (`{order}/{item}`)
**Caso:** `PUT /oficina-auto/ordens-servico/{order}/dvi/{item}` — runner default só substitui `{id}`.

**Solução adotada:** test file pre-resolve `{order}` via `str_replace` no fixture clonado ANTES de invocar `AutosaveContractRunner::run`, deixando `{id}` (=`itemId`) pro runner cuidar. Não exige extender runner.

**Documentado no docblock do fixture + TODO pra migrar quando runner ganhar suporte multi-placeholder.**

### P2 — Response minimal (`{success: 1}` sem objeto persistido)
**Caso:** `PUT /sells/update-shipping/{id}` retorna `['success' => 1, 'msg' => '...']` — NÃO retorna o Transaction atualizado. Runner default falha pq não acha `recv` no JSON.

**Solução adotada:** loop custom inline (espelha `ServiceOrderEditAutosaveContractTest`) com **DB roundtrip** — `DB::table('transactions')->where('id', $id)->first()` lê de volta após PUT.

**Trade-off catalogado:** DB roundtrip valida persistência mas NÃO valida shape de leitura JSON (frontend pode estar lendo chave diferente). Suficiente pra pegar `$request->only` com typo, cast model truncando, mass-assignment guarded, migration ausente. Tier 2 browser smoke cobre shape de leitura no futuro.

### P3 — Inertia redirect+flash (mutações não retornam JSON)
**Caso:** `POST /nfe-brasil/tributacao/regras` e `PUT /nfe-brasil/tributacao/regras/{id}` retornam `RedirectResponse` (302) + flash session (pattern ADR 0029).

**Solução adotada:** loop custom inline + read-back via **Eloquent** (`NfeFiscalRule::where(...)->first()`), aplicando casts float pras alíquotas DECIMAL(7,4). Status 302 considerado sucesso.

**Pattern reusável pra todas telas Inertia que mutam via redirect.**

### P4 — Mutex de campos (CSOSN OU CST, não ambos)
**Caso:** `UpsertRegraTributariaRequest::withValidator` falha se CSOSN + CST preenchidos juntos.

**Solução adotada:** **tabs dedicadas no fixture** — tab `editar_regra_existente` mantém `csosn` no baseFields (Simples), tab isolada `editar_regra_cst` envia `csosn=null` + `cst='000'` (Normal). Evita pollution cross-tab e mantém cada PUT como payload válido.

**Pattern reusável pra qualquer enum mutex (ICMS vs ICMS-ST, NF-e vs NFC-e, etc).**

### P5 — Cobertura POST + PUT (não PUT-only)
**Caso:** Validators podem ter comportamento distinto entre `store` (POST) e `update` (PUT) — `validated()` filter aplicado em ambos mas pode dropar chaves diferentes se rules têm `sometimes`.

**Decisão NCM rules:** cobrir **AMBOS** — POST cria nova regra (testa drop silencioso de validated() no store) + PUT atualiza regra existente (pattern service_order_edit + baseFields). Mesmo custo de manutenção, dobro de bug catching.

**Recomendação:** novos fixtures considerar cobrir POST+PUT se ambos existem (controllers com `Route::resource` ou store/update separados).

### P6 — Decisão doc-only como entregável válido (não forçar fixture)
**Caso:** Stock adjustment tem `Route::resource` + form Blade legacy + Inertia useForm single-submit (Create.tsx) + controller `edit/update` vazios. Não é autosave per-field.

**Decisão:** ADR 0205 § matriz explicitamente lista "Tela CRUD tradicional (form full-page Save+Redirect, não autosave) — ⚪ opcional". Forçar fixture seria over-engineering.

**Entregável:** `memory/sessions/2026-05-27-contract-tests-stock-adjustment-decisao.md` (doc 117 linhas) registrando investigação + decisão + recomendação ("se Stock adjustment for migrado pro padrão drawer/autosave no futuro, criar fixture nessa hora").

**Pattern reusável pra próximas telas:** investigar primeiro, se cair na matriz opcional → doc-only é entregável legítimo (não fingir cobertura).

## Learnings de processo

### L1 — Paralelismo via sub-agents general-purpose em worktree isolado é o caminho
**Caso desta sessão:** 4 sub-agents `Agent(subagent_type: "general-purpose", isolation: "worktree", run_in_background: true)` rodaram simultâneo em ~7min médio cada. Zero conflito de arquivos (cada um toca pasta diferente de `tests/Contract/Fixtures/` + `tests/Feature/Contract/`).

**Coordenação:** prompts auto-suficientes com restrições explícitas ("NÃO mexer no AutosaveContractRunner.php" / "NÃO mexer em SellController.php"). Cada agent decide caminho conforme investigação local.

**Throughput vs sequencial:** estimativa 4 fixtures sequencial Claude direto = ~30min × 4 = ~2h. Paralelo = ~15min + 7min agents = ~22min total. **~5× speedup.**

### L2 — Prompts self-contained com pegadinhas pré-mapeadas economizam re-trabalho
Cada prompt incluía:
- Lista numerada de arquivos canônicos pra ler primeiro (README, runner, ADR 0205, fixture similar como referência)
- Especificação de endpoint + validator + response shape PRE-mapeado por mim antes
- Pré-flight obrigatório (skip graceful sqlite, schema missing, etc) explícito
- Restrições rígidas (NÃO mexer em X/Y/Z) + branch name + commit format
- Critério de pronto + formato do PR
- **Decisão crítica explícita** (ex Stock adjustment: matriz ADR 0205 → caminho A doc-only se confirmar full-form CRUD)

Agentes voltaram com resultados sólidos sem precisar nova rodada.

### L3 — Admin merge bypassa CI failure legítima (mas catalogada)
[PR #1810](https://github.com/wagnerra23/oimpresso.com/pull/1810) falhou no check "Session log (memory/sessions/*.md)" — sub-agent escreveu 117 linhas vs ≤80 recomendado. Wagner aprovou `gh pr merge 1810 --squash --admin --delete-branch` mesmo assim.

**Não é hack** — é decisão consciente: doc-only PR, zero risco produção, schema violator mínimo (linha-count). CI baseline atualizada via PR follow-up se desejado.

Pattern consistente com L2 do session log [2026-05-27 drawer Cliente reorg](2026-05-27-drawer-cliente-reorg-end-to-end.md).

### L4 — GitHub GraphQL rate limit (5000/hr) atrapalha admin merge rápido
4 PRs sequenciais via `gh pr merge ... --admin` consumiu ~5005 GraphQL calls (rate limit já estava em ~73 used antes). 4º merge esbarrou em "API rate limit already exceeded".

**Mitigação aplicada:** `ScheduleWakeup` 130s pra reset (~110s real até reset). Recomendação futura: spread merges com 1-2s sleep entre cada ou pre-check `gh api rate_limit --jq .resources.graphql` antes.

**Cuidado:** core API limit (5000/hr) é separado de GraphQL limit (5000/hr). Comandos `gh pr view --json` usam GraphQL; vão na mesma cota dos merges.

### L5 — Worktree post-merge cleanup falha em local branch deletion (não-fatal)
`gh pr merge --delete-branch` deleta remote OK mas falha em local porque worktree do sub-agent ainda usa a branch. Mensagem benigna, harness limpa worktrees depois.

**Não bloqueia merge** — fix em sessão futura via `git worktree prune` quando agentes encerrarem.

## Atualização de cobertura (pós-sessão)

| Tela | Cobertura | Endpoints |
|---|---|---|
| Cliente drawer | ✅ implementado | 5 abas PATCH (identificacao/contato/endereco/comercial/classificacao) |
| Sells/Create | ✅ parcial | POST /contacts quick-add + PATCH commission-split |
| OficinaAuto/ServiceOrder/Edit | ✅ implementado | PUT cadastrais (7 campos) |
| OficinaAuto/ServiceOrderItems | ✅ implementado | CRUD drawer PECAS & MAO DE OBRA |
| **OficinaAuto/DviInspection** | ✅ implementado (1812) | PUT item DVI (7 campos cadastrais) |
| Produto/Edit | ✅ implementado | PATCH 11 campos cadastrais |
| Vehicles/Edit | ✅ implementado | PATCH 14 campos OficinaAuto |
| Compras/Create | ✅ parcial | POST /contacts quick-add Fornecedor (5 campos) |
| NFe/Config | ✅ parcial | POST 3 endpoints config |
| **NFe/NCM rules** | ✅ implementado (1815) | POST + PUT 3 tabs |
| **Sells/Edit shipping** | ✅ implementado (1813) | PUT 10 campos (DB roundtrip) |
| Stock adjustment | 📋 doc-only (1810) | Justificado fora de escopo (full-form CRUD) |

**Total:** 11 fixtures contract + 1 doc-only decision. Comparativo roadmap ADR 0205:

> "Sprint atual (2026-05-27): drawer Cliente fixture ✅ (este PR — 32 campos)
> +1 semana (Sells/Create + ServiceOrder/Edit): 2 fixtures
> +2 semanas: Compras/Create + Vehicles/Edit + Produto/Edit: 3 fixtures
> +4 semanas: todas as ~10 telas com autosave do oimpresso cobertas"

**Pulamos do +2 semanas marker pra além do +4 semanas em uma única sessão.**

## Próximos passos sugeridos

### Tier 1 (Pest PHP) restante
- **Compras/Create** — completar com `raw_body` quando runner suportar (atualmente parcial, pendente check_ref_number)
- **NFe/Config** — completar com multipart pra upload .pfx (exige extensão runner)
- **Outras telas autosave detectadas** — vaga, requer auditoria `grep -rn "patchJson\|onAutosave\|useAutosave" resources/js/Pages` pra mapear universe

### Tier 2 (browser smoke Pest Browser/Dusk)
ADR 0205 menciona: "Implementar quando Tier 1 cobre 5+ telas." **Já passamos disso** — 11 cobertas. Candidatos top-priority:
- **drawer Cliente** (bug #4 cache stale catalogado em [session 2026-05-27](2026-05-27-drawer-cliente-reorg-end-to-end.md) — Daniela sente)
- **Sells/Create** (cliente piloto Larissa usa diário — bug visual seria notado)
- **ServiceOrder/Edit** (FSM transitions visuais)

### Extensão runner
Se acumular >3 casos de "preciso de feature X no runner" (multipart, raw_body, multi-placeholder), criar PR único de runner v2 com todos. Documentar em ADR específico (esta sessão NÃO criou — pode ser próxima onda).

### Promoção da regra
ADR 0205 review_trigger declara: "Padrão extendido pra >5 telas — promover regra de criar fixture obrigatório em PR de tela nova". Já em 11 fixtures.

**Decisão pendente Wagner:** criar ADR-0206 (ou amendment 0205) promovendo "PR que toque Controller/Page com endpoint(s) PATCH/PUT/POST autosave DEVE incluir fixture contract — CI gate hard"?

## Validação final

- 4 PRs todos merged (deploy normal cobre — não é frontend, sem Force Clean Rebuild necessário per L1 sessão drawer)
- 0 regressões CI Pest existente (módulo gates verde nos 4)
- 0 conflito merge (paralelismo limpo)
- Multi-tenant Tier 0 (ADR 0093) preservado em todos fixtures (setupContext + global scope)
- Dados sintéticos puros (zero PII em fixtures)

---

**Última atualização:** 2026-05-27 — sessão paralela 4 waves contract tests. 4 PRs · 11 fixtures totais · 1 doc-only decision · 6 padrões reusáveis catalogados.
