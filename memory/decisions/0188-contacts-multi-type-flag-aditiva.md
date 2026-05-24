---
slug: 0188-contacts-multi-type-flag-aditiva
number: 188
title: "Contatos multi-type · flags aditivas is_customer/is_supplier/is_employee/is_representative (mesmo cadastro pode ter N papéis)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-24"
accepted_at: "2026-05-24"
accepted_via: "Wagner aprovou em sessão `frosty-greider-83ab2f` 2026-05-24 — comando exato: 'pode fazer' após análise comparativa Delphi WR Comercial (flags bool por papel) vs UltimatePOS (enum single-type) · escolha Opção B (flags aditivas backward-compat) sobre A (mantém single) e C (pivot table)"
module: crm
quarter: 2026-Q2
tags: [crm, contacts, multi-type, schema, multi-tenant, tier-0, ultimatepos-legacy, delphi-pattern, aditivo, append-only]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0179-cliente-drawer-760px-substitui-show-fullpage"
  - "0185-drawer-760-canon-entidades-cadastrais"
  - "0186-chain-certificado-sefaz-consulta-cadastro"
  - "0187-constituicao-ui-v2-ponteiro-canon"
charter_impact:
  - "Pages/Cliente/Index.charter.md v6 → v7 (Slot 2 PT-01 + multi-type flag visible em Drawer)"
pii: false
review_triggers:
  - ">2 papéis por contato virar comum (>10% dos cadastros) → migrar pra ADR-C pivot table"
  - "Outro módulo (Sells, Compras) precisar query \"qualquer contato com type X OU Y\" → revisitar"
  - "Volume contatos passar de 100k → avaliar índice composto vs índice individual"
---

# ADR 0188 · Contatos multi-type · flags aditivas

## Contexto

UltimatePOS (UPOS) v6 herdou tabela `contacts` com coluna `type` ENUM single-value:

```sql
contacts.type = 'customer' | 'supplier' | 'employee' | 'representative'
```

Single-type significa **1 contato = 1 papel**. Se Wagner Rocha é cliente E representante, hoje precisa de **2 linhas duplicadas** no banco · dados de contato (telefone, email, endereço) duplicados · histórico fragmentado.

Wagner detectou problema em validação visual produção 2026-05-24:
- Aplicou Slot 2 PT-01 PT em `/cliente` (sub-tabs Clientes/Fornecedores/Funcionários/Representantes via `?type=X`)
- Observou: "no Delphi (WR Comercial legacy) tenho cadastro de tipo de contato, mesmo contato pode ser classificado como cliente, fornecedor, funcionário — evita ter o mesmo cadastro em tabelas separadas"

Delphi WR Comercial usa **flags bool por papel** (`is_cliente`, `is_fornecedor`, `is_funcionario`, `is_representante`) · 1 cadastro = N papéis simultâneos. Pattern correto pra ERP de PME (cliente vira fornecedor, fornecedor vira representante).

3 caminhos arquiteturais avaliados:

| Caminho | Schema | Backward-compat UPOS | Esforço | Complexidade query |
|---|---|---|---|---|
| **A · Mantém UPOS** | Sem migration · `type` enum permanece | ✅ Total | Baixo (~1.5h) | Trivial · `WHERE type='X'` |
| **B · Flags aditivas** | Migration aditiva 4 colunas bool · `type` vira "papel principal" | ✅ Aditiva sem breaking | Médio (~4h) | Baixa · `WHERE is_X=1` |
| **C · Pivot `contact_types`** | Nova tabela `(contact_id, type)` · N rows por contato | ⚠️ Requer JOIN/EXISTS em todas queries UPOS legacy | Alto (~6h) | Média · `WHERE EXISTS (...)` |

Wagner escolheu **B** ("pode fazer") sobre A (perde insight Delphi · duplicação real) e C (refactor amplo · ROI baixo em PME 100 cadastros/mês).

## Decisão

Adicionar **4 colunas bool aditivas** em `contacts` mantendo coluna `type` enum como "papel principal" pra retrocompat com 200+ telas Blade UPOS legacy:

```sql
ALTER TABLE contacts ADD COLUMN is_customer       TINYINT(1) NOT NULL DEFAULT 0 AFTER type;
ALTER TABLE contacts ADD COLUMN is_supplier       TINYINT(1) NOT NULL DEFAULT 0 AFTER is_customer;
ALTER TABLE contacts ADD COLUMN is_employee       TINYINT(1) NOT NULL DEFAULT 0 AFTER is_supplier;
ALTER TABLE contacts ADD COLUMN is_representative TINYINT(1) NOT NULL DEFAULT 0 AFTER is_employee;

-- Backfill: papel principal = type atual = flag ativa
UPDATE contacts SET is_customer       = 1 WHERE type = 'customer';
UPDATE contacts SET is_supplier       = 1 WHERE type = 'supplier';
UPDATE contacts SET is_employee       = 1 WHERE type = 'employee';
UPDATE contacts SET is_representative = 1 WHERE type = 'representative';

-- Índice composto pra Tier 0 multi-tenant (business_id) + filter rápido
CREATE INDEX idx_contacts_business_customer ON contacts(business_id, is_customer);
CREATE INDEX idx_contacts_business_supplier ON contacts(business_id, is_supplier);
CREATE INDEX idx_contacts_business_employee ON contacts(business_id, is_employee);
CREATE INDEX idx_contacts_business_representative ON contacts(business_id, is_representative);
```

### Invariantes (Tier 0 IRREVOGÁVEL)

1. **`type` enum permanece** · não-remover, não-deprecar nesta ADR · pra retrocompat UPOS legacy
2. **Backfill é one-way** · todas as `contacts` UPOS existentes ganham `is_X=1` correspondente ao `type`
3. **Indexação multi-tenant Tier 0** · todos os índices compostos com `business_id` primeiro (ADR 0093 IRREVOGÁVEL)
4. **Flags aditivas, NUNCA exclusivas** · cliente que vira fornecedor = `is_customer=1 AND is_supplier=1` (não trocar)
5. **Papel principal mutável** · `type` pode ser atualizado pelo Drawer pra refletir papel mais usado (apenas informativo)

### Frontend (Pages/Cliente/Index.tsx)

```tsx
// Slot 2 PT-01 ModuleTopNav: 5 tabs (4 papéis + Todos)
items={[
  { label: 'Clientes',       href: '/cliente?type=customer'       },
  { label: 'Fornecedores',   href: '/cliente?type=supplier'       },
  { label: 'Funcionários',   href: '/cliente?type=employee'       },
  { label: 'Representantes', href: '/cliente?type=representative' },
  { label: 'Todos',          href: '/cliente?type=all'            },
]}
```

Backend interpreta:
- `?type=customer` → `WHERE is_customer = 1`
- `?type=supplier` → `WHERE is_supplier = 1`
- `?type=all` → sem filtro (qualquer papel)
- `?type=ausente ou inválido` → default `customer`

### Drawer 760 (Onda 4 futura · scope-only nesta ADR)

Identificação tab terá seção "Papéis" com 4 checkboxes bool. Wagner pode marcar/desmarcar sem deletar/duplicar contato:

```
☑️ Cliente
☐ Fornecedor
☐ Funcionário
☐ Representante
```

Onda 4 implementa quando ondas anteriores estabilizarem.

## Consequências

### Positivas

- **Resolve dor Delphi:** Wagner Rocha cliente+representante = 1 linha · histórico unificado · contato (tel/email/endereço) sem duplicação
- **Backward-compat total:** 200+ telas Blade UPOS legacy continuam funcionando (`type` enum intacto)
- **Migration aditiva reversível:** rollback = `DROP COLUMN is_X` (sem perda dados)
- **Tier 0 multi-tenant preservado:** índices compostos `(business_id, is_X)` mantêm isolamento (ADR 0093 IRREVOGÁVEL)
- **Vendas/Compras/Folha consultam mesmo cadastro:** menos drift de PII

### Negativas

- **2 fontes de verdade** (`type` enum vs `is_X` flags) podem dessincronizar em queries não-atualizadas. **Mitigação:** Pest test `contacts_type_flag_sync_test` valida sync · todo INSERT deve setar pelo menos 1 flag
- **Queries UPOS legacy** continuam usando `WHERE type='X'` · funcionarão mas perdem contatos multi-papel (ex: cliente+repr só aparece em UI nova)
- **Workflow de migração manual** · usuário com 2 cadastros duplicados de Wagner Rocha (id=42 cliente + id=99 repr) precisa **merge manual** (script futuro)

### Neutras / a observar

- Quando >10% dos cadastros tiver >2 papéis, avaliar migração pra **Opção C** (pivot `contact_types`). Hoje é minoria · não-justificável
- Se Sells/Compras precisarem query "qualquer contato com type X OU Y" (raro), pode usar `WHERE is_X=1 OR is_Y=1`

## Pegadinhas conhecidas

- **NÃO confundir** `is_customer=0` com "deletado" · use coluna `deleted_at` (soft-delete UPOS) ou status `inativo`
- **NÃO setar todas flags 0** em INSERT · pelo menos 1 ativa (futuro Pest guard)
- **NUNCA fazer cross-tenant query** (sem `business_id`) mesmo em multi-type · Tier 0 IRREVOGÁVEL [ADR 0093](0093-multi-tenant-isolation-tier-0.md)
- **type enum permanece authoritative pra UPOS legacy** · qualquer mudança em `is_X` deve sincronizar `type` se houver mudança de "papel principal"

## Plano de execução

1. **ADR 0188 (este doc)** · aceita Wagner 2026-05-24 ✓
2. **Migration aditiva** `2026_05_24_add_role_flags_to_contacts` com backfill
3. **`Contact` model** scope methods `customers()`, `suppliers()`, `employees()`, `representatives()`, `withAnyRole()`
4. **`ContactController::index`** ler `?type=X` filtra via `is_X=1` (fallback `type=X` se flag não existir · transição)
5. **`Pages/Cliente/Index.tsx`** Slot 2 PT-01 `<ModuleTopNav>` 5 tabs (4 papéis + "Todos")
6. **Charter v6 → v7** atualizado
7. **Pest test** sync `type` ↔ `is_X` flags
8. **Onda 4 futura · scope-only:** Drawer 760 seção "Papéis" com 4 checkboxes

## Refs

- ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL (índices compostos com `business_id`)
- ADR 0094 Constituição v2 backend
- ADR 0179 + 0185 drawer 760 (Onda 4 multi-type vai aqui)
- ADR UI-0013 Constituição UI v2 (Slot 2 PT-01 canônico)
- ADR 0186 SEFAZ ConsultaCadastro (multi-tenant `contacts` enriquecimento NF-e)
- ADR 0187 Constituição UI v2 ponteiro MCP
- Delphi WR Comercial · flags bool por papel (pattern legacy 15 anos)
- HANDOFF_CLIENTES.md (Cowork chat1)
