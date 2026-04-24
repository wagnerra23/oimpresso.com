# Sessão — 2026-04-24 — Revert do fix de `format_date` (regressão em ROTA LIVRE)

## Contexto

Algumas horas depois do commit `10634ad2` ("fix(timezone): format_date preserva horario local em vez de converter para UTC") ter sido deployado no Hostinger, o cliente **ROTA LIVRE** (business em Gravatal/SC, BL0001 Termas do Gravatal) reclamou pro Wagner:

> "as vendas estão em horários errados — até as antigas mudaram"

## Diagnóstico

O fix revertido o shift +3h que o `format_date` empurrava no display. Matematicamente estava certo: vendas passaram a exibir o horário real do DB. Mas **na prática quebrou um status quo**: ROTA LIVRE operou o sistema por meses com vendas exibindo horário +3h à frente do real e decorou esses horários nos recibos impressos, conferências de caixa e rotina diária.

Sintoma do cliente: "todas as vendas voltaram 3 horas pra trás". Isso é o próprio fix em ação — mas pra ele é regressão de confiança.

## Decisão

Wagner aprovou **reverter imediatamente** (`git revert --no-edit 10634ad2` → commit `e5c8c90d`) pra parar a sangria.

Tradeoff:
- ✅ ROTA LIVRE volta a ver os horários "que ele lembra" (status quo bugado, mas estável)
- ✅ Operações em aberto não se confundem com horários inesperados
- ❌ Sistema continua exibindo datas de venda com +3h shift para todos os clientes
- ❌ Clientes novos vão onboardar num sistema matematicamente errado

## Plano futuro (não executado nesta sessão)

Pra reaplicar o fix de forma segura é necessário um plano maior:

1. **Levantamento por cliente**: rodar query no Hostinger avaliando se `transaction_date` vs `created_at` tem o mesmo offset (amostras de hoje mostraram mix de +3h e +14h — sugere que a Hostinger mudou TZ do MySQL em algum ponto do passado, o shift não é uniforme)
2. **Migration condicional**: `UPDATE transactions SET transaction_date = DATE_ADD(transaction_date, INTERVAL X HOUR) WHERE business_id = Y AND created_at BETWEEN ...` — uma para cada janela temporal identificada
3. **Comunicação prévia**: cada cliente ativo avisado antes do apply, com data e horário da mudança
4. **Reaplicar `format_date` com `Carbon::parse`** após a migration
5. **ADR formal** em `memory/requisitos/_SystemWide/adr/` documentando a decisão e o diff antes/depois

## Artefatos

- **Commit revertido**: [10634ad2](../..) — `fix(timezone): format_date preserva horario local em vez de converter para UTC`
- **Commit do revert**: [e5c8c90d](../..) — `Revert "fix(timezone): format_date preserva horario local em vez de converter para UTC"`
- **Testes removidos junto**: `tests/Unit/FormatDateTimezoneTest.php` (2 testes)
- **Memória atualizada**: `feedback_carbon_timezone_bug.md` — agora alerta explicitamente que o shift é intencional até plano de migração
- **Session anterior**: `2026-04-24-sells-labels-and-timezone.md` — contém o contexto do que foi feito e depois parcialmente revertido

## Lição aprendida

Corrigir bugs visíveis em sistemas em produção com dados históricos **não é só programação — é gestão de mudança**. Pra usuários que decoraram valores errados, a "correção" é ruptura. Antes de consertar algo que exibe dados ao usuário há meses, medir:

- Quanto tempo o bug existe?
- Os valores exibidos foram usados em algum suporte operacional (recibo, relatório, conferência)?
- Existe plano de migração dos dados + comunicação aos clientes?

Se as 3 respostas forem "sim, sim, não", **não corrija ainda**.
