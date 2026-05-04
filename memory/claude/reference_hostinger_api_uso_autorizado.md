---
name: Hostinger API — uso autorizado para infra
description: Wagner liberou Claude usar API Hostinger (developers.hostinger.com) sempre que necessário pra desbloquear tarefa de infra
type: reference
originSessionId: e1324d13-7148-4faa-9bee-1d5fbcc6286e
---
Wagner em 2026-04-30 liberou explícito: *"use o api da hostinger caso necessário"*. Posso chamar `developers.hostinger.com` sem perguntar quando for caminho mais direto.

**Endpoints úteis (auto-mem `reference_hostinger_dns_api.md` cobre DNS):**
- DNS: `https://developers.hostinger.com/api/dns/v1/zones/{domain}` PUT com `overwrite:false` (canônica) — ADR 0045
- VPS / hosting / Remote MySQL: ver docs developers.hostinger.com (api.hostinger.com tem HTTP 530 crônico — não usar)
- API token: já cadastrado no ambiente local Wagner (variável `HOSTINGER_API_TOKEN` ou similar — verificar `.env` ao precisar)

**Quando usar:**
- Whitelist Remote MySQL pra novo IP (CT 100, dev local).
- Adicionar registro DNS pra novo subdomínio (ex.: `mcp.oimpresso.com`, `realtime.oimpresso.com`).
- Verificar status de cert SSL Let's Encrypt da Hostinger.
- Qualquer operação que evite Wagner abrir hPanel manualmente.

**Quando NÃO usar:**
- Mudar conta/billing.
- Cancelar serviço.
- Mexer em e-mail/MX sem Wagner explícito (esses afetam fluxos críticos do negócio).

**Fluxo padrão:**
1. Tentar via API.
2. Se 530/timeout, fallback pra SSH ou hPanel manual (Wagner faz).
3. Anotar sucesso/falha em session log se mudança permanente foi aplicada.
