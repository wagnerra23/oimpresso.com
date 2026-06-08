# Snapshot Cowork — Oimpresso ERP "Comunicação Visual"

**Data:** 2026-05-26
**Fonte:** [claude.ai/design — projeto Oimpresso ERP Comunicação Visual](https://api.anthropic.com/v1/design/h/LomZF664hHXfiiSswhyEBw)
**Arquivo aberto pelo Wagner:** `project/Oimpresso ERP - Chat.html`
**Fase MWART:** F1 (cópia bruta · sem tocar `Modules/*`)

## Destino apontado pelo Wagner

> "seria a venda" — Wagner 2026-05-26 (resposta `AskUserQuestion` durante o handoff)

Implementação alvo (F3 futuro): **módulo Vendas**. Mapping específico ainda a definir — a tela de Chat do Cowork combina dois conceitos (cockpit Jana + lista de conversas humanas), e a relação com Vendas não foi destilada ainda. Próxima sessão deve abrir essa decisão antes de F2.

## Composição da tela Oimpresso ERP - Chat.html

A tela monta a **rota `chat`** do shell Cowork via `app.jsx::ChatPage`. Tem 2 modos no topnav (chaveado por `oimpresso.chat.tab` localStorage):

- **Dashboard** (chat humano) — `chat.jsx`
  - `ConvList`: lista de conversas filtrada por aba (Todas / OS / Equipe / Clientes) + busca + grupos Fixadas/Recentes
  - `Thread`: thread + composer (textarea auto-grow, Enter envia, ⇧+Enter quebra linha)
  - `LinkedAppsPanel`: painel direito com apps ligados à conversa
- **Analista IA** (Jana) — `chat-jana.jsx`
  - Brief diário com paragráfos (text/action/anomaly) + chips HITL
  - KPIs (Receita mês, A receber vencido, Ticket médio, Frota utilização)
  - Análises (Inadimplência buckets, Faturamento sparkline, Concentração Pareto, Churn ouro, Frota donut)

CSS específico: `chat-jana.css`, mais o shell global `styles.css`.

## O que foi cortado deste snapshot

Pra manter PR enxuto, removi do bundle original:

- `project/backups/2026-05-14-pre-handoff/` (~2MB) — snapshot velho redundante
- `project/uploads/` (~12MB) — screenshots avulsos e cópia anterior do handoff
- `project/_audit/` (~780KB) — screenshots de audit visual (Vendas, KB-9.75)
- `project/prototipo-ui-patch/` (~2.7MB) — patches anteriores (parte já aplicada no projeto: PR #295 + PR #1638)
- `project/.thumbnail` — binário Cowork

Bundle original total: ~22MB. Snapshot aqui: ~4.7MB.

## Próximos passos sugeridos (não executados nesta sessão)

1. **F1.5 (visual-comparison)** — Wagner abre o `Oimpresso ERP - Chat.html` no browser (ou usa `audit-*.png` em `project/` raiz) e decide mapping pra Vendas
2. **F2 (backend baseline)** — confirmar se entra como sub-feature de `Modules/Sells` (drawer? rota nova?) ou usa Copiloto/Whatsapp como host técnico
3. **F3 (frontend incremental)** — `resources/js/Pages/Sells/Chat/Index.tsx` com `visual-comparison.md` + charter
4. **F4-F5 (QA + cutover)** — Pest + smoke real + browser MCP

Conforme ADR 0104, F2/F3 só rodam após `visual-comparison.md` gerado e SCREENSHOT aprovado por Wagner.

## Referências canon

- [ADR 0104 — Processo MWART canônico](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) (5 fases obrigatórias)
- [ADR 0114 — Loop Cowork ↔ Claude Code formalizado](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [prototipo-ui/PROTOCOL.md](../PROTOCOL.md) — operação do diretório
- [PR #1638](https://github.com/wagnerra23/oimpresso.com/pull/1638) — bundle KB-9.75 Vendas aplicado (mesma sessão Cowork)
