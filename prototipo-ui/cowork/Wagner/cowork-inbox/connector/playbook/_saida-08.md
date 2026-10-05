---
sessao: "_saida-08"
thread: "08 · CONN-O8 · saúde com histórico de 14 dias"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main cb1fe1d6f4
---
# _saida-08

## Resultado
Entregue no PR [#8667](https://github.com/wagnerra23/oimpresso.com/pull/8667), mergeado em
2026-10-05 (`6323b2ae4b`).

`connector:health` publica **uma entrada por execução** e poda o que passou de 14 dias:

**Caminho do json:** `storage/app/connector/health-history.json`
(`ConnectorHealthCommand::caminhoHistorico()`; `connector.health_history_path` sobrescreve, os
testes usam isso para nunca escrever no storage real).

Cada entrada: `executado_em`, `ok`, `tokens_active_24h`, `licencas_recent_24h`,
`rotas_registradas`, `issues[]` e `delphi{chamadas_24h, desvios_24h, taxa_desvio}`. Check que não
pôde medir (tabela ausente) grava `null`, não zero.

A aba Saúde (`/connector/client?aba=saude`) recebe o histórico pela prop `saude`: última execução
com os alertas, uma série de 14 dias por check com o `Chart` do DS (`@/Components/shared/Chart`,
última execução de cada dia) e os desvios do DelphiSync do dia. Sem execução publicada, um vazio
diz que a rotina não rodou.

## De onde vem cada número (medido antes de escrever)
- A saúde antes só ia para o `laravel.log` (`Log::info('connector:health', …)`); a aba media rotas
  e tokens do negócio ao abrir e mostrava licenças como "não medido".
- **Taxa de desvio do DelphiSync:** lida do `licenca_log`, que o middleware `log.delphi`
  (`Modules/Officeimpresso/Http/Middleware/LogDelphiAccess.php`) grava por chamada com
  `metadata.body_format`. Desvio = `unknown`; denominador = chamadas com corpo (`empty` fica fora).
  `DelphiSyncService::logDrift()` não serve de fonte: não tem chamador. `DelphiSyncService` não foi
  tocado (`nao_toca`).
- Sem tabela nova, sem cron novo (o `dailyAt('06:15')` já estava no `ConnectorServiceProvider`),
  sem daemon. Os números são de todos os negócios; a tela é só de superadmin (`abort(403)` em
  `ClientController::index`).

## Provas
**Lane `connector-pest.yml` (MySQL), run
[37314770035](https://github.com/wagnerra23/oimpresso.com/actions/runs/37314770035)**
(pull_request, `017d08f03f`; o paths-filter casou e o Pest executou):

```
PASS  Modules\Connector\Tests\Feature\ObservabilityTest
  ✓ it UC-CONN-26 taxa de desvio conta corpo em formato desconhecido do…
  ✓ it UC-CONN-26 connector:health publica entrada por execução e poda…
  ✓ aba saude le o historico publicado dos ultimos 14 dias        (UC-CONN-27)
Tests:    197 passed (737 assertions)
```

Antes deste PR, no run citado no `_saida-10`: 194 passed (725 assertions). Delta +3 testes,
+12 assertions, 0 skipped.

O commit seguinte do PR (`998319e`) só quebrou uma lista de chaves do teste em várias linhas (o
gitleaks lia `tokens_active_24h` como segredo) e registrou o commit histórico no `.gitleaksignore`.

| | antes | depois |
|---|---|---|
| histórico do `connector:health` | só `laravel.log` | `storage/app/connector/health-history.json`, 14 dias |
| licenças em 24 h na aba | "não medido aqui" | valor da última execução + série |
| desvio do DelphiSync | não medido | `desvios_24h` ÷ `chamadas_24h` do `licenca_log` |
| casos | `[BACKLOG]` sem teste | UC-CONN-26 e UC-CONN-27 |

## Ressalvas
- **O arquivo em produção ainda não foi visto.** Ele nasce na próxima execução da rotina (06:15
  de Brasília) depois do deploy; até lá a aba mostra o vazio "nenhuma execução publicada". A prova
  aqui é a do CI.
- A lane é **advisory**: prova que os casos passam no MySQL, não afirma que bloqueia merge.
- Visual: nenhum baseline regravado (ADR 0409). As seções novas (`saude-ultima`,
  `saude-desvios`) não estão no `connector-api.contract.json`: a aprovação F1.5 e a entrada no
  contrato são do [W].

## Placar
`08 [feito]` — entregue 1 de 1, pela prova de execução: o comando publica uma entrada por execução
no caminho acima, UC-CONN-26 verde na lane MySQL. O `00-INDICE.md` não foi editado.
