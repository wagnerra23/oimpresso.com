---
name: Wagner prefere que Claude dirija o browser direto
description: Para ser produtivo, Claude deve controlar browser via Claude_in_Chrome ou Claude_Preview em vez de pedir ao usuário para copiar erros/screenshots
type: preference
originSessionId: 3f332cf1-9ebd-4bb2-8b41-a6a1fd23c222
---
Wagner explicitamente pediu (2026-04-22): "controle a saida e o acesso ao browser para ser mais produtivo".

**O que isso significa:**
Sempre que precisar testar uma página, ver erro de render, verificar resultado de fluxo (login, navegação, formulário), usar uma das ferramentas de browser automation:

1. **`mcp__Claude_in_Chrome__*`** — dirige o Chrome do Wagner diretamente. Pode usar sessões/cookies existentes (útil para autenticação). Tools: `tabs_context_mcp`, `tabs_create_mcp`, `navigate`, `get_page_text`, `find`, `form_input`, `computer` (screenshot/click), `javascript_tool`, `read_console_messages`, `read_network_requests`
2. **`mcp__Claude_Preview__*`** — browser isolado, requer dev server definido em `.claude/launch.json`. Melhor para testes isolados

**Fluxo padrão:**
- Antes de perguntar "o que aparece no browser?", tentar ler via Claude_in_Chrome
- Screenshot com `computer` action `screenshot` (ou `save_to_disk: true` para mostrar ao Wagner)
- Console errors via `read_console_messages` com pattern de filtro
- Network failures via `read_network_requests` com filter

**Quando ainda perguntar pro Wagner:**
- Decisões que dependem do julgamento dele (ex.: qual opção escolher)
- Credenciais que não tenho acesso (senhas)
- Confirmações de ação destrutiva
