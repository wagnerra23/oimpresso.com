# Visual canon — Recurring/Index (Cobrança recorrente)

> **Status:** Cowork output renderizado e validado em 2026-05-16. Match exato com 2 screenshots Wagner.
> **Destino visual:** `9,75/10` (Método KB-9.75 alvo final, **NÃO** R#1 intermediário `3,8/10`).
> **Fonte canônica:** [`prototipo-ui/prototipos/recurring/recurring-page.jsx`](../../../prototipo-ui/prototipos/recurring/recurring-page.jsx) (1.637 linhas IIFE expondo `window.RecurringPage`) + [`recurring-data.jsx`](../../../prototipo-ui/prototipos/recurring/recurring-data.jsx) (mock 18 assinaturas + 5 planos + timelines + troubleshooters) + [`recurring-icons.jsx`](../../../prototipo-ui/prototipos/recurring/recurring-icons.jsx)
> **Snapshot vivo:** `prototipo-ui/cowork-snapshot/` (rodar `python -m http.server --directory prototipo-ui/cowork-snapshot 5550` → `http://localhost:5550/Oimpresso%20ERP%20-%20Chat.html` → localStorage `oimpresso.route=recurring`).

---

## Arquitetura visual (4 sub-rotas)

| Sub-rota | URL alvo | Conteúdo |
|---|---|---|
| `assinaturas` | `/recurring` (default) | 3-col: filtros sidebar · lista 18 assinaturas · drawer detalhe |
| `planos` | `/recurring/plans` | CRUD 5 planos: cardápios/banner/wind/fachada/rótulos |
| `faturas` | `/recurring/invoices` | Lista faturas (open/paid/overdue) + ação cancelar |
| `configuracoes` | `/recurring/settings` | Gateways · régua dunning · NFe auto-emission · webhooks |

Tab control via `tab` prop em `<RecurringPage tab onTab>` — Inertia equivalente: query string `?tab=assinaturas|planos|faturas|configuracoes` ou rotas separadas.

---

## Tela 1 (Assinaturas) — decomposição cirúrgica

### Top header (linha 1)
- Title `Cobrança recorrente` (28px / 700 / -0.02em)
- Subtitle mono `13 ATIVAS · MRR R$ 8.420,00 · CHURN 8.3%` (11px mono uppercase letter-spacing 0.05em)
- Tabs 4 botões com badge contador (`Assinaturas 1`, `Planos 2`, `Faturas 3`, `Configurações 4`) — tab ativa underline azul
- CTA primary `+ Nova assinatura` (texto+ícone) + 6 botões mini-navigation (provavelmente paginar/voltar)

### 4 KPI cards (linha 2 — grid 4col gap=10px)
1. **MRR · RECEITA RECORRENTE** (card escuro `var(--text)` bg + sparkline verde) — `R$ 8.420,00` + delta `↑ R$ 1,2k vs abr`
2. **CHURN MAIO** — `1 cancelamento` + sub `taxa 8.3%`
3. **PRÓXIMA COBRANÇA** — `amanhã` + sub `R$ 2.140,00 · 4 boletos`
4. **RETENTADO FALHOS** — `2` em `var(--bad)` + sub `requer ação` em bad

### 3-col body (Filtros · Lista · Drawer)

**Coluna esquerda · Filtros (~220px)**
- `★ Mostrar só favoritos` toggle (count badge)
- Seção `PRÓXIMA COBRANÇA`:
  - Qualquer data 18 (selected default · highlight azul)
  - Hoje 1, Amanhã 1, Esta semana 2, Próx. 30 dias 8
- Seção `STATUS`:
  - Todas 18 (selected · highlight azul)
  - Em dia 9 (dot verde), Retentando 2 (dot warn), Falharam 2 (dot bad), Pausadas 1 (dot mute), Canceladas 4 (dot mute strikethrough)
- Seção `PLANO`:
  - Lista 5 planos com `name`, `R$ X,XX`, `N ativ.`
- Rodapé (quando filtra): `MRR FILTRADO R$ X | N ativ. de 18`

**Coluna meio · Lista assinaturas (flex 1)**
- Search input topo `/` shortcut hint + `Buscar (/) — cliente, CNPJ, OS` + contador `18 / 18`
- Cada linha:
  - Avatar circular (28×28, gradient derivado de `hueFor(nome)` — `linear-gradient(135deg, oklch(0.70 0.10 H), oklch(0.50 0.13 H))`) + iniciais 2 letras
  - `★` favorito (toggle ouro/cinza)
  - Nome bold + linha sub `plano · sub-info · desde há Xa/Xm`
  - Lado direito: `RecStatusBadge` (5 estados — em dia/retentando N/M/falhou Nx/pausada/cancelada)
  - Linha 2: ícone método (pix/boleto/card) + valor `R$ X,XX` mono
  - Linha selecionada bg purple soft + border purple

**Coluna direita · Drawer detalhe (~340px)**
- Header: avatar 36px + nome bold + CNPJ formatado + buttons `PDF` ghost + status badge
- Card destacado purple-soft `PRÓXIMA COBRANÇA`: `em N dias` grande + valor mono + data `19 de jun. · ciclo mensal` + ícone método
- Grid 2col key-value:
  - PLANO / CICLO / DESDE / COBRANÇAS PAGAS / FALHAS / LTV / CONTATO / OS RECENTE
- Card amarelo soft `NOTA PINADA` (se `is_pinned`): "5 lojas · cada uma com KV diferente"
- Card NFE row: badge tipo NFe/NFS-e/none + ENVIO (E-mail/WhatsApp/Email+WA) + `Última: NFe 8427` + button `Reenviar`
- 2 buttons: `Editar plano [E]` + `Pausar [P]`
- Card JANA·IA com tabs Sugerir/Resumir/Perguntar + button `+ Resumir saúde da assinatura`
- Card `NOTAS & EVENTOS N itens` + input `Anotar internamente... (use #os4821)` + button `Anotar [E]`
- Lista timeline append-only (event-create/note/event-charge/event-nf/event-retry/event-status/event-plan)
- Card `HISTÓRICO DE PAGAMENTOS`: row 12 cells (1 por mês passado) com cor pago/falhou + legenda `pago (19) · falhou (1) · futuro`

---

## Schema gap vs estado atual (Modules/RecurringBilling)

Existe hoje (✓): `rb_plans`, `rb_subscriptions`, `rb_invoices`, `rb_charge_attempts`, `pg_webhook_events`, `rb_boleto_credentials` (todos com `HasBusinessScope`).

**Tabelas novas:**
- `rb_subscription_notes` — id, subscription_id, business_id, user_id, body TEXT, is_pinned BOOL, created_at, updated_at — INDEX (subscription_id, is_pinned DESC)
- `rb_subscription_favorites` — id, subscription_id, user_id, business_id, created_at — UNIQUE (user_id, subscription_id)
- `rb_subscription_events` — id, subscription_id, business_id, kind ENUM (event-create/event-status/event-plan/event-charge/event-retry/event-nf/note), by_actor VARCHAR(64), body TEXT, occurred_at TIMESTAMP — INDEX (subscription_id, occurred_at DESC)

**Colunas adicionar em `rb_subscriptions`:**
- `payment_method` ENUM('pix','boleto','card') NULL
- `last_jobsheet_id` BIGINT UNSIGNED NULL (soft link Modules/Repair — sem FK constraint pra evitar Tier 0 cross-module hard dep)
- `total_paid_cached` SMALLINT UNSIGNED DEFAULT 0
- `failed_count_cached` SMALLINT UNSIGNED DEFAULT 0
- `total_revenue_cached` DECIMAL(14,2) DEFAULT 0
- `paused_until` DATE NULL
- `churn_reason` VARCHAR(64) NULL
- `contact_phone_cached` VARCHAR(32) NULL (denormalizado pra lista rápida)

**Colunas adicionar em `rb_plans`:**
- `descricao_curta` VARCHAR(200) NULL (mock chamou `items`)
- `fiscal_type` ENUM('nfe','nfse','none') DEFAULT 'none'
- `fiscal_cfop` VARCHAR(8) NULL
- `fiscal_servico` VARCHAR(8) NULL

---

## Plano de ondas (cirúrgico — cada PR ≤300 linhas)

| Onda | Conteúdo | Estimate |
|---|---|---|
| **1** | Migration aditiva (3 tables + 12 cols) + Note/Favorite/Event Models + base Pest | 4h |
| **2** | Observer que recalcula cached cols + comando `rb:backfill-cached-fields` + Pest | 2h |
| **3** | SubscriptionController (index/show/store/cancel/pause) + Policy + AuditLog + Pest | 4h |
| **4** | `Pages/Recurring/Assinaturas/Index.tsx` — header + 4 KPIs + sidebar filtros + lista (read-only) | 5h |
| **5** | `Pages/Recurring/Assinaturas/_components/SubscriptionDrawer.tsx` (drawer detalhe completo) | 4h |
| **6** | PlanController + `Pages/Recurring/Planos/{Index,Create,Edit}.tsx` + FormRequest | 5h |
| **7** | `Pages/Recurring/Faturas/Index.tsx` (reusa InvoiceController existente) | 3h |
| **8** | `Pages/Recurring/Configuracoes/Index.tsx` (gateways · régua · NFe auto · webhooks) | 4h |
| **9** | NotesController + FavoritesController + integração JANA·IA fallback graceful + reenviar NFe wire | 4h |
| **10** | Sidebar entry + redirect 301 /recurringbilling→/recurring + Permissions Spatie + cutover | 2h |

**Total efetivo:** ~37h código. Recalibrado fator 10x ADR 0106 ≈ 4h IA-pair por onda + margem 2x.

---

## Validação realizada na Onda 0 (2026-05-16)

- ✅ `python -m http.server 5550 --directory prototipo-ui/cowork-snapshot` rodando
- ✅ Navegação via `mcp__Claude_Preview__preview_screenshot` confirma rendering pixel-perfect das 2 screenshots Wagner
- ✅ Estrutura DOM mapeada: header, 4 KPIs, 3-col body, drawer com 6 cards
- ✅ Schema gap enumerado: 3 tables + 12 cols
- ✅ Cross-module dependências identificadas: link Repair OS (soft FK), Jana panel (fallback graceful), reenviar NFe (endpoint já existe em `POST /nfe/emissoes/{id}/reenviar-email`)
- ✅ Visual canon `recurring-page.jsx` 1.637ln + `recurring-data.jsx` 220ln + `recurring-icons.jsx` 38ln salvos em `prototipo-ui/prototipos/recurring/`

**Próximo:** Onda 1 — migration aditiva + 3 models.
