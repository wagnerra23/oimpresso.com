---
name: Hostinger server access for oimpresso.com
description: SSH endpoint and deploy path for the oimpresso.com production server on Hostinger. Claude/sandbox CAN SSH directly (key at ~/.ssh/id_ed25519_oimpresso).
type: reference
originSessionId: 0922b4af-6c32-45e6-ae30-5d09580ae4ca
---
Servidor produção oimpresso.com (Hostinger):

- **SSH:** `ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115` (sempre `-4` pra IPv4)
- **Deploy path:** `/home/u906587222/domains/oimpresso.com/public_html` (ou `domains/oimpresso.com/public_html` relativo ao home)
- **Branch de deploy:** `6.7-bootstrap` (GitHub `wagnerra23/oimpresso.com`)
- **Branch `producao`:** estado real do servidor (90k+ arquivos)
- **Servidor em UTC** — horário do servidor = UTC; BR é UTC-3

**Claude/sandbox SSHa direto** (key instalada em 2026-04-23):
- `ConnectTimeout=600` ou mais — conexão é lenta/flaky
- Frequente dar `connection timed out` — normalmente resolvido com `curl https://oimpresso.com/login` antes pra "aquecer" a rota + retry
- Não fazer sleep entre commands; usar `run_in_background` ou Monitor com until-loop

**Deploy típico (Claude pode fazer):**
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   git fetch origin 6.7-bootstrap && \
   git reset --hard origin/6.7-bootstrap && \
   php artisan view:clear && php artisan config:clear && php artisan cache:clear"
```

**Workflow `.github/workflows/deploy.yml`** existe mas é manual (workflow_dispatch). `quick-sync.yml` auto-dispara em push pra `6.7-bootstrap` mudando `resources/`, `public/`, `config/`, `routes/`, `lang/` ou `app/` — mas precisa das secrets `SSH_PRIVATE_KEY`/`SSH_HOST`/`SSH_PORT`/`SSH_USER` configuradas no GH.

**Ver também:** `feedback_hostinger_ipv4.md` (IPv4 obrigatório).
