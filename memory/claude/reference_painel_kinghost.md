---
name: KingHost — painel de DNS de wr2.com.br
description: Acesso ao painel KingHost. Hospeda DNS do domínio wr2.com.br (não migrar pra cá — usa-se oimpresso.com em Hostinger). Manter pra zonas DNS legadas, e-mails da empresa, etc
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
**Painel web:** https://painel.kinghost.com.br/index.php
**Login:** `eliana@wr2.com.br`
**Senha do painel:** `4EwAv#MrA#` ✅ (confirmado por Wagner em 2026-04-28: "4EwAv#MrA# senha do painel de controle")
**Token Wscrct*2312** — Wagner mencionou mas testado contra `https://api.uni5.net/cliente|/dominio|/dns` em todos os formatos retornou 401. Pode ser senha de outro contexto (e-mail @wr2.com.br?), 2FA, ou token expirado. **Status: a investigar quando precisar usar API Uni5.**

**API Uni5** (`https://api.uni5.net/`):
- Wagner declarou que KingHost roda na Uni5 (`api.uni5.net/cliente` etc.)
- Token `Wscrct*2312` testado em 2026-04-28: retornou **401 Unauthorized** em `Bearer`/`Token`/header direto/query param em endpoints `/cliente`, `/dominio`, `/dns`
- **Token API real ainda desconhecido** — pedir Wagner gerar token específico no painel KingHost se precisarmos automatizar DNS de `wr2.com.br` via Claude

**NÃO é prioridade:** subdomínios do projeto ficam em `oimpresso.com` (Hostinger, API funciona). KingHost só pra coisas legadas/e-mails.

**Pra que serve:**
- Hospeda zona DNS de `wr2.com.br` (domínio da empresa WR2 do Wagner — diferente da WR2 Sistemas / Eliana(WR2) que é cliente do PontoWr2)
- Pode ter e-mails @wr2.com.br configurados
- NÃO usar pra `oimpresso.com` (esse fica na Hostinger — ver `reference_hostinger_*`)

**Quando usar:**
- Adicionar/editar registros DNS de subdomínios `*.wr2.com.br`
- Renovar plano de hospedagem ou domínio
- Configurar e-mails da empresa

**Cuidado:**
- Senha tem caracteres especiais (`#`) — escapar em scripts
- Login é o e-mail da Eliana (esposa do Wagner) — provavelmente compartilhado com Wagner

**Decisão atual (2026-04-28):** os subdomínios `reverb`, `portainer`, `traefik`, `vault` ficam em `*.oimpresso.com` (Hostinger), não em `*.wr2.com.br`. KingHost só pra coisas legadas/e-mails da empresa.
