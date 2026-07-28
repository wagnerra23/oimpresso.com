---
title: "RUNBOOK — Patch WP /ajuda/ pra PHP 8.4 (`create_function` removido)"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — Patch WP /ajuda/ pra PHP 8.4 (`create_function` removido)

> **Quando usar:** se `oimpresso.com/ajuda/` voltar a dar HTTP 500 com erro `Call to undefined function create_function()`.

**Servidor:** Hostinger, `/home/u906587222/domains/oimpresso.com/public_html/ajuda/`
**WP:** 6.1.10 · **Tema:** `knowledge-base` (custom) · **Plugin afetado:** `ht-knowledge-base`

## Causa-raiz

PHP 8.4 removeu `create_function()` (deprecated em 7.2, gone em 8.0). O plugin `ht-knowledge-base` tem 6 chamadas no padrão:

```php
add_action('widgets_init', create_function('', 'register_widget("XYZ");'))
```

## Patch aplicado (2026-04-25)

Substituição via `s/create_function('', 'register_widget("(\w+)");')/function(){register_widget("\1");}/`:

| Arquivo | Linha |
|---|---|
| `wp-content/plugins/ht-knowledge-base/widgets/widget-kb-toc.php` | 150 |
| `wp-content/plugins/ht-knowledge-base/widgets/widget-kb-categories.php` | 245 |
| `wp-content/plugins/ht-knowledge-base/widgets/widget-kb-articles.php` | 311 |
| `wp-content/plugins/ht-knowledge-base/widgets/widget-kb-search.php` | 115 |
| `wp-content/plugins/ht-knowledge-base/widgets/widget-kb-authors.php` | 263 |
| `wp-content/plugins/ht-knowledge-base/exits/php/widget-kb-exits.php` | 154 |

Backup do plugin pré-patch em `ht-knowledge-base.bak.20260425-195657/` (mesmo dir).

## Reversibilidade

```bash
cd ~/domains/oimpresso.com/public_html/ajuda/wp-content/plugins
rm -rf ht-knowledge-base
mv ht-knowledge-base.bak.20260425-195657 ht-knowledge-base
```

## ⚠️ ARMADILHA — update wp-admin reverte o patch

Se Wagner clicar **"Atualizar plugin"** no wp-admin, o WP baixa a versão original do repositório e o `create_function()` volta — site quebra de novo.

**Soluções de longo prazo:**
1. Substituir o plugin por algo mantido (ex: BetterDocs, BasePress)
2. Reaplicar o patch após cada update (este runbook é a receita)

## Diagnóstico (caso reincida)

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html/ajuda && \
   php -d display_errors=1 -r 'require \"wp-load.php\";'"
```

Se aparecer `Fatal error: Uncaught Error: Call to undefined function create_function()` em alguma das 6 linhas listadas acima — reaplicar patch.
