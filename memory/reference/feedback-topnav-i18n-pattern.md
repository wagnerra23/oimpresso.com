---
id: reference-feedback-topnav-i18n-pattern
name: Topnav.php aceita literal e i18n key (resolveLabel)
description: LegacyMenuAdapter::resolveLabel resolve tanto strings literais quanto chaves "modulo::file.key"; Jana migrou pra i18n em 2026-04-27 e setou precedente
type: feedback
originSessionId: c8fd0d09-0309-4bc2-a741-717921f4c8cf
---
`Modules/<Modulo>/Resources/menus/topnav.php` aceita 2 formatos no campo `label`:

1. **Literal**: `'label' => 'Dashboard'` — passa direto (PontoWr2, MemCofre, Officeimpresso usam assim)
2. **Chave i18n**: `'label' => 'copiloto::copiloto.menu.dashboard'` — `LegacyMenuAdapter::resolveLabel()` em `app/Services/LegacyMenuAdapter.php#180` chama `trans()` se a string contém `::`

**Why:** Wagner pediu em 2026-04-27 que a Jana "integrasse" tradução — o `topnav.php` tinha labels literais ("Conversar", "Dashboard"…) enquanto a lang file `Modules/Jana/Resources/lang/pt-BR/copiloto.php` já tinha as chaves `menu.*` correspondentes vindo do sprint 1 mas não eram consumidas pelo menu superior. PR #31 corrigiu.

**How to apply:**
- Pra módulos novos: já criar topnav.php usando chaves i18n — mantém uma fonte só de tradução (lang file)
- Pra módulos existentes (PontoWr2, MemCofre, Officeimpresso): NÃO migrar agressivamente — quebra teste de regressão se o lang file não tiver as chaves. Migrar só se for tocar no topnav.php por outro motivo
- Antes de migrar um módulo: confirmar que `Resources/lang/pt-BR/<modulo>.php` tem TODAS as chaves usadas no topnav (ex.: `module_label`, `menu.dashboard`, etc.)
- Pra fallback gracioso: se a chave não existe, `trans()` retorna a chave bruta — o menu vai mostrar "copiloto::copiloto.menu.dashboard" literal, o que é feio mas não quebra
