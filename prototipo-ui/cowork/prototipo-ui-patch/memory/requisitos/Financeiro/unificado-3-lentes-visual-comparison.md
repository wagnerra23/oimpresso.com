# Visual Comparison (MWART) — Unificado "3 lentes" (US-FIN-029)

---
screen: /financeiro/unificado
charter: resources/js/Pages/Financeiro/Unificado/Index.charter.md (v13 → v14 com esta entrega)
status: approved-direction ([W] 2026-05-31, sessão Cowork handoff de design; registrado no charter v10)
author: [CC] Claude Cowork · 2026-06-09
f1_reference: prototipo Cowork `financeiro-page.jsx` (FinHero + FinOverflowMenu, host oimpresso.com.html)
related_us: US-FIN-029
---

## O problema (estado atual do live — anti-pattern registrado no charter)

O header do Unificado carrega **fileira de ~7 botões inline** (Buscar · Resumir mês · Fechamento ·
Apresentar · Conciliar · Plano de contas · Novo) — [W] 2026-05-31: *"está muito apertado"*; esmaga o
título abaixo de ~1100px (Larissa @1280 com sidebar = conteúdo ~1040px). O mesmo bloco foi
copy-paste pro DRE (decisão Q8a, `Dre/Index.charter.md` Goal 6), duplicando o anti-pattern.

## A direção aprovada ([W] 2026-05-31 — não rediscutir)

1. **3 lentes segmented no header**: **Caixa · A receber · A pagar** — dirigem o filtro grosso da tela.
2. **Menu `···`** com as ações secundárias: Buscar ⌘K · Resumir mês · Fechamento · Apresentar · Imprimir · Exportar.
3. **"+ Novo título"** (dropdown Onda 25) permanece como única ação primária visível.
4. **Sub-páginas ficam no sidebar** (`FinSubNav` — JÁ EXISTE no live, não tocar).

## Antes → Depois (resumo estrutural)

| Zona | Antes (live) | Depois (US-FIN-029) |
|---|---|---|
| Header direita | 7 botões inline | Lentes (segmented 3) + `+ Novo título` + `···` |
| Filtro grosso | só chips (Todas/Aberto/Receber/Pagar/Recebidas/Pagas/Atraso) | Lente (camada 1) + chips refinam DENTRO da lente (camada 2) |
| Sub-páginas | FinSubNav sidebar ✓ | inalterado |
| DRE topnav | copy-paste dos 7 botões | extrai `<FinModuleTopnav>` compartilhado (gatilho US-FIN-TOPNAV-COMPONENT atingido: 2ª tela tocada) |

## Semântica das lentes (contrato de comportamento)

- **Caixa** (default) = todos os estados (rec + received + pay + paid) — a visão "entra × sai".
- **A receber** = rec + received (+ atrasados de receivable).
- **A pagar** = pay + paid (+ atrasados de payable).
- Chips de ciclo continuam existindo e **refinam dentro da lente**; chip incompatível com a lente
  ativa some (não renderiza desabilitado — menos ruído).
- **Querystring `?lente=caixa|receber|pagar`**, clamp default `caixa` (mesmo padrão `?tab=` do Fluxo). Deep-link funciona.
- **KPI cards continuam clicáveis** e ficam coerentes: clicar KPI "A pagar" seta a lente `pagar` (drill-down ADR ui/0002 preservado).
- Contadores das lentes opcionais (qtd por lente) — só se não custar query extra (usar os counts que `kpisCore` já expõe).

## 8 dimensões (régua MWART)

| Dimensão | Antes | Depois (esperado) |
|---|---|---|
| 1. Hierarquia | título disputa com 7 botões | título + 1 decisão (lente) + 1 ação primária |
| 2. Densidade útil | densidade de CHROME, não de dado | chrome enxuto; densidade fica na tabela |
| 3. Reflow 1280px | esmaga título <1100px | lentes + 2 botões cabem; `os-page-h` reflua sem quebra |
| 4. Modelo mental | filtro = 7 chips planos | pergunta do domínio primeiro ("caixa? receber? pagar?") |
| 5. Consistência | DRE duplica header na mão | `<FinModuleTopnav>` único, 2 telas |
| 6. Acesso a ações | tudo exposto, tudo igual | primária visível; secundárias a 1 clique no `···` |
| 7. Deep-link | filtros por querystring ✓ | + `?lente=` (paridade com Fluxo `?tab=`) |
| 8. Teclado | ⌘K, `/`, J/K ✓ | inalterado + `···` navegável por teclado (DropdownMenu shadcn já dá) |

## O que NÃO muda (anti-regressão)

- FinSubNav, FinBaixaSheet, TituloCreateSheet, drawer, KPI strip, tabela, atalhos — intactos.
- Nenhum token novo, nenhuma cor nova (gates ①②③: zero def de token, zero `.css` novo, zero cor crua).
- Multi-tenant Tier 0 intocado (mudança é só de apresentação/filtro client+querystring).

## Riscos

- **R1:** testes Pest existentes que asseram os filter chips podem quebrar → atualizar junto, nunca apagar GUARD.
- **R2:** usuários habituados aos chips — mitigado: chips continuam, só ganham a camada de lente acima.
- **R3:** DRE: extrair `<FinModuleTopnav>` muda 2 telas num PR — se crescer, separar em PR-2 (Unificado primeiro).

## Gate F1.5

Screenshots antes/depois no PR @1280px (Larissa) e ~1440px ([W]). Direção já aprovada por [W]
2026-05-31; cumprido o MWART + screenshots, merge segue o loop autônomo (não é Tier 0).
