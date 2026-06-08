---
session: 2026-05-26 smoke test prod Hostinger pós KB-9.75 P0-P3 + fix #1646
page: /sells/create + /sells/{id}/edit
component: resources/js/Pages/Sells/Create.tsx + resources/js/Pages/Sells/Edit.tsx
visual_source: prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/vendas-page.jsx (VendaCreateDrawer linha 1471)
canon_method: smoke test direto prod Hostinger via browser MCP
related_adrs: [0093, 0094, 0104, 0107, 0143, 0149, 0192]
charter_impact: Edit.charter.md wave1-draft → live PENDENTE (gap descoberto)
---

# Comparativo Sells/Create vs Sells/Edit em prod (smoke 2026-05-26)

> **Wagner pediu (16:30 UTC):** "teste edição e insert, tem que ser sênior que o blade confira e compare crie uma lista". Smoke test direto em https://oimpresso.com via browser MCP logado como `wagnerra@gmail.com` (biz=1 WR2 SC).

## TL;DR — **Gap crítico descoberto**

- ✅ **Create.tsx é Inertia Cowork** moderno em prod (deployed)
- ❌ **Edit.tsx ainda é Blade legacy AdminLTE roxo** em prod (charter status `wave1-draft` nunca foi pra `live`)

Inconsistência visual sério: Larissa cria venda nova num shell moderno (sidebar dark + 4 KPIs + tabs Cowork) MAS ao clicar "Editar" cai num shell antigo (sidebar roxo "WR2 Sistemas" + formulário gigante sem tabs). **Cognitive load alto pro user** — parece outro app.

## Teste 1 — Create

**URL:** `https://oimpresso.com/sells/create`
**Renderiza:** ✅ Inertia React (`Sells/Create.tsx`)
**Title:** "OI Impresso" (Inertia adapter)
**H1:** "Adicionar venda"
**Subtitle:** "Registre uma venda completa — cliente, produtos, pagamento e frete."

### Layout — `Sells/Create.tsx`

| Elemento | Estado |
|---|---|
| AppShellV2 sidebar dark (260px) | ✅ |
| Sidebar canônica nova (Contatos · Produtos · Fabricação · COMERCIAL · Vendas · Crm · Oficina Auto · FINANÇAS · FISCAL · Notas Fiscais · etc) | ✅ |
| H1 "Adicionar venda" + subtitle | ✅ |
| **Tab bar canônica:** Dados (selected) · Produtos · Pagamento · Resumo · Mais opções | ✅ |
| 4 KPI hero cards: ITENS / TOTAL VENDA / PAGO / STATUS PGTO | ✅ |
| Bloco "Dados da venda": Cliente + Data + Status + Local | ✅ |
| Customer search combobox (≥2 chars busca · walk-in default) | ✅ |
| Bloco "Produtos": search bar + empty state ilustrado | ✅ |
| Placeholder "Buscar produto por nome, SKU ou código de barras..." | ✅ |
| Empty state "Nenhum produto adicionado · Use a busca acima ou aperte / pra focar" | ✅ |
| Footer sticky bottom: Cancelar / Salvar e Imprimir / Salvar venda | ✅ |
| Hint footer: "Adicione pelo menos 1 produto" | ✅ |
| Inputs: 22 (form enxuto) | ✅ |

### Features modernas

- ✅ Atalhos teclado `/` foca busca produto
- ✅ Auto-save draft localStorage (Tier 0 multi-tenant per biz_id + user_id)
- ✅ FieldError component inline (border vermelha + role=alert)
- ✅ `<details>` colapsável "Mais opções" (price_group · commission_agent · pay_term · invoice_scheme · invoice_no · document · tax_rate · shipping)
- ✅ TS strict interfaces (`SellsCreatePageProps`)

## Teste 2 — Edit

**URL testada:** `https://oimpresso.com/sells/25150/edit` (venda mais recente · OS00129 · payment_status=paid)
**Renderiza:** ❌ **Blade legacy** (`sale_pos.edit.blade.php`)
**Title:** "Editar venda - WR2 Sistemas" (Blade title antigo)
**H1:** "Editar venda ( Nº da fatura : #OS00129)"

### Layout — Blade legacy AdminLTE

| Elemento | Estado |
|---|---|
| AppShellV2 sidebar dark | ❌ usa AdminLTE roxo "WR2 Sistemas" |
| Top bar roxo gradient com botões PDA POS Reparar | ❌ legacy (não tem no Inertia) |
| Sidebar legacy: Auditoria · Office Impresso · Módulos · Backup · Dashboard · CMS · Conector · Gerenciamento de u... · Contatos · Produtos · Fabricação · Reparar · Compras · Crm · Vendas · Oficina Auto · Transferências de a... | ❌ different sidebar shape |
| H1 sem subtitle | ❌ inconsistente com Create |
| Tab bar (Dados / Produtos / Pagamento / Resumo / Mais opções) | ❌ AUSENTE — form gigantesco numa só tela |
| 4 KPI hero cards | ❌ AUSENTE |
| Cliente combobox legacy com chip "Cliente padrão" | ⚠️ funciona mas estilo legacy |
| **Alerta "Cliente vencido: R$ 27.657,79"** abaixo do cliente | ✅ informação útil (NÃO existe no Create Inertia) |
| Cards de campos lado-a-lado em grid 4 col | ❌ layout antigo (Create usa stack vertical) |
| Inputs: 179 (vs 22 do Create — form muito maior) | ⚠️ legacy mostra tudo |
| Form com bordas grossas + cores planas | ❌ visual estado-da-arte 2016 |

### Features que SÓ existem no Blade Edit

Pra preservar paridade quando migrar:

- "Inscrever-se?" checkbox (assinatura recorrente)
- "Cliente vencido: R$ X" alerta inline
- "Endereço de cobrança" + "Endereço de entrega" separados
- "Prazo de pagamento" + "Selecionar" dropdown
- Comissionista select (com "nenhum" default)
- "Anexar documento" + botão "Procurar..." (upload file)
- "Tamanho máximo do arquivo: 5MB" hint
- "Arquivo permitido: .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png" lista de tipos
- "Selecionar responsável pela venda" (usuário/avatar)
- Tabela produtos com colunas: Produto · Quantidade (+/-) · Preço unitário · Desconto (valor/% toggle) · Subtotal · botão delete X
- "Item: 1.00 · Total: 90,00" rodapé tabela
- Adicionar produto IMEI / nº série info linha extra

### Features que SÓ existem no Create Inertia

- Tab bar (5 abas)
- 4 KPI hero
- Auto-save draft localStorage
- Atalho `/` pra foco busca
- Empty state ilustrado bonito
- "Salvar e Imprimir" botão (2-em-1)

## Lista comparativa estruturada

### Score visual (0-10) por aspecto

| Aspecto | Create (Inertia) | Edit (Blade) | Gap |
|---|---:|---:|---:|
| Identidade visual canônica | **9** | 2 | **-7** |
| Sidebar coerente com app | **9** | 3 | **-6** |
| Tipografia IBM Plex Sans | **9** | 4 | **-5** |
| Atalhos teclado | **8** | 0 | **-8** |
| Auto-save draft | **9** | 0 | **-9** |
| KPIs hero | **9** | 0 | **-9** |
| Tabs organização | **9** | 0 | **-9** |
| Densidade (1280px Larissa) | **8** | 5 | **-3** |
| Validação inline | **7** | 5 | **-2** |
| Estado completude info (cliente vencido, etc) | 4 | **8** | +4 |
| Cobertura features fiscais | 5 | **9** | +4 |
| **Score médio** | **7,8** | **3,5** | **-4,3** |

### Funcionalidades por tela

| Funcionalidade | Create Inertia | Edit Blade |
|---|---|---|
| Adicionar cliente (search) | ✅ Cowork ≥2 chars | ✅ Select2 legacy |
| Walk-in customer default | ✅ | ✅ |
| Data da venda input | ✅ datetime-local | ✅ datetime |
| Status (Final/Draft/Quotation) | ✅ select | ✅ select |
| Local select | ✅ | ✅ |
| Buscar produto (nome/SKU/cód barras) | ✅ Cowork autocomplete | ✅ legacy search |
| Adicionar IMEI/nº série | ❌ falta | ✅ campo extra |
| Editar quantidade +/- | ✅ component | ✅ buttons legacy |
| Editar preço unitário | ✅ inline | ✅ |
| Editar desconto (R$ ou %) | ⚠️ só R$ | ✅ toggle fixo/% |
| Deletar linha produto | ✅ | ✅ |
| Subtotal linha + total | ✅ | ✅ |
| Frete (custo + endereço + status) | ⚠️ colapsado | ✅ visível |
| Notas venda | ✅ textarea | ✅ |
| Notas equipe | ❌ falta | ✅ |
| Prazo pagamento | ⚠️ colapsado | ✅ visível |
| Comissionista | ⚠️ colapsado | ✅ visível |
| Inscrever-se (assinatura) | ❌ falta | ✅ checkbox |
| Anexar documento (upload .pdf/.csv/etc) | ❌ falta | ✅ Procurar btn |
| Responsável pela venda (user) | ⚠️ inferido | ✅ select avatar |
| Endereço cobrança ≠ entrega | ❌ falta | ✅ 2 campos |
| Cliente vencido alerta | ❌ falta | ✅ inline R$ 27.657,79 |
| Tax rate (impostos) | ⚠️ colapsado | ✅ |
| Inv scheme (regime fiscal) | ⚠️ colapsado | ✅ |
| Auto-save draft localStorage | ✅ exclusivo | ❌ |
| Tab bar (5 abas) | ✅ exclusivo | ❌ |
| 4 KPI hero | ✅ exclusivo | ❌ |
| Atalho `/` foca busca | ✅ exclusivo | ❌ |
| "Salvar e Imprimir" botão | ✅ exclusivo | ❌ |
| AppShellV2 sidebar dark | ✅ | ❌ |
| FieldError inline | ✅ | ⚠️ flash |

## Próximos passos sugeridos

### P0 — Migrar Edit pra Inertia (charter wave1-draft → live)

Esforço estimado (IA-pair ADR 0106 fator 10x): **~6-8h codáveis** (~1 dia útil).

Plano:
1. Criar `Edit.charter.md` v2 com mesmas regras do Create.charter.md
2. Refator `Edit.tsx` (já existe estrutura wave1-draft) — copiar pattern Create.tsx:
   - Tab bar (Dados / Produtos / Pagamento / Resumo / Mais opções)
   - 4 KPI hero
   - AppShellV2
   - Customer search Cowork
   - Product autocomplete + tabela linhas
3. Preservar features Blade que faltam no Create:
   - Cliente vencido alerta
   - Endereço cobrança ≠ entrega
   - Inscrever-se (assinatura)
   - Anexar documento upload
   - Responsável select avatar
   - Tabela produtos com IMEI/série inline
   - Comissionista visível (não colapsado)
4. Form deferred via `Inertia::defer()` (RUNBOOK-inertia-defer-pattern Tier 0)
5. Pest tests anti-regressão (manter Wave1EditBaseline/Inertia)
6. Migration silenciosa: branch Inertia ativa quando `request()->header('X-Inertia')`, Blade fallback preservado

### P1 — Paridade Create features faltantes

Esforço: ~3-4h codáveis.

Adicionar no `Sells/Create.tsx`:
- Cliente vencido alerta (consume `customer.dues_total` props)
- IMEI/nº série na linha produto (input opcional)
- Desconto toggle R$/% (não só R$)
- Anexar documento upload (já tem em Edit)

### P2 — Auto-save no Edit

Aplicar mesmo padrão `oimpresso.sells.{biz_id}.{user_id}.edit.{id}.draft` do Create.

## Refs

- [Sells/Create.charter.md](../../../resources/js/Pages/Sells/Create.charter.md)
- [Sells/Edit.charter.md](../../../resources/js/Pages/Sells/Edit.charter.md) (wave1-draft pendente)
- [Sells/Edit.tsx](../../../resources/js/Pages/Sells/Edit.tsx) (existe mas Inertia branch não roteado em prod)
- [edit-visual-comparison.md](edit-visual-comparison.md) (r1 anterior pré-paridade)
- [sells-create-visual-comparison.md](sells-create-visual-comparison.md) (r1 anterior)
- ADR 0149 screen-pattern reuse (Create pattern → Edit)

## Stack PRs sessão 2026-05-26

| PR | O que entregou | Em prod? |
|---|---|---|
| [#1638](https://github.com/wagnerra23/oimpresso.com/pull/1638) | bundle KB-9.75 raiz | ✅ |
| [#1639](https://github.com/wagnerra23/oimpresso.com/pull/1639) | snapshot Cowork | ✅ |
| [#1640](https://github.com/wagnerra23/oimpresso.com/pull/1640) | r4 visual-comparison | ✅ |
| [#1641](https://github.com/wagnerra23/oimpresso.com/pull/1641) | VdNextActionPanel + validações + glossário | ✅ |
| [#1644](https://github.com/wagnerra23/oimpresso.com/pull/1644) | Emit modals + Bulk + Saved view "Aguardando" | ✅ |
| [#1642](https://github.com/wagnerra23/oimpresso.com/pull/1642) | Recibo 80mm + Orçamento A4 | ✅ |
| [#1643](https://github.com/wagnerra23/oimpresso.com/pull/1643) | Cheat-sheet '?' + Toast hub | ✅ |
| [#1645](https://github.com/wagnerra23/oimpresso.com/pull/1645) | Link "Ver tela →" drawer→Show | ✅ |
| [#1646](https://github.com/wagnerra23/oimpresso.com/pull/1646) | Fix guard VdNextActionPanel mount | ✅ |

**Gap descoberto neste smoke (próximo PR):** Edit.tsx Inertia → live.
