# GAPS_v3_FINANCEIRO_PRA_CODE.md
# Diagnóstico final + checklist + prompt zero-touch
# 2026-05-20 · Cowork → Code

> **TL;DR:** o Code aplicou F3 do Financeiro baseado num snapshot ANTIGO do protótipo (provavelmente pré refino v2 do drawer/CSS). O resultado em prod (`oimpresso.com/financeiro/unificado`) está com **CSS parcial + seeder vazio + drawer sem abas**. Os 9 arquivos canônicos do Cowork estão sincronizados em `prototipo-ui-patch/vendas-financeiro-completo/` — **sobrescrever inteiro** os equivalentes em `prototipo-ui/` no repo resolve a parte visual. Seeder fica como tarefa separada.

---

## 1. Diagnóstico (3 screenshots comparados)

### A — Cowork prototype (gold standard) ✅
- `Oimpresso ERP - Chat.html` → módulo Financeiro
- Header de ações estilizado (Buscar ⌘K · Resumir mês · Fechamento · Apresentar · Imprimir · Conciliar · Plano de contas · + Novo lançamento)
- Hero KPI card preto com **sparkline 30d** + 4 KPI cards (Recebido / A receber / Pago / A pagar)
- Pills de filtro coloridas (A receber 11 · Recebidas 6 · A pagar 7 · Pagas 5 · Só atrasados 3)
- Tabela densa com agrupamento por mês, FSM stepper inline, badge de status, valor tabular
- Drawer 440px com nav `Detalhes [💬3] · ✦ IA`, `.fin-conferido-toggle` grande, `.fin-ai-anomalia` banner, audit trail com timeline `.fin-audit-row`, comments

### B — Prod (oimpresso.com) — estado quebrado ❌
**Sintoma raiz:** o `financeiro.css` (1296 linhas, 129 classes `.fin-*`) **não está sendo importado** ou foi importado parcialmente no bundle Inertia. Sem ele todos os elementos viram fluxo inline padrão do browser.

Visíveis no screenshot:
- Header de ações: `BuscarK✦ Resumir mês☑ Fechamento▶ Apresentar Imprimir Conciliar Plano de contas+ Novo lançamento` colados sem espaço — falta `.fin-toolbar` com `display:flex; gap:`.
- KPI strip: textos `Saldo previsto · maio R$ 0,00R$ 0,00 realizado · R$ 0,00 pendenteRecebido R$ 0,00 0 entradas confirmadasA receber R$ 0,00 0 títulos...` — falta grid/borders/cards.
- 4 checkboxes raw (`☐ A receber ☐ Recebidas ☐ A pagar ☐ Pagas`) sem `.fin-toggles-row` nem badge counter.
- Tabela sem `.fin-table` (sem padding, sem zebra, sem alinhamento de números).
- Footer `⌘K palette/ buscarJ/K navegar...` colado sem `.fin-footer` flex.
- Sparkline 30d **ausente** no hero card.
- KPIs todos zerados → seeder não gerou dados pra Maio 2026.

### C — Prod alterada parcialmente (segundo screenshot) ⚠️
Alguns refinos chegaram (header de ações com botões espaçados, KPI strip com cards brancos, pills coloridas), MAS:
- Tabela continua vazia (`Nenhum lançamento em Maio 2026 · [+ Adicionar primeiro lançamento]`)
- Sparkline ainda ausente
- Drawer (quando aberto em screenshot anterior) sem abas, audit smashed

---

## 2. Causa raiz provável

Code aplicou um pacote **anterior à série de refinos #1..#4** (drawer abas, FSM stepper, anomalia banner, audit timeline, frescor pills, sparkline). O `financeiro-app.jsx` que está em prod é versão de ~25/04 do Cowork, não a de 18/05.

Confirmação: classes que ESTÃO presentes em prod (`fin-toolbar`, `fin-kpi-strip`, `fin-toggles-row` quando aparecem) batem com versão antiga. Classes do v2 que faltam:

```
fin-drawer-tabs · fin-drawer-tab · fin-drawer-tab-ai · fin-drawer-tab-ct · fin-drawer-tab-tag
fin-conferido-toggle · fin-conf-check · fin-conf-lbl · fin-conf-pill-inline
fin-ai-anomalia (+ -high/-low/-ic/-body/-cta)
fin-audit-row · fin-audit-ic · fin-audit-body · fin-audit-h · fin-audit-list
fin-frescor (+ -fresh/-soon/-today/-warning/-overdue/-paid)
fin-edit-panel · fin-edit-btn · fin-edit-grid · fin-edit-actions
fin-trilha-* · fin-pres-* · fin-digest-*
fin-fsm-wrap · fin-hero-title-sub
```

Total ~60 classes `.fin-*` ausentes do CSS atual em prod.

---

## 3. Solução — sobrescrever 9 arquivos canônicos

**Não fazer merge incremental.** O Cowork validou os 9 arquivos como um conjunto coerente. Sobrescrever inteiro garante consistência classe ↔ JSX.

| Arquivo | Onde no repo | Linhas | SHA-checkable |
|---|---|---|---|
| `financeiro.css` | `prototipo-ui/financeiro.css` | 1296 | 42.9 KB |
| `financeiro-app.jsx` | `prototipo-ui/financeiro-app.jsx` | 1134 | ~58 KB |
| `financeiro-data.jsx` | `prototipo-ui/financeiro-data.jsx` | — | 7.9 KB |
| `financeiro-telas-extras.jsx` | `prototipo-ui/financeiro-telas-extras.jsx` | — | 35.9 KB |
| `financeiro-curation.jsx` | `prototipo-ui/financeiro-curation.jsx` | — | 16.4 KB |
| `financeiro-ai.jsx` | `prototipo-ui/financeiro-ai.jsx` | — | 15.8 KB |
| `financeiro-output.jsx` | `prototipo-ui/financeiro-output.jsx` | — | 20.6 KB |
| `financeiro-icons.jsx` | `prototipo-ui/financeiro-icons.jsx` | — | 5.9 KB |
| `fsm-stepper.jsx` | `prototipo-ui/fsm-stepper.jsx` | — | 7.0 KB |

**`styles.css` raiz (189 KB)** também deve ser atualizado — contém tokens compartilhados que outros módulos consomem. Mas se Code tiver receio de tocar nesse, é OK pular: o Financeiro funciona com `financeiro.css` sozinho desde que `styles.css` tenha os tokens base já presentes (`--text-mute`, etc).

### Pós-sobrescrita — checklist de verificação no preview do prot

Code roda `npm run dev` (ou equivalente) e navega pra `/financeiro/unificado`. Validar:

- [ ] **Header de ações** (top da página): 7 botões com gap visível, ícones lucide consistentes
- [ ] **Hero KPI card preto** mostra `Saldo previsto · maio` + valor grande + linha `realizado · pendente` + **sparkline 30d gradiente verde no fundo**
- [ ] **4 KPI cards brancos** (Recebido / A receber / Pago / A pagar) com border-stone-200 e shadow-sm
- [ ] **Pills de filtro** coloridas (verde / amber / red / stone)
- [ ] **Tabela** com header `Vencimento · Lançamento · Contraparte · Categoria · Status · Valor`, números tabulares à direita, FSM 5-bolinhas inline
- [ ] **Click numa linha → drawer 440px abre da direita**
- [ ] **Drawer tem nav superior `Detalhes [💬N] · ✦ IA`** (2 abas)
- [ ] **Drawer mostra `.fin-conferido-toggle` grande** (verde quando on)
- [ ] **Drawer mostra `.fin-ai-anomalia` banner** quando lançamento tem flag IA
- [ ] **Drawer Histórico** lista `.fin-audit-row` com timeline vertical (linha cinza conectando ícones)
- [ ] **Footer atalhos**: `⌘K palette · / buscar · J/K navegar · ␣ marcar pago/recebido · B favoritar · ? Resolver 4`

Comando de verificação rápido (Code roda no terminal do repo após pull):

```bash
grep -c "fin-drawer-tabs\|fin-conferido-toggle\|fin-ai-anomalia\|fin-audit-row\|fin-frescor" prototipo-ui/financeiro.css
# Esperado: >= 30
```

Se retornar < 30 → CSS está truncado, reaplicar.

---

## 4. Seeder Financeiro (tarefa separada — não é F3 visual)

Sintoma atual em prod: `WR2 Sistemas` tem `Maio 2026 = 0 lançamentos`. Cowork prototype usa `financeiro-data.jsx` com 14 receivables + 12 payables distribuídos.

Esse arquivo é mock pro protótipo — em prod o Code precisa de um Seeder Laravel equivalente. **Distribuição mínima pra avaliar visual:**

- 60% `kind=receivable` / 40% `kind=payable`
- 50% liquidados (pago/recebido) / 50% em aberto
- 10–15% atrasados (não 100%)
- Categorias reais ROTA LIVRE: Banner · Adesivo · Fachada · Placa · Gráfica rápida · Insumo · Aluguel · Utilidade · Imposto · Folha · Serviço
- Contraparte real (Padaria Pão Quente, Cervejaria Lupulada, Studio Foco, Receita Federal, etc — ver `financeiro-data.jsx` linhas 80-180)
- Banco padrão: `Itaú PJ · 4521` (confirmar com Wagner se WR2 também usa esse mock)

**Pré-flight obrigatório (lição F3 anterior):**
1. Code lê `Modules/Financeiro/Entities/Titulo.php` e `TituloBaixa.php` ANTES de escrever seeder
2. Seeder respeita `business_id` (R-FIN-001) — todos os Títulos com `business_id = $business->id` do tenant alvo
3. Permission: seeder roda só em `php artisan db:seed --class=FinanceiroDemoSeeder` (não automático)
4. Datas relativas a `now()`: 60% no mês corrente, 30% mês anterior, 10% próximo mês

---

## 5. Prompt zero-touch pro Claude Code (Wagner cola UMA VEZ)

```
@claude Por favor execute estes passos no repo wagnerra23/oimpresso.com:

1. Branch nova: git checkout -b fix/financeiro-css-jsx-v2-sync main

2. Baixar e sobrescrever os 9 arquivos canônicos do Financeiro (Cowork v2, 18/05/2026):

   curl -L -o prototipo-ui/financeiro.css 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro.css?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-app.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-app.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-data.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-data.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-telas-extras.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-telas-extras.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-curation.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-curation.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-ai.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-ai.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-output.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-output.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/financeiro-icons.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/financeiro-icons.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

   curl -L -o prototipo-ui/fsm-stepper.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/fsm-stepper.jsx?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

3. (Opcional, se confiar) atualizar styles.css raiz também:

   curl -L -o prototipo-ui/styles.css 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo/styles.css?t=71a021dde9e6089f758b477035562ae63a32ad11cf7d344d1003b04b56912a43.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779277129&direct=1'

4. Verificação rápida:

   wc -l prototipo-ui/financeiro.css         # esperar 1296
   wc -l prototipo-ui/financeiro-app.jsx     # esperar 1134
   grep -c "fin-drawer-tabs\|fin-conferido-toggle\|fin-ai-anomalia\|fin-audit-row\|fin-frescor" prototipo-ui/financeiro.css
   # esperar >= 30
   grep -c "fin-drawer-tabs" prototipo-ui/financeiro-app.jsx
   # esperar >= 1 (nav das abas)

5. Se a tela /financeiro/unificado em prod consome o protótipo via Inertia direto:
   - Garantir que o bundle Inertia importa o financeiro.css novo
   - Verificar que resources/js/Pages/Financeiro/Unificado.tsx (se existe) não tem CSS inline conflitando

6. Se o Inertia/Vite tem cache: php artisan view:clear && npm run build

7. git add prototipo-ui/financeiro*.{css,jsx} prototipo-ui/fsm-stepper.jsx
   git commit -m "fix(financeiro): sync 9 arquivos canônicos do Cowork v2 (drawer abas, FSM, anomalia banner, audit timeline, frescor pills)

   - financeiro.css 1296 linhas / 129 classes .fin-*
   - financeiro-app.jsx 1134 linhas com <nav className=fin-drawer-tabs>
   - Resolve drawer sem abas + textos colados (ver GAPS_v3 no Cowork)

   Refs: GAPS_v3_FINANCEIRO_PRA_CODE.md no Cowork"

8. git push -u origin fix/financeiro-css-jsx-v2-sync

9. Abrir PR contra main com title:
   "fix(financeiro): sync 9 arquivos canônicos Cowork v2 — drawer abas + frescor pills + audit timeline"

10. (Tarefa separada, NÃO bloquear este PR) Seeder Financeiro:
    - Ler Modules/Financeiro/Entities/Titulo.php e TituloBaixa.php
    - Criar database/seeders/FinanceiroDemoSeeder.php que respeita business_id
    - Distribuição: 60% receivable / 40% payable · 50% liquidado · 10-15% atrasado
    - Categorias ROTA LIVRE (Banner, Adesivo, Fachada, etc — ver financeiro-data.jsx)
    - PR separado: feat(financeiro): seeder demo data para Maio 2026
```

---

## 6. Anti-padrões a NÃO repetir (sumário de LICOES_F3_FINANCEIRO_REJEITADO.md)

- ❌ Inventar nomes de Model (usar `Titulo`, `TituloBaixa`, `ContaBancaria`, `Categoria` — pt-BR legado UltimatePOS)
- ❌ Esquecer tenant scope em controller (R-FIN-001 Tier 0)
- ❌ Inventar middleware (`tenant` não existe — usar stack literal do `routes/web.php`)
- ❌ Sobrescrever controller que JÁ EXISTE em prod (PR #355/#358 — `UnificadoController` é só atualização visual)
- ❌ TSX com shape diferente do que `shapeTitulo()` devolve
- ❌ Marketing otimista em commit ("F3 completo") quando é WIP

---

## 7. Status do protótipo (Cowork)

🟢 9 arquivos sincronizados em `prototipo-ui-patch/vendas-financeiro-completo/`
🟢 URLs públicas geradas (validade ~1h — regenerar se Wagner colar depois)
🟢 Diagnóstico screenshot-comparativo concluído
🟡 Aguardando Code aplicar + screenshot pro F2 (aprovação Wagner)
🔴 Seeder Financeiro fora deste PR — abrir issue/PR separado
