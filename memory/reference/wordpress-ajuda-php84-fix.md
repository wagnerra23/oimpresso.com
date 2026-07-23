---
id: reference-wordpress-ajuda-php84-fix
name: WP /ajuda/ fix create_function PHP 8.4
description: O WordPress 6.1.10 em /ajuda/ quebrou com PHP 8.4 porque o plugin ht-knowledge-base usa create_function() (removido no PHP 8.0). Patch aplicado 2026-04-25 trocando 6 ocorrências por closures em widget-kb-{toc,categories,articles,search,authors}.php + exits/php/widget-kb-exits.php. Backup em ht-knowledge-base.bak.20260425-195657. ATENÇÃO: atualizar o plugin pelo wp-admin sobrescreve o patch.
type: reference
originSessionId: 78bc6849-f503-4b7f-93a1-4c2a439cc019
---
**Servidor:** Hostinger, `/home/u906587222/domains/oimpresso.com/public_html/ajuda/`
**WP:** 6.1.10 | **Tema:** `knowledge-base` (custom) | **Plugin afetado:** `ht-knowledge-base`

**Causa-raiz:** PHP 8.4 removeu `create_function()` (deprecated em 7.2, gone em 8.0). Plugin tem 6 chamadas no padrão:
```php
add_action('widgets_init', create_function('', 'register_widget("XYZ");'))
```

**Patch aplicado** (`s/create_function('', 'register_widget("(\w+)");')/function(){register_widget("\1");}/`):
- `wp-content/plugins/ht-knowledge-base/widgets/widget-kb-toc.php:150`
- `.../widget-kb-categories.php:245`
- `.../widget-kb-articles.php:311`
- `.../widget-kb-search.php:115`
- `.../widget-kb-authors.php:263`
- `.../exits/php/widget-kb-exits.php:154`

**Reversibilidade:**
```bash
cd ~/domains/oimpresso.com/public_html/ajuda/wp-content/plugins
rm -rf ht-knowledge-base
mv ht-knowledge-base.bak.20260425-195657 ht-knowledge-base
```

**ARMADILHA:** se Wagner clicar "Atualizar plugin" no wp-admin, o WP baixa a versão original do repositório e o create_function() volta — site quebra de novo. Solução longo prazo: substituir o plugin por algo mantido (ex: BetterDocs, BasePress) ou reaplicar patch após cada update.

**Diagnóstico** (caso reincida):
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html/ajuda && php -d display_errors=1 -r 'require \"wp-load.php\";'"
```
