# RUNBOOK — Smoke Cowork Sells/Index (5 Ondas KB-9.75)

> **Quando rodar:** após qualquer deploy SSH que toque `resources/js/Pages/Sells/`, `app/Http/Controllers/SellController.php`, `resources/css/sells-cowork*.css`, ou ADR alterando processo MWART/multi-tenant.
>
> **Quem roda:** Wagner manualmente via Brave (R1 PROTOCOLO smoke real, não Pest narration).
>
> **Onde rodar:** `https://oimpresso.com/sells` autenticado biz=1 (NÃO biz=4 cliente piloto — ADR 0101).
>
> **Tempo total:** ~8min full run (~3min se já tiver dados de hoje).

## Pré-requisitos

- Login válido biz=1 no oimpresso.com (sessão CookieStore funcional)
- Cliente Brave (não Chrome — Wagner usa Brave canônico)
- Pelo menos 1 venda hoje em biz=1 pra renderizar Hero "Faturado hoje" não-zero
- DevTools aberto (F12) — Console + Network pra validar deferred props

## Cenário 1 — Index lista carrega (Onda 1 R1 Fundação)

1. Abrir `https://oimpresso.com/sells` em Brave
2. ✅ **Header esperado:**
   - Wordmark + breadcrumb "Vendas"
   - 4 KPIs Cowork (Hero "Faturado hoje" + Ticket médio + A receber + 4º card dinâmico)
   - 5 status pills (Todas · Pagas · Parciais · Pendentes · Atrasadas)
   - Tabs (Lista de vendas) + Visões (Orçamentos · Rascunhos · Assinaturas)
   - Toolbar com botões "Imprimir caixa" + "Visões ▼"
3. ✅ **Grade esperada:**
   - 10 colunas (checkbox + ID + Cliente + Itens + Data + Forma + Total + Pago + Status + ✦)
   - Tabular nums em valores R$ (alinhados à direita)
   - Hover row destaca borda esquerda (var `--accent`)
4. ✅ **Atalhos teclado:** `J/K` ou `↑/↓` navega rows, `Enter` abre drawer, `Esc` fecha
5. ✅ **⌘K palette:** abre overlay com search + 4 ações rápidas (Nova venda, PDV, Orçamento, Imprimir caixa)

## Cenário 2 — Drawer abre + IA + Curadoria (Ondas 2/2.5/3)

1. Click numa linha → drawer abre na direita (sm:max-w-xl)
2. ✅ **Header drawer:**
   - `#INV-NNNN` em mono + status badge (Pago/Parcial/A receber)
   - Title `Venda INV-NNNN` + cliente como description
   - Botão `✦ IA` no canto superior direito
3. ✅ **Body:** 4 KPIs mini (Itens · Valor · Pago · Saldo) + Cliente + Produtos (com 💬 por linha) + Pagamentos + (Onda 4) Mensagem WhatsApp + Notas + Fiscal + Pipeline FSM + OS + Histórico + Audit trail
4. ✅ **✦ IA (Onda 2 + 2.5 Jana real):**
   - Click `✦ IA` → painel violet expande inline com 3 blocos (Resumo · Histórico · Sugestões)
   - Loading dots animados durante POST `/sells/{id}/ai-ask`
   - Network tab: response.source = "jana" (se `SELLS_AI_USE_JANA_REAL=true` em .env) OR "stub"
   - Se "jana" falha → fallback stub determinístico transparente
5. ✅ **💬 Comentários por item (Onda 3):**
   - Click 💬 em produto → thread inline expande
   - Digite "teste oimpresso" + ⌘↵ → comentário aparece com timestamp PT-BR
   - Refresh página → comentário persiste (localStorage `oimpresso.sells.itemComments`)
6. ✅ **Audit trail (Onda 3):** seção no final do drawer com timeline cronológica (create + payment + fiscal autorizada/rejeitada)
7. ✅ **Cross-link #V-NNNN (Onda 3):** em Notas com texto `Ref: #V-1234`, o `#V-1234` vira pill verde clicável → navega `/sells/1234`

## Cenário 3 — Onda 4 R4 Distribuição (transcript + apresentação + WhatsApp)

1. No drawer aberto, footer tem 4 botões: `Transcript` · `Apresentar` · `Imprimir` · `Editar`
2. ✅ **Transcript A4:**
   - Click `Transcript` → overlay modal escuro com página A4 794px branca centralizada
   - Conteúdo: header brand (Oimpresso + CNPJ) + 4-grid (cliente/atendido/pgto/total) + tabela items + (se NFe) fiscal chave 4-em-4 + 2 assinaturas + footer "documento não-fiscal"
   - Click `Imprimir` (toolbar) ou `Ctrl+P` → diálogo print abre, preview mostra APENAS a página A4 (resto do app oculto via `@media print`)
   - `Esc` fecha overlay
3. ✅ **Apresentação fullscreen:**
   - Click `Apresentar` → modal fullscreen escuro (z-index 70)
   - Slide 1 (intro): nome cliente + "Resumo da venda"
   - `→` ou `Space`: slide 2 (itens grandes com qtde × nome × valor)
   - `→`: slide 3 (valor R$ gigante + status pagamento)
   - `→`: slide 4 (próximos passos numerados)
   - Dots indicator + setas ◀ ▶ + `Esc` fecha
4. ✅ **Mensagem WhatsApp (Onda 4 dentro do drawer):**
   - Seção "Mensagem WhatsApp" no body do drawer
   - 3 tabs (Confirmação · Retirada · Cobrança) — clica troca template
   - Bolha verde renderiza msg com vars substituídas: `{{cliente}}` → Larissa, `{{id}}` → INV-XXX, etc
   - Botão `Copiar texto` → clipboard recebe msg renderizada (verifica via DevTools `await navigator.clipboard.readText()`)
   - Botão `Abrir no WhatsApp` → nova aba em `wa.me/55<digits>?text=<encoded>`

## Cenário 4 — Onda 5 Polish dados reais

1. Voltar pra `/sells` (lista)
2. ✅ **Hero "Faturado hoje":**
   - DevTools Network: `XHR /sells?_inertia_partial=...` deferred prop `coworkAggregates` chega após render inicial
   - Delta dinâmico: se há vendas ontem → "↑ +N%" ou "↓ N%" (com cor verde ou vermelha suave)
   - Se ontem zerado → mostra apenas count vendas sem delta
3. ✅ **Sparkline real:**
   - Curva mostra 30d de revenue (último ponto = hoje)
   - Se sem vendas em alguns dias → linha desce em vales reais (NÃO mais base mockada)
4. ✅ **Ticket médio:**
   - Delta WoW dinâmico (esta semana ticket médio vs semana passada)
5. ✅ **Top vendedor (foco=comissao):**
   - Click pill `Comissão` no segmented control → 4º card vira "Top vendedor (mês)"
   - Se há commission_agent atribuído em vendas do mês → mostra nome + total R$
   - Caso contrário → "sem commission_agent atribuído este mês"
6. ✅ **Imprimir caixa wired:**
   - Click botão `Imprimir caixa` → diálogo print do browser abre

## Cenário 5 — Multi-tenant Tier 0 (defesa em profundidade)

> ⛔ NÃO logar como biz=4 (ROTA LIVRE Larissa) pra testar — ADR 0101 proíbe.
> Use biz=1 (oimpresso interno) + verifique cross-tenant via curl autenticado.

1. DevTools Network → request `/sells` body do response
2. Validar Inertia props NÃO contém vendas de outros businesses:
   ```
   props.rows.every(r => r.business_id === 1)  // true esperado
   ```
3. Validar `coworkAggregates.topSeller.total` é APENAS soma do biz autenticado
4. Smoke automatizado cron `sells:smoke-daily` faz esse check 06:30 BRT — log em `storage/logs/laravel.log` busca por `[sells:smoke-daily]`

## Validação smoke automatizado

```bash
# Manual (Hostinger SSH):
php artisan sells:smoke-daily --notify

# Saída esperada:
# [sells:smoke-daily] início — 5 checks Cowork
#   · schema essencial OK
#   · tenancy biz=1=N biz=4=M
#   · vite manifest: todos chunks Cowork presentes
#   · css scoped imports: 4/4 OK
#   · coworkAggregates: shape canônico OK
# [sells:smoke-daily] OK — 5/5 checks passaram
```

## Quando smoke falhar

| Sinal | Diagnóstico | Fix canônico |
|---|---|---|
| `manifest: chunks Cowork ausentes` | Vite build:inertia faltou no deploy | `npm run build:inertia` no Hostinger SSH |
| `css: imports ausentes` | Drift em `resources/css/inertia.css` | Git revert + reapply Ondas 1-4 importers |
| `tenancy: biz=4 ZERO vendas 30d` | ROTA LIVRE parou de operar OU global scope quebrado | Audit `Transaction` model + check business_id WHERE clause |
| `aggregates: SellController drift` | Refactor removeu `buildCoworkAggregates` | Git revert PR que tocou SellController index() |

## Refs

- [Onda 1 PR #1032](https://github.com/wagnerra23/oimpresso.com/pull/1032) · [Onda 2 #1036](https://github.com/wagnerra23/oimpresso.com/pull/1036) · [Onda 2.5 #1040](https://github.com/wagnerra23/oimpresso.com/pull/1040) · [Onda 3 #1041](https://github.com/wagnerra23/oimpresso.com/pull/1041) · [Onda 4 #1042](https://github.com/wagnerra23/oimpresso.com/pull/1042) · [Onda 5 #1043](https://github.com/wagnerra23/oimpresso.com/pull/1043)
- [RUNBOOK Ondas Cowork mãe](../_DesignSystem/RUNBOOK-onda-cowork.md) F11 Encerramento
- [PROTOCOLO WAGNER SEMPRE](../../reference/PROTOCOLO-WAGNER-SEMPRE.md) R1 smoke real
- [ADR 0093 Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 Tests biz=1 nunca cliente](../../decisions/0101-tests-business-id-1-nunca-cliente.md)

---
**Criado:** 2026-05-17 (Onda 6 R6 smoke automatizado).
**Cron:** `sells:smoke-daily --notify` daily 06:30 BRT em `app/Console/Kernel.php`.
