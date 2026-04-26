---
name: Copiloto UI estilo Claude (claude.ai)
description: Wagner pediu — interface online do Copiloto deve ser igual ao do claude.ai (chat minimalista, streaming, markdown, code blocks, conversation history). Existe scaffold em resources/js/Pages/Copiloto/Chat.tsx (319 linhas, criado em PR #13 fechado).
type: ideia
originSessionId: 018TdooDxragXjSEnhEmChCE
date: 2026-04-26
---

## Pedido literal

> "gostaria da interface online do copiloto, como ele seria? queria igual ao do claude"

## Contexto atual (2026-04-26)

- **Existe scaffold:** `resources/js/Pages/Copiloto/{Chat,Dashboard}.tsx` (554 linhas total) — criado em PR #13 (`claude/copiloto-implement-real`), fechado **sem merge**.
- **Status do código:** componentes rodam, mas branch `claude/copiloto-implement-real` está fora do `6.7-bootstrap`. PR #14 mergeado depois pegou só integração `/manage-modules`, **sem** as pages React.
- **FAB global** existe (`FabCopiloto.tsx`, 22 linhas) — botão flutuante chama Chat.

## Ingredientes "estilo Claude" (claude.ai)

| Ingrediente | Status no scaffold | Esforço pra ter |
|---|---|---|
| Layout 2 colunas (sidebar conversas + main chat) | parcial — Chat.tsx tem 2 colunas | revisar grid |
| Streaming text effect (token a token) | ❌ não tem | 1h — `EventSource` ou ReadableStream do AiAdapter |
| Markdown rendering com code blocks syntax-highlighted | ❌ texto plano | 30min — `react-markdown` + `prism-react-renderer` |
| Tipografia Inter / system-ui clean | ?  | conferir Tailwind config |
| Auto-scroll on new message | ?  | 15min |
| Copy code button | ❌ | 15min |
| Conversation history persisted (sidebar) | parcial — endpoint exists | conectar UI |
| Input com Cmd+Enter, multi-line, auto-resize | ?  | 30min |
| File/image attach | ❌ | 1-2h — endpoint upload + validation |
| Prompt suggestions ao abrir | ❌ | 30min — chips clicáveis |
| Tema light/dark | ?  | depende do design system Cms |

## Caminhos possíveis (ordem de ROI)

### Opção A — Reabrir/revisar branch `claude/copiloto-implement-real` (1-2h)
- Diff de Chat.tsx vs claude.ai → identifica gaps
- Adiciona streaming + markdown + code highlight como PR pequeno
- ROI: alto, código quase pronto

### Opção B — Reescrever Chat.tsx do zero (4-6h)
- Stack: Inertia v2 + React + Tailwind 4 + shadcn/ui + react-markdown + prism-react-renderer
- Estrutura: `<ChatLayout>` com `<ConversationSidebar>` + `<MessageList>` + `<MessageInput>`
- Backend: ChatController já existe; falta endpoint streaming SSE
- ROI: médio (mais trabalho, mas controle total)

### Opção C — Embutir biblioteca pronta (assistant-ui, llm-ui, ai-elements) (2-3h)
- assistant-ui: https://www.assistant-ui.com — copia visual do Claude/ChatGPT
- Stack já compatível: React + Tailwind
- ROI: alto se aceitar dependência externa

## Recomendação

**Opção A** — reusar o scaffold do PR #13. Razões:
1. Código existe, só falta polir.
2. PR #13 foi fechado por **tamanho** (5485 linhas), não por qualidade — fatiar em mini-PRs (streaming/markdown/code-highlight) torna mergeável.
3. Mantém alinhamento com decisões já tomadas em `memory/requisitos/Copiloto/adr/ui/0001-*`.

## Decisões pendentes pra confirmar com Wagner

- [ ] Reabrir branch `claude/copiloto-implement-real` ou criar novo?
- [ ] Sidebar com conversas históricas: persiste em qual tabela? (já existe `copiloto_conversas`, ver migration)
- [ ] Streaming via SSE ou WebSocket (Pusher já configurado)?
- [ ] FAB global vs página dedicada `/copiloto/chat` — manter ambos?
- [ ] Theme (Tailwind 4 já tem dark mode no Cms — replicar)?

## Decision log (quando virar PR)

Registrar em `memory/requisitos/Copiloto/adr/ui/0002-chat-streaming-markdown.md` quando começar a codar.
