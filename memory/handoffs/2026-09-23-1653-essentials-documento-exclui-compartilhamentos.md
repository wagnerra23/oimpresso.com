---
date: "2026-09-23"
time: "1653"
slug: "essentials-documento-exclui-compartilhamentos"
tldr: "Excluir documento no Essentials passou a apagar junto os compartilhamentos (UC-EDOC-04, vermelho provado antes do conserto, verde depois). #7814 mergeado. Prod medido: 0 compartilhamentos, 0 órfãos — nada a limpar."
decided_by: [W]
cycle: null
prs: [7814]
us: []
next_steps:
  - "Nada pendente deste tema. O vermelho do visual-regression em Financeiro/Dre é da US-FIN-069 (#7799), não deste PR"
  - "Se o compartilhamento de documentos passar a ser usado, o UC-EDOC-04 já defende o comportamento na lane Essentials"
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0358-doutrina-de-teste-tenant-98-supersede-0101", "0130-handoff-append-only-mcp-first"]
---

# Handoff 2026-09-23 16:53 BRT — Essentials: excluir documento apaga os compartilhamentos

## TL;DR

A thread 03 do playbook Prontidão (#7763) achou, por leitura de código, que o `destroy` de
`DocumentController` do Essentials apagava o documento e deixava a linha de
`essentials_document_shares` órfã, contra o charter de `Essentials/Documents/Index`
(*"apagando junto os compartilhamentos"*). Esta sessão provou o defeito com teste, corrigiu e
mergeou: **[#7814](https://github.com/wagnerra23/oimpresso.com/pull/7814)**, squash
`ae3c4d92b`, 2026-09-23 16:52 UTC.

## O que foi feito

| Passo | Resultado |
|---|---|
| Teste antes do conserto (UC-EDOC-04, novo em `Index.casos.md`) | memo meu no tenant 98 com 2 compartilhamentos (`user` + `role`) e memo **de minha autoria** no tenant 99 (`seededSupportClientTenant()`) com 1. Excluir o do 99 não apaga nada; excluir o meu apaga o memo e os 2 compartilhamentos |
| Vermelho provado | run [35877603451](https://github.com/wagnerra23/oimpresso.com/actions/runs/35877603451) (dispatch, só o teste): 1 `<failure>` em 106, linha 202, `Failed asserting that 2 is identical to 0`. Os asserts do tenant 99 passaram antes dele. Conferido no JUnit baixado, não pelo exit code |
| Conserto | `DocumentController::destroy`: compartilhamentos + documento numa `DB::transaction`. O `$document` já vem filtrado pelo `business_id` da sessão; o scope via parent do `DocumentShare` reforça. Compartilhamentos saem antes do documento porque o scope resolve o tenant pelo parent |
| Verde | run [35879941799](https://github.com/wagnerra23/oimpresso.com/actions/runs/35879941799): 0 falhas em 106 (354 asserts), UC-EDOC-04 com 8 asserts |
| Nota velha do casos.md | dizia que o arquivo de teste não estava em lane nenhuma; já estava na allowlist de `essentials-pest.yml` desde o #7763. Corrigida; o workflow não precisou mudar |
| Medição em prod (só leitura) | `artisan tinker` no Hostinger, ambiente `live`, banco `u906587222_oimpresso`: `essentials_document_shares` com **0 linhas**, logo **0 órfãos**. Controle: a mesma sessão leu 10 documentos (9 memos + 1 arquivo, 1 business). Registrado no corpo do #7814 |

## O vermelho que ficou no PR, e por que não foi mexido

O `visual-regression` (não bloqueia o merge) reprovou **Financeiro/Dre** com diff de 6,6%. O
PR não toca Financeiro e o próprio gate registrou que nenhuma tela do raio dele mudou. A causa
já está diagnosticada na **US-FIN-069** ([#7799](https://github.com/wagnerra23/oimpresso.com/pull/7799)):
a baseline do DRE que entrou com o #7767 foi fotografada fora do ambiente do CI. Regenerar a
baseline está vetado (ADR 0409; §5 2026-09-21). O dono é o Financeiro.

## Ambiente nesta sessão

- **CT 100 inacessível** a sessão toda (`tailscale ssh` → 502). As provas de teste vieram do CI
  (lane `PHP / Pest (Essentials · MySQL)`), nunca de Pest local.
- **MCP `oimpresso` fora do ar** (`CONNECT_TIMEOUT` no SessionStart).

## Estado MCP no momento do fechamento

Não consultado: o servidor MCP `oimpresso` não conectou nesta sessão, então `cycles-active`,
`my-work`, `sessions-recent` e `decisions-search` não puderam rodar. Substituto usado: índice
`memory/08-handoff.md` + `git log origin/main` + `gh pr view`. Nenhuma task MCP foi criada ou
alterada.
