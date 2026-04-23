---
name: Hostinger server access for oimpresso.com
description: SSH endpoint and deploy path for the oimpresso.com production server on Hostinger
type: reference
originSessionId: 3f332cf1-9ebd-4bb2-8b41-a6a1fd23c222
---
Servidor produção oimpresso.com (Hostinger):

- **SSH:** `ssh -p 65002 u906587222@148.135.133.115` (sempre `-4` para forçar IPv4)
- **Deploy path:** `/home/u906587222/domains/oimpresso.com/public_html` (ou `domains/oimpresso.com/public_html` relativo ao home)
- **Branch de deploy:** `6.7-bootstrap` (GitHub `wagnerra23/oimpresso.com`)
- **Branch `producao`:** estado real do servidor com 90k+ arquivos (não é o branch de desenvolvimento)

**Quem executa no servidor:** Eliana (cliente) — Claude/sandbox não conecta direto. Tarefas típicas:
```bash
git pull origin 6.7-bootstrap
php artisan module:enable PontoWr2
php artisan cache:clear && php artisan config:clear && php artisan view:clear
composer dump-autoload
```

**Ver também:** `feedback_hostinger_ipv4.md` (IPv4 obrigatório).
