---
thread: "04 · Casos: Csat + Metricas + Whatsapp/Settings + Whatsapp/Templates"
dono: "[CL]"
estado: entregue — PR #8330, teste verde no CI
base_lida: wagnerra23/oimpresso.com@main 7201ce433 (2026-09-30)
prefixo_tocado: 4 × .casos.md · tests/Feature/Whatsapp/ · + 1 linha em .github/ci-sqlite-pest.list (fora do prefixo, declarado abaixo)
---
# _saida-04 · Casos das 4 telas de administração do Atendimento

## Entregue
| Page | `.casos.md` | UCs |
|---|---|---|
| `Atendimento/Csat/Index` | `Modules/Whatsapp/Resources/js/Pages/Atendimento/Csat/Index.casos.md` | UC-ACSAT-01..03 |
| `Atendimento/Metricas/Index` | `Modules/Whatsapp/Resources/js/Pages/Atendimento/Metricas/Index.casos.md` | UC-AMET-01..03 |
| `Whatsapp/Settings` | `resources/js/Pages/Whatsapp/Settings.casos.md` | UC-WSET-01..04 |
| `Whatsapp/Templates/Index` | `resources/js/Pages/Whatsapp/Templates/Index.casos.md` | UC-WTPL-01..03 |

13 UCs, derivados do charter de cada tela (Goals · Non-Goals · Anti-hooks) e do DoD das US (WA-CSAT, WA-041, WA-001/067, WA-013). Nenhum `.tsx` aberto nem tocado.

**Teste:** `tests/Feature/Whatsapp/AtendimentoAdminContratoTest.php` — 1 `it()` por UC, o id no título (alcançável pelo G-7). DB-less: as consultas rodam em `DB::pretend()`, que registra SQL e bindings sem executar; assim o filtro de tenant (ADR 0093) é provado sem schema. UC-WSET-01 monta uma tabela mínima em sqlite :memory: com as conexões do business 98 e 99 para provar com dado que o 98 não vê o 99 e que o `meta_access_token` não chega às props. Tenant fictício 98 (ADR 0358).

**Fora do prefixo, de propósito:** 1 linha em `.github/ci-sqlite-pest.list`. Sem ela o teste não roda em lane nenhuma de PR (a lista é o run-set do job `PHP / Pest (Unit)`), e teste que não roda não prova nada. O arquivo é `merge=union`, então não conflita com as threads 02/03.

## Provas do json
| prova | estado |
|---|---|
| `Csat/Index.casos.md` existe | ✅ |
| `Metricas/Index.casos.md` existe | ✅ |
| `Whatsapp/Settings.casos.md` existe | ✅ |
| `Whatsapp/Templates/Index.casos.md` existe | ✅ |

`casos-coverage-guard` local: sem violação nova; o débito caiu (−19 vs baseline, inclui −15 que já vinham do main). O baseline **não** foi regravado aqui: as linhas destas 4 telas são vizinhas das telas das threads 02/03 no JSON, e encolher em paralelo daria conflito. Encolher é sempre permitido depois.

**CI do PR:** job `PHP / Pest (Unit)`, run 36805852386 — os 13 `it()` aparecem pelo nome com ✓, nenhum pulado (suíte: 1305 passed, 79 skipped de outros arquivos, 4954 assertions). Os UCs subiram de `⬜` para `🧪`.

Dois consertos no caminho, ambos medidos: (1) `uses(Tests\TestCase::class)` duplicava o `tests/Pest.php` e o Pest recusou o arquivo; (2) o `sdd-scorecard` ratchet contou o teste como corruptor do MySQL compartilhado (`sqlite_corruptors` 0→1) porque a DDL do UC-WSET-01 rodava na conexão default — agora roda numa conexão `:memory:` privada, e `sqlite-test-corruptors.mjs --json` voltou a `corruptors: 0`.

## Pendências (não inventadas — ficam para [W])
1. **Templates — charter desatualizado.** O Non-Goal "NÃO faz sync Meta nesta versão" contradiz o backend (`POST /whatsapp/templates/sync-meta` existe) e o DoD da US-WA-013. Non-Goal é campo [W]; não editei. Registrado no backlog do `casos.md`.
2. **Métricas — período "custom".** O charter lista 7/30/90/custom; o backend só aceita 7/30/90 (UC-AMET-03 trava isso). Ou o charter perde o "custom", ou vira US.
3. **Templates — isolamento só pelo global scope.** `TemplatesController::buildTemplatesPayload` não tem `where business_id` explícito; depende de `auth` + `HasBusinessScope`. Funciona porque a rota exige `auth` (UC-WTPL-01 trava os dois), mas é a única das 4 telas sem defesa em profundidade. Não mexi: está fora do prefixo.
4. **CSAT — link do cliente** aponta para `/atendimento/inbox?thread=`, cujo render está órfão (thread 01, decisão D1).
