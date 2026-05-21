---
title: "RUNBOOK — Cliente/Edit (`/contacts/{id}/edit`)"
module: Cliente
tela: Cliente/Edit
owner: W
status: ativo
last_validated: 2026-05-21
preconditions:
  - "Usuário autenticado com permission `customer.update` (Spatie UPOS canon)"
  - "Cliente {id} pertence ao business_id da sessão (Tier 0 — ADR 0093)"
  - "Migração `2026_05_21_restore_br_fields_to_contacts` aplicada (PR #1313)"
  - "Backfill cpf_cnpj rodou (`php artisan contacts:backfill-cpf-cnpj` — PR #1319)"
preconditions_short: customer.update, ownership business_id, migration BR + backfill aplicados
steps:
  - "GET /contacts/{id}/edit pré-preenche form com dados existentes"
  - "Larissa edita campos (CPF/CNPJ travado se já validado, opening_balance ajustado)"
  - "Submit PUT /contacts/{id} → backend valida e retorna redirect /cliente/{id} ou errors"
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3, 0110-cockpit-pattern-v2-canon-list-detail, 0149-mwart-screen-pattern-reuse-cowork]
---

# RUNBOOK — Cliente/Edit (`/contacts/{id}/edit`)

> Rota: `/contacts/{id}/edit` · Componente: `resources/js/Pages/Cliente/Edit.tsx`
> Controller: `app/Http/Controllers/ContactController@edit` + `@update`
> Charter: `resources/js/Pages/Cliente/Edit.charter.md`
> Última atualização: 2026-05-21

## 1. Objetivo

Editar dados de cliente existente preservando layout idêntico ao Create + campos BR completos + `opening_balance` ajustado (descontando pagamentos via `TransactionUtil::getTotalAmountPaid`), substituindo Blade legacy `contact.edit.blade.php`.

## 2. Persona principal

Larissa @ ROTA LIVRE corrigindo dado errado num cadastro (típico: telefone trocado, endereço novo, IE atualizada). Operação de manutenção, < 30s por edit.

## 3. Pré-requisitos

- Permission `customer.update` (Spatie UPOS canon)
- `Contact::find($id)->business_id === session('business.id')` — ownership obrigatório (Tier 0)
- Migration BR aplicada (PR #1313)
- Backfill `cpf_cnpj` populado (PR #1319 — comando `php artisan contacts:backfill-cpf-cnpj` que migra `tax_number_1` legacy → `cpf_cnpj` canon)
- `type` (customer/supplier) NÃO é editável (risco data integrity — Charter Non-Goal)

## 4. Fluxo principal (golden path)

1. Larissa abre Show `/cliente/{id}`, clica botão **Editar** no header
2. `/contacts/{id}/edit` renderiza com todos os campos pré-preenchidos (incluindo bloco BR)
3. Bloco "Pessoa Física/Jurídica" travado (radio disabled — não pode mudar tipo)
4. Larissa edita campos desejados — useForm marca `isDirty`
5. CPF/CNPJ formatado via máscara (display) mas valor enviado é dígitos puros
6. `opening_balance` exibido como saldo ATUAL (ajustado por pagamentos — backend calcula)
7. Submit PUT `/contacts/{id}` → validação + persistência
8. Sucesso → redirect `/cliente/{id}` com flash "Cliente atualizado"
9. Erro → useForm.errors inline

## 5. Sub-componentes

- `resources/js/Pages/Cliente/Edit.tsx` — page raiz (compartilha layout Create via composição)
- `resources/js/Pages/Cliente/_form/DadosFiscaisBRSection.tsx` — reuso 100% Create (Slices 2+3 PR #1316)
- `resources/js/Pages/Cliente/_form/EnderecoBRSection.tsx` — reuso 100% Create
- Shared: `Input`, `Select`, `RadioGroup` disabled-aware

## 6. Estados (loading / empty / error / success)

| Estado | UI | Trigger |
|---|---|---|
| Loading inicial | Skeleton fields | useForm não setado ainda (raro — SSR-like via Inertia) |
| Idle dirty=false | Form preenchido + Submit disabled | render inicial |
| Idle dirty=true | Submit habilitado em sky | usuário tocou ≥1 campo |
| Validando | Submit + spinner | `form.processing === true` |
| Erro validação | Inline rose | `form.errors.<campo>` |
| Sucesso | Redirect Show + flash | response 302 |

## 7. Atalhos de teclado

| Tecla | Ação |
|---|---|
| Tab / Shift+Tab | Navegação campos |
| Ctrl+Enter | Submit form (apenas se dirty) |
| Esc | Cancelar (confirm se dirty) → volta Show |

## 8. Dependências de API/backend

- `ContactController::edit($id)` linha ~768 — retorna contact + options pré-carregados
- `ContactController::update(Request $req, $id)` — valida e persiste via `Rule\BR\CpfCnpj` (Slice 7)
- `TransactionUtil::getTotalAmountPaid($contact_id)` — usado pra calcular opening_balance ajustado

## 9. Multi-tenant + LGPD

- **Tier 0 (ADR 0093):** `Contact::where('business_id', session('business.id'))->findOrFail($id)` — sem isso, 404 em vez de 403 (anti-enumeração)
- **PII:** mesma regra de Create — `cpf_cnpj` mascarado no display
- **Activity log:** mudanças logadas em `activity_log` (sem PII em plain text — exclui `tax_number_1`)
- **`type` imutável:** customer ↔ supplier NÃO pode trocar (vira nova linha)

## 10. Smoke check pós-deploy

```bash
# 1. HTTP smoke
curl -sv "https://oimpresso.com/contacts/1234/edit" -H "Cookie: laravel_session=<sess_biz4>" 2>&1 | grep -E "(HTTP/|component)"
# Esperado: HTTP/2 200 + "component":"Cliente/Edit"

# 2. Cross-tenant denied
curl -sv "https://oimpresso.com/contacts/99999/edit" -H "Cookie: laravel_session=<sess_biz4>" 2>&1 | grep "HTTP/"
# Esperado: 404 (cliente 99999 de biz=1, sessão biz=4)

# 3. Smoke real edição
# Via browser MCP: editar telefone, confirmar persistência + activity_log entry
```

## 11. Refs

- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual gate F1.5](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110 — Cockpit Pattern V2](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0149 — Pattern reuse Crm](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- Charter: [`resources/js/Pages/Cliente/Edit.charter.md`](../../../resources/js/Pages/Cliente/Edit.charter.md)
- PR #1313 — migration BR
- PR #1316 — Slices 2+3 UI Create/Edit (reuso de seções)
- PR #1319 — Slice 4 comando backfill cpf_cnpj
