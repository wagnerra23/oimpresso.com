---
page: /fiscal/nfe
component: resources/js/Pages/Fiscal/Nfe.tsx
page_id: fiscal-nfe
url: /fiscal/nfe
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-001]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0114-prototipo-ui-cowork-loop-formalizado, 0143-fsm-pipeline-live-prod-marco-2026-05-12]
prototypes:
  - prototipo-ui/Oimpresso ERP - Chat.html (fiscal-page.jsx §9 FiscalNFePage)
  - prototipo-ui/fiscal-page.css
---

# Charter — `Fiscal/Nfe`

> **Tipo:** charter canônico Tier A skill `charter-first` — Wagner aprova Non-Goals + Anti-hooks ANTES de marcar `status: live`.

## Mission

Dar à pessoa fiscal (Eliana contadora + Wagner operador) a **lista navegável de NF-e/NFC-e emitidas** com **status SEFAZ legível**, **janela legal de cancelamento visível**, e **detalhe acionável via drawer** — substituindo a UI atual fragmentada de `Pages/NfeBrasil/Transactions/NfceStatus` por visão consolidada multi-modelo (55 + 65).

## Goals (Definition of Done PR #1)

1. **Lista paginada** de NfeEmissao (HasBusinessScope ativo — multi-tenant Tier 0) por modelo (55, 65) + filtros (Todas, Autorizadas, Rejeitadas, Janela 24h, Processando).
2. **SEFAZ pill** colorida por tom (ok/warn/bad) com código + label + hint hover — espelha SEFAZ_CODES do design.
3. **Pílula temporal de cancelamento** (24h NFC-e / 168h NF-e) — visível na linha e drawer.
4. **Drawer slide-in com detalhe** (status, destinatário, operação, mapa SEFAZ guiado "Jana sugere" quando cstat rejeitado).
5. **Atalhos J/K + Enter** pra navegar lista e abrir drawer.
6. **Inertia::defer** em rows (skill `inertia-defer-default`) — tabela carrega só quando solicitada.
7. **Pest biz=1** (ADR 0101): isolation cross-tenant + permission gate `fiscal.nfe.view`.

## Non-Goals (Wagner aprova explicitamente — NÃO entrar no PR #1)

- ❌ **Ações de mutação** (cancelar, retransmitir, CC-e, inutilizar) — botões existem desabilitados; ativação em PR seguintes (cada uma chama Service NfeBrasil existente via Job).
- ❌ **NFS-e** na mesma tela (sub-página 3 separada do design).
- ❌ **Manifesto DF-e** (sub-página 4 separada).
- ❌ **Emissão nova** (botão "Emitir" desabilitado — entra com EmitirSheet em PR após cancelar/retransmitir).
- ❌ **⌘K palette** completa com busca cross-fiscal (PR #3 do cockpit).
- ❌ **Sparklines, alertas, KPIs** (são do Cockpit sub-página 1 — PR #2).
- ❌ **Importar XML** entrada de fornecedor (depende endpoint NfeBrasil ainda não exposto).
- ❌ **Dest_name/CNPJ via JOIN com transactions/contacts** — primeiro PR lê de `metadata` JSON; PR seguinte adiciona join (perf sob carga real).

## Anti-hooks (regras de proteção — bloqueiam regressão)

- 🚫 **Não acessar NfeEmissao sem global scope** — toda query usa `HasBusinessScope` (ADR 0093). Pest cross-tenant biz=1 vs biz=99 quebra se vazar.
- 🚫 **Não usar `withoutGlobalScopes`** no Controller — superadmin acessa via session do business escolhido.
- 🚫 **Não cachear sefazCodes do lado servidor por business** — mapa estático global, cache ok.
- 🚫 **Não disparar polling SEFAZ no `index()`** — leitura pura; reconsulta vem por ação explícita.
- 🚫 **Não mostrar PII real** (CPF/CNPJ completo) sem masking — `formatDoc` aplica truncamento quando necessário.
- 🚫 **Não emitir botão habilitado sem permission gate** — `fiscal.nfe.acoes` obrigatório quando ativar mutations.

## UX targets

- **Densidade:** linhas ~48px, fonte 12.5px corpo / 13.5px número da nota (mono).
- **Cor de status:** verde =100/104 (ok), âmbar =999/691 (warn), vermelho =110/204/220/539/778 (bad).
- **Pílula 24h:** verde >12h, âmbar 6-12h, vermelho <6h (urgência cresce).
- **Drawer:** largura 480px desktop, full-width mobile, ESC fecha, click-outside fecha.
- **Foco visual:** linha cursor (J/K) com `outline: 2px solid var(--fis)`.

## Automation hooks (futuros — não-bloqueantes PR #1)

- `Modules/Jana` consome `sefazCodes` + receitas SEFAZ_ACTIONS pra responder dúvidas em chat ("o que significa rejeição 539?").
- Telemetria: `viewed_fiscal_nfe` event quando carrega lista (cycle goal "Eliana usa cockpit").
- Hook futuro pós-cancel: emit `FscalNotaCancelled` event consumido por Whatsapp/Email (já há `CancelarVendaCascade` em FSM ADR 0143).

## Riscos conhecidos

- **R1:** lista carrega lenta se business tem >10k notas — mitigação: defer + paginate 50 + index em `emitido_em DESC`.
- **R2:** metadata->dest_name pode estar vazio em notas antigas pré-Sprint 3 ARQ-019 — fallback "—".
- **R3:** janela 24h vs UTC vs America/Sao_Paulo — Controller usa `now()` (timezone do app); pílula JS usa `Date.now()` (browser timezone). Risco baixo porque comparação é minutos antes da deadline, não horas; futuro: passar `nowMs` server-rendered pra precisão.
