---
sessao: "06"
titulo: Painel do HRM — Page
dono: "[CL]"
base: 159e572dd448
prefixo: resources/js/Pages/Essentials/Painel.tsx OU Painel/Index.tsx (+ charter/casos) · DashboardController.php (@hrmDashboard apenas) · prototipo-ui/contrato/essentials-painel.contract.json
nao_toca: @essentialsDashboard · @getUserSalesTargets · AttendanceController · Pages/Essentials/** · AppShellV2 · DS
depende: thread 09 (os cards de presença apontam pro Ponto, não a essentials_attendances) — vaga 2. Caminho = resources/js/Pages/Essentials/ (a árvore respondeu). Irmã golden: Essentials/Metas.tsx (#6869)
---
# 06 · Painel

## A · Identidade
- **alvo (layout):** `hrm-page.jsx` (`Painel`) — medido 04/09 (T1 estável **1007 nós**; a 1ª leitura dá 771 por causa do skeleton `useCarga` — **não medir antes de duas leituras iguais**): `.hrm-grid` com **8 `Card`** · 1º card "O que fazer primeiro" com `.hrm-list` de `.hrm-row` (`.hrm-row-l` → `.hrm-row-t` + `.hrm-row-s` · `.hrm-row-v` → `button.os-btn.ghost`), linha urgente `.urg` + `i.hrm-dot` · vazio = `Vazio variante="done"` · 13 botões, todos navegam (`window.__go`) · 0 tabela.
- **âncora (código):** `DashboardController@hrmDashboard` (rota `/hrm/dashboard`, `name('hrmDashboard')`, **existe**) · blade que sai: `dashboard/hrm_dashboard.blade.php` (12 KB).
- arquétipo PT-05 (dashboard) · persona Wagner.

## B · Não inventar
- **DADO NÃO LIDO:** `DashboardController@hrmDashboard` (11 KB) não foi lido em 04/09 nem em 05/09. A thread **começa** lendo o método; cada card só recebe agregado que o método já calcule. Sem agregado ⇒ `—` + linha no PR. **Nenhuma query nova só para encher card.**
- Depois de D1: a fila "marcação sem saída" e qualquer contagem de presença **não** vêm de `essentials_attendances` — ou vêm do Ponto (fora deste prefixo) ou viram link "Ver no Ponto" sem número.
- Reusar: `@/Layouts/AppShellV2` · `shared/{PageHeader,KpiCard,KpiGrid,EmptyState}` · o padrão de gráfico **acessível** de `Pages/Repair/Dashboard/Index.tsx` (`role=img` + `<title>` + coluna textual + resumo sr-only) · `Inertia::defer` para séries.

## C · Comportamento (EARS)
| elemento | TAG | QUANDO → O SISTEMA DEVE | persiste | reversível | prova |
|---|---|---|---|---|---|
| item da fila | BUTTON `.os-btn.ghost` | clique → navegar para a tela dona (licenças pendentes → 02; meta faltando → 04) | — | voltar | rota muda |
| card KPI | BUTTON (não DIV) | clique → navegar com filtro aplicado | querystring | filtro reversível na tela destino | rota + filtro |
Invariantes: Painel **não escreve nada** (sem `aria-live` por decoração) · permissão nega antes · sem número inventado.

## Execução
```
ARQUIVOS A EDITAR : resources/js/Pages/Essentials/Painel{.tsx|/Index.tsx} (CRIAR via criar-tela.mjs Essentials/Painel PT-05)
                    DashboardController.php (@hrmDashboard → Inertia::render com os agregados que JÁ calcula)
PASSO A PASSO     : 1) gh pr list --state open × estes arquivos 2) LER @hrmDashboard 3) listar agregados existentes × 8 cards do alvo
                    4) criar-tela.mjs 5) KPIs + fila derivada dos MESMOS agregados 6) cards de presença → "Ver no Ponto"
                    7) _saida-06.md com a tabela card × agregado × (dado | —)
PARAR SE          : (a) card sem agregado → "—" (não inventar) (b) precisar de query nova → card sai do escopo
                    (c) 09 não fechada → cards de presença ficam como link sem número, declarado
```

## Prova
- `resources/js/Pages/Essentials/Painel.tsx` **ou** `Painel/Index.tsx` + charter + casos · `contrato/essentials-painel.contract.json` · controller contém `Inertia::render('Essentials/Painel` · tabela card × agregado no `_saida-06.md`
- `_saida-06.md` · placar no PR · T7 não verificável daqui
