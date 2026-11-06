---
title: "RUNBOOK — Modules/Producao (operação: chaves, fila, comandos e integrações)"
module: Producao
owner: W
status: rascunho
last_validated: "2026-11-05"
related_adrs:
  - 0431-pcp-sai-do-desejo-modules-producao
  - 0436-os-por-item-producao-dona-da-os-do-pedido
  - 0093-multi-tenant-isolation-tier-0
---

# RUNBOOK — Modules/Producao

> Medido no código em `origin/main` `7d997591d7` (2026-11-05), a partir do eixo 4 da auditoria
> do módulo. **Nada aqui foi lido em produção.** Onde o estado em produção importa, a linha diz
> "não medido" e traz o comando que mede. O fluxo de ponta a ponta mora no dicionário do domínio
> (`memory/dominio/producao.md`), e a fronteira com o Manufacturing no [SCOPE](SCOPE.md).
> Este arquivo é o RUNBOOK do módulo; as 6 telas ainda não têm RUNBOOK próprio (US-PCP-020).

## 1. Chaves e flags (todas nascem desligadas)

| Chave | Onde é lida | Como liga | O que fica parado desligada |
|---|---|---|---|
| `business.common_settings.producao_reserva_insumo` | `Modules/Producao/Services/ReservaDeInsumoDaOs.php` | tela Fluxos, `PATCH /fluxos/reserva` (`FluxosController::reserva`) | a reserva do insumo da receita na venda (US-PCP-031). Desligar não apaga o que já foi reservado |
| `business.common_settings.producao_aguarda_material` | `FaltaDeMaterialDaOs.php`, `SituacaoDaOrdem::aguardarMaterialSeFaltar` | **sem tela**: `CatalogoProcessos.php:140` diz "A chave ainda não tem campo na aba Fluxos" | a situação "Aguardando material" nunca é usada (US-PCP-032) |
| `sinal_minimo_pct` (regras da empresa em `fsm_modelos_empresa`; também cliente e venda) | `SinalDaOrdem.php` | regras da empresa, do cliente ou da venda | nulo = sem sinal: a trava do Liberar e a **liberação automática pelo pagamento** não agem (US-PCP-025) |
| `PRODUCAO_ARTE_WHATSAPP` → `producao.aprovacao_arte.whatsapp` | `EnvioDaProva.php`; `Modules/Producao/Config/config.php:9` | env por ambiente | o link da prova não sai por WhatsApp; o operador copia o link |
| `app_apontamento_exige_qr` | `app/Services/AppLojas/AjustesDaEmpresa.php:54` (padrão `false`, na tela de ajustes do app) | ajuste da empresa | o app aceita o número do pedido digitado |
| `CUSTO_FABRICADO_UNIFICADO` → `custo_fabricado.unificado` | `config/custo_fabricado.php` | env | nada: o `CustoDoFabricado` não tem chamador (US-PCP-034) |
| `QUEUE_BACKLOG_WORKER_ENABLED` → `queue.backlog_worker_enabled` | `config/queue.php`; `app/Console/Kernel.php:1310` | env | o worker que drena a fila `default` não existe (ver §3) |

Estado de cada chave em produção: **não medido**. `governance/prod-flags.json` não tem entrada
do Producao. As chaves de `common_settings` se medem por empresa, no tinker de produção:

```php
json_decode(DB::table('business')->where('id', $biz)->value('common_settings'), true)['producao_reserva_insumo'] ?? null
```

## 2. Comando agendado — `producao:fechar-turno`

- Agendado **só** no `ProducaoServiceProvider` (`everyFifteenMinutes()->withoutOverlapping()`);
  `app/Console/Kernel.php` e `routes/console.php` não o citam.
- Fecha o cronômetro esquecido no fim do turno ou na saída do ponto, com chave `auto-<id>`
  (idempotente). O fechamento grava a hora final, então **define as horas e o custo real** do
  apontamento. Não muda a situação da OS.
- Sem `environments()`: roda onde houver `schedule:run`. Sem try/catch por empresa: uma exceção
  interrompe as empresas seguintes. Sem `onFailure`: a falha só aparece no log do scheduler.
  `withoutOverlapping()` sem tempo: se o processo morrer, o mutex segura o comando por 24h.
- Rodar à mão e ver por empresa: `php artisan producao:fechar-turno` (imprime
  `business <id>: <n> fechado(s)`).
- Conferir onde roda (oráculo do runtime, não leitura de código):
  `php artisan schedule:list | grep fechar-turno`.

## 3. Fila — `CriarOsDaVendaJob`

- É o único job do módulo. Nasce no `SaleStageHistory::created` quando a venda de modelo entra
  em `aprovado`, com `->afterCommit()`. `tries = 3`, sem `backoff`, sem `failed()`.
- Sem `onQueue`: vai para a fila `default` da conexão `config('queue.default')`, que é
  `env('QUEUE_CONNECTION', 'sync')`. O worker da `default` no Kernel só existe com
  `queue.backlog_worker_enabled = true` (padrão `false`).
- O que acontece em cada cenário:
  - `database` com o worker desligado: **o job fica parado na tabela `jobs` e nenhuma OS nasce
    da venda aprovada**.
  - `sync`: roda no mesmo request, depois do commit. Se falhar, o usuário vê erro com a
    transição já gravada, e o `tries` não vale.
- Estado em produção: **não medido**, e a memória se contradiz (handoff de 2026-05-14 diz
  `sync`; o de 2026-07-17 diz `database`). Medir no tinker de produção:

```php
config('queue.default'); config('queue.backlog_worker_enabled');
DB::table('jobs')->where('payload', 'like', '%CriarOsDaVendaJob%')->count();
```

- A OS é idempotente: a venda é travada com `lockForUpdate` e o unique
  `(business_id, transaction_sell_line_id)` de `pcp_ordens` impede OS duplicada. Reenfileirar
  não duplica.

## 4. Listeners síncronos (rodam em toda empresa)

Ficam no `boot()` do `ProducaoServiceProvider`:

| Evento | O que faz |
|---|---|
| `TransactionPayment::saved` | `SituacaoDaOrdem::liberarPeloSinalDaVenda`, dentro da transação do pagamento; falha vira `Log::warning('producao.sinal: …')` e nunca derruba o pagamento |
| `Transaction::updating` | `OsDaVenda::guardar`: a venda só passa para `entregue` com as OS prontas |
| `Transaction::updated` (venda cancelada) e `Ordem::updated` (OS concluída ou cancelada) | liberam a reserva de insumo que sobrou |

Procurar falha da liberação pelo sinal: `grep "producao.sinal" storage/logs/laravel.log`.

## 5. Superfícies externas

- **Página pública da prova** `/arte/{token}` (`Modules/Producao/Routes/web.php`): sem login,
  `throttle:30,1`, token de 48 caracteres, só o sha256 no banco, expira e vale uma vez.
- **App das lojas** `/api/app/producao/*` (`routes/api/app/producao.php`, dentro de `auth:api`):
  escritas com `Idempotency-Key` obrigatória e `throttle:30,1`; etiqueta com `throttle:60,1`; os
  GETs `em-andamento` e `motivos-pausa` sem throttle. A empresa vem sempre do usuário do token.
- **WhatsApp da prova**: chamado no request do operador pelo contrato
  `App\Contracts\Atendimento\AvisaClienteWhatsapp`; `EnvioDaProva` nunca lança.

## 6. Integrações (quem chama quem)

Pelo `register()` do provider, o Producao **oferece** contratos do núcleo: `CustoHoraDoPosto`,
`ProgramacaoDaOs` e `CustoRealDaOs` (consumidos pela Comunicação Visual), `HorasDosPostos`
(custeio RKW do Financeiro, que também grava `pcp_postos.custo_hora`), `ReservaDeInsumo` (tela
Fluxos) e `RoteiroDoProduto` (consulta de produto). Ele **consome** `InsumosDaReceita`
(Manufacturing) e `CalendarioDaEmpresa` (Essentials). Três acoplamentos sem contrato:
leitura direta de `comvis_*` (declarada em `governance/module-table-coupling-baseline.json`),
import de `Modules\Ponto\Services\PresencaDoDiaService` no `ApontamentoOperadorService`, e escrita
em `transactions.due_date` (`PrometerPrazoDaVenda`) e `business.common_settings`.

## 7. Riscos conhecidos (achados da auditoria de 2026-11-05)

Estão aqui para quem opera saber onde olhar. Nenhum foi corrigido por este documento; quando
um conserto mergear, a linha muda junto.

- **`transactions.due_date`** recebe a data estimada pela fila (`PrometerPrazoDaVenda`) e é a
  mesma coluna que o Financeiro usa como vencimento do título a receber.
- **OS de venda cancelada** continua apontável e baixando estoque: o cancelamento da venda libera
  a reserva, mas não cancela a OS.
- **Liberar manual** existe no serviço sem rota nem tela; sem sinal mínimo configurado, a OS fica
  em "Aguardando liberação" sem saída pela interface.
- Os cadastros da thread 03 conferem a permissão e não o pacote `producao_module`.

## 8. Testes

Os testes do módulo rodam no CT 100 (nunca local) e no CI pela lane `verticais-pest.yml`. Eles
**pulam em SQLite**: leia `assertions`, não "0 failed".

```bash
tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test Modules/Producao/Tests"
```
