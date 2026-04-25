---
name: Carbon createFromTimestamp empurra UTC
description: Em Carbon 3.x (e 2.x), Carbon::createFromTimestamp($ts) sem 2º arg cria objeto em UTC — formatar depois gera shift de timezone. Nunca usar sem passar TZ explícito.
type: feedback
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
Em código novo: nunca chamar `Carbon::createFromTimestamp($ts)` ou `Carbon::createFromTimestamp(strtotime($str))` sem passar timezone no 2º argumento. Preferir `Carbon::parse($str)` ou passar `config('app.timezone')` explicitamente.

**MAS** em `app/Utils/Util.php::format_date()` **o comportamento bugado (shift +3h) é intencionalmente preservado** até que os dados históricos sejam migrados. Não "corrigir" sem plano completo.

**Why (histórico completo):**
1. Bug descoberto 2026-04-24: `Carbon::createFromTimestamp(strtotime($date))` em `format_date` cria em UTC, empurra +3h ao formatar. Vendas em SP aparecem 3h adiantadas.
2. Fix aplicado (commit `10634ad2`): trocado por `Carbon::parse($date)`.
3. **Revertido no mesmo dia** (commit `e5c8c90d`): cliente ROTA LIVRE (Gravatal/SC) reclamou que "vendas antigas mudaram de horário". Ele operava o sistema há meses com o shift +3h e decorou os horários nos recibos/conferências de caixa. Fix matematicamente correto quebrou a memória visual dele.
4. Sistema continua no status quo bugado até que:
   - Seja feita análise por cliente de quanto shift cada base de dados tem (Hostinger pode ter mudado TZ do MySQL em algum ponto — não é universalmente +3h)
   - Seja executada migration `UPDATE transactions SET transaction_date = DATE_ADD(...)` pra normalizar dados históricos
   - E AÍ então reaplicar `Carbon::parse()` no `format_date`

**How to apply:**
- Em código novo que lê/formata datetime do DB, usar `Carbon::parse()` — mas se a data vem de `transactions` ou tabelas afetadas pelo bug histórico, CUIDADO: pode mostrar horário diferente do que o cliente espera
- Se for criar novo endpoint que exibe `transaction_date` em tela legada (ex: recibo impresso), ALINHAR com o comportamento existente pra não causar inconsistência parcial
- Se `format_date()` for corrigido de novo no futuro, **obrigatório**: plano de migração de dados históricos + comunicação com todos os clientes ativos
- ADR candidate: registrar essa decisão em `memory/requisitos/_SystemWide/adr/` quando for virar decisão arquitetural formal
