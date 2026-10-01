---
sessao: "01"
titulo: Render órfão /atendimento/inbox
dono: "[CL]"
base: ca44a3d54cd2
---
# 01 · Atendimento/Inbox/Index sem Page

`InboxController:268` renderiza `Atendimento/Inbox/Index`; nenhum `Inbox/` em `Atendimento/` na árvore @ca44a3d54cd2. Antes de mexer: abrir `/atendimento/inbox` em staging e ver o que acontece (erro de resolver ou fallback).

Se D1 = redirecionar: `index()` passa a `redirect()` pra Caixa Unificada preservando `?thread=`, `?channel_id=`, `?tab=`. **Não tocar** `send`, `updateTags`, `blockContact` — a Caixa pode depender deles.

## Prova
No JSON do índice.
