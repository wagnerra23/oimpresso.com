# Sessão 2026-04-27 — Protótipo "Chat Cockpit" + ADR 0039

**Worktree:** Cowork (projeto "Oimpresso ERP Comunicação Visual")
**Operador:** Claude (Sonnet 4.5)
**Solicitante:** Wagner

---

## Pedido

Wagner pediu protótipo de chat em React + Laravel para o ERP Oimpresso, evoluindo em 3 iterações:

1. **v1** — chat clássico (sidebar empresa + lista conversas + thread)
2. **v2** — sidebar dual Chat/Menu + tela de Tarefas unificada (inbox)
3. **v3** — 3 colunas: sidebar + chat com abas internas Todos/OS/Equipe/Clientes + painel de apps vinculados à direita

Em paralelo: gravar a memória integrada no repo `wagnerra23/oimpresso.com` e ensinar o `CLAUDE.md` a seguir o padrão de design do Claude ao alterar/criar telas.

## O que foi feito

### 1. Protótipo

Arquivos no projeto Cowork:
- `Oimpresso ERP - Chat.html` (entrada)
- `Oimpresso ERP - Chat v1.html` (snapshot histórico v1)
- `app.jsx`, `sidebar.jsx`, `chat.jsx`, `tasks.jsx`, `viewers.jsx`, `data.jsx`, `icons.jsx`, `laravel-panel.jsx`, `tweaks-panel.jsx`, `styles.css`
- `CLAUDE.md` (instruções de projeto local)

**Stack:** React 18.3 via CDN + Babel inline + IBM Plex Sans/Mono. HTML único auto-contido (alinhado à preferência do cliente — ADR 0009).

**Tweaks expressivos (3):**
- `vibe` — workspace / daylight / focus
- `density` — slider skim ↔ briefing (recalcula `--row-h`, padding)
- `accentHue` — slider 0–360° (repinta accent + cores de origem OS/CRM/FIN/PNT)

### 2. Memória integrada no repo

- **ADR 0039** — `memory/decisions/0039-ui-chat-cockpit-padrao.md` (formato Nygard, contexto + decisão + alternativas + plano de migração)
- **CLAUDE.md** — adicionado **§10 Padrão de UI/UX para criar ou alterar telas no React** (instruções operacionais para qualquer agente que atuar no React do repo)
- **Session log** — este arquivo

### 3. Decisões registradas

- Padrão de UI do ERP em React = "Chat Cockpit" (3 colunas), formalizado em ADR 0039.
- Wagner autorizou refazer telas React livremente — ainda não usadas em produção pelos clientes finais (Copiloto, MemCofre, Financeiro, Site, parcial em Essentials/Ponto).
- Designer canônico das telas em React = **Claude**. Qualquer agente futuro que for criar/alterar tela consulta CLAUDE.md §10 + ADR 0039 antes.

## Trabalho residual

- 🟡 **Fase 1 do plano de migração** — portar protótipo pro repo (`resources/js/Layouts/AppShellV2.tsx`). Esforço estimado: 1 sessão.
- 🟡 **TaskProvider interface** — definir contrato PHP em `app/Contracts/TaskProvider.php` + `app/Services/TaskRegistry.php`. Implementar primeiro provider em Officeimpresso (`OsAprovarArteTask`).
- 🟡 **Decommission AppShell antigo** — só após Fase 4 (flag `useV2Shell` virar default).

## Refs

- ADR 0039 — [memory/decisions/0039-ui-chat-cockpit-padrao.md](../decisions/0039-ui-chat-cockpit-padrao.md)
- CLAUDE.md §10 — Padrão de UI/UX para criar ou alterar telas no React
- Projeto Cowork — "Oimpresso ERP Comunicação Visual"
- Protocolo MWART — session log 2026-04-25 (LegacyMenuAdapter + flag `inertia`)

## Próximo passo sugerido

Quando Wagner aprovar o protótipo v3 visualmente, abrir branch `feat/app-shell-v2` e portar o `AppShellV2.tsx` + `Pages/Tarefas/Index.tsx` pro repo, seguindo o padrão Jana e o ADR 0029.
