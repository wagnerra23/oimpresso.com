---
date: "2026-10-05"
time: "09:38 BRT"
slug: threads-vendas-repair-pilha-mergeada
tldr: "Threads de Vendas e de Repair do playbook Cowork entregues e mergeadas; pilha do Repair (00→A1→02→05) inteira no main com fotos de referência aprovadas por [W]; smoke em produção feito. Abertos: busca cross-empresa do portal ConsultaOs, captcha e subida da thread 00 ao projeto cópia."
prs: [8502, 8527, 8528, 8530, 8531, 8533, 8576, 8587, 8610, 8493, 8490, 8486, 8520]
decided_by: [W]
related_adrs: [0411-snapshot-de-pixel-fora-do-passo-3-da-0409, 0409-zero-baseline-de-tolerancia-conformidade-absoluta]
next_steps:
  - "[W] decidir como o portal /consulta-os identifica a empresa do cliente (hoje a busca cruza empresas)"
  - "Maiara subir _saida-00.md e repair-page.jsx ao projeto cópia do Cowork"
  - "Captcha do portal (US-CONSULTA-001 segue parcial)"
---

# Handoff — threads de Vendas e Repair, pilha do Repair no main

## TL;DR

Pilha do Repair (#8528→#8530→#8531→#8533) e threads de Vendas no main; fotos de referência de Repair/JobSheet aprovadas por [W]; smoke em produção feito. Abertos: busca cross-empresa do portal /consulta-os, captcha, subida da thread 00 ao projeto cópia.

## Estado MCP no momento

- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work` (@wr23): sem tasks ativas.

## O que aconteceu

Sessão longa (02→05/10) executando as threads do playbook Cowork de Vendas e de Repair e gerindo os merges.

- **Vendas:** threads 01/02/03/04/05/06 + filtro de data do Sells/Index (#8502). Todas mergeadas; #8493 (Remessas) entrou em 03/10.
- **Repair:** pilha #8528 (00, rota rep-* por tela) → #8530 (A1, alvos) → #8531 (02, título 22px) → #8533 (05, contratos + abas Pendentes/Concluídas/Entrega vencida/Todas no JobSheet). Toda no main; o último entrou em 05/10 11:22Z. #8576 (04, portal /consulta-os lê OS reais) + follow-up #8610.
- **Fotos de referência novas** (ADR 0411, decisão [W]): Repair e Repair/JobSheet entraram no `visreg-screens.json`, com os flags `MWART_REPAIR_*` ligados só no runner do visual-regression. [W] aprovou as duas e depois a do JobSheet com abas (comentários no #8531 e no #8533).
- **Refutação GT-G5** do lote do #8528: Fable 5.1, 19/19 arquivos, 0 erros (`memory/sessions/2026-10-02-refutacao-gt-g5-lote-8528-r1.md`).
- **Cowork (projeto w):** subiram `_saida-01/03` de Vendas (#8587), `_saida-02/05` de Repair e os arquivos de decisão D1–D3.
- **Merges:** a partir de 03/10 ficaram com a sessão vigia (`local_4a3a47af…`); esta sessão cuidava de conflitos e pushes.

## Smoke em produção (05/10, WR2 Sistemas, só leitura)

- `/repair/repair` e `/repair/job-sheet`: telas React, título 22px medido, âncoras presentes, abas fazem `recorte=` e recebem 200, sem erro no console.
- JobSheet vazio na WR2 (`recordsTotal: 0` até na aba Todas): o filtro de cada aba não pôde ser conferido com dados reais.
- `/repair-status` → 302 para `/consulta-os`; o portal responde 200.

## Lições catalogadas

- Afirmei que o `_STATUS-GENERATED` de Sells reprovaria o Governance Gate; o passo é `continue-on-error`. Regenerá-lo só pôs o Felipe como code owner do #8530. Revertido no 2b8048cf12. Mesma família LC-08 (afirmar sem medir a fonte).
- A linha `✗ PLANS-INDEX… DESATUALIZADO` no log do Governance Gate é saída do selftest (`plans-index.test.mjs`), não drift real.
- Push em PR com code owner derruba a aprovação (`dismiss_stale_reviews`); resolver conflito no PR de base da pilha derruba a de todos acima.

## Próximos passos pra retomar

1. [W] decidir a identificação da empresa no portal `/consulta-os`. Hoje `JS2026/0001` traz OS de várias lojas; um teste trava o comportamento atual.
2. Maiara: `node scripts/design-sync/pendentes-cowork.mjs --plano --projeto copia` (thread 00 do Repair).
3. Captcha e decisão sobre o nome do funcionário nas atividades do portal.

## Pointers

- Script de cadeia de PRs empilhados (scratch desta sessão, não versionado): `sobe-cadeia.sh` + `uniao-estado-cowork.mjs`.
- Evidência da refutação: `memory/sessions/2026-10-02-refutacao-gt-g5-lote-8528-r1.md`.
