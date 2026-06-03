# coord-paralelo — Múltiplos endereços de entrega por contato

> Coordenador-paralelo · 2026-06-02 · pattern `how-trabalhar.md` §"Paralelização N agents na mesma worktree"
> Feature aprovada por Wagner. Escopo TRAVADO. Migração DIRETA. NF-e enderEntrega FORA do v1.
> US no MCP: US-CRM-080/081/082/083.
> Worktree: `.claude/worktrees/frosty-greider-83ab2f` (branch base `feat/staging-ct100`).

---

## 1. Research curta (como os melhores fazem em 2026)

Padrão de mercado (Dynamics 365 Business Central, Sana Commerce) é consolidado e simples:
relação **1:N** cliente→endereços de entrega numa tabela satélite (`ship-to addresses`),
com **um endereço default** marcado e os demais selecionáveis por código/ID no documento de venda.
O pedido **snapshota** o endereço escolhido no fechamento (não referência viva), e a seleção fica
disponível pro próximo pedido. Exatamente o desenho que Wagner travou: tabela `contact_addresses`,
`is_default` único, FK rastreio na venda + string congelada no snapshot.

---

## 2. Inventário local (correções ao dossier)

| Item | Estado real | Nota |
|---|---|---|
| `contact_addresses` | **AUSENTE** | build do zero |
| Trait global scope canônico | `Modules/Financeiro/Models/Concerns/BusinessScope.php` + `BusinessScopeImpl.php` | **PADRÃO IMITADO** (copiado pra `app/Concerns/`) |
| Model referência | `Modules/Financeiro/Models/Titulo.php` | estrutura imitada |
| Backfill colunas | contacts tem `shipping_address` text + `address_line_1/2`, `numero`, `neighborhood`, `city`, `city_code`, `state`, `zip_code`, `country` | `neighborhood`/`city_code` existem (dossier omitiu) |
| Snapshot string venda | `app/Utils/TransactionUtil.php:90` (create) + `:214` (update) — NÃO SellController | corrige dossier (SellController só lê/whitelist) |
| Whitelist updateShipping | `app/Http/Controllers/SellController.php:3383-3386` | adicionar `shipping_address_id` |
| Drawer Cliente endereço | `resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx` | NÃO `EnderecoBRSection.tsx` (não existe); drawer 760px ADR 0179, autosave per-field |
| Backend drawer | `Modules/Crm/Http/Controllers/ClienteAutosaveController::endereco` (rota `PATCH /cliente/{id}/endereco`) | tenancy via session + where business_id |
| Tela venda | `resources/js/Pages/Sells/Create.tsx:202-208` (`useForm.shipping{}`) + 129-134 | seletor dropdown |
| Charters | `Sells/Create.charter.md` e charter drawer **não existem** nesta worktree | criar se MWART F1 exigir |
| Consumidores legado | Connector `NewContactResource` usa exclude-list / raw attrs | **não-breaking** — coluna legada preservada; accessor é safety net |
| PII | `Contact::getActivitylogOptions` não loga endereço | ContactAddress NÃO usa LogsActivity |

**Gap em 1 frase:** não existe relação 1:N — contato tem 1 endereço plano e a venda snapshota a string; falta tabela satélite + FK rastreio + UI.

---

## 3. Decomposição em ondas (dependência do Model é dura)

- **Onda 1 (sozinha):** US-080 — trunk, bloqueia tudo. ✅ ARTEFATOS ENTREGUES.
- **Onda 2 (2 áreas isoladas após 080):**
  - **2A** = US-081 backend venda (migration FK + TransactionUtil + SellController + Pest). ✅ migration+Pest entregues; 2 diffs PHP pendentes de aplicar.
  - **2B** = US-082 drawer UI MWART → para no gate visual Wagner. ⏸️ wave-prompt especificado.
- **Onda 3 (após 081):** US-083 tela venda seletor MWART → para no gate visual. ⏸️ wave-prompt especificado.

**Nota de paralelismo:** este ambiente não expõe tool de spawn de sub-agents (`Agent`/`Task`).
O coordenador atuou como parent-consolidador: produziu os artefatos por área isolada diretamente,
respeitando as fronteiras (Onda 1 = `app/ContactAddress.php`+`Contact.php`+migrations 080;
Onda 2A = migration 081+`TransactionUtil`+`SellController`; sem overlap). Ondas UI (2B/3) NÃO
foram codadas pra cutover — MWART exige gate visual antes, e Wagner aprova screenshot.

---

## 4. Spawn outputs (artefatos entregues na worktree)

### Onda 1 — US-080 (TRONCO) ✅
- `app/Concerns/BusinessScope.php` + `app/Concerns/BusinessScopeImpl.php` (global scope, espelha Financeiro)
- `database/migrations/2026_06_02_100000_create_contact_addresses_table.php`
- `database/migrations/2026_06_02_100100_backfill_contact_addresses.php` (idempotente)
- `app/ContactAddress.php` (Model: BusinessScope, scopeDefault, markAsDefault, toFlatString)
- `tests/Feature/Crm/ContactAddressTest.php` (5 testes: isolamento, auto business_id, default único, accessor compat, PII)
- **PENDENTE (diff, Edit indisponível):** relações `addresses()`+`addressDefault()`+accessor `getShippingAddressAttribute()` em `app/Contact.php` — diff abaixo na seção Consolidação.

### Onda 2A — US-081 (backend venda) ✅ parcial
- `database/migrations/2026_06_02_110000_add_shipping_address_id_to_transactions.php`
- `tests/Feature/Sells/ShippingAddressSnapshotTest.php` (2 testes: string congelada após edit; FK set null após delete)
- **PENDENTE (diff):** `TransactionUtil.php:90,214` aceitar `shipping_address_id`; `SellController.php:3384` whitelist.

### Onda 2B — US-082 (drawer) ⏸️ MWART, não codado
- Wave-prompt na seção Consolidação. Alvo real: `EnderecoTab.tsx` + `ClienteAutosaveController`.

### Onda 3 — US-083 (tela venda) ⏸️ MWART, não codado
- Wave-prompt na seção Consolidação. Alvo: `Sells/Create.tsx:202`.

---

## 5. Consolidação

Status detalhado, diffs PHP pendentes e plano git de N PRs estão na resposta ao parent
(devolvida ao Wagner). Resumo: 8 arquivos novos escritos na worktree, 2 diffs PHP pendentes
(Contact.php + TransactionUtil/SellController), 2 ondas UI bloqueadas em gate visual MWART.

**Conflitos detectados:** nenhum overlap de arquivo entre áreas. Refinamento vs dossier: o
snapshot da string mora em `TransactionUtil` (compartilhado SellController+SellPosController),
não só em `SellController` — Onda 2A toca `TransactionUtil` (fronteira ampliada, documentada).
