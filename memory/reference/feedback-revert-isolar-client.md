---
name: Antes de reverter PR em prod, isolar client-side primeiro
description: Quando Wagner reporta "travou" pós-merge, NÃO reverter de imediato — primeiro pedir pra abrir incognito/outro browser pra isolar se é client-state ruim vs bug real do código
type: feedback
---
Wagner reportou em 2026-05-12 (sessão musing-hopper, pós-Wave 4): **"travou"** após merge PR #706 (Sells badges Produção+Agrupada). Reverti via PR #712 em emergência protegendo ROTA LIVRE 99% volume. Backend respondia OK (HTTP 302 < 1s, sem 500). Browser MCP travou tentando smoke. Wagner DEPOIS disse "era só o browser, fechei e abri outro" — **não era bug do PR, era estado client-side ruim**.

Custo desnecessário do revert precipitado:
- 2 PRs extras (#712 revert + #713 restore-do-revert)
- 2 SSH pulls + 2 quick-syncs
- US-SELL-023/024 vai-volta de done→todo→done no MCP (audit log poluído)
- 50min de "prod indisponível" no relato do Wagner que era só o tab dele
- Diluiu confiança no diagnóstico

**Regra:** quando Wagner (ou qualquer dev) reportar "travou"/"quebrou"/"branco" pós-merge:

1. **PRIMEIRO** — pedir pra ele tentar:
   - Hard reload (Ctrl+Shift+R)
   - Incognito / outro browser
   - Fechar e abrir Chrome inteiro
   - Limpar localStorage do app: `Object.keys(localStorage).filter(k => k.startsWith('oimpresso')).forEach(k => localStorage.removeItem(k))`

2. **SE 1 falhar** — confirmar via 2 sinais independentes ANTES de reverter:
   - Backend curl HTTP probe (precisa retornar 500 ou timeout, não 302/401 — esses são saudáveis)
   - 2º browser/máquina diferente reproduz o trava

3. **SÓ ENTÃO** considerar revert. Browser MCP travado SOZINHO não é evidência suficiente — pode ser estado salvo do MCP extension acumulado de tentativas anteriores.

**Quando reverter direto está justificado:**
- HTTP 500 reproduzível em curl
- Tela branca em browser limpo (não só Wagner — pelo menos 2 superfícies)
- Erro fatal em log Laravel server-side (`tail -100 storage/logs/laravel.log` via SSH)
- Migration falhou (DB schema corrompido)

**Quando NÃO reverter direto:**
- "Tab travou" sozinho sem confirmar em outro browser
- Browser MCP frozen + curl OK (= problema é client-state acumulado)
- Wagner diz "lento" mas tela carrega eventualmente
- DevTools console mostra warnings mas não erros fatais

**Custo do revert:**
- 2 PRs (revert + eventual restore se foi falso alarme)
- 2 deploys (revert + restore se for restaurar)
- Audit log MCP poluído (vai-volta de status)
- Risco de quebrar outras coisas no caminho do revert (se há merges no meio)

Sempre **isolar 30s** vale mais do que **reverter 5min e descobrir que era falso alarme**.
