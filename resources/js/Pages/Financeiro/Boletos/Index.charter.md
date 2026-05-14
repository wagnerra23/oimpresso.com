---
page: /financeiro/boletos
component: resources/js/Pages/Financeiro/Boletos/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-14
parent_module: Financeiro
related_adrs: [ui/0114, 0093, 0094, 0104]
related_us: [US-BOL-XXX]
related_prototype: prototipo Cowork "Boleto e Contas Inter" (Financeiro.html), aprovado [W] 2026-05-09
related_decisions: memory/requisitos/Financeiro/boletos-visual-comparison.md (Q1-Q5 aprovadas [W] 2026-05-14)
tier: A
charter_version: 1
---

# Page Charter — /financeiro/boletos

> **Status:** F3 refator visual entregue (charter criado junto com refator BoletoController + Index.tsx + Pest, sessão 2026-05-14).
> Persona: **Eliana [E]** (financeiro escritório) + **Larissa [Cliente Piloto]**. Desktop ≥1024px.

---

## Mission (1 frase)

Eliana acompanha **toda a cobrança via boletos** em uma view única (funil 5 etapas + 3 KPIs + tabela rica com chip-banco), pra responder em <30s "quantos vencem amanhã?" e "quem pagou esse mês?", sem abrir relatório separado.

---

## Goals — Features (faz)

- **Funil de cobrança 5 etapas** (Em aberto → Lembrete → Cobrança ativa → Vencidos +5d → Protesto)
  - Q1 aprovado: UI-only F1 derivando de `status` + regras simples (vencimento entre today+1..today+3 = Lembrete; today-5..today-1 = Cobrança ativa)
  - Protesto = stub 0 (Onda 2 com job real)
- **3 KPI cards**: Pago no mês (emerald) · Vencido (rose) · Em aberto (default)
- **Tabs filtro status**: Todos / Em aberto / Pagos / Vencidos / Cancelados
- **Dropdown conta bancária** (aparece só se `>1 ContaBancaria` ativa)
- **Tabela densa** (text-[12.5px] tabular-nums): vencimento + dias_atraso → cliente/título → nosso_número (mono) → chip banco (cor + sigla) → valor + status badge + ações
- **Chip banco visual** (cor + sigla "Inter"/"BB"/"Itaú"/etc) — mapping COMPE (Banco Central) hardcoded F1
- **Drawer detalhe simplificado** (Q5 aprovado): info principal (vencimento, valor, conta, estratégia, status, nosso_número, linha digitável, código barras) + 2 ações (Copiar linha digitável, Cancelar boleto)
- **Cancelar boleto** inline OU via drawer — chama POST `/financeiro/boletos/{id}/cancelar` → `TituloService::cancelarBoleto`
- **Copiar linha digitável** via clipboard API + toast feedback
- **Multi-tenant Tier 0** ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)): `BoletoRemessa`/`Titulo`/`ContaBancaria` filtrados por `business_id` global scope (BusinessScope trait)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ Sheet "Emitir boleto multi-título" — Q2 aprovado: emissão hoje funciona em `/contas-receber` → ação inline "Emitir boleto" no título. Sheet bulk entra em US-BOL-XXX backlog
- ❌ Sheet "Remessa/Retorno" CNAB upload — Q3 aprovado: depende `CnabDirectStrategy` real em prod (hoje é mock). Entra em Onda 2
- ❌ Tela "Contas & Caixa" integrada — Q4 aprovado: escopo separado, `/financeiro/contas-bancarias` já tem CRUD básico
- ❌ Timeline cronológica rica no drawer — Q5 aprovado: drawer F1 mostra info principal + ações; timeline (criação → envio → pagamento) entra em F2 com `activity_log` Spatie
- ❌ Pagination explícita (limit 100 atual mantido)
- ❌ Export CSV/PDF — entra em US-BOL-XXX backlog
- ❌ Auto-refresh real-time via Centrifugo — F2 quando webhook Inter LIVE virar canal
- ❌ Edição inline do título (cliente/valor/categoria) — drawer leva pra rotas de edição existentes
- ❌ Mobile responsive (cards stack) — F1 desktop only (Persona Eliana)
- ❌ Pix dinâmico inline na linha — feature separada (CYCLE-XXX Pix)

---

## UX Targets

- p95 first-paint < 500ms (5 queries pequenas: remessas + KPIs + funil + contas)
- Cabe em monitor 1280px sem scroll horizontal (Persona Eliana)
- 0 erros JS console
- Tabular nums em TODOS valores monetários (`tabular-nums`)
- Funil grid 5 colunas em `lg:` (≥1024px)
- Drawer abre em <100ms (lazy load via `useState`)
- Cores semânticas Cockpit V2: emerald (pago), rose (vencido), amber (alerta funil), sky (em aberto), purple (mock)

---

## UX Anti-patterns

- ❌ KPI "Próxima janela" com data hardcoded — protótipo tinha "hoje 18:30 · remessa diária"; F1 omite até existir job de remessa real
- ❌ Mostrar botão "Emitir boleto" no header sem implementação real (T-AP-13: mutação NO-OP)
- ❌ Drawer com 12+ campos vazios (canon F1 = só campos com valor real)
- ❌ Chip banco emoji ou ícone customizado (canon = quadrado `rounded-sm` 8x8 + sigla curta texto)
- ❌ Status badge sem ícone (canon = ícone Lucide + label + cor semântica)
- ❌ Cor crua `bg-(blue|green|red)-N` sem semântica (canon = stone/emerald/rose/amber/sky/purple)

---

## Automation Hooks

- `BoletoController::index(Request $r): Response`
  - lê `session('business.id')` + `?status=X&conta_id=N` query
  - chama `kpis()`, `funil()`, `listarContas()` privados
  - retorna `Inertia::render('Financeiro/Boletos/Index', $shape)`
- `BoletoController::cancelar(Request, int $remessaId, TituloService): RedirectResponse`
  - delega `TituloService::cancelarBoleto($remessa, $motivo)`
  - estratégia-aware: cnab direct (mock) ou gateway (Inter API real)
- Multi-tenant: `BusinessScope` trait em `BoletoRemessa`/`Titulo`/`ContaBancaria`
- Permission canon: `financeiro.dashboard.view` (granularidade `financeiro.boletos.view` em backlog F2)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir
- ❌ Não dispara WhatsApp / SMS
- ❌ Não escreve no banco no render (read-only — só cancelar() é mutação)
- ❌ Não chama Service externo (Inter API, banco webhook) no render
- ❌ Não acessa BoletoRemessa de outro `business_id` (Tier 0 ADR 0093)
- ❌ Não loga PII em plain text (cliente_descricao + linha_digitavel ficam só em response Inertia; activity_log via Spatie já configurado)
- ❌ Não gera PDF do boleto no render (lazy — sob demanda em outra rota)

---

## Métricas vivas (Pest GUARD em [BoletoControllerTest.php](../../../../Modules/Financeiro/Tests/Feature/BoletoControllerTest.php))

```php
it('renderiza Inertia component Financeiro/Boletos/Index')
it('expõe Props no shape esperado (remessas, kpis, funil, contas, filtros)')
it('expõe 3 KPIs (pago_mes, vencido, aberto) com qtd + valor')
it('expõe funil 5 etapas (aberto, lembrete, cobranca, vencido_5d, protesto)')
it('filtra por status via querystring')
it('Tier 0 IRREVOGÁVEL: BoletoRemessa respeita business_id global scope (ADR 0093)')
it('cancelar boleto pago/cancelado retorna error (idempotente)')
it('não dispara mutação em GET /boletos (read-only puro)')
```

---

## Comparáveis canônicos

- **Stripe Dashboard "Payments"** — densidade tabular + chip-banco + drawer detalhe
- **Conta Azul "Cobranças"** — funil de etapas + status badge
- **QuickBooks "Receivables"** — KPIs no topo + linha clicável
- **Excluir:** Bradesco Net Empresa (UI 2010), Banco Inter PJ web (foco emissão, não gestão)

---

## Refs

- [Visual comparison F1.5](../../../../memory/requisitos/Financeiro/boletos-visual-comparison.md) — 8 dimensões + score 82/100 + Q1-Q5 aprovadas
- [Protótipo F1](../../../../prototipo-ui/prototipos/boletos/cowork-app.jsx) — aprovado [W] 2026-05-09 (Cowork "Boleto e Contas Inter")
- [Lições F3 Financeiro rejeitado](../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — pre-flight aplicado
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR ui/0114 — Loop Cowork formalizado](../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- Charter irmão — [Visão Unificada](../Unificado/Index.charter.md) · [Fluxo de caixa](../Fluxo/Index.charter.md)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-14 | Wagner [W] + Claude Code [CL] | F3 refator visual entregue — funil 5 etapas + 3 KPIs + tabela rica + chip-banco + drawer simplificado. Pre-flight LICOES aplicado literalmente. Q1-Q5 aprovadas. Sheets emitir/remessa e tela contas-caixa cortados de escopo (Q2/Q3/Q4 — backlog). |
