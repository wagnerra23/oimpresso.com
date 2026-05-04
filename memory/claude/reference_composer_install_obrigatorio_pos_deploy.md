---
name: composer install é obrigatório no servidor após push em main
description: Após qualquer push pra branch main que altere composer.json/composer.lock (ex: upgrade Inertia v2→v3 do ADR 0023), é OBRIGATÓRIO rodar composer install no servidor Hostinger via SSH. quick-sync.yml NÃO faz isso. Sintoma: tela branca em rotas Inertia ("Cannot read properties of null reading component") porque vendor/ está em versão antiga e payload é da versão nova. Resolvido em 2026-04-25 com composer install --optimize-autoloader (com dev deps — Faker é usado em prod). Nota: branch foi 6.7-bootstrap até 2026-04-27 quando virou main.
type: reference
originSessionId: 78bc6849-f503-4b7f-93a1-4c2a439cc019
---
**Sintoma:** Após push de mudança que envolve composer.json/lock (ex: nova dep, upgrade major), o site fica com **tela branca** em rotas Inertia. Console: `TypeError: Cannot read properties of null (reading 'component')` em `app-XXXX.js:138`.

**Causa raiz (caso 2026-04-25, Inertia v2→v3):**
- `composer.json`: `"inertiajs/inertia-laravel": "^3.0"` ✅
- `composer.lock`: `v3.0.6` ✅
- Bundle JS (frontend): `@inertiajs/react ^3.0.3` ✅ (vai pelo build Vite)
- **`vendor/inertiajs/inertia-laravel/`: AINDA v2.0.24** ❌ (composer install nunca foi rodado)

Mismatch: backend manda payload v2 (sem `clearHistory`/`encryptHistory`), JS espera v3, **client crasha** ao tentar interpretar.

**Fix correto:**
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   composer install --optimize-autoloader --no-interaction && \
   php artisan optimize:clear"
```

**Atenção: NUNCA usar `--no-dev`** em produção neste projeto:
- Algum ServiceProvider/Module está usando `Faker\Generator` que está em `require-dev`
- Sem Faker, `php artisan package:discover` falha → site cai pra HTTP 500
- Em 2026-04-25 caiu temporariamente; resolvido restaurando dev deps
- TODO: identificar quem usa Faker em prod e mover pra `require` (não `require-dev`)

**Quando rodar:**
- ✅ Sempre que `composer install`/`composer require`/`composer update` for usado local
- ✅ Sempre que mergear PR que mexa em `composer.json` ou `composer.lock`
- ❌ Não precisa se só mudou código PHP/JS sem deps novas

**Sinal de smoke test pós-deploy (validar v3):**
```bash
ssh ... "cd ... && composer show inertiajs/inertia-laravel | grep versions"
# Deve mostrar 'versions : * v3.0.6' (ou versão atual desejada)
```

**Ver também:**
- `reference_hostinger_server.md` — comandos SSH base
- `project_inertia_v3_upgrade.md` — contexto do upgrade
- ADR 0023 (no repo) — decisão original

**Como `quick-sync.yml` deveria ser melhorado** (TODO):
- Adicionar step `composer install --optimize-autoloader` após git pull
- Validar diff pra detectar mudança em composer.lock
- Notificar Wagner se composer install falhar
