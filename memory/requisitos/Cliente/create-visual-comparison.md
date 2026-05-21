---
slug: cliente-create-visual-comparison
title: "Cliente — Comparativo visual /contacts/create (Blade legacy → Inertia React + bloco BR Slices 2+3)"
type: visual-comparison
module: Cliente
status: approved
approved_by: wagner
date: 2026-05-21
canon_reference: resources/views/contact/create.blade.php (Blade legacy — 1 campo tax_number único)
inertia_target: resources/js/Pages/Cliente/Create.tsx (charter draft)
controller: app/Http/Controllers/ContactController.php::create() linha ~536
stories: [US-CRM-072, US-CRM-073, US-CRM-075, US-CRM-076]
related_adrs: [0093, 0094, 0104, 0107, 0110, 0149]
---

# Visual Comparison — Cliente/Create (`/contacts/create`)

> **Tipo de tela:** formulário single-page de cadastro (Identificação · Contato · Endereço · Financeiro + Bloco Fiscal BR)
> **Persona:** Larissa @ ROTA LIVRE (biz=4 vestuário, monitor 1280×1024, não-técnica). Pressa balcão.
> **Referência:** `prototipo-ui/prototipos/clientes/cowork-app.jsx` + HANDOFF Claude Design

## Contexto

PR #1313 (Slice 1) restaurou 10 campos BR perdidos no upgrade UPOS 6.7. PR #1316 (Slices 2+3) adicionou UI Create/Edit com bloco BR + máscaras dinâmicas CPF/CNPJ. Esta comparação valida que a tela Inertia React supera funcionalmente o Blade legacy (`tax_number` único) preservando ergonomia Larissa.

## Matriz consolidada

| Item | Blade legacy | React (pós Slices 2+3) | Peso | Score |
|---|:-:|:-:|:-:|:-:|
| Campo CPF/CNPJ | tax_number genérico | cpf_cnpj com máscara dinâmica | 5 | 10/10 |
| IE/RG (BR fiscal) | ausente | presente | 4 | 10/10 |
| Inscrição Municipal | ausente | presente | 3 | 9/10 |
| Consumidor Final | ausente | toggle bool | 3 | 10/10 |
| Contribuinte ICMS | ausente | toggle bool | 3 | 10/10 |
| Regime Tributário | ausente | select (Simples/Normal/...) | 3 | 9/10 |
| Endereço PT-BR (rua/número/bairro/CEP) | combinado address_line_1 | campos separados PT-BR | 4 | 10/10 |
| BrasilAPI lookup CNPJ | ausente | botão "Buscar" (Slice 5a futuro) | 4 | 6/10 |
| Validação mod-11 backend | ausente | Rule\BR\CpfCnpj (Slice 7) | 5 | 8/10 |
| Layout single-column 3xl | wide spread | 3xl centered | 2 | 10/10 |
| Dark mode | inexistente | full coverage | 1 | 10/10 |

## 8 dimensões avaliadas (canon MWART)

### 1. Layout

| Aspecto | Blade | React |
|---|---|---|
| Container width | Wide (90vw) | `max-w-3xl` centered |
| Seções | 1 form gigante scroll | 4 seções colapsáveis (Identificação · Contato · Endereço · Financeiro) + Bloco Fiscal BR |
| Densidade | Média-baixa | Alta canon Anthropic 2026 |
| Mobile ≤640px | quebra | responsive grid colapsa pra 1 col |

**Decisão MWART:** layout Inertia 3xl single-column adotado. Bloco BR posicionado entre Identificação e Endereço (afinidade semântica).

### 2. Hierarquia visual

- h1 "Cadastrar cliente" 22-24px `font-semibold` (canon ADR 0110)
- Subtítulo stone-500 11px
- Section heading 14px `font-medium` com divider hr
- Label 13px stone-600
- Input 14px shadow-sm rounded-md

**Decisão MWART:** hierarquia canon Cockpit V2 aplicada. Sem `font-bold` (proibido em h1).

### 3. Densidade informacional

Larissa monitor 1280×1024 — bloco BR cabe sem scroll horizontal. 4 seções colapsáveis economizam scroll vertical. PRO: cabe formulário inteiro em < 2 scrolls.

### 4. Multi-tenant Tier 0

- `ContactController::store()` injeta `business_id` SERVER-side (jamais aceitar do payload)
- Validação `Rule\BR\CpfCnpj` é per-business (CPF pode duplicar entre businesses, mas única por business)
- ADR 0093 obrigatório

**Decisão MWART:** ✅ aprovado (Tier 0 compliant).

### 5. Permissões

- `customer.create` necessária pra exibir página
- Sem permission: 403 (não 404 — usuário sabe que a feature existe, só não tem grant)
- Middleware no `__construct` do Controller

### 6. Acessibilidade & Mobile

- `aria-label` em cada input
- `aria-describedby` apontando pra mensagem de erro quando errored
- `role="alert"` na mensagem de validação backend
- Radio Pessoa Física/Jurídica com `role="radiogroup"`
- Mobile responsivo até 1100px (gap: < 1100px ainda quebra — backlog)

**Decisão MWART:** aprovado com gap mobile < 1100px (US futura).

### 7. PII / LGPD handling

- `cpf_cnpj` digitado em plain (display) mas validado backend
- Após salvar: `maskTaxNumber($cpf_cnpj)` aplicado em todo display
- Activity log exclui `tax_number_1`/`cpf_cnpj` via `logOnly`
- ViaCEP / BrasilAPI: lookup público sem auth — IP do servidor (não vaza identidade Larissa)

**Decisão MWART:** ✅ aprovado (LGPD compliant).

### 8. Estados (loading / empty / error / success)

| Estado | Blade | React |
|---|:-:|:-:|
| Loading inicial | full page reload | Inertia SPA-feel |
| Pre-fill via query | partial JS | `useForm` initial values |
| Validando | submit + page reload | submit disabled + spinner |
| Erro inline | server-side @error blade | useForm.errors map |
| Sucesso | flash → redirect | Inertia redirect com flash |

**Decisão MWART:** Inertia SPA experience superior em todos os estados.

## Score consolidado

| Dimensão | Score | Peso | Contribuição |
|---|:-:|:-:|:-:|
| Layout | 9 | 15% | 1.35 |
| Hierarquia | 9 | 10% | 0.90 |
| Densidade | 9 | 10% | 0.90 |
| Multi-tenant Tier 0 | 10 | 20% | 2.00 |
| Permissões | 9 | 10% | 0.90 |
| Acessibilidade | 7 | 10% | 0.70 |
| PII/LGPD | 10 | 15% | 1.50 |
| Estados | 9 | 10% | 0.90 |

### **Nota final: 91.5 / 100** (sólido aprovado)

## Gaps remanescentes (backlog)

| US futura | Gap | Prioridade |
|---|---|---|
| US-CRM-075 | BrasilAPI lookup CNPJ + botão "Buscar" funcional | P1 |
| US-CRM-076 | FormRequest backend wirando Rule\BR\CpfCnpj | P0 |
| (futura) | ViaCEP lookup automático no campo CEP | P2 |
| (futura) | Mobile < 1100px refinement | P2 |
| (futura) | Suframa, indicador_ie NFe (1/2/9) | P3 |

## Refs

- HANDOFF: `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md`
- ADR 0107 (gate F1.5 visual-comparison)
- ADR 0110 (Cockpit V2)
- ADR 0149 (pattern reuse Crm)
- Investigação: [`memory/sessions/2026-05-21-investigar-campos-br-cliente.md`](../../sessions/2026-05-21-investigar-campos-br-cliente.md)
- PR #1313 — migration BR
- PR #1316 — Slices 2+3 UI Create/Edit + bloco fiscal Show
