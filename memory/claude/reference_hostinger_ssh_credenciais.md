---
name: Hostinger SSH credenciais (descoberto via hPanel 2026-04-26)
description: SSH em 148.135.133.115:65002 user u906587222 com chave id_ed25519_oimpresso; repo Laravel em ~/domains/oimpresso.com/public_html
type: reference
originSessionId: 866e50c8-744a-42e4-8e79-7470bb472801
---
Credenciais SSH oficiais do servidor de produção (Hostinger Cloud Startup):

```
Host: 148.135.133.115
Port: 65002
User: u906587222
Key:  ~/.ssh/id_ed25519_oimpresso
```

Comando padrão (sempre `-4` per regra IPv4):
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 -o ConnectTimeout=60 -o BatchMode=yes u906587222@148.135.133.115 'COMANDO'
```

**Why:** Memory anterior dizia "SSH funciona direto" mas não tinha host/port/user. Sem essas info eu não conseguia SSH. Achado via hPanel → Sites → oimpresso.com → Avançado → Acesso SSH em 2026-04-26.

**How to apply:**
- Use SEMPRE `-4` (IPv4 forçado) per memória `feedback_hostinger_ipv4`.
- `ConnectTimeout=60` mínimo — Hostinger às vezes leva 30-50s pra primeiro handshake.
- SSH é **intermitente** — primeiro try frequentemente dá timeout, segundo conecta. Implementar retry loop com até 3 tentativas + sleep 8s entre elas.
- BatchMode=yes pra falhar rápido em caso de prompt de senha (não queremos prompts em script).
- Repo Laravel: `~/domains/oimpresso.com/public_html` (NÃO em `~/domains/oimpresso.com/` direto — esse só tem backup .sql + tarball).
- Composer está em `/usr/local/bin/composer`. PHP em `/usr/bin/php` (PHP 8.4.19).
- NÃO usar `composer install --no-dev` (memória `composer_install_obrigatorio_pos_deploy` — Faker é usado em prod).
- Usuário do banco/grupo: u906587222/o51617061.
- IP do servidor (atribuído ao SSH): 148.135.133.115.

## Multiplexing não funciona

Tentei `ControlMaster=auto` + `ControlPath` em 2026-04-26 — falhou com "mux_client_request_session: read from master failed". Hostinger SSH não suporta multiplexing direito. Use uma conexão por comando.

## Quando SSH for usado

- Deploy manual (quick-sync.yml está quebrado — ver `reference_quick_sync_quebrada.md`)
- Diagnóstico de problemas em produção (ver tabelas, run artisan, etc)
- Clear cache pós-deploy: `php artisan optimize:clear`
- NUNCA editar arquivo direto via SSH — sempre via git pull. Bypass git = drift permanente.
