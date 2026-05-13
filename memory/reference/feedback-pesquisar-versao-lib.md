---
name: Pesquisar versão mais nova quando lib externa dá erro (antes de reverter)
description: Antes de abandonar/reverter uma library externa que deu erro, pesquisar GitHub issues + changelog + versões intermediárias — pode ter fix conhecido ou workaround documentado
type: feedback
---
Quando uma library externa (npm, composer, etc) dá erro num projeto, **NÃO reverter direto pra versão anterior sem pesquisar**. A maioria das libs ativas tem:

1. **Issues abertas no GitHub** com workarounds
2. **Versão intermediária** que resolve o bug sem breaking change
3. **Branch/fork de community** mantida
4. **Notes de migration** pra mudanças breaking

**Why:** 2026-05-11 perdi ~2h tentando Baileys 6.7.9 (versão pinned no projeto) que tinha bug "Connection Failure" recorrente. Subi pra 6.7.18 latest → ERR_REQUIRE_ESM (virou ESM-only). **Reverti pra 6.7.9 sem pesquisar** se 6.7.10/6.7.13/6.7.16 (intermediárias) eram CommonJS-compatible E sem o bug. Resultado: Wagner ficou com Baileys quebrado por causa de duas decisões binárias quando havia caminho intermediário.

**How to apply:** Quando erro aparecer em lib externa, ANTES de reverter:

1. Procurar no GitHub do projeto: `is:issue <error message exato>`
2. Ler changelog/RELEASES desde a versão atual até latest
3. Testar versões intermediárias (`npm view <pkg> versions --json | tail -10`)
4. Procurar issues "stuck on X" / "downgrade to Y" pra encontrar last-good-version
5. Se nada disso resolver, considera fork/alternativa antes de reverter

**Aplica em particular pra:**
- `@whiskeysockets/baileys` (Whatsapp Web reverse-engineered — muda rápido)
- Bibliotecas Inertia / Laravel ai (em evolução ativa)
- Drivers oficiais (Meta Graph SDK, etc)
- Qualquer dependência com `^` ou `~` no composer/package.json
