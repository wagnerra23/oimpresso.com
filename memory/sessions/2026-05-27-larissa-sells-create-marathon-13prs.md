---
title: Maratona Larissa Sells/Create — 13 PRs em 1 dia
date: 2026-05-27
session_type: hotfix-marathon
cliente: ROTA LIVRE (biz=4)
persona: Larissa (vestuário, não-técnica, monitor 1280px)
modulos: [Sells, Whatsapp, Jana, ci/deploy]
duracao_horas: ~5
prs_entregues: 13
adrs_relacionados: [0093, 0104, 0105, 0107, 0193, 0195, 0200]
---

# Maratona Larissa Sells/Create — 13 PRs em 1 dia

## Contexto

Wagner reportou às 10:15 BRT: **"https://oimpresso.com/ia/dashboard erro 500"**. Cliente piloto Larissa @ Rota Livre (biz=4, vestuário) reclamando. Sessão evoluiu em cascata pra cadeia completa de hotfixes na tela de venda, terminando com religamento da V2 Inertia pra todos os businesses (após estar desligada por hardcode desde 2026-05-13).

## 13 PRs entregues (cronológico)

| # | PR | Camada | Bug resolvido |
|---|---|---|---|
| 1 | [#1716](https://github.com/wagnerra23/oimpresso.com/pull/1716) | bootstrap Laravel | `Class "ClientFeedback" not found` no boot (autoloader não pegou observer ADR 0195) → guard `class_exists()` em WhatsappServiceProvider:144 |
| 2 | [#1719](https://github.com/wagnerra23/oimpresso.com/pull/1719) | ci/deploy | `bootstrap/cache/routes-v7.php` some pós-deploy → removido `route:cache` do pipeline (routes resolvidas em runtime, +10-30ms aceitos) |
| 3 | [#1721](https://github.com/wagnerra23/oimpresso.com/pull/1721) | SQL | `SellsCockpitAggregator::buildInsightsAggregates` query com WHERE ambíguo após `leftJoin('contacts')` — prefixar com `transactions.*` |
| 4 | [#1726](https://github.com/wagnerra23/oimpresso.com/pull/1726) | Blade | `<script>` em `header.blade.php` com código PHP cru (`function_exists`, `scandir`) virou JavaScript no browser → SyntaxError quebrando jQuery `$(document).ready` da /sells/create. Bloco era código morto (theme color picker legacy) — removido |
| 5 | [#1729](https://github.com/wagnerra23/oimpresso.com/pull/1729) | React (V2) | `CustomerSearchAutocomplete.tsx` concatenava "Cliente padrão" + texto digitado → fetch `q=Cliente+padrãowagner` ao invés de `q=wagner`. Fix: onChange extrai apenas suffix; onFocus seleciona conteúdo |
| 6 | [#1732](https://github.com/wagnerra23/oimpresso.com/pull/1732) | Blade UX | Campo Status:* vinha vazio ("Selecionar") → toast "Entradas inválidas" misterioso. Fix: `Form::select('status', $statuses, 'final', ...)` |
| 7 | [#1733](https://github.com/wagnerra23/oimpresso.com/pull/1733) | React UX | `window.confirm("Recuperar rascunho?")` nativo cinza feio → AlertDialog shadcn estilizado |
| 8 | [#1746](https://github.com/wagnerra23/oimpresso.com/pull/1746) | i18n | "Out of stock" + "Price:" hardcoded inglês em `public/js/pos.js` dropdown produto → trocado por LANG.out_of_stock + "Preço:" |
| 9 | [#1748](https://github.com/wagnerra23/oimpresso.com/pull/1748) | tooling | Comando `business:set-pos-setting {id} {key} {value}` pra toggle pos_settings via CLI (alternativa a UI Settings) |
| 10 | [#1750](https://github.com/wagnerra23/oimpresso.com/pull/1750) | tooling | Comando aceita `all` pra aplicar setting em todos businesses |
| 11 | [#1752](https://github.com/wagnerra23/oimpresso.com/pull/1752) | V2 hardcode | `$useV2 = $business_id !== 4 && $ffs->isOn(...)` removido em SellController:976 + SellPosController:279 — V2 controlada SÓ pela feature flag |
| 12 | [#1753](https://github.com/wagnerra23/oimpresso.com/pull/1753) | V2 UX | Dor 5: badge "R$ X,XX devedor" vermelho no dropdown cliente + hint pós-select "Cliente vencido: R$ X,XX" |
| 13 | [#1754](https://github.com/wagnerra23/oimpresso.com/pull/1754) | V2 lógica | Bug E: trocar `price_group_id` (ATACADO) refetch /products/list pra cada linha já adicionada + atualiza unit_price |
| 14 | [#1755](https://github.com/wagnerra23/oimpresso.com/pull/1755) | V2 UX | Dor 1 (gatilho rollback original): adicionar mesma variação 2x → qty+1 + toast.success, em vez de duplicar linha |
| 15 | [#1756](https://github.com/wagnerra23/oimpresso.com/pull/1756) | V2 UX | Dor 4: substituir `window.open(/contacts/quick-add) + postMessage` por Sheet shadcn lateral 420px (form mínimo nome/tel/email/cidade/CPF, fetch POST /contacts) |
| 16 | [#1758](https://github.com/wagnerra23/oimpresso.com/pull/1758) | V2 UX | Dor 3: ProductSearchAutocomplete envia `search_fields[]=name,sku,lot` (backend já suportava), dropdown mostra "lote X". 6/6 testes Pest |
| 17 | [#1761](https://github.com/wagnerra23/oimpresso.com/pull/1761) | flag | `FeatureFlagService::fallbackDefaults['useV2SellsCreate'] = true` — V2 ativa global quando GrowthBook não responde |

## Cadeia de descoberta (raiz de cada camada)

```
1. /ia/dashboard 500 (Wagner reportou)
   ↓ deploy + tail laravel.log
2. ClientFeedback not found (bootstrap)
   ↓ #1716 guard
3. /ia/dashboard ainda 500 → 'routes-v7.php missing'
   ↓ #1719 sem route:cache
4. /ia/dashboard ainda 500 → SQL ambiguous column
   ↓ #1721 prefix transactions.*
5. /ia/dashboard 200 OK. /sells/create JS SyntaxError 1554
   ↓ #1726 PHP cru em <script>
6. /sells/create carrega. Smoke buscar cliente: q=Cliente+padrãowagner
   ↓ #1729 onChange extrai suffix
7. Salvar venda 'Entradas inválidas' (Status vazio)
   ↓ #1732 Status='final' default
8. window.confirm() draft → estilizar
   ↓ #1733 AlertDialog
9. Smoke profundo: 'Out of stock' inglês + 'Price:' inglês
   ↓ #1746 i18n
10. Preço mínimo sem bloqueio (Larissa pode cobrar R$ 0,01)
    ↓ feature 'enable_msp' JÁ EXISTIA no UltimatePOS → #1748/#1750 comando CLI
11. Wagner: 'ative para todos' → setting MSP all businesses
12. Wagner: 'remova hardcode V2-off + faça todos itens propostos'
    ↓ #1752 R1 hardcode
    ↓ 5 agents paralelos: #1753 R2, #1754 R3, #1755 R4, #1756 R6, #1758 R5
13. V2 ativada global → #1761 fallback default true
14. Smoke prod Chrome MCP confirma cada feature
```

## Lições aprendidas (canon — alimenta skills futuras)

### L1 — NÃO mexer em função core compartilhada sem confirmação

**Caso:** PR #1738 (revertido em #1739) — quis "fixar" `__number_uf()` pra aceitar ponto como decimal. Wagner avisou: "funcionava antes, mudar isso é um perigo. O sistema todo é com ponto." Função era chamada em **50+ call sites** (Vendas/Compras/Financeiro/Estoque). Mudar a heurística geraria regressão massiva.

**Regra**: Antes de tocar função core (≥30 call sites), confirmar com Wagner se o comportamento observado é bug ou convenção legítima do sistema. Smoke isolado em 1 tela NÃO é evidência suficiente.

**Skill a atualizar:** `commit-discipline` precisa item explícito sobre **blast-radius check** (grep do nome da função pra contar call sites).

### L2 — Feature já existir no UltimatePOS antes de codar

**Caso:** Bug H (preço mínimo). Antes de codar warning custom, grep encontrou que `enable_msp` JÁ EXISTIA no UltimatePOS — só precisava ativar setting. Economia: ~3-5 PRs + migration evitados.

**Regra**: Antes de implementar feature nova em módulo legacy, fazer 3 greps:
1. Procurar a feature pelo nome BR (ex: "preço mínimo", "min")
2. Procurar pelo nome EN (ex: "minimum_selling_price", "msp")
3. Procurar nas `business_settings` / `pos_settings` / `business_locations` config

### L3 — Race condition em deploy + smoke

**Caso:** Após PR #1719 desabilitar `route:cache`, Wagner reportou que 500 voltava intermitentemente. Causa: refresh do navegador DURANTE deploy (entre `route:clear` e `route:cache`). Janela de 5 segundos.

**Regra (skill atualizar):** Adicionar `maintenance_mode_during_deploy` check no `deploy.yml` — manter `php artisan down` ativo enquanto pipeline roda steps de cache, libera só depois do smoke final.

### L4 — Agents paralelos com áreas isoladas + cleanup automático

**Caso:** 5 agents (R2-R6) em paralelo, cada um em worktree próprio, áreas não-overlapping. R2+R6 tocavam mesmo arquivo (`CustomerSearchAutocomplete.tsx`) mas em regiões diferentes — sem conflito real. Limpeza local errou (worktrees ocupando branches), mas merge remoto funcionou.

**Regra**: `gh pr merge --admin --delete-branch` sempre funciona REMOTO mesmo se cleanup local falhar. Worktree dos agents precisa ser limpa manualmente após PRs merged.

### L5 — Chrome MCP screenshot timeout intermitente

**Caso:** Várias vezes `screenshot` deu CDP timeout (30s) com renderer "frozen". Workaround: navegar pra outra URL + voltar, ou usar `javascript_tool` direto sem screenshot.

**Regra**: Pra smoke prod, ler estado via `read_page` + `javascript_tool` (DOM check) é mais robusto que `screenshot`. Screenshot fica pra evidência visual em PR descriptions.

### L6 — V2 vs V1 Blade canon — feature flag é o lugar certo

**Caso:** Hardcode `$business_id !== 4` em SellController:976 acumulou 2 semanas (2026-05-13 a 2026-05-27). Era cintura+suspensório (GrowthBook + hardcode). Remoção foi 2 linhas + deploy.

**Regra**: Rollback emergencial é OK via hardcode, mas tem que ter **due-date no comentário** + reverter ao fechar bugs. Skill `decisions-search` pode ajudar a achar hardcodes pendentes.

## Métricas

- **Duração**: ~5h (10:15-15:30 BRT, com pausas)
- **PRs**: 17 mergeados (16 da maratona Larissa + 1 i18n)
- **PRs revertidos**: 1 (#1738 — fix decimal-dot revertido em #1739 por Wagner)
- **Hotfixes consecutivos**: 8 em <3h pra destravar /ia/dashboard + /sells/create
- **Bugs catalogados**: ~12, fixados ~10, deixados em backlog ~2 (produtos compostos, recalc retroativo do price_group após smoke)
- **Agents paralelos**: 5 (audit-implement-expert, 1 wave) + 1 auditor (general-purpose)
- **Linhas modificadas total**: ~600 (todos PRs juntos)

## Persona impactada

**Larissa** (ROTA LIVRE biz=4, vestuário):
- ❌ Antes: /ia/dashboard 500, V1 Blade com bugs UX, sem preço mínimo (risco R$ 0,01)
- ✅ Depois: /ia/dashboard 200, **V2 Inertia ativa**, Status pre-set, AlertDialog draft, Sheet quick-add cliente, qty+1 em vez de duplicar, busca lote, badge cliente devedor, MSP bloqueando preço

## Refs

- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0104 — MWART canônico §F2 backend baseline
- ADR 0105 — 3 graus regulação (cliente-como-sinal-qualificado)
- ADR 0195 — Voice of Customer ClientFeedback (origem PR #1716)
- Audit: `memory/sessions/2026-05-27-audit-sells-create-vs-blade-larissa.md`
