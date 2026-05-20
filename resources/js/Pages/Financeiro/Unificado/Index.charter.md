---
page: /financeiro/unificado
component: resources/js/Pages/Financeiro/Unificado/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-20
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-FICHA.md
related_adrs: [arq/0005, ui/0002, ui/0114, 0093, 0094]
related_us: [US-FIN-013, US-FIN-020, US-FIN-050-anexos, US-FIN-055-aprovacao]
related_prototype: canon REAL public/cowork-preview/Oimpresso ERP - Chat.html (aprovado Wagner 2026-05-19)
canon_method: Bundle copy CSS 9054 LOC inteiro (regra Tier 0 feedback-cowork-bundle-aplicar-inteiro) — Ondas 12-21
tier: A
charter_version: 6
---

# Page Charter — /financeiro/unificado

> **Status:** F3 entregue (PR #349). Charter retroativo (sessão 2026-05-09 audit) — sem `Index.charter.md` original, divergências do ADR ui/0002 documentadas abaixo.
> Persona: **Eliana [E]** — financeiro escritório, densidade alta, atalhos teclado.

---

## Mission (1 frase)

Tela única de **fluxo financeiro do mês** que mistura **Pagar / Pagas / Receber / Recebidas** em uma view só, evitando que Eliana abra 4 menus diferentes pra responder "quanto entra/sai esta semana".

---

## Goals — Features (faz)

- **Ondas 12-21 KB CANON CSS BUNDLE COMPLETO** (2026-05-20, charter v6):
  - **Bundle copy CSS 9054 LOC** — `resources/css/cowork-canon-financeiro-bundle.css` importado inteiro escopado em `.fin-cowork` (regra Tier 0 `feedback-cowork-bundle-aplicar-inteiro`). Substitui cherry-pick fragmentado.
  - **Markup canon EXATO**: `os-page-h fin-page-h` (header) + `os-page-h-l/r` (left/right) + `fin-stat fin-stat-hero` (KPI hero warm dark hue 80) + `os-btn ghost` (botões transparentes) + `os-drawer-head` (drawer header) + `fin-footer-tips sticky` (footer atalhos+summary).
  - **Plano de Contas filtro** (Onda 12.7) — substitui Categorias livres no dropdown filtro. Backend: `where('plano_conta_id', $id)` com fallback OR categoria_id. Hierárquico BR (47 entries Receita Federal/DCASP seedados via `PlanoContasBrSeeder`).
  - **Filtros lifecycle default ON** (Onda 12.5) — pills coloridos hue semântico (verde 145 receber/recebidas · rosa 25 a pagar · azul 240 pagas). Toggle "Só atrasados" classe distinta `fin-filter-toggle` (não `fin-filter-cb`).
  - **Densidade compact default** (Onda 12.6) — remove modo "spacious" (não usado). Apenas 2 modos: compacto/médio.
  - **Footer sticky bottom** colado na viewport com summary numérica (`N lançamentos · entrada R$ X · saída R$ Y`) + atalhos teclado.
  - **KPI strip full width** (Onda 12.7) — grid 5 cards 100% container.
  - **Hue accent custom via localStorage** — `cockpit.theme.accentHue` default 220 azul canon (Wagner pode mudar via picker UI).

- **Onda 20 #50 — Anexos NF/comprovante** (2026-05-20, charter v6):
  - **Botão `📎 Anexar`** no drawer dispara file input invisible → POST `/financeiro/unificado/{id}/anexos` (multipart 10MB max)
  - **Storage local privado** `storage/app/private/financeiro/anexos/{biz_id}/{titulo_id}/` (não public)
  - **Idempotência SHA-256** — não duplica upload do mesmo arquivo (toast warning)
  - **Aceita** .pdf, .png, .jpg, .jpeg, .xml (NF eletrônica)
  - **Backend**: `TituloAnexo` model + 3 endpoints (listarAnexos GET + anexar POST + removerAnexo DELETE) + tabela `fin_titulo_anexos`

- **Onda 21 #55 — Workflow aprovação pagamento** (2026-05-20, charter v6):
  - **Visível só pra título kind=payable + status=aberto/atrasado/vencendo** (não aplica receivable)
  - **3 estados condicionais**:
    - `null` (default — sem fluxo): botão "⏳ Solicitar aprovação" → POST `/solicitar-aprovacao`
    - `'pendente'`: pill amber + botões "✓ Aprovar" / "✗ Rejeitar (motivo)"
    - `'aprovado'`: pill emerald "liberado pra pagamento"
    - `'rejeitado'`: pill rose "bloqueado pra pagamento"
  - **Backend**: 3 endpoints + 4 campos novos em `fin_titulos` (aprovacao_status enum + aprovado_by + aprovado_at + aprovacao_motivo)
  - **Backward compat**: títulos antigos com `aprovacao_status=NULL` seguem fluxo direto (sem aprovação obrigatória)

- **Onda Edit** (2026-05-18, charter v5):
  - **TituloEditSheet** — Sheet drawer inline edita campos seguros do título: `cliente_descricao` (texto livre + cross-links `#V-/#OS-/#PC-`), `observacoes`, `categoria_id`, `vencimento`. `valor_total` mutável SOMENTE se `status` aberto/parcial (ADR fin-tech/0002 imutabilidade pós-baixa). PUT `/financeiro/unificado/{id}` via `useForm` Inertia. Wire-up no botão "Editar" do drawer de detalhe.
  - **Conferido per-user DB** — `FinConferidoToggle` migrado de localStorage para `conferido_by` (FK users.id) + `conferido_at` (timestamp). Substitui Onda 5 R1 storage. Eliana confere ≠ Wagner confere → audit per-user. Routes POST/DELETE `/unificado/{id}/conferir`.
  - **Cross-links auto-pop** — `TituloAutoService` sintetiza `#V-{transaction_id}` (vendas) e `#PC-{transaction_id}` (compras) em `cliente_descricao` no `afterCreate`. FinCrossLinkify renderiza pills clicáveis.

- **Onda 7 KB-9.75 R3 Output + Cross-link** (2026-05-18):
  - **FinCrossLinkify** — regex parser detecta `#V-` `#BL-` `#PC-` `#OS-` `#R-` `#P-` no `desc` do row → pills coloridas clicáveis que `router.visit` para o módulo apropriado (Sells / Boletos / Compras / Repair / Contas-Receber legacy / Contas-Pagar legacy). Fecha o loop "do Financeiro pra origem do lançamento".
  - **FinChecklistFechamento** — trilha 12 passos do fechamento mensal agrupada em 4 (Conciliação / Revisão / Exportação / Comunicação). Persistido em `localStorage[oimpresso.financeiro.fechamento.YYYY-MM]`. Progress bar + timestamp por passo. Trigger ☑ Fechamento no header da página.
- **Onda 6 KB-9.75 R2 IA** (2026-05-18):
  - **FinAnomalyDetector** — detecta valor outlier vs média histórica da contraparte (threshold ≥25%, severity high/medium/low). Mostrado no drawer quando aplicável. Pure compute, sem LLM.
  - **FinPartyHistory** — stats da contraparte no drawer (count, total, média, on-time%, categoria top, 5 recentes). Detecta isNew (1 lançamento) vs isRecurrent (≥3). Pure compute.
  - **FinMonthDigest** — section colapsável acima da tabela com 4 cards (Recebido / Pago / Saldo do mês / Atrasados) + top contraparte in/out. Pure compute, "Eliana 5min sexta" digest.
- **Onda 5 KB-9.75 R1 Curadoria** (2026-05-18):
  - **Conferido toggle** por Eliana (localStorage `oimpresso.financeiro.conferido`) — pill grande no drawer + badge ✓ silent na linha
  - **Comentários inline** thread Eliana ↔ Wagner ↔ Bruna (localStorage `oimpresso.financeiro.comments`) — textarea no drawer + badge 💬N silent na linha
  - **Audit trail determinístico** (5 kinds: create / categorize / edit / concil / alert) derivado do row sem persistência — exibido no drawer
  - **Frescor pill** 6 estados (paid · overdue · today · warning · soon · fresh) derivado de `vencimento`+`liquidacao` — compact ao lado do StatusPill na linha e full no drawer
- 5 KPI cards: Saldo previsto · Recebido · A receber · Pago · A pagar (com qtd de baixas/títulos)
- KPI cards **clicáveis** — cada um filtra a tabela pra tab correspondente (drill-down ADR ui/0002)
- Filter chips: Todas, Aberto, Receber, Pagar, Recebidas, Pagas, Atraso
- Dropdowns: Conta bancária, Categoria
- Filtro de período por querystring (default: mês corrente)
- Busca textual (atalho `/`)
- Tabela única com setas direcionais ↑↓ (entrada/saída), valor com sinal, status pill colorido
- Drawer detalhe (Sheet) ao clicar linha
- 1-clique baixa: botão "✓ Recebi" / "✓ Paguei" inline na linha (R-FIN-007)
- CmdK palette (`Cmd+K` ou `Ctrl+K`) — atalho navegação
- Densidade configurável: compact / comfortable / spacious (persiste em URL)
- Empty state com CTA "+ Adicionar primeiro lançamento" → /unificado/novo
- Header dinâmico: período PT-BR + nome do business logado (sem hardcode)
- Multi-tenant: query scoped por `business_id` (Tier 0 ADR 0093)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ Form unificado de novo lançamento inline — F1 é stub picker (Receber/Pagar) em `/unificado/novo`. Roadmap entrega form modal/sheet futuramente
- ❌ Cancelamento/estorno — vai por rotas dedicadas (`status='cancelado'` via append-only, não delete)
- ❌ Edição de `tipo`, `origem`, `origem_id`, `status`, `emissao` — imutáveis (anti-corrupção contábil; alterar requer cancelar+criar novo). Onda Edit edita só campos seguros + valor pré-baixa.
- ❌ Pagination explícita (default `limit(200)` no controller) — paginar quando 1000+ títulos virar dor
- ❌ Aging buckets <30 / 30-60 / 60-90 / 90+ — ADR ui/0002 previa, F1 simplifica pra status `atrasado` único
- ❌ Comparação `+12% vs mês anterior` — ADR ui/0002 previa `delta_pct`, F1 não calcula
- ❌ Combobox cliente/contraparte com autocomplete — F1 só filtra por chip, sem typeahead
- ❌ Mobile responsive (cards stack 2×2) — F1 só desktop ≥1024px (Eliana é desktop)
- ❌ Export PDF/Excel — Onda 4
- ❌ KPI configurável por user (esconder card) — Onda 4
- ❌ Substituir telas legacy `/financeiro/contas-receber` e `/contas-pagar` — coexistem (decisão ADR 0002 em aberto)

---

## UX Targets

- p95 first-paint < 600ms (controller agrega in-process; sem N+1 nas Eloquent relations já eager-loaded)
- Cabe em monitor 1280px sem scroll horizontal (Eliana está em desktop)
- AppShellV2 layout (Cockpit ADR 0039)
- 0 erros JS console
- Atalho `Cmd+K` abre palette
- Atalho `J/K` navega linhas (placeholder, não implementado em F1)
- Atalho `/` foca busca

---

## UX Anti-patterns

- ❌ Modal nested — só Sheet (drawer) lateral pra detalhe
- ❌ Toast/snackbar — flash session do Laravel (1-clique baixa volta com `back()`)
- ❌ Loading skeleton — props vêm do controller, sem async no client
- ❌ Cores berrantes — paleta restrita (emerald entrada / rose saída / amber vencendo / stone neutro)
- ❌ Animações decorativas — só transições em hover/drawer

---

## Automation Hooks

- Endpoint `/financeiro/unificado` chama `UnificadoController::index`
- Mock fallback **NÃO existe** — biz sem `Titulo` cadastrado renderiza tabela vazia (empty state com CTA)
- Multi-tenant: `Titulo::where('business_id', $businessId)` em todas as queries
- 1-clique baixa: POST `/unificado/{id}/baixar` chama método `baixar()` que aplica `TituloBaixaService` (R-FIN-002 audit)
- Stub `/unificado/novo` redireciona pra `/contas-receber` ou `/contas-pagar` (não implementa form unificado ainda)
- **Edit Sheet** (Onda Edit 2026-05-18): PUT `/unificado/{id}` → `UnificadoController::update(UpdateTituloRequest)` → guard `assertValorMutavel` se status quitado/cancelado
- **Conferir per-user**: POST/DELETE `/unificado/{id}/conferir` → `conferido_by` (FK users.id) + `conferido_at` timestamp

---

## Divergências registradas vs ADR ui/0002

> ADR ui/0002 (accepted 2026-04-24) propôs shape de KPIs/tabela diferente. F1 implementação diverge.

| Item ADR ui/0002 | F1 implementação | Justificativa |
|---|---|---|
| KPIs: `receber_aberto + pagar_aberto + recebido_mes + pago_mes` (4) | `saldo_previsto + recebido + a_receber + pago + a_pagar` (5) | Wagner pediu Saldo Previsto destacado no protótipo Cowork 2026-05-09 |
| Aging vencidos por bucket | Status `atrasado` simples | F1 enxuto; aging vira US futura |
| Pagination 25/100 | `limit(200)` fixo | Volume típico ~50-200/mês; paginar quando virar dor |
| Combobox cliente | Sem combobox | F1 simplifica; autocomplete é US futura |
| Mobile responsive | Desktop only | Eliana persona desktop |
| `delta_pct` (+12% vs mês anterior) | Não calcula | F1 simplifica; comparativo é US futura |

**Apend a [ADR ui/0002](../../../../memory/requisitos/Financeiro/adr/ui/0002-dashboard-unificado-4-estados.md) ou nova ADR superseding** quando próxima sessão tocar — formaliza divergência.

---

## Backlog futuro (US explícitas)

- **US-FIN-021** — Form unificado inline (modal/sheet) — substitui stub `/unificado/novo`
- **US-FIN-022** — Aging buckets <30/30-60/60-90/90+ + filtro
- **US-FIN-023** — Comparação `+X% vs mês anterior` por KPI (delta_pct)
- **US-FIN-024** — Combobox cliente/contraparte com autocomplete
- **US-FIN-025** — Mobile responsive (cards stack 2×2 + lista)
- **US-FIN-026** — Pagination 25/100 quando volume passar 500 títulos
- **US-FIN-027** — Pest GUARD: Tier 0 isolation + KPIs corretos + filtro tab querystring
- **US-FIN-028** — visual-comparison.md retroativo (ADR ui/0114 / mwart-comparative V4)
