---
sessao: "04"
titulo: Fechamento da competência — BLOQUEADA (W1–W4)
dono: "[W]"
base: e86130722de1
prefixo: nenhum até W1–W4. Quando destravar: Modules/Ponto/Http/routes.php (rota nova) · Http/Controllers/FechamentoController.php · Database/Migrations (se W1 = tabela) · resources/js/Pages/Ponto/Fechamento/** · prototipo-ui/contrato/ponto-fechamento.contract.json · Tests/Feature/FechamentoContratoTest.php
nao_toca: Services/ApuracaoService.php (consolidar CARIMBA, não recalcula — UC-PTF-05) · ponto_marcacoes (append-only) · Jobs/ReapurarDiaJob.php
depende: W1 · W2 · W3 · W4
---
# 04 · Fechamento — bloqueada, de propósito

## Por que não abre
Não existe rota nem Page de fechamento no `main` (medido em `routes.php`: 10 grupos, nenhum "fechamento"). Abrir sem as 4 respostas é **inventar lei**: onde vive o estado da competência (W1), quem pode fechar (W2), onde persistem as exceções assinadas e se bloqueiam o AFD (W3), e se reabrir é auditado ou definitivo (W4). Nenhuma dessas é decisão de tela.

## O que já é lei e vale para quem abrir a thread
- Marcação e movimento de banco **append-only** (Portaria MTP 671/2021): correção = anulação + nova marcação.
- **Consolidar carimba** o que `ponto_apuracao_dia` já tem; recalcular é só `ReapurarDiaJob`.
- Competência **fechada** desabilita Anular no `Espelho/Show` e trava intercorrência (invariante 3 do doc de 04/09).
- Número sem lei não entra na tela: artigo literal.

## Alvo de layout (já medido, 04/09 — 997 nós)
`.pt-body` com **5 seções nesta ordem**: `.pt-toolbar` (5 filhos) · `.pt-passos` (4 passos — a trilha) · `.pt-cols-2` (2) · `SECTION.pt-card` (2) · `.pt-legal` (1) · 2 tabelas (7 `th`, com `scope="col"`) · 30 botões · 1 campo. Fonte: `prototipo-ui/cowork/ponto-fechamento.jsx` (`Fechamento`). A11y do alvo já corrigida no build (aria-live, th scope).

## Quando destravar (ordem)
```
1) [W]  responde W1–W4 (uma mensagem; vira nota no PEDIDO absorvido, não ADR paralela)
2) [CL] PR 1 — migration/policy (se W1 = tabela) + Pest do append-only, SEM UI (≤300 ln)
3) [CL] PR 2 — criar-tela.mjs Ponto/Fechamento PT-01 → rota + controller + Page + charter + casos + contrato + FechamentoContratoTest
4) [CL] PR 3 — thread 05 (Conformidade) só depois
PARAR SE : qualquer PR tocar cálculo de valor/hora sem dupla prova (proibicoes.md VALOR)
```

## Prova (quando destravar — hoje `bloqueada` não conta como ausente)
- `${PAGES}/Fechamento/Index.tsx` + trio · `contrato/ponto-fechamento.contract.json` no schema · `routes.php` contém `fechamento` · `_saida-04.md`
