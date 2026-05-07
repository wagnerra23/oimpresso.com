# BENCHMARKS — Catálogo de SaaS de referência por categoria de tela

> Carregado em **Modo B (Audit)** quando precisar avaliar "essa tela está no estado da arte de mercado?" e em **Modo C (Compare)** pra ancorar a comparação em pattern externo. Não carregado em Modo A (Generate) por default — runbook foca no canon interno.
>
> Append-only. Cada tela nova do ERP que ganhe canon vira referência aqui.

## Como usar

1. Identificar a **categoria** da tela (inbox conversacional, master-detail, dashboard, form/CRUD, settings, listagem operacional)
2. Pegar os 5-7 patterns canônicos da categoria + a UX move chave de cada SaaS de referência
3. No relatório de audit, incluir bloco `## Benchmark` listando quais patterns essa tela implementa, quais não implementa, e quais são intencionalmente diferentes (com link pra ADR de exceção)

Tradeoff: benchmarks são da web em **Q2 2026**. Estado da arte muda. Re-avaliar a cada 6 meses (ver "Última atualização" no rodapé).

---

## 1. Inbox conversacional

**SaaS de referência:** Front, Intercom, Crisp, WhatsApp Web, Missive

**Patterns canônicos:**

| # | Pattern | Implementação típica |
|---|---|---|
| 1 | **3-painéis split-view** | Lista esq (320-400px) + Thread centro (1fr) + Sidebar contexto dir (280-340px) |
| 2 | **Search debounced server-side** | 200-300ms debounce; busca por any-word em nome+telefone+body |
| 3 | **Real-time presence + typing** | WebSocket subscribe; badge `● online` + "fulano está digitando…" |
| 4 | **Atalhos vim-style** | J/K navega lista, E resolve, A snooze, R reply, /` foca search |
| 5 | **Mensagens agrupadas por dia + tail no último de mesmo lado** | "Hoje / Ontem / DD MMM" + bubble corner suave só na borda inferior do último |
| 6 | **Empty state com CTA** | Não só "💬 vazio" — convida ação ("Envie a primeira mensagem", link config) |
| 7 | **Sidebar contextual cross-módulo** | Cliente CRM, OS/ticket relacionado, histórico de eventos, anexos compartilhados |
| 8 | **Drafts auto-save** | Composer salva rascunho a cada 2s em localStorage; recupera ao reabrir |
| 9 | **Ações inline na lista (hover)** | Atribuir / arquivar / marcar lida sem abrir thread |
| 10 | **Status visual por status badge + dot color** | Aberta/Aguardando/Resolvida/Arquivada com cores semânticas constantes |

**UX move chave:** **conversa como entidade central**, não tela. Tudo ao redor (CRM, OS, FIN, atalhos) orbita a conversa em foco.

**Telas oimpresso na categoria:**
- [Whatsapp/Conversations/Index](../../resources/js/Pages/Whatsapp/Conversations/Index.tsx) — implementa 1, 2, 3, 5, 7 (parcial); falta 4, 6, 8, 9
- [Copiloto/Cockpit](../../resources/js/Pages/Copiloto/Cockpit.tsx) — piloto; implementa 1, 4, 7; falta 2, 3, 5, 6, 8, 9, 10

---

## 2. Master-detail (lista + viewer)

**SaaS de referência:** Linear, Asana, GitHub Issues, Jira, Notion DB

**Patterns canônicos:**

| # | Pattern | Implementação típica |
|---|---|---|
| 1 | **Lista esq + viewer dir** | 320-400px lista com row densa; viewer 1fr com toolbar |
| 2 | **Filtros + savedviews** | Tabs/pills com contadores ao lado; "All / Mine / Done / @me mentioned" |
| 3 | **Bulk actions** | Checkbox por linha + barra "X items selected: assign / done / delete" |
| 4 | **Atalhos vim** | J/K, E (done), A (snooze), C (comment), N (new), `/` (search) |
| 5 | **Keyboard navigation total** | Cmd+K busca global; Cmd+Enter envia comment; Esc fecha viewer |
| 6 | **Optimistic updates** | UI atualiza antes do servidor confirmar; rollback se falhar |
| 7 | **Quick add inline** | "+ New issue" no rodapé da lista sem abrir modal |
| 8 | **Drag-to-reorder** | Reordenar prioridade arrastando |
| 9 | **Comments threaded + mentions** | @username notifica; markdown supported |
| 10 | **Activity log timeline** | "fulano alterou status há 3min", "fulana atribuiu pra você" |

**UX move chave:** **velocidade do power user**. Navegação 100% teclado, optimistic updates, bulk actions — Linear é a referência mais alta hoje.

**Telas oimpresso na categoria:**
- (Repair Inbox de tarefas — futuro, ADR 0039 §2 menciona)
- (Project Inbox — futuro)

---

## 3. Dashboard / KPI overview

**SaaS de referência:** Mixpanel, Amplitude, Vercel Analytics, Stripe Dashboard

**Patterns canônicos:**

| # | Pattern | Implementação típica |
|---|---|---|
| 1 | **KPI cards row no topo** | 3-5 métricas grandes com delta (▲5.2% vs período anterior) |
| 2 | **Time range picker global** | "Hoje / 7 dias / 30 dias / Custom" — afeta todos cards |
| 3 | **Charts agrupáveis por dimensão** | Dropdown "Por canal / Por produto / Por região" |
| 4 | **Drill-down em qualquer card** | Click no card → tela detalhada da métrica |
| 5 | **Segment filter persistente** | "Apenas clientes Pro" — chip removível |
| 6 | **Export PDF/CSV** | Botão dedicated na toolbar |
| 7 | **Empty state pra primeira semana** | "Aguardando 7 dias de dados pra calcular delta" |
| 8 | **Refresh manual + auto-refresh** | Botão `↻` + auto cada 30s opcional |

**UX move chave:** **decisão em <10s**. Top 3 KPIs visíveis sem scroll, delta vs período colorido (verde/vermelho), drill-down 1 clique.

**Telas oimpresso na categoria:**
- (Financeiro Dashboard — futuro)
- (Repair Dashboard — futuro)

---

## 4. Form / CRUD (criar/editar entidade)

**SaaS de referência:** Salesforce, HubSpot, Pipedrive, Notion forms

**Patterns canônicos:**

| # | Pattern | Implementação típica |
|---|---|---|
| 1 | **Auto-save por field** | "Salvo" indicator pisca 2s depois de blur; sem botão "Salvar" pra rascunho |
| 2 | **Side-by-side preview** | Form esq + preview live dir (NFe, contrato, etc) |
| 3 | **Validação inline** | Erro aparece no blur do campo, não no submit |
| 4 | **Required marker discreto** | `*` antes do label, não asterisco gigante |
| 5 | **Smart defaults baseados em contexto** | "Cliente novo de hoje preenche timezone do business" |
| 6 | **Tabs/accordion pra forms longos** | "Geral / Endereço / Fiscal / Histórico" — não scroll infinito |
| 7 | **Confirma antes de fechar com unsaved** | "Tem alterações não salvas. Sair?" |
| 8 | **Atalho Cmd+S salvar / Cmd+Enter submit** | Padrão indústria |

**UX move chave:** **eliminar fricção de salvar**. Auto-save por field é estado da arte; "Salvar" virou exceção pra finalização (ex: emitir NFe, fechar OS).

**Telas oimpresso na categoria:**
- (Repair Edit JobSheet — Blade legado)
- (NFe Emitir — Inertia, parcial)

---

## 5. Settings / Configurações

**SaaS de referência:** Stripe Dashboard Settings, Vercel Project Settings, Linear Workspace Settings

**Patterns canônicos:**

| # | Pattern | Implementação típica |
|---|---|---|
| 1 | **Sidebar sub-navegação** | Categorias verticais (General / Billing / Team / API / Integrations) |
| 2 | **Cards por bloco** | Cada setting é um card isolado com título + descrição + control + Save próprio |
| 3 | **Confirma destrutivo com type-to-confirm** | "Digite o nome do business pra confirmar exclusão" |
| 4 | **Status connection visível** | Webhook configurado: badge `🟢 Connected` ao lado |
| 5 | **Test action inline** | Botão "Send test webhook" testa sem salvar |
| 6 | **Documentation link contextual** | "Read more →" abre docs em side-drawer ou nova aba |
| 7 | **Audit log de mudanças** | "Alterado por fulano há 3 dias" no rodapé do setting |
| 8 | **Section "Danger zone" no fundo** | Excluir, transferir ownership, etc — vermelho + isolado |

**UX move chave:** **ações destrutivas isoladas + reversibilidade onde possível**. Stripe é referência: você não consegue cancelar uma conta sem 3 cliques deliberados.

**Telas oimpresso na categoria:**
- [Whatsapp/Settings](../../resources/js/Pages/Whatsapp/Settings.tsx) — implementa parcial; sem 1 (sub-nav), 5 (test webhook inline), 8 (danger zone)
- (Configurações de business — Blade legado)

---

## 6. Listagem operacional (CRUD ops + filtros)

**SaaS de referência:** Notion DB, Airtable, Retool tables, Linear filters

**Patterns canônicos:**

| # | Pattern | Implementação típica |
|---|---|---|
| 1 | **Toolbar superior compacta** | Search + Filter + Sort + Density toggle + New + Bulk |
| 2 | **Filtros AND combinados via chips** | "status:open" + "client:rota livre" + "due:<7d" — chips removíveis |
| 3 | **Sort multi-coluna** | Click coluna 1 (asc), Shift+click coluna 2 (asc-secundário) |
| 4 | **Density skim/normal/briefing** | Toggle muda altura da row + truncate de células |
| 5 | **Resize columns + reorder columns drag** | Personalização persiste em localStorage |
| 6 | **Inline edit nas células** | Double-click célula → edit em-place |
| 7 | **Saved views por usuário** | "Minhas views: My open / Today / Q2 review" |
| 8 | **Keyboard navigation grid** | Setas + Enter + Tab pra mover entre células |
| 9 | **Sticky header + sticky first column** | Em scroll horizontal/vertical |
| 10 | **Pagination ou infinite scroll** | Infinite pra <500 rows; pagination pra >500 |

**UX move chave:** **personalização leve persistida**. Cada usuário sente que "esta listagem é dele" depois de 2-3 ajustes (filter + sort + colunas).

**Telas oimpresso na categoria:**
- [Whatsapp/Templates/Index](../../resources/js/Pages/Whatsapp/Templates/Index.tsx) — listagem simples, falta 2, 3, 4, 7
- (Repair JobSheets index — Blade legado, MWART pendente)
- (Sells lista — Blade legado)

---

## Como Audit cita Benchmark

No relatório de Modo B, após a tabela de Score, opcional adicionar:

```
## Benchmark (categoria: <Inbox conversacional>)

Patterns implementados (referência: Front + Intercom + WhatsApp Web):
- ✅ 3-painéis split-view
- ✅ Search debounced server-side
- ✅ Real-time presence (Centrifugo)
- ✅ Mensagens agrupadas por dia
- ⚠️  Empty state sem CTA — está em "💬 Selecione uma conversa", deveria sugerir ação
- ❌ Atalhos vim ausentes
- ❌ Drafts auto-save ausente
- ❌ Ações inline na lista (hover) ausentes

Gap pra estado da arte de mercado: 3 patterns CRITICAL faltando (atalhos, drafts, hover actions). Detalhes nos findings UX-WARN/CRITICAL acima.
```

---

## Como evoluir este catálogo

- Quando ERP ganhar tela nova de categoria coberta: adicionar em "Telas oimpresso na categoria"
- Quando aparecer SaaS de referência novo (ex: Cron app pra agenda, Granola pra notas), adicionar
- Quando categoria nova surgir no produto (ex: "Workflow builder"): nova seção
- Re-avaliar patterns canônicos a cada 6 meses (estado da arte muda)

**Última atualização:** 2026-05-07 (criação inicial — categorias 1-6 baseadas em audit Whatsapp/Conversations PR #173 + estado da arte SaaS B2B Q2 2026)
