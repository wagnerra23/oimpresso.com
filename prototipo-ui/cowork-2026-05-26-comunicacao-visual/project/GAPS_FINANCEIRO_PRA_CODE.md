# GAPS_FINANCEIRO_PRA_CODE.md
# Anexar ao PROMPT_PARA_CODE_VENDAS_FINANCEIRO.md
# 2026-05-18 · análise do que falta acertar pra bater o padrão Cowork

Wagner mandou screenshot do **Financeiro rodando no servidor após sync inicial**. Comparei com o padrão Cowork e listei o que ainda diverge.

## ✅ Já acertou (parabéns)

- Header limpo: título + action buttons + Novo lançamento
- 5 KPI cards no topo (sparkline no Saldo Previsto)
- "Resumo do mês · 23 lançamentos · 0% conferido" no banner
- Tabela agrupada por data (SÁB 02 MAI, SEG 04 MAI, etc.)
- Sidebar tem Financeiro + Fluxo de Caixa + DRE/Relatórios como rotas
- ⌘K, J/K, space (atalhos rodapé visíveis)
- Botão "Recebi"/"Paguei" inline na linha
- Botões "Conciliar" e "Plano de contas" no header como ghost actions
- Densidade compact como default (3 ícones no canto direito)

## ❌ O que ainda diverge do padrão Cowork

### 1. Filtros estão como **radio** (escolha única) — deveriam ser **4 checkboxes multi-select**

**Hoje no servidor:**
```
[✓ Todas 23][ Aberto][ Receber 20][ Pagar 3][ Recebidas][ Pagas][ Atraso 23]
       (radio · só 1 selecionado por vez)
```

**Padrão Cowork (FilterBar refeito):**
```
[✓ A receber 20] [✓ Recebidas 0] [✓ A pagar 3] [✓ Pagas 0]   |   [☐ Só atrasados 23]
       (checkboxes lifecycle · multi-select)              |    (toggle separado)
```

**Por quê:** "Aberto" é redundante (= A receber+A pagar não pagos). "Todas" é redundante (= tudo marcado). Eliana quer ver "só pagas + só pagar" pra um relatório específico — radio não permite. Multi-checkbox sim.

**Como fazer:** olha `financeiro-app.jsx` na função `FilterBar` — já vem refatorado no sync. As consts `FILTER_KIND` e `FILTER_STATE` foram removidas, substituídas por `FILTER_LIFECYCLE` (4 items) + `states` (Set<string>). Confirmar que o componente está chamando o novo FilterBar e que `states` está sendo passado, não o `tab` antigo.

### 2. Todos os lançamentos como "Atrasado" + categoria "Sem categoria"

**Hoje:** todas 23 linhas mostram status `Atrasado` (vermelho) e categoria `Sem categoria`.

**Esperado:** O `FIN_ROWS` mock do Cowork tem categorias reais (Banner, Adesivo, Insumo, Aluguel...) e status variados (vencendo · em-aberto · atrasado · recebido · pago). Provável: o seeder do backend está produzindo dados sem categoria, e o status do server está computando "atrasado" pra tudo que tem `due < hoje`, mas as datas mock têm tudo em maio com `due` no passado.

**Como fazer:**
- Backend: usar `financeiro-data.jsx` como referência de shape — cada row tem `category`, `status`, `paid_at` proper
- Seeder/factory: replicar a distribuição: 60% receivable / 40% payable, mistura de pagos e em-aberto, categorias reais
- OU temporariamente: deixar o front fazer derive de `status` a partir de `due` + `paid_at` no client-side se backend ainda não computa

### 3. Faltam cross-links `#V-` `#OS-` `#PC-` `#BL-` no campo `desc`

**Hoje:** todos os `desc` são tipo `"Titulo R000007"` — texto morto, sem ID.

**Padrão Cowork:**
```
"Banner lona 4×1m — promo dia das mães · #V-7832 #OS-4831"
"Adesivagem frota · #V-7823 #OS-4833 #BL-4113"
"Papel couché 250g · #PC-281 #BL-9982"
```

Esses tokens são parseados pelo `VdLinkify` e renderizados como pills clicáveis coloridas (verde=venda, azul=OS, laranja=compra, azul-petróleo=boleto).

**Como fazer:**
- Quando o `Sells/StoreController` cria uma venda, no `afterCreate` ele tem que gerar o lançamento financeiro **com o desc carregando a referência cruzada**. Sugestão: trabalhar com helper:
  ```php
  $desc = sprintf('%s · #V-%s', $sale->descricao, $sale->id);
  if ($sale->os_id) $desc .= sprintf(' #OS-%d', $sale->os_id);
  if ($sale->boleto_id) $desc .= sprintf(' #BL-%d', $sale->boleto_id);
  ```
- Mesma coisa pro lado `Compras → Financeiro`: incluir `#PC-XXX` no desc
- Recurring quando estiver pronto: `#REC-XXX` (criar prefix novo)

### 4. Status pill de frescor não está renderizando

**Esperado:** badge ao lado do amount no drawer:
- `🟢 fresh` (>7d até vencer)
- `🟡 soon` (4-7d)
- `🟠 warning` (1-3d)
- `🔴 today` (vence hoje)
- `🔴 overdue` (atrasado)
- `⚪ paid`

**Hoje:** vejo só "Atrasado" plain.

**Como fazer:** o componente `FinPillFrescor` está no `financeiro-curation.jsx`. Está sendo carregado? Verificar se `window.FinPillFrescor` está definido após Babel transpile. Se não estiver carregando, provavelmente o script tag `financeiro-curation.jsx` está ausente do `Oimpresso ERP - Chat.html` (ver o asset shipping pipeline).

### 5. Action buttons sem cores semânticas

**Hoje:** "Resumir mês", "Fechamento", "Apresentar", "Conciliar", "Plano de contas" — todos cinza-neutro.

**Padrão Cowork:**
- `✦ Resumir mês` — **roxo** (IA accent: oklch 295)
- `☑ Fechamento` — **verde-floresta** (trilha persistente: oklch 145)
- `▶ Apresentar` — **azul** (read-only mode: oklch 240)
- `Conciliar` — neutro ok
- `Plano de contas` — neutro ok

Diferenciação visual é importante porque os 3 primeiros são **acessórios de governança** (não rotina). Eliana usa só sexta de manhã, não todo dia. Cor sutil sinaliza.

**CSS no Cowork:** classes `.fin-btn-ai`, `.fin-btn-trilha`, `.fin-btn-present` já estão no `styles.css`. Garantir que os botões do `FinHero` usam essas classes:
```jsx
<button className="os-btn ghost fin-btn-ai">✦ Resumir mês</button>
<button className="os-btn ghost fin-btn-trilha">☑ Fechamento</button>
<button className="os-btn ghost fin-btn-present">▶ Apresentar</button>
```

### 6. `Conferido` toggle vs `Recebi/Paguei` ação — não confundir

**Cuidado conceitual:**
- **Recebi / Paguei** (ação inline na linha) = registra `paid_at = NOW()` — é o **ato de compensação** (dinheiro entrou/saiu)
- **Conferido** (toggle Eliana) = `conferido_by = Eliana, conferido_at = NOW()` — é a **paritária dela** ("já bati o olho")

São coisas DIFERENTES:
- Posso ter um lançamento **conferido mas em aberto** (Eliana já validou que o boleto está correto, mas o cliente ainda não pagou)
- Posso ter um lançamento **pago mas não conferido** (cliente pagou, mas Eliana ainda não validou se o valor bate)

A UI Cowork mostra os DOIS estados independentes:
- Pill "✓ Conferido" no header do drawer (verde)
- Botão "Recebi/Paguei" como antes (verde também mas SIM ele muda `paid_at`)

**Hoje no servidor:** "0% conferido" no banner sugere que o sistema TEM a feature mas faltam dados. OK!

**O que conferir no Backend:** model `FinancialEntry` precisa de 2 campos novos:
```sql
ALTER TABLE financial_entries ADD COLUMN conferido_by VARCHAR(64) NULL;
ALTER TABLE financial_entries ADD COLUMN conferido_at TIMESTAMP NULL;
```

### 7. Drawer detalhe — está renderizando os componentes Cowork?

Quando clicar numa linha (ex: "Titulo R000007"), o drawer abre. Conferir:
- [ ] Header tem pill "A receber · R-NNNN" + (se conferido) "✓ conferido" + (se editado) "✎ editado"
- [ ] Banner de anomalia se valor está 25%+ acima da média histórica do `party`
- [ ] Botão "Conferir" + "Editar campos" lado a lado
- [ ] Section "Documento" com cross-links se houver
- [ ] Section "Conciliação extrato" com box de match
- [ ] Section "Histórico" com `FinAuditTrail` (não a versão hardcoded antiga)
- [ ] Section "Comentários" — `FinCommentsThread` com textarea
- [ ] Section "✦ IA Copiloto" no rodapé — `FinAiPanel` com stats de party + botão "✦ Perguntar"
- [ ] Footer: `FinTroubleButton` + "Ver NFe" + "Cobrar" + "Marcar como recebido"

Se algum dessas seções está faltando, é porque o script `financeiro-curation.jsx`, `financeiro-ai.jsx`, ou `financeiro-output.jsx` não está sendo carregado no Chat.html OU o hot-reload do Babel não pegou os novos `window.X` exports.

**Como debugar:** abrir DevTools console e digitar:
```js
['useFinComments','useFinConferido','useFinEdits','FinAiPanel','FinPillFrescor','FinAuditTrail','FinTroubleButton','FinEditPanel'].forEach(n => console.log(n, !!window[n]))
```
Tudo deve retornar `true`. Se algum `false`, esse script não carregou.

### 8. Side notes (não-críticos)

- **Sidebar tem "Gateway de Pagamento" — não estava no Cowork.** Sem problema (módulo separado, faz sentido), só não confundir com o que combinamos
- **Sidebar tem "Contas de pagamento" — também extra.** OK, é granularidade fina pra Eliana, não estraga
- **Bottom bar tem "marcar pago/recebido" como atalho — bom!** Mas no Cowork tem mais (R/F/B/?). Não é blocking
- **"23 lançamentos · 0% conferido"** — banner novo no servidor (não tinha no Cowork). É um bom add — manter

## Resumo executivo pra Wagner ler

> A sync inicial saiu **75% certo**. Falta:
> 1. **Trocar tabs por checkboxes** (FilterBar refatorado já está no `financeiro-app.jsx` do patch — confirmar uso)
> 2. **Popular `category` + `desc` com `#V- #OS- #PC-` no seeder/backend**
> 3. **Garantir os 3 scripts novos carregam**: `financeiro-curation.jsx`, `financeiro-ai.jsx`, `financeiro-output.jsx` (testar `window.FinPillFrescor` no console)
> 4. **Aplicar classes `.fin-btn-ai/trilha/present`** nos 3 action buttons coloridos
> 5. **Migrar `financial_entries`** com `conferido_by` + `conferido_at` se ainda não tem

Tudo o resto está perfeito. Esses 5 pontos fecham 95% → 100% do padrão.
