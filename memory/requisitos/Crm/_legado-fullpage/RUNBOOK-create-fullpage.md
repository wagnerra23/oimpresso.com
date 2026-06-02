---
title: "RUNBOOK — Cliente/Create (`/contacts/create`)"
module: Cliente
tela: Cliente/Create
owner: W
status: ativo
last_validated: 2026-05-21
preconditions:
  - "Usuário autenticado com permission `customer.create` (Spatie UPOS canon)"
  - "business_id válido na sessão (multi-tenant Tier 0 ativo — ADR 0093)"
  - "Migração `2026_05_21_restore_br_fields_to_contacts` aplicada (PR #1313) — campos cpf_cnpj/ie_rg/rua/numero/bairro/cep/consumidor_final/contribuinte/regime/is_sincronizado presentes"
preconditions_short: customer.create, business_id ativo, migration BR aplicada
steps:
  - "GET /contacts/create renderiza form Inertia useForm"
  - "Larissa preenche 4 seções (Identificação · Contato · Endereço · Financeiro) + bloco BR"
  - "Máscara dinâmica formata CPF (11 dig) ou CNPJ (14 dig) conforme digitação"
  - "Submit POST /contacts → backend valida (Rule\\BR\\CpfCnpj) e redirect Index ou retorna errors"
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3, 0110-cockpit-pattern-v2-canon-list-detail, 0149-mwart-screen-pattern-reuse-cowork]
---

# RUNBOOK — Cliente/Create (`/contacts/create`)

> Rota: `/contacts/create` · Componente: `resources/js/Pages/Cliente/Create.tsx`
> Controller: `app/Http/Controllers/ContactController@create` + `@store`
> Charter: `resources/js/Pages/Cliente/Create.charter.md`
> Última atualização: 2026-05-21

## 1. Objetivo

Formulário single-page de cadastro de novo cliente/fornecedor com campos BR completos (CPF/CNPJ + IE/RG + endereço PT-BR + consumidor final + contribuinte ICMS + regime tributário), substituindo Blade legacy `contact.create.blade.php`.

## 2. Persona principal

Larissa @ ROTA LIVRE (biz=4 vestuário) cadastrando cliente recém-chegado no balcão. Geralmente CNPJ + Razão Social + Endereço CEP via ViaCEP (lookup futuro). Pressa: cadastrar e voltar pra venda em < 60s.

## 3. Pré-requisitos

- Permission `customer.create` (Spatie UPOS canon)
- Multi-tenant Tier 0 ativo — `App\Contact::business_id = session('business.id')` obrigatório (ADR 0093)
- Migration BR aplicada — colunas `cpf_cnpj`, `ie_rg`, `rua`, `numero`, `bairro`, `cep`, `consumidor_final`, `contribuinte`, `regime`, `is_sincronizado` (PR #1313 restaurou após perda no upgrade UPOS 6.7)
- Pre-fill via `?prefill_name=` opcional (vindo do `CustomerSearchAutocomplete` em Sells/Create)

## 4. Fluxo principal (golden path)

1. Larissa clica "Novo cliente" no Index (ou usa atalho ⌘K → "Cadastrar")
2. `/contacts/create` renderiza com radio Pessoa Física / Pessoa Jurídica focado em Física (default)
3. Larissa muda pra Jurídica → label muda pra "Razão Social" + "Nome fantasia" aparece
4. Digita CNPJ "12345678000195" → máscara formata `12.345.678/0001-95` inline
5. (Futuro Slice 5a) Botão "Buscar" chama BrasilAPI `/cnpj/v1/{cnpj}` → preenche razão social + endereço
6. Preenche endereço (rua, número, bairro, CEP, cidade, UF)
7. Submit → backend valida via `Rule\BR\CpfCnpj` (FormRequest Slice 7)
8. Sucesso → redirect `/cliente` com flash success "Cliente cadastrado: NOME"
9. Erro → useForm.errors exibido inline por campo (PT-BR canon)

## 5. Sub-componentes

- `resources/js/Pages/Cliente/Create.tsx` — page raiz com useForm Inertia
- `resources/js/Pages/Cliente/_form/DadosFiscaisBRSection.tsx` — seção CPF/CNPJ + IE/RG + IM + consumidor_final + contribuinte + regime (Slices 2+3 PR #1316)
- `resources/js/Pages/Cliente/_form/EnderecoBRSection.tsx` — campos rua/numero/bairro/cep + CEP lookup (Slices 2+3)
- Shared: `Input`, `Select`, `RadioGroup` (de `resources/js/Components/`)

## 6. Estados (loading / empty / error / success)

| Estado | UI | Trigger |
|---|---|---|
| Idle | Form vazio + radio "Pessoa Física" selecionado | render inicial |
| Pre-filled | Campo Nome preenchido (vindo de `?prefill_name=`) | query string presente |
| Validando | Botão Submit disabled + spinner | `form.processing === true` |
| Erro validação | Mensagem inline rose abaixo do campo | `form.errors.<campo>` populado |
| Sucesso | Redirect com flash success | response 302 → Index |
| Erro server | Toast rose "Erro ao salvar — tente novamente" | response 500 |

## 7. Atalhos de teclado

| Tecla | Ação |
|---|---|
| Tab | Próximo campo (ordem lógica top→bottom) |
| Shift+Tab | Campo anterior |
| Enter (em input) | Avança próximo campo (não submete) |
| Ctrl+Enter | Submit form |
| Esc | Cancelar (volta pra Index com confirm se dirty) |

## 8. Dependências de API/backend

- `ContactController::create()` linha ~536 — retorna options (customer_groups, business_locations, default_type)
- `ContactController::store(Request $req)` linha ~1358 — persiste após validação
- (Slice 5a) `GET https://brasilapi.com.br/api/cnpj/v1/{cnpj}` — lookup público sem auth
- (Slice 5a) `GET https://viacep.com.br/ws/{cep}/json/` — lookup endereço
- (Slice 7) FormRequest `StoreContactRequest` com `Rule\BR\CpfCnpj`

## 9. Multi-tenant + LGPD

- **Tier 0 (ADR 0093):** `Contact::create([..., 'business_id' => $request->session()->get('business.id')])` — JAMAIS aceitar `business_id` do payload
- **PII:** `cpf_cnpj` armazenado em plain text na DB mas mascarado no display via `maskTaxNumber` (ADR 0127 inferido — feedback-nunca-publicar-credenciais.md)
- **Activity log:** `Contact` exclui `tax_number_1`/`cpf_cnpj` do log via `logOnly`
- **Validação:** `Rule\BR\CpfCnpj` (Slice 7) usa mod-11 (já existe em `lib-custom/laravel-boleto/src/Util.php:1162`)

## 10. Smoke check pós-deploy

```bash
# 1. Verificar migration aplicada
ssh prod 'cd /home/oimpresso/public_html && php artisan tinker --execute="echo Schema::hasColumn(\"contacts\", \"cpf_cnpj\") ? \"OK\" : \"FAIL\";"'

# 2. HTTP smoke
curl -sv https://oimpresso.com/contacts/create -H "Cookie: laravel_session=<sess_biz4>" 2>&1 | grep -E "(HTTP/|component)"
# Esperado: HTTP/2 200 + "component":"Cliente/Create"

# 3. Bloco BR presente no payload
# Inspect Inertia props no DevTools → procurar contact.cpf_cnpj/ie_rg/regime nas options

# 4. Smoke real cadastro
# Via browser MCP: cadastrar PJ CNPJ válido → confirmar redirect + flash + listagem
```

## 11. Refs

- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual gate F1.5](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110 — Cockpit Pattern V2](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0149 — Pattern reuse Crm](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- Charter: [`resources/js/Pages/Cliente/Create.charter.md`](../../../resources/js/Pages/Cliente/Create.charter.md)
- PR #1313 — Slice 1 migration restore 10 campos BR + Rule\BR\CpfCnpj
- PR #1316 — Slices 2+3 UI Create/Edit + bloco fiscal Show
- Investigação base: [`memory/sessions/2026-05-21-investigar-campos-br-cliente.md`](../../sessions/2026-05-21-investigar-campos-br-cliente.md)
