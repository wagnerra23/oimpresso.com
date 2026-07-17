---
slug: 0066-format-date-shift-3h-preservado-legacy-clientes
number: 66
title: "format_date com shift +3h preservado intencionalmente — quirk legacy ROTA LIVRE"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-04-24"
module: null
quarter: 2026-Q2
tags: [timezone, carbon, legacy, ux, rota-livre, format_date, dados-históricos]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0027-gestao-memoria-roles-claros, 0061-conhecimento-canonico-git-mcp-zero-automem]
pii: false
review_triggers:
  - "Quando alguém propor 'corrigir' format_date sem migration de dados históricos → bloquear até cumprir checklist"
  - "Quando outro cliente reclamar de horário diferente do esperado → primeiro perguntar 'qual horário ele decorou?' antes de assumir bug"
---

# ADR 0066 — format_date com shift +3h preservado intencionalmente — quirk legacy ROTA LIVRE

## Contexto

Em **2026-04-24** descobriu-se bug em `app/Utils/Util.php:297` (helper global `format_date()`):

```php
// Estado atual (preservado intencionalmente):
return ! empty($date) ? \Carbon::createFromTimestamp(strtotime($date))->format($format) : null;
```

**Carbon 3.x** (e 2.x) — `Carbon::createFromTimestamp($ts)` sem 2º argumento cria objeto em **UTC**, e formatar depois gera shift de timezone. Pra dados em `America/Sao_Paulo` armazenados como string SP, isso empurra +3h ao exibir. Reproduzido via tinker:

```
input:  2026-04-24 09:00:00
fromTimestamp(no tz): 24/04/2026 12:00 +0000   ← bug
parse:                24/04/2026 09:00 -0300   ← correto
```

Sintoma visível: vendas em SP aparecem 3h adiantadas em telas/recibos.

### Tentativa de fix e revert no mesmo dia

| Commit | Ação |
|---|---|
| `10634ad2` | fix(timezone): `format_date` preserva horário local em vez de converter para UTC — trocou `createFromTimestamp(strtotime($date))` por `Carbon::parse($date)` |
| `e5c8c90d` | **Revert** do `10634ad2` — regressão histórica ROTA LIVRE |

### Por que reverteu

Cliente **ROTA LIVRE** (`business_id = 4`, dona Larissa Fernandes, localização Termas do Gravatal/SC, timezone cadastrado `America/Sao_Paulo`) opera o sistema há meses com o shift +3h e **decorou os horários** nos recibos impressos e conferências de caixa. Reclamou imediatamente que "vendas antigas mudaram de horário". Fix matematicamente correto **quebrou a memória visual operacional** dela — usuária não sabe que era bug, sabe que "às 16h fechei caixa com R$ X" e os recibos antigos batem com isso.

ROTA LIVRE concentra **~99% do volume de vendas** do sistema (17.251+ vendas em 2 anos, 56 businesses cadastrados mas só 7 com vendas reais). Romper UX dela = romper o sistema na prática.

## Decisão

**`Util::format_date()` mantém o comportamento bugado (shift +3h) preservado** — não "corrigir" sem cumprir todo o checklist abaixo. Assertiva intencional, não bug pendente.

### Pré-condições obrigatórias pra reaplicar o fix

1. **Análise por cliente** de quanto shift cada base de dados tem
   - Hostinger pode ter mudado TZ do MySQL em algum ponto histórico — shift **não é universalmente** +3h
   - Olhar `time_zone` do business + `transaction_date` vs `created_at` em sample por cliente
2. **Migration de dados históricos**:
   ```sql
   UPDATE transactions SET transaction_date = DATE_ADD(transaction_date, INTERVAL X HOUR)
   WHERE business_id = ? AND created_at < '2026-04-24'
   ```
   onde `X` vem da análise por cliente (pode ser +3, 0, ou -3 dependendo do estado).
3. **Comunicação prévia com todos os clientes ativos** — não só ROTA LIVRE. Aviso 7+ dias antes informando "horários históricos serão recalculados".
4. **Reaplicar `Carbon::parse()`** em `format_date()` somente APÓS os 3 passos acima.
5. **Sentinela de regressão**: teste Pest que verifica `format_date('2026-04-24 09:00:00')` retorna string contendo "09:00" pra timezone SP — protege contra reaplicação inadvertida do fix sem migration.

## Justificativa

**Por que ADR formal e não auto-mem?**
Esse quirk impacta qualquer dev/agente que:
- Edite código que exibe `transaction_date` em tela
- Tente "consertar" `format_date()` por achar que é bug
- Crie endpoint novo de relatório com datetime
- Investigue queixa de timezone de cliente

Conhecimento operacional crítico — outro membro do time PRECISA saber antes de mexer. Critério de promoção atendido (ADR 0061 + ADR 0064).

**Por que não migrar os dados imediatamente?**
- ROTA LIVRE não pediu — pra ela o sistema funciona
- Migration cross-tenant exige análise individual; não é "rodar uma SQL universal"
- Custo (operacional + comunicação) > benefício (matemática correta) hoje
- Quando virar dor (ex: cliente novo reclama de horário), aí investe

**Por que sentinela em vez de só comentário no código?**
Dev distraído deleta comentário sem entender. Teste Pest falha visível em CI bloqueia merge.

## Consequências

**Positivas:**
- ROTA LIVRE continua operando sem regressão de UX
- Decisão documentada formalmente — próximo agente que ler `Util.php:297` encontra esse ADR via grep ou `decisions-search`
- Pré-condições explícitas evitam fix bem-intencionado mas destrutivo

**Negativas / Trade-offs:**
- Código novo precisa lembrar regra: usar `Carbon::parse()` em datetimes de DB, **mas alinhar com legacy** quando exibir `transaction_date` em tela compartilhada com legado (recibo, listagem, dashboard)
- Helper `format_date()` mente sobre o que é correto matematicamente — fonte de confusão recorrente pra dev novo
- Cliente novo que entrar agora herda o shift sem motivo histórico — onboarding precisa registrar baseline antes de virar quirk decorado

**Riscos mitigados:**
- Sentinela impede fix automático bem-intencionado (CI verde > revert noturno)
- ADR vinculado em `Util.php:297` (próxima edição cita ADR 0066) ajuda manutenção
- Outras helpers (`format_now_local`) seguem padrão correto — só `format_date` está bugado (ver ADR `feedback_format_now_local_e_default_datetime` de auto-mem, futuro ADR)

## Como aplicar (regras pro Claude / dev)

Em código novo que lê/formata datetime do DB:

| Fonte da data | Exibição em tela legada (Larissa-visível)? | Use |
|---|---|---|
| `created_at`, `updated_at` | Não | `Carbon::parse($timestamp)` ou Eloquent cast — correto |
| `transactions.transaction_date` | **Sim** (recibo, listagem /sells, dashboard) | **`format_date()` legacy** — preserva shift, alinha com expectativa |
| Datetime "agora" (form pré-preenchido) | Sim | `Util::format_now_local()` (helper novo, sem shift histórico) |
| Endpoint API novo (sem tela legacy) | Não | `Carbon::parse()` ISO 8601 — correto |

Se hesitar: **alinha com o que o usuário decorou**, não com o que é matematicamente correto. Pergunta ao Wagner se vai impactar tela compartilhada com legacy.

## Referências

- ADR 0061 — Conhecimento canônico = git → MCP (justifica esse ADR existir vs auto-mem)
- ADR 0064 — Modularização (separação Copiloto/TeamMcp/ADS contexto desta decisão)
- Commit `10634ad2` — fix aplicado e
- Commit `e5c8c90d` — revert do fix (motivo desse ADR existir)
- `app/Utils/Util.php:297` — código com bug preservado intencionalmente
- `tests/Unit/TimezoneMiddlewareTest.php` — testes timezone existentes (não cobrem `format_date` ainda — TODO)
- `memory/sessions/2026-04-24-sells-labels-and-timezone.md` — log da sessão de descoberta
- `memory/sessions/2026-04-24-consolidacao-final.md` — log do revert

### Quirks relacionados ao mesmo cliente

- ROTA LIVRE (`business_id = 4`) opera em monitor 1280px → telas com 21+ colunas precisam `columnDefs.visible: false` em colunas raras
- ROTA LIVRE digita `transaction_date` retroativo (até 17h de diferença do `created_at`) propositalmente — vendas em lote no fim do dia. **Não é bug** — é fluxo dela.
- Role `Vendas#4` precisa ter `location.4` explícita (não basta `permitted_locations='all'`)
