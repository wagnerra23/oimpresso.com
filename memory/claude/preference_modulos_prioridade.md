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

## AiAssistance ❌ removido (2026-04-27)
- Wagner: "acho que não vai ser útil" + "é um esboço antigo do ultmatepos, muito fraco na minha opnião, o copiloto vai ser muitomelhor"
- **Status:** PR #30 (2026-04-27) deletou `Modules/AiAssistance/` do git e tirou a chave do `modules_statuses.json`. Não migrar nem restaurar.

## LaravelAI ❌ removido (2026-04-27)
- Spec original (2026-04-24): "agente que responde sobre o ERP" — placeholder scaffold-only
- Wagner: "pode remover LaravelA" — Copiloto absorveu a visão e foi muito além
- **Status:** PR #30 (2026-04-27) deletou `Modules/LaravelAI/` do git. Copiloto continua tendo `LaravelAiSdkDriver` (que usa o **package** `laravel/ai`, não o módulo) — adapter resolver cai pro `OpenAiDirectDriver` quando o package não está disponível.

## Estratégia IA (2026-04-27)
- **Copiloto** (Modules/Copiloto) = motor de IA atual: drivers `OpenAi*Driver`/`LaravelAiSdkDriver`/`MeilisearchDriver`/`NullDriver` em `Modules/Copiloto/Services/Ai/`
- **EvolutionAgent** (meta-tool de evolução do projeto, não SaaS) = plano usa Vizra ADK + Prism PHP
- Hoje nem `laravel/ai` (package) nem Vizra estão em `composer.lock` — Copiloto roda em `dry_run`

## Módulos perdidos na migração 3.7 → 6.7 (decisão pendente)
Descobertos em 2026-04-22 via `git diff` entre branches `6.7-bootstrap` e `3.7-com-nfe`:

| Módulo | Existia em 3.7 | Existia no backup main-wip | Decisão |
|--------|----------------|----------------------------|---------|
| **Fiscal** (NFe) | ✅ | ✅ | Restaurar via pacote padrão `nfephp-org/sped-nfe` (Fase 15) |
| **Boleto** | ✅ | ✅ | Wagner: apagar e usar `eduardokum/laravel-boleto` (Fase 15) |
| **Chat** (WhatsApp/Telegram/Email) | ✅ | ✅ | ❌ removido do Hostinger 2026-04-27 (Copiloto substitui) |
| **Jana** (IA assistente antigo) | ✅ | ✅ | 📦 movido pra `~/backups/Jana_2026-04-27` no Hostinger (Wagner usaria Dify) |
| **BI** (Business Intelligence) | ✅ | ✅ | Avaliar uso real antes |
| **Dashboard** | ✅ | ✅ | Overlap com `CustomDashboard` (existe em 6.7) — comparar |
| **Help** | ✅ | ✅ | ❌ removido do Hostinger 2026-04-27 (docs vão pro Laravel) |
| **Knowledgebase** | ✅ | ✅ | Semelhante a Help |
| **codecanyon-ticketing** | ✅ | ✅ | Renomeado pra "Grow" em 6.7? Investigar |

## Fluxo de trabalho com specs
Wagner quer:
1. Spec automática por módulo (PHP scanner) — **feito em `php artisan module:specs`**
2. Diff vs 3.7 e vs main-wip pra identificar customizações — **a fazer**
3. LLM só para sintetizar diffs não-triviais (custo/benefício ótimo)
4. Todas specs em `memory/modulos/` + INDEX.md consolidado
