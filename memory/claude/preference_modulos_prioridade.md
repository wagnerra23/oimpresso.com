---
name: Preferências de priorização dos módulos
description: Decisões do Wagner sobre cada módulo do UltimatePOS do oimpresso.com — usar pra planejar ordem de migração e o que restaurar
type: preference
originSessionId: 3f332cf1-9ebd-4bb2-8b41-a6a1fd23c222
---
Decisões explícitas do Wagner (2026-04-22):

## Grow ⭐ prioridade produção
- **O que é:** sistema do CodeCanyon (item 32094844 "Perfect Support / Ticketing / Document Management System") instalado como módulo
- **Decisão:** usar pra **parte de produção do Office Impresso**
- **Wagner:** "é ótimo. preciso fazer aqui na parte de produção. ver a viabilidade"
- **Tamanho:** gigante (797 rotas, 957 views) — tratar como sub-sistema, não como módulo simples
- **Ação sugerida:** antes de migrar pra React, avaliar **viabilidade de manter vs reescrever** em React. Se for 957 views de ERP de suporte, o custo de migrar é enorme.

## AiAssistance ❌ descartado
- Wagner: "acho que não vai ser útil"
- **Ação:** desativar em `modules_statuses.json`. Não investir migração.
- Alternativa de IA é o Jana (do 3.7, perdido na migração) + OpenAI direto via `openai-php/laravel`.

## Módulos perdidos na migração 3.7 → 6.7 (decisão pendente)
Descobertos em 2026-04-22 via `git diff` entre branches `6.7-bootstrap` e `3.7-com-nfe`:

| Módulo | Existia em 3.7 | Existia no backup main-wip | Decisão |
|--------|----------------|----------------------------|---------|
| **Fiscal** (NFe) | ✅ | ✅ | Restaurar via pacote padrão `nfephp-org/sped-nfe` (Fase 15) |
| **Boleto** | ✅ | ✅ | Wagner: apagar e usar `eduardokum/laravel-boleto` (Fase 15) |
| **Chat** (WhatsApp/Telegram/Email) | ✅ | ✅ | Avaliar — pode ser valioso |
| **Jana** (IA assistente antigo) | ✅ | ✅ | Avaliar — potencial de fundir com nova estratégia IA |
| **BI** (Business Intelligence) | ✅ | ✅ | Avaliar uso real antes |
| **Dashboard** | ✅ | ✅ | Overlap com `CustomDashboard` (existe em 6.7) — comparar |
| **Help** | ✅ | ✅ | Docs/treinamento — substituir por docs externas |
| **Knowledgebase** | ✅ | ✅ | Semelhante a Help |
| **codecanyon-ticketing** | ✅ | ✅ | Renomeado pra "Grow" em 6.7? Investigar |

## Fluxo de trabalho com specs
Wagner quer:
1. Spec automática por módulo (PHP scanner) — **feito em `php artisan module:specs`**
2. Diff vs 3.7 e vs main-wip pra identificar customizações — **a fazer**
3. LLM só para sintetizar diffs não-triviais (custo/benefício ótimo)
4. Todas specs em `memory/modulos/` + INDEX.md consolidado
