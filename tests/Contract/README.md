# Contract Tests — autosave persistence (ADR 0205)

> **Wagner 2026-05-27:** "criou esse fix do mismatch canon — porque não criou regra dessas pra TODAS telas?"

Esta pasta tem o **framework reusável** que valida contrato `frontend → backend → frontend` de endpoints autosave (`PATCH /resource/{id}/tab`). Roda em CI a cada PR — falha = bloqueia merge.

## Por que existe

Bugs silenciosos descobertos em 2026-05-27 (drawer Cliente):

| Bug | Sintoma usuário | Por que passou em testes convencionais |
|---|---|---|
| Aliases PT-BR (`nome→name`, `doc→tax_number`, `tel→mobile`, `site→site_url`, `canal→canal_preferido`) | Daniela @ Martinho cadastrou Heinig: Razão social/CNPJ/Tel principal não salvaram, badge "Salvo" verde aparecia | Validator backend filtra `validated()` chaves desconhecidas → `Eloquent::update([])` no-op. PATCH retorna 200. Test unit `assertJsonPath('contact.fantasia', X)` passa pq fantasia funciona — mas `nome` (que era o quebrado) não tinha test cobrindo. |
| `contato` órfão sem coluna destino | "Contato principal" → badge "Salvo" mas dado some | Idem acima — validator não tinha `contato`, sem teste cobrindo |
| `contact_status` vs `status` alias | Dropdown Status sempre vazio | Frontend lia `contact.status` (PT-BR), backend enviava `contact_status` (canon EN). Test unit cobria PATCH mas não verificava chave de resposta consumida pelo frontend |
| Coluna IE duplicada (`ie` vs `inscricao_estadual`) | IE preenchida via autosave, sumia ao reabrir drawer | Backend autosave gravava em `ie` (Wave drawer), payload rows lia de `inscricao_estadual` (Wave canon BR). Tests separados pra cada — sem teste cruzando |

**Comum a todos:** chave que frontend envia ≠ chave que validator aceita OU chave que backend retorna ≠ chave que frontend lê. Test convencional `assertJsonPath` não pega — só pega se backend de fato retornar a chave esperada **com o valor enviado**.

## Como funciona

1. **Fixture PHP** (`Fixtures/<tela>.php`) declara cada tab + endpoint + lista de campos:
   ```php
   return [
     'identificacao' => [
       'endpoint' => '/cliente/{id}/identificacao',
       'fields' => [
         ['send' => 'nome',  'value' => 'CT-{stamp}', 'recv' => 'name'],          // alias PT-BR → canon EN
         ['send' => 'doc',   'value' => '11.222.333/0001-44', 'recv' => 'tax_number_masked', 'match' => 'partial'],
         ['send' => 'vip',   'value' => true,      'recv' => 'vip', 'match' => 'bool'],
         // ...
       ],
     ],
   ];
   ```
2. **Test file Pest** (`tests/Feature/Contract/<Tela>AutosaveContractTest.php`) invoca runner:
   ```php
   $fixture = require __DIR__ . '/../../Contract/Fixtures/<tela>.php';
   $result = AutosaveContractRunner::run($this, $fixture, $this->resourceId);
   expect($result['passed'])->toBe($result['total']);
   ```
3. **CI** (`PHP / Pest (Unit)` workflow): roda em todo PR, falha bloqueia merge.

## Match modes

| Modo | Quando usar |
|---|---|
| `equals` (default) | Valor exato — strings simples, enums |
| `partial` | `str_contains` — útil pra mask CPF/CNPJ (envia `11.222.333/0001-44`, recebe `11.222.***/0001-**` ou similar) ou telefones formatados |
| `bool` | Cast bool comparison — toggle/checkbox |
| `int` | Cast int — quando frontend envia `45` e backend retorna `"45"` (string) |
| `array_eq` | JSON serialize ambos — tags, multi-select |

## Opções avançadas de tabSpec (extensão 2026-05-27 — Sells/Create)

| Chave | Default | Quando usar |
|---|---|---|
| `method` | `patch` | `post` (criação tipo `/contacts`), `put` (legacy shipping update) |
| `responseRoot` | `contact` | `commission_split`, `data` (ContactController@store), `''` (raiz) |
| `payloadShape` | `flat` | `nested:commission_split` quando endpoint exige `{ commission_split: {...} }` |
| `baseFields` | `[]` | Campos sempre enviados junto (required pelo validator mas não foco do teste) |
| `expectStatus` | `200` | `201` se algum endpoint Created |

Exemplo (PATCH nested):
```php
'commission_split' => [
    'endpoint' => '/sells/{id}/commission-split',
    'method' => 'patch',
    'responseRoot' => 'commission_split',
    'payloadShape' => 'nested:commission_split',
    'baseFields' => ['mecanico_id' => 1, 'mecanico_pct' => 100, 'balcao_id' => null, 'balcao_pct' => 0],
    'fields' => [['send' => 'mecanico_pct', 'value' => 100, 'recv' => 'mecanico_pct', 'match' => 'int']],
],
```

## Setup multi-tenant (Tier 0 ADR 0093)

`AutosaveContractRunner::setupContext($this)` cuida:
- Pega `Business::first()` + `User::where('business_id', $business->id)->first()`
- Cria contact base no biz alvo
- `actingAs($user)` + `session(['user.business_id' => $business->id])`

Skips gracefully se ambiente sem schema (`Schema::hasTable('contacts')` false) — não quebra CI em runners sqlite memory.

## Como adicionar nova tela

**Exemplo: Sells/Create**

1. **Fixture** `tests/Contract/Fixtures/sells_create.php`:
   ```php
   return [
     'cliente_quick_add' => [
       'endpoint' => '/pos/quick-add-customer',  // pode ser POST tb
       'fields' => [
         ['send' => 'name', 'value' => 'CT-{stamp}', 'recv' => 'name'],
         ['send' => 'mobile', 'value' => '11999990001', 'recv' => 'mobile', 'match' => 'partial'],
       ],
     ],
     // ...
   ];
   ```

2. **Test file** `tests/Feature/Contract/SellsCreateAutosaveContractTest.php`:
   ```php
   <?php
   use Illuminate\Foundation\Testing\DatabaseTransactions;
   use Tests\Contract\AutosaveContractRunner;

   uses(DatabaseTransactions::class);

   beforeEach(function () {
       $ctx = AutosaveContractRunner::setupContext($this);
       // ... setup específico Sells
   });

   it('Sells/Create — todos endpoints autosave persistem TODOS os campos', function () {
       $fixture = require __DIR__ . '/../../Contract/Fixtures/sells_create.php';
       $result = AutosaveContractRunner::run($this, $fixture, $this->resourceId);
       expect($result['passed'])->toBe($result['total']);
   });
   ```

3. **Done.** CI roda automaticamente.

## Telas-candidatas (próximas iterações)

| Tela | Prioridade | Endpoints |
|---|---|---|
| Cliente drawer | ✅ implementado | 5 abas PATCH (identificacao/contato/endereco/comercial/classificacao) |
| Sells/Create | ✅ parcial | quick-add cliente (POST /contacts), commission-split (PATCH /sells/{id}/commission-split). NÃO cobre POST /pos (full-form). |
| OficinaAuto/ServiceOrder | ✅ implementado | PUT /oficina-auto/ordens-servico/{id} (7 campos cadastrais) |
| OficinaAuto/ServiceOrderItems | ✅ implementado | CRUD drawer PECAS & MAO DE OBRA |
| OficinaAuto/DviInspection | ✅ implementado | PUT item DVI (7 campos cadastrais). Multi-placeholder `{order}/{item}` pre-resolved no test file (P1). |
| Sells/Edit shipping | ✅ implementado | PUT /sells/update-shipping/{id} (10 campos shipping_*). DB roundtrip pq response é `{success:1}` minimal (P2). |
| NFe/NCM rules (Tributação) | ✅ implementado | POST + PUT 3 tabs (mutex CSOSN/CST via tabs dedicadas — P4). Pattern Inertia redirect+flash, read-back via Eloquent (P3). |
| Compras/Create | ✅ parcial | quick-add Fornecedor (POST /contacts type=supplier, 5 campos). NÃO cobre POST /purchases (redirect-flow). check_ref_number reservado pra runner raw_body. |
| NFe/Config | ✅ parcial | ambiente SEFAZ (POST), auto-emission toggle (POST), config-default upsert (POST). NÃO cobre upload .pfx (multipart, fixture próprio) nem testar SEFAZ (action, não autosave). |
| Vehicles/Edit | ✅ implementado | PATCH 14 campos OficinaAuto |
| Produto/Edit | ✅ implementado | PATCH 11 campos cadastrais |
| Stock adjustment | 📋 doc-only | Justificado fora de escopo — full-form CRUD Blade legacy + Inertia useForm single-submit. Ver [session 2026-05-27 decisão](../../memory/sessions/2026-05-27-contract-tests-stock-adjustment-decisao.md). |

## Padrões reusáveis catalogados (sessão 2026-05-27 4 waves paralelas)

Ver [session log completo](../../memory/sessions/2026-05-27-contract-tests-rollout-4-waves-paralelas.md). Resumo:

- **P1 — Multi-placeholder endpoint** (`{order}/{item}`): pre-resolve `{order}` no test file antes do runner. Não exige extender runner.
- **P2 — Response minimal** (só `{success:1}`): loop custom inline + DB roundtrip via `DB::table()`. Trade-off: valida persistência mas não shape leitura JSON.
- **P3 — Inertia redirect+flash**: loop custom inline + read-back via Eloquent model (com casts aplicados).
- **P4 — Mutex de campos** (CSOSN OU CST): tabs dedicadas no fixture pra cada caminho — evita pollution cross-tab.
- **P5 — POST + PUT cobertura dupla**: cobre `store` (drop validated()) + `update` (baseFields + 1 variado) — mesmo custo, dobro de bug catching.
- **P6 — Doc-only como entregável válido**: se tela cai em "Tela CRUD tradicional ⚪ opcional" da matriz ADR 0205, decisão doc-only é entregável legítimo (não forçar fixture).

## Tier 2 (futuro)

Browser smoke (Pest Browser/Dusk) — abre drawer, preenche, fecha, reabre, screenshot + assert text visible. Captura bugs de cache stale frontend (tipo bug CEP #1786 que persistia backend mas drawer não exibia).

**ADR 0205 declara:** "Implementar quando Tier 1 cobre 5+ telas." Pós sessão 4 waves paralelas (2026-05-27) temos **11 fixtures cobertas** — gate atingido. Candidatos top-priority: drawer Cliente (bug #4 cache stale catalogado), Sells/Create (cliente piloto Larissa), ServiceOrder/Edit (FSM visuais).

## Refs

- ADR 0205 — Contract tests autosave padrão canônico
- ADR 0179 — Drawer Cliente 760
- ADR 0093 — Multi-tenant Tier 0 (preservado nos contract tests)
- Session 2026-05-27 — bateria exaustiva drawer (5 bugs descobertos)
