# INFRA.md — Acesso, deploy, fixes de produção

> **Para quem é este arquivo:** quem precisa subir código pra `oimpresso.com` (Hostinger), debugar produção via SSH, ou aplicar patches manuais que não cabem no fluxo git/CI.
>
> Pra fluxo de trabalho diário (ler memória, convenções, padrões de código), comece em [`CLAUDE.md`](CLAUDE.md). Pra qualquer coisa visual, em [`DESIGN.md`](DESIGN.md).

---

## 1. Servidor de produção (Hostinger Cloud Startup)

```
Host: 148.135.133.115
Port: 65002
User: u906587222
Key:  ~/.ssh/id_ed25519_oimpresso
Repo: ~/domains/oimpresso.com/public_html      (Laravel)
DB:   u906587222 / o51617061                    (MySQL, no mesmo host)
PHP:  /usr/bin/php          (8.4.19)
Composer: /usr/local/bin/composer
```

**IPv4 only** — sempre `-4`. Sem isso o handshake falha intermitentemente.

**Comando padrão:**
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 -o ConnectTimeout=60 u906587222@148.135.133.115 'CMD'
```

---

## 2. Cuidados com SSH

- SSH é **flaky**: 1ª tentativa frequentemente dá timeout. Receita: `curl -s4 https://oimpresso.com/ > /dev/null` pra warm e retry com `ConnectTimeout=120`.
- Multiplexing (`ControlMaster=auto`) **não funciona** no Hostinger — uma conexão por comando.
- **Nunca editar arquivo direto via SSH** — sempre `git pull` no repo. Bypass git = drift permanente (já queimou Eliana no 3.7→6.7).

---

## 3. Deploy

**Após push na branch principal (atualmente `main`) com mudança em `composer.json`/`composer.lock`:** rodar `composer install` (sem `--no-dev` — Faker é usado em prod). O workflow `quick-sync.yml` NÃO faz isso; sintoma de skip é tela branca Inertia. Ver `memory/sessions/` para incidente do upgrade Inertia v3.

**Receita de deploy manual** (quando `quick-sync.yml` falhar — ver auto-memória `reference_quick_sync_quebrada.md`):
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   git pull origin main && \
   php artisan optimize:clear && \
   composer dump-autoload"
```
Se `composer.lock` mudou, trocar `dump-autoload` por `composer install`.

**Branch hardcoded em workflows CI:** `.github/workflows/deploy.yml` e `quick-sync.yml` ainda referenciam `6.7-bootstrap` em algumas linhas — PR de cleanup pendente (ver handoff).

---

## 4. Patches manuais ativos

- **WP `/ajuda/`** tem patch manual de PHP 8.4 (`create_function` → closures) — atualização via wp-admin reverte; ver auto-memória `reference_wp_ajuda_fix.md` se precisar repatchar.

---

## 5. Dev local (Herd / Laragon)

- Site: `https://oimpresso.test` (Herd SSL)
- MySQL: Laragon `127.0.0.1:3306` root sem senha, DB `oimpresso`
- PHP: 8.4 Herd
- Login: `WR23` / `Wscrct*2312`
- Meilisearch local: `http://127.0.0.1:7700` (PID auto, ver `D:\oimpresso.com\meilisearch\`)

---

> **Última atualização:** 2026-04-28 (extraído do CLAUDE.md §8 pra centralizar tudo de produção/infra num arquivo só)
