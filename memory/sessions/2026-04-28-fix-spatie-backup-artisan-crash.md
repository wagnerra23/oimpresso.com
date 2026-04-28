# Sessão — 2026-04-28 — fix: spatie/laravel-backup quebra artisan

## Contexto

Wagner reportou que TODA chamada `php artisan <qualquer-coisa>` no projeto crasheia com:

```
Spatie\Backup\Exceptions\InvalidConfig: is not a valid email address.
at vendor/spatie/laravel-backup/src/Exceptions/InvalidConfig.php:11
chamado de vendor/spatie/laravel-backup/src/Config/NotificationMailConfig.php:27
```

Descoberto enquanto rodava smoke local do Copiloto (worktree `claude/eager-lichterman-683ca0`),
ao tentar `php artisan config:clear` após editar `.env` local pra fix de `OPENAI_API_KEY`.

Versão real do pacote: `spatie/laravel-backup ^10.0` (não `^9` como sugerido inicialmente — mas validação fail-fast é a mesma).

## Diagnóstico

1. `config/backup.php:173` lia `env('MAIL_FROM_ADDRESS', 'hello@example.com')`
2. `.env - Copia.example:38` define `MAIL_FROM_ADDRESS=` (string vazia, não ausente)
3. `env()` retorna **string vazia** quando a chave existe vazia → o default `'hello@example.com'` **não** é usado
4. spatie/laravel-backup ^10 valida na bootstrap dos providers (`NotificationMailConfig::__construct`) e `fail-fast` quando o "to" não é email válido
5. Requests web não bootstrap o provider de console, então só comandos artisan eram afetados

## O que foi feito

Worktree: `claude/sharp-williamson-d8767b`

1. **`config/backup.php`** (linhas 172-183): trocado `env(KEY, 'default')` por `env(KEY) ?: 'default'` para
   também cair pro default em string vazia. Adicionado `BACKUP_NOTIFICATION_MAIL_TO` como override
   explícito (cai pra `MAIL_FROM_ADDRESS`, e por fim `hello@example.com`).
2. **`.env - Copia.example`**: `MAIL_FROM_ADDRESS` agora vem com default `noreply@oimpresso.com` e
   adicionado `BACKUP_NOTIFICATION_MAIL_TO=` (vazio mas comentado, com explicação do gotcha).

## Validação local

Aplicado **temporariamente** no checkout principal (`D:\oimpresso.com`, branch `work-main-mirror`,
clean) pra rodar artisan com vendor instalado:

- `php artisan config:clear` → `INFO  Configuration cache cleared successfully.` ✅ exit 0
- `php artisan list` → `Laravel Framework 13.6.0` ✅ (PowerShell 5.1 retorna exit 255 por causa do
  `2>&1` em native exe, mas a saída mostra que rodou)

Após validação, `git checkout -- config/backup.php` no main checkout reverteu o teste; checkout
voltou clean. Fix permanente fica só na branch `claude/sharp-williamson-d8767b`.

## Hostinger — validado, prod NÃO afetado

1ª tentativa de SSH deu `Connection timed out` (com `ConnectTimeout=30`). Wagner apontou que eu
ignorei a receita oficial em [`INFRA.md`](../../INFRA.md): SSH Hostinger é flaky por design, sempre
`curl -s4 https://oimpresso.com/ > /dev/null` pra warm + `ConnectTimeout=120` no SSH.

Aplicada a receita certa, validação read-only em prod:

```
$ ssh -4 -i ~/.ssh/id_ed25519_oimpresso -o ConnectTimeout=120 -p 65002 u906587222@148.135.133.115 \
    'cd ~/domains/oimpresso.com/public_html && grep ^MAIL_FROM .env && php artisan list 2>&1 | head -3'
MAIL_FROM_ADDRESS="no-reply@wr2.com.br"
MAIL_FROM_NAME="WR2 Sistemas"
Laravel Framework 13.6.0
EXIT=0
```

**Conclusão:** prod tem `MAIL_FROM_ADDRESS` preenchido (provavelmente desde quando configuraram
notificações pro WR2), então a config não bate na string vazia, não há crash. O bug só afeta:

1. Dev clonando o repo e copiando `.env.example` literal sem editar
2. Dev "resetando" `MAIL_FROM_ADDRESS=` durante debug (caso do Wagner — mexendo no `.env` pra
   ajustar `OPENAI_API_KEY` e por algum motivo apagou também o `MAIL_FROM_ADDRESS`)

O fix em `config/backup.php` continua valendo como **medida defensiva** (proteger contra
regressão se alguém esvaziar a var em prod no futuro), e o fix em `.env.example` evita a mesma
pegadinha pra próximo dev. Mas não há urgência de deploy em prod — fica pro próximo deploy
normal qualquer.

## Lição

`reference_hostinger_server.md` em auto-memória já mencionava IPv4. Faltava warm com curl +
timeout grande. Deveria ter lido [`INFRA.md`](../../INFRA.md) antes do 1º SSH em vez de chutar o
timeout. Atualizar auto-memória.

## Definition of Done

- [x] `config/backup.php` patcheado (string vazia → default)
- [x] `.env.example` atualizado com fallback sensato + `BACKUP_NOTIFICATION_MAIL_TO` documentado
- [x] `php artisan config:clear` valida limpo (exit 0) com fix aplicado
- [x] Session log criado (este arquivo)
- [x] Hostinger validado — prod NÃO afetado (`MAIL_FROM_ADDRESS` preenchido)
- [ ] PR mergeado em main

## Decisões

- **Não publiquei** `vendor/spatie/laravel-backup` original (sem patch nele) — fix vai em
  `config/backup.php` (que é nosso) por ser mais durável: `composer update` não rever te.
- **Adicionei `BACKUP_NOTIFICATION_MAIL_TO`** como var dedicada mesmo que `MAIL_FROM_ADDRESS`
  cubra o caso, porque deixa explícito pra ops em prod separar destinatário de notificação de
  backup do remetente geral do sistema.
- **Não criei ADR** — fix de bug pontual, não decisão arquitetural.

## Próximos passos

1. Wagner: revisar diff e mergear PR (branch `claude/sharp-williamson-d8767b`).
2. Após merge, rodar deploy normal no Hostinger (quick-sync action ou SSH manual).
3. Confirmar `php artisan optimize:clear` rodando limpo em prod.
4. (Opcional) Setar `MAIL_FROM_ADDRESS=noreply@oimpresso.com` direto no `.env` de prod —
   independente do fix de código, deixa o sistema com email de remetente real (relevante quando
   habilitar notificação de backup falhada).
