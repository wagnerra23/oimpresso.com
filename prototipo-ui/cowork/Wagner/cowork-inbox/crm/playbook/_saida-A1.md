---
sessao: "A1"
titulo: "ALVO lote 1 do Crm — 3 de 3 telas medidas — saída da thread"
autor: "[CL]"
data: 2026-09-30
base: origin/main 10ea9f390
thread: 01-alvos.md §A1
veredito: "entregue — 3 alvos medidos (25 seções, 0 ausentes), cada um 2× byte-idêntico; secao-check conforme nos três."
---

# _saida-A1 · Alvos do Crm, lote 1

## O que saiu

| tela | slug | rota no protótipo | seções |
|---|---|---|---|
| Leads | `crm--leads--index` | `crm-leads` → `CrmBladePage view=leads` → `TelaLeads` (`crm-blade.jsx`) | 6 · header · nav · filtros · toolbar · grade · rodape |
| Acompanhamentos | `crm--acompanhamentos--index` | `crm-followups` → `CrmBladePage view=acompanhamentos` → `TelaAcompanhamentos` | 7 · header · nav · filtros · toolbar · abas · grade · rodape |
| Painel | `crm--painel--index` | `crm-painel` → `CrmBladePage view=painel` → `TelaPainel` | 12 · header · nav · kpis_pessoais · meus · chamadas · kpis_totais · fontes · fases · aniversarios · por_usuario · conversao · chamadas_todos |

Arquivos: `governance/design/targets/<slug>.{secoes,alvo}.json` para os 3 slugs. Os `.alvo.json` são saída do `alvo:medir`, não editados à mão.

## Como foi medido

- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR`) na porta 5563.
- Seletores colhidos com `alvo:mapa --rota <r> --raiz <seletor>` no DOM vivo (raízes `.cb-root`, `.cb-root > .pb-body`, `[data-contract=…]` e `… > .pb-widget-b`). Onde o protótipo tem `data-contract` (widgets, toolbar, rodapé), o seletor usa o contrato; as duas grades de KPI do Painel e o bloco de filtros não têm contrato e vão por posição.
- `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5563/ --tela <slug> --rota <r> --secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000`, duas vezes por tela; sha256 (16 primeiros) idêntico nas duas:
  leads `ea8ec2d2444e33a8` · acompanhamentos `f99b292dfb3e0e03` · painel `de1cad166ddd0603`.
- Viewport 1280×900, tema dark (default do `alvo.mjs`, que espera `__oiLazyDone` e 2000 ms sem mudança no nº de nós). `nos_totais`: 891 · 865 · 753. `ausentes: []` nos três.
- `node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho`: **conforme** nos três (6 · 7 · 12 seções).

## Ressalva medida: o relógio do header

O header do `CrmBladePage` mostra "Atualizado HH:MM" (`new Date()`). O 1º par de medidas de Acompanhamentos divergiu **só** em `base.assinatura` (23:43 × 23:44, virada de minuto); refeito dentro do mesmo minuto, saiu idêntico. O relógio só entra em `base.assinatura`, que o `secao-check` trata como informativo (não bloqueia) — por isso os três checks acima mostram `~ (página) → base.assinatura`. Nenhuma seção carrega o horário.

## Provas do json conferidas

- `governance/design/targets/crm--leads--index.alvo.json` com a chave `secoes` — existe.
- `governance/design/targets/crm--acompanhamentos--index.alvo.json` com a chave `secoes` — existe.
- `governance/design/targets/crm--painel--index.alvo.json` com a chave `secoes` — existe.

## Pendente (não inventado)

1. **Vistas secundárias fora do alvo.** Leads mediu a vista padrão `list_view` (o kanban é a outra opção do `CliSeg`); Acompanhamentos mediu a aba padrão `todos` (a aba `Acompanhamento recorrente` não). Se o contrato da thread 01 quiser essas vistas, é outro alvo (com `--clicar`).
2. **`design.json` por tela e `tableRow` em `__DD_ROLES`** (pedido da ficha) são do `design-diff` (`governance/design/targets/medidas/` e `roles/`), fora do `prefixo` desta thread; o `alvo.mjs` não recebe `__DD_ROLES`. Não feito aqui — mesmo caso do `_saida-A1` do lote-trio-medida.
3. A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada: o README está fora do `prefixo`.
4. Fidelidade do protótipo ao Blade legado segue não provada (o próprio índice §4 diz isso). O alvo mede o protótipo, não o legado.

## Placar

entregue 3 de 3 alvos · 25 de 25 seções medidas · ausentes 0.

## PR

O PR que adiciona este arquivo — branch `claude/crm-thread-A1`.
