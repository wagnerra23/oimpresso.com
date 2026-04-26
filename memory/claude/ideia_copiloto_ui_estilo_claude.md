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

- [x] Reabrir branch `claude/copiloto-implement-real` ou criar novo? → **Reabrir, mas com foco enterprise** (qualidade, não scaffold cru)
- [x] Sidebar com conversas históricas: persiste em qual tabela? → **`copiloto_conversas` + `copiloto_mensagens`** (já existem, business_id-scoped). Vizra (`vizra_messages`) é estrutura paralela pra Wagner-internal — confirma que o shape tá certo, mas Copiloto tem o seu.
- [x] Streaming via SSE ou WebSocket (Pusher já configurado)? → **Pusher/Echo** (já temos)
- [x] FAB global vs página dedicada `/copiloto/chat` — manter ambos? → **Ambos** (FAB global + página dedicada)
- [x] Theme (Tailwind 4 já tem dark mode no Cms — replicar)? → **Enterprise design system, melhorar padrão Cms também**

## Plano fatiado (4 PRs)

| # | Branch | Conteúdo | Linhas |
|---|---|---|---|
| 1 | `claude/design-system-enterprise` | Audit Cms + tokens (cores, type, spacing) + componentes shadcn (Button/Card/Input/Sheet/ScrollArea/Avatar/Tooltip/CodeBlock) + tipografia (Inter ou Geist) + theme light/dark via CSS vars + aplica em 1-2 telas Cms como prova | ~500 |
| 2 | `claude/copiloto-chat-streaming` | Reabre `claude/copiloto-implement-real` extraindo só Chat.tsx; adiciona react-markdown + shiki/prism + streaming via Echo (Pusher); auto-scroll, copy-code, Cmd+Enter | ~400 |
| 3 | `claude/copiloto-chat-history` | Sidebar com `copiloto_conversas` (lista user/business), nova conversa, arquivar, troca de conversa carrega mensagens | ~300 |
| 4 | `claude/copiloto-fab-global` | FAB sempre visível em layout admin; `/copiloto/chat` full-screen dedicada; FAB abre Sheet com Chat embutido | ~200 |

Total: ~1400 linhas em 4 PRs vs 5485 do PR #13 monolítico (que foi rejeitado por tamanho).

## Recomendação de ordem

PR 1 primeiro (design system) — é base que serve Copiloto + Cms + qualquer tela futura.
PR 2 depende do 1 (componentes prontos).
PR 3 e 4 paralelos depois do 2.
