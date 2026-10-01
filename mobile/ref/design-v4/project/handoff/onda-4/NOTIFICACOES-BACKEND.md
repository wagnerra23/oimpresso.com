# Notificações — o que falta no servidor (Onda 4)

**Não escrito aqui:** o código do servidor tRPC (`@/server/routers`) não foi lido neste trabalho. Isto é a especificação para quem for implementar.

## Já existe no app
- `hooks/use-push-registration.ts` registra o aparelho (montado em `_layout.tsx` → `PushBootstrap`).
- Tela `app/notificacoes/index.tsx` — hoje com estado local.

## Falta
1. **Tabela `notifications`**: `id`, `company_id`, `user_id`, `origin` (OS | CRM | FIN | PNT | MFG | OFI — mesmo `OiOriginKey`), `title`, `body`, `target` (rota do app, ex. `/oss/123`), `read_at` (null = não lida), `created_at`. Índice `(company_id, user_id, read_at)`.
2. **Rotas tRPC**: `notifications.list({ cursor })`, `notifications.markRead({ id })`, `notifications.markAllRead()`, `notifications.unreadCount()`.
3. **Emissores** (quem cria a linha + push): orçamento aprovado pelo link, título vencido, OP iniciada/concluída, proposta visualizada, marcação de ponto pedindo justificativa.
4. **App**: trocar o estado local da tela por `notifications.list`; o sino do Início lê `unreadCount`; tocar → `markRead` + `router.push(target)` (comportamento da tela 16 do espelho).
