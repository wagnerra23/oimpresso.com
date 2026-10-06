# _saida-12 · Aposentar o `POST /boletos/{id}/cancelar`

> Sessão [CL] · 2026-10-06 · base `origin/main` 1ab4ab51b1 · thread 12 do playbook Financeiro.
> Decisão D-FIN-BOLETO-CANCELAR: **condição satisfeita (0), rota aposentada.**

## Passo 1 · Medida (read-only, produção)

Rodado via `php artisan tinker --execute` no Hostinger (`u906587222_oimpresso`, checkout `1ab4ab51b1`), consultando `fin_boleto_remessas` direto (sem global scope, todos os business). Saída literal:

```json
{"total_com_deleted":0,"por_status_vivos":[],"cancelaveis_spec":0,"cancelaveis_guarda_controller":0,"por_business_guarda":[],"max_created":null}
```

- `cancelaveis_spec` = status ∈ {gerado, enviado, registrado, vencido}, como pede o playbook.
- `cancelaveis_guarda_controller` = status ∉ {cancelado, pago}. Medi também este porque a guarda real do `cancelar()` aceitava **qualquer** status fora desses dois, inclusive `gerado_mock`, que não está na lista do playbook. Os dois deram 0.
- Por business: vazio (nenhum business, inclusive o 164).

**Controle positivo** (o zero não é banco errado nem tabela ausente):

```json
{"db":"u906587222_oimpresso","has_table":true,"colunas":19,"controle_fin_titulos":true,"controle_business":true,"eloquent_semscope":0}
```

A tabela existe, com 19 colunas, no banco de produção; outras tabelas do mesmo banco têm linhas; e o Eloquent sem escopo e com `withTrashed()` também conta 0. A tabela **nunca recebeu uma linha** em produção (`total_com_deleted` = 0, `max_created` = null).

## Passo 2 · O que saiu

| Arquivo | Mudança |
|---|---|
| `Modules/Financeiro/Routes/web.php` | sai o `use … BoletoController` e o `Route::post('/boletos/{remessaId}/cancelar' …)->name('boletos.cancelar')`. O 301 de `/boletos` **fica**. O comentário dos 60 dias vira "aposentado 2026-10 (thread 12)". |
| `Modules/Financeiro/Http/Controllers/BoletoController.php` | removido. |
| `Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php` | sai a entrada `BoletoController.php` do D9.a. Lógica do teste intacta. |
| `memory/requisitos/Financeiro/SUPERFICIE.md` | regenerado por `module-surface.mjs Financeiro --write` (derivado: 24 → 23 controllers). Fora do prefixo da thread, mas é o gerador obrigado a acompanhar a árvore; sem ele o `--check` acusa drift. |

`OrphanRenderGateTest.php:40` **não** foi tocado: o comentário cita `BoletoController::index` como história e nenhum gate o lê.

## Varredura de chamadores

`git grep -e 'boletos.cancelar' -e 'BoletoController' -e 'boletos/.*cancelar'` em `Modules app routes resources/js tests config database` (sem `.md`), antes da remoção: só os 3 arquivos listados no playbook, mais 2 comentários (`DrawerCobranca.tsx:122`, `OrphanRenderGateTest.php:40`). Nenhum job, comando ou outro módulo.

Fora disso, só artefatos derivados: `phpstan-baseline.neon` (10 entradas do arquivo removido, inofensivas porque `reportUnmatchedIgnoredErrors: false`; a poda fica pra próxima regeneração do baseline), `memory/governance/catalog.json` (o `catalog-graph.mjs --check` sai 0 com a remoção) e docs históricos em `memory/`.

## Candidato a limpeza (não apagado)

`git grep 'cancelarBoleto'` depois da remoção acha só a definição (`TituloService.php:50`) e o teste unitário dela (`tests/Feature/Modules/Financeiro/TituloServiceTest.php:45-55`). O `CancelarVendaCascade::cancelarBoletos` é método privado de nome parecido que despacha `EstornarBoletoJob` e não chama o do `TituloService`. Logo `TituloService::cancelarBoleto` fica **sem chamador de produção**. Conforme o playbook, **não apaguei**: fica registrado como candidato a uma thread separada de limpeza.

## Prova pendente (pós-merge)

- `php artisan route:list --path=financeiro/boletos` em produção deve listar só `boletos.index`.
- `curl -X POST /financeiro/boletos/1/cancelar` antes: `419` (CSRF, rota existia). Depois do deploy: esperado `404`/`405`.
- Lane `Modules/Financeiro/Tests/Feature/` verde no CI.

## O que esta thread não resolve

O destino dos registros `BoletoRemessa` (não há nenhum hoje) e o modelo em si seguem vivos e fora daqui. O link `#BL-` do Unificado é a thread 11.
