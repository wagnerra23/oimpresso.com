# GAPS_v2_FINANCEIRO_PRA_CODE.md
# Acompanha o pacote v2 — análise do que o Code skippou na 1ª aplicação
# 2026-05-18

Wagner mostrou 2 screenshots: a lista (boa) + o drawer (quebrado).

## ✅ O que o Code aceitou (lista)

- 4 checkboxes lifecycle: A receber 20 / Recebidas / A pagar 3 / Pagas + Só atrasados 23
- Action buttons no header: Resumir mês · Fechamento · Apresentar · Conciliar · Plano de contas
- Atalhos rodapé: ⌘K · / · J/K · B · ? Resolver 4
- KPI strip 5-cards
- Sidebar com Fluxo de Caixa + DRE/Relatórios + Gateway de Pagamento

## ❌ O que o Code SKIPPOU (drawer)

### 1. **Drawer sem abas** — falta `<nav className="fin-drawer-tabs">`

Screenshot mostra drawer abrindo direto no conteúdo sem o nav de abas:
```
Titulo R000014                                            ×
─────────────────────────────────────────────────────────
Atrasado ×15d em atraso  R$ [redacted Tier 0]
ConferirPer-user audit
◇
Valor fora do padrão 57% abaixo da média histórica Média histórica R$ [redacted Tier 0] ...
...
```

**Esperado:** logo após o X, deveria ter:
```html
<nav className="fin-drawer-tabs">
  <button className="fin-drawer-tab on">Detalhes [💬3]</button>
  <button className="fin-drawer-tab fin-drawer-tab-ai">✦ IA</button>
</nav>
```

**Possível causa:** Code pode ter aplicado o `financeiro-app.jsx` SEM esse trecho de nav. O str_replace pode ter falhado silenciosamente na build local dele (whitespace, encoding, ou conflict).

**Como ele resolve:** baixar **NOVAMENTE** o `financeiro-app.jsx` v2:
```
prototipo-ui-patch/vendas-financeiro-completo/financeiro-app.jsx
```
e procurar pela string `<nav className="fin-drawer-tabs">` — se não tiver, copiar o arquivo inteiro de novo.

### 2. **CSS de drawer + ai + audit + conferido FALTANDO no styles.css**

Sintoma: textos colados sem espaço (`ConferirPer-user audit`, `MédiaR$ [redacted Tier 0]`, `Atrasados22`). Isso só acontece quando as classes não têm padding/gap/grid CSS.

**Classes que devem estar no `styles.css`** (verificar com grep no arquivo):
```
.fin-drawer-tabs
.fin-drawer-tab
.fin-toggles-row
.fin-conferido-toggle
.fin-edit-btn
.fin-edit-panel
.fin-ai-anomalia
.vd-ai-stats          (mesmo prefixo .vd usado no Financeiro também)
.vd-ai-stat
.fin-audit
.fin-audit-row
.fin-audit-ic
.fin-audit-body
.fin-comments-h
.fin-comment
.fin-drawer-wide
.fin-drawer-footer
.fin-frescor
.fin-pill-frescor
```

Total de CSS novo esperado: **~1500 linhas** dos refinos #1+#2+#3+#4.

**Comando de verificação** (rodar no terminal do repo):
```bash
grep -c "fin-drawer-tabs\|fin-conferido-toggle\|fin-ai-anomalia\|fin-audit-row\|fin-frescor" prototipo-ui/styles.css
```

Deve retornar pelo menos **30+ matches**. Se retornar <10, o styles.css foi truncado/skippou seções.

**Fix:** baixar o `styles.css` v2 inteiro e SOBRESCREVER (não fazer merge incremental).

### 3. **Backend seeder ainda fraco** — todos lançamentos "Sem categoria" e "Atrasado"

Não é blocking pro design system mas afeta a percepção visual. Quando todos os 23 estão atrasados, o módulo perde graça (todos vermelhos).

**Fix:** seeder deveria distribuir:
- 60% receivable / 40% payable
- 50% paid / 50% open
- Categorias reais: Banner, Adesivo, Fachada, Placa, Gráfica rápida, Insumo, Aluguel, Utilidade, Imposto, Folha, Serviço
- 10-15% em status "atrasado" (não 100%)

O `financeiro-data.jsx` do Cowork já tem essa distribuição como referência. Pegar o shape de lá.

## Comando pra Wagner colar no Code (rápido)

```
Por favor reaplique o pacote v2 do prototipo-ui-patch/vendas-financeiro-completo/. 
O drawer do Financeiro ficou sem as abas (Detalhes / ✦ IA) e o CSS não aplicou 
as classes .fin-drawer-tabs, .fin-conferido-toggle, .fin-ai-anomalia, .fin-audit-row, 
.fin-frescor (textos aparecem colados sem espaço).

Verifica:
1. financeiro-app.jsx tem <nav className="fin-drawer-tabs">
2. styles.css tem >30 menções a fin-drawer-tabs|fin-conferido-toggle|fin-ai-anomalia|fin-audit-row|fin-frescor
3. Se não tiver, sobrescreve INTEIRO (não merge incremental) e push de novo
```

## Resumo

A aplicação ficou **75% certa na lista, 30% certa no drawer**. O drawer foi o ponto fraco — provavelmente algum bloco grande de CSS/JSX foi pulado por causa de conflito de patch.

Quando o Code aplicar de novo + verificar os 2 pontos (nav presente, CSS contado), o drawer deve abrir bonito com FSM stepper + Conferir + Editar inline + IA tab + tudo.
