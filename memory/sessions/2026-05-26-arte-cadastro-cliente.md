---
session: 2026-05-26 · arte-cadastro-cliente
agent: design-arte (estratégico — benchmark + nota + Capterra)
escopo: Cadastro/edição de Cliente PJ/PF — 3 superfícies (Drawer 760px, Edit.tsx, Show.tsx)
solicitante: Wagner (após PRs #1693/#1694/#1695 mergeados hoje)
modulo_alvo: Cliente (resources/js/Pages/Cliente/)
adrs_referencia: [0093, 0094, 0104, 0107, 0149, 0179, 0188 · UI-0013]
charter_ativo: Pages/Cliente/Index.charter.md v7
---

# Design Arte — Cadastro/edição de Cliente (Drawer + Edit + Show)

> Sessão estratégica zoom-out: benchmark vs SOTA 2026, nota 0-100 ponderada em 15 dimensões, top 5 gaps Capterra, recomendações acionáveis com path + linhas.
>
> **NÃO é critique tático** (cor de botão, microcopy isolado) — pra isso use plugin Anthropic `design:design-critique` / `design:ux-copy`.

---

## Fase 1 — Persona + jobs-to-be-done (research interno, sem invenção)

### Persona dupla curada (de `memory/reference/`)

**Daniela @ Martinho Caçambas** (biz=164, prospect ativo)
- **Quem é:** dona/gestora oficina de caçambas pesadas em Tubarão/SC. Demo realizada 2026-05-13, migração legacy WR2 Firebird concluída 2026-05-16 (91 caçambas + 9.988 contatos + 44k vendas). Concorre com HiSoft.
- **Contexto operacional:** PC de escritório (~1920px presumido — não confirmado em ficha), uso diário, gestão financeira pesada (aging real 90% fóssil pré-2020). Não-técnica.
- **Jobs-to-be-done (3-5 reais, ancorados em reclamações):**
  1. Cadastrar veículo da frota linkado ao cliente PJ (US-OFICINA-023 Equipamentos — Box/Elevadores/Locais). **Confirmado fonte:** PR #1694 hoje.
  2. Diferenciar 2 e-mails — vendedor (cotação) vs contador (NF-e). **Confirmado fonte:** PR #1695 hoje.
  3. Guardar 3º telefone pra recados (familiar/secretária). **Confirmado fonte:** PR #1695 hoje.
  4. Ver histórico financeiro + aging do cliente direto na ficha (R$ 4M aberto · bombshell). **Confirmado:** Show.tsx tabs Extrato/Pagamentos.
  5. Editar dados básicos (CNPJ/endereço) sem CNPJ vir vazio. **Confirmado fonte:** PR #1693 hoje (bug data integrity).
- **3 fricções conhecidas:**
  - Edit Inertia carregava CNPJ vazio + save quebrava (#1693 — gravidade A · funcional travado).
  - Cadastrar veículos exigia abrir OficinaAuto separado (#1694 — gravidade B · troca de contexto).
  - 1 telefone + 1 email = subjuga papéis distintos (vendedor ≠ contador) (#1695 — gravidade B).

**Larissa @ ROTA LIVRE** (biz=4, cliente piloto 99% volume)
- **Quem é:** dona vestuário Termas do Gravatal/SC, monitor 1280px, balcão, não-técnica. Decorou shift +3h `format_date` (ADR 0066). Cliente típico PF de bairro (Jociane, Edna, Guilherme…).
- **Contexto operacional:** balcão de loja, 1280px (telinha — tela `/sells` com 21 colunas era inutilizável até 2026-04-24). Sensível a mudança visual.
- **Jobs-to-be-done:**
  1. Cadastrar PF rápido no balcão (nome + CPF + tel) sem 38 campos vazios assustando.
  2. Buscar cliente recorrente pelo nome/tel pra venda balcão.
  3. **Não usar** veículos/frota/3º telefone/2 emails/IE/SUFRAMA/regime — irrelevante pro vestuário PF.
- **3 fricções conhecidas:**
  - Monitor 1280px com drawer 760px deixa só ~520px de coluna principal — qualquer poluição extra estoura.
  - Cadastro com 8 tabs no drawer + 4 seções no Edit + 8 tabs no Show = 20+ superfícies de "onde edito".
  - Mudança visual = regressão percebida (decorou estado anterior).

**Conflito persona crítico:** Daniela quer MAIS campos (frota, 3º tel, 2 emails). Larissa quer MENOS visual. **Mesmo ContatoTab** atende as duas hoje. Solução não pode ser "esconder do Daniela" nem "mostrar pro Larissa" — tem que ser **adaptativa** ou **disclosure progressivo**.

### TODOs Wagner curate (info não encontrada)

- [ ] **biz_id Martinho confirmado = 164?** Ficha diz "biz=?" no commit mas refmd canon diz 164. Verificar.
- [ ] **Resolução monitor Daniela** — não está em `memory/reference/cliente-martinho-cacambas.md`. Assumi 1920px desktop padrão escritório. Confirmar próxima visita.
- [ ] **Frequência de uso do drawer** — quantas vezes/dia Larissa abre cadastro? Daniela? Sem session log com métrica. Não invento.
- [ ] **Pessoas de contato vs 3º telefone overlap** — drawer tem tab "OSs > Pessoas" + ContatoTab tem 3º tel. São conceitos diferentes ou duplicação? Não documentado claramente.

---

## Fase 2 — Pesquisa SOTA 2026 (5 líderes globais + 4 concorrentes BR)

### Líderes globais

**1. HubSpot CRM (referência B2B 2026)**
- **Quem:** CRM líder global, milhões de SMBs.
- **Padrões 2026:** 3 colunas no record (esquerda = primary/secondary properties · centro = activity timeline · direita = associations). **"Show only filled fields" toggle** + reorder + customize via UI (não código). Custom fields agrupados em "field groups".
- **Por que referência:** dominância de mercado + customização real pelo usuário não-dev. Resolveu há anos o trade-off "muito campo = poluído" via toggle "filled only".

**2. Pipedrive (referência field-density)**
- **Quem:** CRM PME, design opinionated.
- **Padrões 2026:** Sidebar do detail editável inline com seção reordenável + "Customize fields" por seção + **toggle "Show only filled fields"** (ícone três linhas piramidal). Bulk edit via ícone lápis no header da seção (multi-campo de uma vez).
- **Por que referência:** PME-first como oimpresso, lida com 50+ campos sem virar formulário labiríntico. Não pré-renderiza vazio.

**3. Notion (referência side-peek)**
- **Quem:** flexível, modular, ~100M users.
- **Padrões 2026:** Database row → **side peek** (drawer lateral). Mesma row pode abrir como "side peek / center peek / full page" — **escolha do usuário**, não dupla rota. Edit acontece **no mesmo lugar onde lê** (1 superfície, 1 mode-switch).
- **Por que referência:** Resolveu "drawer vs page" virando **escolha do usuário, mesmo doc**. Não duas implementações.

**4. Linear (referência densidade + atalhos)**
- **Quem:** dev-tools, opinionated, dark-mode-first.
- **Padrões 2026:** Issue panel lateral com autosave invisível (`Cmd+S` opcional, não obrigatório), atalhos por todo lado, microcopy seca. Edit acontece in-place — sem "página de edição separada".
- **Por que referência:** Já é o paradigma do drawer 760 do oimpresso (Wagner aprovou 2026-05-21). Validar consistência.

**5. Shopify Admin (referência varejo Larissa-equivalente)**
- **Quem:** plataforma de varejo, 4M+ stores, persona equivalente Larissa.
- **Padrões 2026:** **Customer profile = 1 página única** com seções colapsáveis (Overview, Contact, Addresses, Marketing, Tags, Notes, Timeline). **Inline edit por seção via "Manage > Edit"** — nada de página `/edit` separada. Múltiplos endereços suportados nativamente; 1 phone por endereço (não suportam N phones diferenciados).
- **Por que referência:** Persona de varejo idêntica à Larissa. Confirmação que **dupla superfície (drawer + edit page) é anti-padrão de varejo**.

### Concorrentes BR ERP

**6. Bling ERP**
- **Padrões:** Cadastro tabbed (Geral / Endereço / Contato / Fiscal / Financeiro). Múltiplos tipos no mesmo contato (cliente+fornecedor+transportador — paralelo ao ADR 0188 multi-type oimpresso). Endereço com **abas Geral + Cobrança** separadas. Campos customizados via config.
- **Gap vs SOTA:** ainda é form denso 90s. Sem disclosure progressivo. Mas é o que Daniela/Larissa já conhecem.

**7. Omie**
- **Padrões:** "Atomic Search" — só CNPJ/CPF/telefone preenche automaticamente os outros campos (BrasilAPI equivalente). Já existe no oimpresso (lookup CNPJ Wave 1419).
- **Gap vs SOTA:** layout ainda denso, sem toggle filled-only.

**8. Tiny ERP**
- **Padrões:** Cadastro denso plano, 1 tela longa rolando. Sem drawer/peek.

**9. Conta Azul**
- **Padrões:** Sidebar customer com edit inline + módulo financeiro lateral.

### Padrões emergentes 2026 consolidados (referências cruzadas)

| Padrão SOTA 2026 | Fonte | Estado oimpresso |
|---|---|---|
| **Toggle "Show only filled fields"** | Pipedrive, HubSpot | ❌ Ausente |
| **"Add another phone/email" button** (não N campos vazios fixos) | NN/g, Zuko, ventureharbour | ❌ Ausente (3 fixos visíveis) |
| **1 superfície edit** (drawer OU page, não ambos) | Notion, Linear, Shopify | ❌ Drawer + Edit + Show coexistem |
| **Autosave debounced + sem botão Save explícito** | Linear, Notion drawer | 🟡 Parcial (drawer sim, Edit não) |
| **Customizar quais campos aparecer** (sem dev) | HubSpot, Pipedrive | ❌ Ausente |
| **Empty state com CTA orientado** | Linear, Stripe | 🟡 Parcial |
| **Single column form** mobile-first | NN/g, ventureharbour 2026 | 🟡 Drawer sim, Edit também (max-w-3xl) |
| **Single source of truth** | cxtoday.com 2026 | ❌ Drawer e Edit divergem em campos (Edit tem `regime/suframa`, Drawer não) |

---

## Fase 3 — Avaliação em 15 dimensões UX/UI (cada 0-10)

> Avaliação **honesta**. Cliente piloto Larissa (1280px) é benchmark dominante.
> Onde Drawer e Edit divergem, anoto nota separada `[D]` vs `[E]` vs `[S]` (Show).

### Tabela comparativa

| # | Dimensão | Estado-da-arte (SOTA) | oimpresso (atual) | Distância | Nota /10 |
|---|---|---|---|---|---|
| **1** | **Hierarquia visual** | Linear: grid 12 + 8pt, agrupamento Gestalt forte | [D] 8 tabs horizontais drawer + sub-tabs verticais OSs · [E] 4 seções cards · [S] 8 tabs + sidebar | média | **6** |
| **2** | **Densidade informacional** | Pipedrive: filled-only toggle, ~12 campos visíveis default | ContatoTab 5 campos fixos (com 2 quase nunca preenchidos: site, 3º tel) · IdentificacaoTab 9-10 campos · Show.tsx 8 tabs simultâneas | longa | **5** |
| **3** | **Navegação primária** | Notion side peek + escolha usuário · Linear breadcrumb consistente | Drawer abre clicando row · Edit é rota separada `/contacts/{id}/edit` · Show é rota separada `/cliente/{id}` · **3 superfícies pro mesmo cliente** | longa | **4** |
| **4** | **Sistema de design** | Shadcn + Tailwind tokens consistentes | Sólido — tokens cor/tipo/espaço Constituição UI v2 (ADR UI-0013), shadcn-first, lucide-icons | curta | **9** |
| **5** | **Microcopy PT-BR** | Linear: seca, ativa, sem jargon | "Telefone alternativo (opcional)" · "Telefone 3 (opcional · recados)" · "Sobrenome" · "Tax number (legado UPOS)" — **Edit.tsx tem jargon dev exposto** | média | **6** |
| **6** | **Empty states** | Linear/Notion: ilustração + CTA orientado | Avatar com 1ª letra · Show "Documento não informado" — sem CTA primário. Drawer tab vazia mostra "—" só | média | **5** |
| **7** | **Loading + skeleton** | Vercel: skeleton específico por componente | Inertia::defer + skeletons (StatsSkeleton, TabSkeleton) bem feitos | curta | **8** |
| **8** | **Error UX** | Linear: inline + retry contextual | FieldStatus inline `AlertCircle + msg` por campo · rollback otimista 4xx/5xx · 422/403/404 mapeados | curta | **8** |
| **9** | **Atalhos teclado** | Linear: tudo via teclado, ⌘K paleta | Index tem ⌘K + J/K + Enter (KB-9.75 Slice A) · Drawer **NÃO tem Cmd+S** explícito (autosave) · Edit também não tem Cmd+Enter | média | **6** |
| **10** | **Mobile/touch 1280px** | Shopify: single-col stacking | Drawer 760 + AppShell 240 = 520px main em 1280 (caber sem scroll horizontal Pest test) · Edit max-w-3xl (~768px) cabe · Show.tsx max-w-6xl pode estourar | curta | **7** |
| **11** | **Acessibilidade WCAG 2.1 AA** | Material 3: AAA auto | aria-invalid, aria-describedby, role=radiogroup, htmlFor — bem feito · contrast tokens shadcn 4.5:1 default · focus visible | curta | **8** |
| **12** | **Feedback ações** | Linear: optimistic + undo | FieldStatus "Salvando…/Salvo" inline + rollback otimista · sem undo · toast só pra erros globais | média | **7** |
| **13** | **Formulários** | NN/g: single col, validação inline, autosave drafting | [D] autosave debounced 800ms blur ótimo · [E] save explícito tradicional · **inconsistência drawer ≠ edit** · sem disclosure progressivo · 3º tel/2 emails extras sempre visíveis (NN/g antipattern) | longa | **4** |
| **14** | **Dataviz** | Stripe: clarity + semantic | StatCard with danger condition (rose) + tabular-nums + Risco card · Show com 4 stats financeiros — bom | curta | **8** |
| **15** | **Onboarding/disclosure progressivo** | Linear: empty hint + tour discreto | Cliente novo abre 8 tabs vazias · sem "começar pelo básico" · sem "+ adicionar telefone" · sem distinção PF/PJ que esconda IE/SUFRAMA pra PF | longa | **3** |

### Cálculo nota ponderada

Pesos (calibração PME BR não-técnica):
- **Peso 3** (primeira impressão): Dim 1, 2, 3 → notas 6, 5, 4 = 15 × 3 = 45
- **Peso 2** (confiabilidade): Dim 4, 5, 6, 7, 8 → 9, 6, 5, 8, 8 = 36 × 2 = 72
- **Peso 1** (polish): Dim 9, 10, 11, 12, 13, 14, 15 → 6, 7, 8, 7, 4, 8, 3 = 43 × 1 = 43

`Σ(dim × peso) = 45 + 72 + 43 = 160`
`Σ(pesos) = 3×3 + 2×5 + 1×7 = 9 + 10 + 7 = 26`
`Média = 160 / 26 = 6.15`
**Nota oimpresso atual: 6.15 × 10 = `61/100`**

### Referências comparativas

| Player | Nota estimada (mesma rubrica) |
|---|---|
| **HubSpot Contact 2026** | 88/100 (toggle filled-only + customize + 3 col + activity) |
| **Linear issue panel** | 92/100 (densidade + atalhos + autosave invisível) |
| **Notion side peek** | 85/100 (escolha usuário + 1 superfície edit) |
| **Shopify customer** | 84/100 (1 página, inline edit, persona varejo) |
| **Bling ERP cliente** | 52/100 (tabs densos sem disclosure) |
| **Tiny ERP cliente** | 48/100 (form plano longo) |

```
NOTA OIMPRESSO ATUAL (Cliente/cadastrar+editar): 61/100
NOTA REFERÊNCIA TOP (Linear issue panel):       92/100
NOTA REFERÊNCIA BR (Bling cliente):              52/100

Gap pro topo: -31 pts. Causa principal: 3 superfícies pro mesmo cadastro (Drawer + Edit + Show) + campos opcionais raramente preenchidos sempre visíveis sem disclosure progressivo.
Gap pro BR: +9 pts (já à frente do mercado BR).
```

---

## Fase 4 — Capterra-Design + Recomendações concretas

### Top 5 gaps priorizados (impacto × esforço)

| # | Gap | Impacto | Esforço | Prio | Capterra |
|---|---|---|---|---|---|
| **G1** | **3 superfícies pro mesmo cadastro** (Drawer 760 + Edit.tsx full-page + Show.tsx full-page) — usuário não sabe "onde eu edito" | 10 | M (deprecate Edit, consolidar tudo no drawer) | **P0** | ❌ |
| **G2** | **Campos opcionais raramente preenchidos sempre visíveis** — 3º tel, 2 emails extras, site, IE pra PF, SUFRAMA pra cliente sem IE | 9 | S (esconder atrás de "+ adicionar X") | **P0** | ❌ |
| **G3** | **Inconsistência autosave (drawer) vs save explícito (Edit)** — drawer salva automágico, Edit não | 7 | M (matar Edit ou aplicar autosave nele) | **P1** | 🟡 |
| **G4** | **Empty state cliente novo com 8 tabs vazias** assusta Larissa (varejo PF balcão) | 8 | S (modo "rápido" PF: 4 campos · tabs aparecem on-demand) | **P1** | 🟡 |
| **G5** | **Show.tsx → click Editar leva pra Edit.tsx full-page** que é fluxo redundante ao drawer (charter Show.tsx está superseded mas ainda existe em prod) | 8 | M (Show.tsx redireciona pra `/cliente?contact_id={id}` abrindo drawer) | **P1** | ❌ |

### Recomendações CONCRETAS (path + linhas + diff conceitual)

#### REC-1 (G1+G3+G5) — Consolidar em 1 superfície: drawer 760 é canônico, Edit.tsx vira redirect

**Por que:** Notion/Linear/Shopify confirmam que 2 lugares pra editar o mesmo dado é anti-padrão. Charter `Show.charter.md` JÁ está `superseded` por `Index.charter.md` v6 mas `Show.tsx` ainda existe e botão "Editar" ainda manda pra `/contacts/{id}/edit`. Charter Edit ainda `status: draft` (nunca foi formalizado).

**Como (acionável hoje):**

1. **Show.tsx linha 165** — botão "Editar" passa a abrir drawer:
   ```diff
   - <a href={`/contacts/${contact.id}/edit`}>
   + <a href={`/cliente?contact_id=${contact.id}&tab=identificacao`}>
   ```

2. **Edit.tsx** — adicionar redirect server-side no controller `ContactController::edit()` quando `config('mwart.cliente_drawer.enabled')` true:
   - Redirect 302 → `/cliente?contact_id={id}&tab=identificacao`
   - Charter `Edit.charter.md` ganha `status: deprecated` + `superseded_by: Index.charter.md v6`

3. **Show.tsx** inteira — Wagner aprova manter (paridade Blade Wave Final 2026-05-21) ou redirect também:
   - Opção A: Mantém Show.tsx full-page (vista de leitura rica · risk-card · OSs paginados longos) + "Editar" abre drawer pra mutate
   - Opção B (Notion-like): Show.tsx vira drawer também — 1 só superfície

**Esforço:** ~50 linhas. 1 PR. Roll-out feature-flag `mwart.edit_redirect_to_drawer.enabled` 1 cliente/vez.

#### REC-2 (G2) — Disclosure progressivo nos campos opcionais do ContatoTab

**Por que:** NN/g 2026 + Zuko + ventureharbour consolidam: campos opcionais raramente preenchidos = "+ Adicionar X" button, não input vazio default. Larissa quer 1 telefone + 1 email. Daniela quer 3 telefones + 3 emails. **Mesmo componente, comportamento progressivo.**

**Layout proposto (path: `resources/js/Pages/Cliente/_drawer/ContatoTab.tsx` linhas 186-340):**

```
ANTES (atual prod main pós-PR #1695):
┌──────────────────────────────────────────────────────┐
│ Telefone principal      │ Telefone alternativo       │
├──────────────────────────────────────────────────────┤
│ Telefone 3 (opcional · recados)         [col-span-2] │
├──────────────────────────────────────────────────────┤
│ E-mail                                  [col-span-2] │
├──────────────────────────────────────────────────────┤
│ E-mail comercial       │ E-mail NF-e                 │
├──────────────────────────────────────────────────────┤
│ Site (opcional)                         [col-span-2] │
├──────────────────────────────────────────────────────┤
│ Canal preferido: WhatsApp Email Tel Presencial       │
└──────────────────────────────────────────────────────┘
↑ 7 campos visíveis sempre. Larissa vê 5 campos vazios assustadores.

DEPOIS (recomendação SOTA):
┌──────────────────────────────────────────────────────┐
│ Telefone principal                      [col-span-2] │
│   ↓ se preenchido OU se já tinha tel2:               │
│   [+ Adicionar telefone alternativo]                 │
├──────────────────────────────────────────────────────┤
│ E-mail                                  [col-span-2] │
│   ↓ se preenchido OU se já tinha email_billing/nfe:  │
│   [+ Adicionar e-mail comercial (vendedor)]          │
│   [+ Adicionar e-mail NF-e (contador)]               │
├──────────────────────────────────────────────────────┤
│ Site (opcional)                         [col-span-2] │
│   ↑ continuar opcional — quase ninguém preenche      │
│     candidato a esconder atrás de "Mais campos"      │
├──────────────────────────────────────────────────────┤
│ Canal preferido: WhatsApp Email Tel Presencial       │
└──────────────────────────────────────────────────────┘
↑ Larissa vê 3 campos. Daniela clica "+ adicionar e-mail NF-e" e ganha o campo.

REGRA: se ANY campo opcional já tem valor → mostra todos do mesmo grupo
       (Daniela já cadastrada: vê os 3 telefones expandidos)
```

**Microcopy específico (sem jargon, ancorado em job real):**
- "+ Adicionar telefone alternativo" (não "tel2")
- "+ Adicionar e-mail do comercial (cotação)" (Daniela entende)
- "+ Adicionar e-mail do contador (NF-e)" (Daniela entende)
- Site → mover pra grupo "Mais informações" (collapsed por default · aparece se preenchido)

**Esforço:** ~80 linhas em ContatoTab.tsx. 1 PR. Sem mudança backend (campos já existem).

#### REC-3 (G4) — Modo "Cadastro rápido PF" na criação

**Por que:** Larissa cadastra Cliente Balcão (PF, 30s, balcão lotado). Drawer 760 com 8 tabs vazias = 100ms olhando "onde clico". Bling/Omie têm form denso mas plano. SOTA = wizard 1-step com expansão on-demand.

**Como:**
- **Index.tsx botão "Novo Cliente"** → abre drawer no tab Identificação **com toggle PF/PJ default = PF** (vestuário Larissa é 99% PF — viés calibrado).
- **Quando PF selecionado:** esconder automaticamente tabs Comercial (limite crédito), tabs OSs (vazio), tab IA (cliente novo, sem histórico pra analisar) — só expor: **Identificação · Contato · Endereço**. Outras tabs aparecem com ícone grayed-out "Disponível após primeira venda" tooltip.
- **Quando PJ selecionado:** todas 8 tabs visíveis.

**Esforço:** ~40 linhas em `Index.tsx` + props condicionais. 1 PR.

#### REC-4 (G2 evolução) — Toggle "Mostrar só campos preenchidos" no Show.tsx

**Por que:** Pipedrive + HubSpot 2026. Daniela ao revisitar cadastro Martinho (cliente migrado com SUFRAMA, IE, regime preenchidos) vê tudo. Larissa abrindo PF Jociane vê 90% "Não informado" — ruído visual.

**Como:**
- **Show.tsx sidebar `DadosFiscaisBRCard`** (linha 389) — já tem `if (!hasAnyField) return null` — bom. Mas no `ContactRow` (linha 367) mostra `value ?? '—'`. Adicionar prop `showOnlyFilled: boolean` global toggle no header da sidebar.
- Toggle persistido em `localStorage` por cliente.

**Esforço:** ~20 linhas. 1 PR menor.

#### REC-5 (G1 extensão) — Single source of truth nos campos BR

**Por que:** Edit.tsx (linhas 99-108) edita `regime`, `suframa`, `indicador_ie`, `consumidor_final`, `contribuinte` — Drawer NÃO tem esses campos. Daniela vai ter cliente que precisa de regime preenchido — onde edita? **Hoje: SÓ no Edit.tsx full-page (que é fluxo redundante).** Se REC-1 redireciona Edit→Drawer, esses campos somem.

**Como:**
- Adicionar grupo "Dados fiscais avançados" no `IdentificacaoTab.tsx` (collapsed por default · expande se PJ + qualquer campo preenchido) com: regime, indicador_ie, suframa, consumidor_final, contribuinte.
- Cobertura backend: `ClienteAutosaveController` já tem `PATCH /cliente/{id}/identificacao` — adicionar esses campos no validator.

**Esforço:** ~120 linhas (Tab + controller + migration validator). 1 PR. **BLOQUEIA REC-1** — se redirecionar Edit→Drawer sem isso, Daniela perde acesso a regime/suframa.

### Refs/screenshots dos concorrentes consultados

1. **Pipedrive Detail View Sidebar** — [support.pipedrive.com/en/article/detail-view-sidebar](https://support.pipedrive.com/en/article/detail-view-sidebar) — "Show only filled fields" toggle icon (três linhas piramidal invertida). Cada seção tem ícone "..." → "Customize fields". Drag-to-reorder. Bulk edit lápis.

2. **HubSpot Contact Record 2026** — [knowledge.hubspot.com/records/understand-the-default-record-layout](https://knowledge.hubspot.com/records/understand-the-default-record-layout?region=france) — 3 col layout: left primary/secondary properties + Information card · middle Overview tab com highlighted property values + Activities tab timeline · right associations (companies/deals/leads).

3. **Notion Side Peek vs Full Page** — [notion.com/help/intro-to-databases](https://www.notion.com/help/intro-to-databases) + [makeuseof.com/change-notion-side-peek-setting](https://www.makeuseof.com/change-notion-side-peek-setting/) — usuário escolhe modo (side peek · center peek · full page) via Layout menu. Mesmo doc, mesma edição, escolha de viewport.

4. **Shopify Customer Profile** — [help.shopify.com/en/manual/customers/manage-customers](https://help.shopify.com/en/manual/customers/manage-customers) — 1 página única + seções colapsáveis (Overview / Contact / Addresses N / Marketing / Tags / Notes / Timeline). Inline "Manage > Edit contact information". 1 phone por endereço (não suportam multi-phone diferenciado — confirma que oimpresso indo além do varejo SOTA é OK pra Daniela).

5. **NN/g Accordions on Desktop** — [nngroup.com/articles/accordions-on-desktop](https://www.nngroup.com/articles/accordions-on-desktop/) — accordions OK pra fluxo linear (checkout). **Ruim** pra forms com muitos campos opcionais onde user precisa da maioria. Mesa-cabeceira: usar disclosure progressivo "+ adicionar X" em vez de accordion gigante.

6. **Zuko Phone Field Optimization** — [zuko.io/blog/optimizing-the-phone-number-field-on-forms](https://www.zuko.io/blog/optimizing-the-phone-number-field-on-forms) — phone tem maior abandonment rate. "Asking twice = trouble". Implícito: 3 campos de phone simultâneos = pior ainda.

---

## CAPTERRA-DESIGN-FICHA (formato canônico ADR 0089)

| ID | Dimensão | HubSpot | Pipedrive | Notion | Linear | Shopify | **oimpresso** |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| D-001 | 1 superfície edit (não dupla) | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ (Drawer+Edit+Show) |
| D-002 | Toggle "show only filled" | ✅ | ✅ | 🟡 | 🟡 | ❌ | ❌ |
| D-003 | "+ Add another phone/email" | ✅ | ✅ | ✅ | n/a | ❌ | ❌ |
| D-004 | Autosave invisível drawer | 🟡 | 🟡 | ✅ | ✅ | 🟡 | ✅ (drawer) ❌ (Edit) |
| D-005 | Custom fields sem dev | ✅ | ✅ | ✅ | 🟡 | 🟡 | ❌ |
| D-006 | Empty state CTA | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 |
| D-007 | PF/PJ form adaptativo | ✅ | ✅ | n/a | n/a | ✅ | 🟡 (toggle visual, não esconde tabs) |
| D-008 | Multi-tenant invisível ao user | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (Tier 0 ADR 0093) |
| D-009 | Atalhos teclado paleta ⌘K | ✅ | 🟡 | ✅ | ✅ | 🟡 | ✅ (Index) 🟡 (Drawer) |
| D-010 | Microcopy sem jargon | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 ("Tax number legacy UPOS" expõe dev) |

**Top 3 P0 a fechar:**
1. ❌ D-001 (1 superfície edit) → REC-1
2. ❌ D-003 ("+ adicionar phone/email") → REC-2
3. ❌ D-005 (toggle filled-only) + D-007 (PF adaptativo) → REC-3 + REC-4

---

## Decisão Wagner — qual REC tocar primeiro?

| REC | Linhas | PRs | Risk | Persona ganha | Ordem sugerida |
|---|---|---|---|---|---|
| REC-2 (disclosure progressivo ContatoTab) | ~80 | 1 | baixo | Larissa ⬆⬆ Daniela = | **1º** |
| REC-3 (modo rápido PF) | ~40 | 1 | baixo | Larissa ⬆⬆ Daniela = | **2º** |
| REC-4 (toggle filled-only Show) | ~20 | 1 | baixo | Larissa ⬆ Daniela ⬆ | **3º** |
| REC-5 (campos BR avançados no drawer) | ~120 | 1 | médio | Daniela ⬆ Larissa = | **4º** (bloqueia REC-1) |
| REC-1 (deprecar Edit.tsx → redirect drawer) | ~50 | 1 | médio | ambas ⬆ | **5º** (depois REC-5) |

**Caminho mínimo pra subir de 61 → 75:** REC-2 + REC-3 + REC-4 (~140 linhas, 3 PRs pequenos). Caminho completo pra 85+: + REC-5 + REC-1 (~310 linhas total).

**Caminho de máximo risco (não recomendado primeiro):** REC-1 sem REC-5 = Daniela perde acesso a regime/suframa.

---

## Refs canônicas

- Charter ativo: `resources/js/Pages/Cliente/Index.charter.md` v7
- Charter superseded: `Show.charter.md` v2 + (a propor) `Edit.charter.md` deprecated
- ADR mãe drawer: [0179](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
- ADR multi-type: [0188](../decisions/0188-contacts-multi-type-flag-aditiva.md)
- Constituição UI v2: [UI-0013](../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- Protótipo Cowork: `prototipo-ui/prototipos/clientes/`
- Cliente piloto Larissa: `memory/reference/cliente-rotalivre.md`
- Cliente piloto Daniela: `memory/reference/cliente-martinho-cacambas.md`

## Sources web SOTA

- [Pipedrive Detail View Sidebar](https://support.pipedrive.com/en/article/detail-view-sidebar)
- [Pipedrive Contact Detail View](https://support.pipedrive.com/en/article/contact-detail-view)
- [HubSpot Default Record Layout](https://knowledge.hubspot.com/records/understand-the-default-record-layout?region=france)
- [HubSpot Customize Properties Records](https://knowledge.hubspot.com/object-settings/customize-properties-in-record-sections)
- [Notion Side Peek Help](https://www.notion.com/help/intro-to-databases)
- [Notion Switch Peek Modes](https://templatesfornotion.com/newsletters/notion-switch-peek-modes)
- [Linear Edit Issues Docs](https://linear.app/docs/editing-issues)
- [Shopify Manage Customers](https://help.shopify.com/en/manual/customers/manage-customers)
- [Bling Cadastrar Clientes](https://ajuda.bling.com.br/hc/pt-br/articles/360035913053-Cadastrar-clientes-fornecedores-e-transportadoras)
- [Omie Cadastro Contas](https://www.omie.com.br/funcionalidades/cadastro-de-contas/)
- [NN/g Accordions on Desktop](https://www.nngroup.com/articles/accordions-on-desktop/)
- [NN/g Tabs vs Accordions](https://www.nngroup.com/videos/tabs-vs-accordions/)
- [Baymard Accordion UX Pitfalls](https://baymard.com/blog/accordion-and-tab-design)
- [Zuko Phone Field Optimization](https://www.zuko.io/blog/optimizing-the-phone-number-field-on-forms)
- [UXPin Progressive Disclosure 2026](https://www.uxpin.com/studio/blog/what-is-progressive-disclosure/)
- [Venture Harbour 58 Form Best Practices 2026](https://ventureharbour.com/form-design-best-practices/)
- [Damian Wajer Autosave vs Explicit](https://www.damianwajer.com/blog/autosave/)
- [cxtoday CRM Source of Truth](https://www.cxtoday.com/crm/your-crm-isnt-a-source-of-truth-its-a-system-for-scaling-customer-confusion/)
- [SaaSFrame Side Panel Examples](https://www.saasframe.io/patterns/side-panel)
- [Evil Martians Phone Inputs Guide](https://evilmartians.com/chronicles/phone-inputs-and-you-the-designers-essential-ui-guide)
