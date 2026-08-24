---
status: proposal
title: Onda 3 de Arquivos — retenção pela tela, aviso ao titular (LGPD Art. 18 §VI) e purge atrás de portão
proposed_by: Claude Code (a pedido de [W])
proposed_at: 2026-08-24
relates_to:
  - 0123-modules-arquivos-backbone
  - 0093-multi-tenant-isolation-tier-0
  - 0360-deprecacao-admin-center-supersede-0122
---

# PROPOSAL — onda 3 de Arquivos: retenção pela tela, aviso ao titular, purge com portão

> **Status:** `proposal`. ADR é Tier 0 — **só [W] cunha**. Esta proposta existe porque [W]
> escolheu, em 2026-08-24, *"onda 3 inteira, com ADR reabrindo a lápide"*, depois que a
> verificação do módulo mostrou que o escopo esbarra numa decisão anterior.
>
> **Nenhum código da onda 3 entra antes desta proposta virar ADV aceita.** As ondas 0, 1 e 2
> não dependem dela.

## Por que isto precisa de ADR (e não é só mais um PR)

Em **2026-07-27** [W] descartou o item #6 do loop IA-OS — o `jana:retention-purge` — com a
razão registrada em [`memory/proibicoes.md` §5](../../proibicoes.md): *"num ERP não se apaga PII
— controle é por permissão de acesso, não por retenção"*. A lápide bane, textualmente,
**varredura automática por TTL que apague ou anonimize conteúdo de negócio**, sob qualquer nome
(*retention purge, expurgo, poda, anonimização agendada, "limpeza LGPD"*).

A onda 3 de Arquivos constrói UI que dispara exatamente essa classe de varredura. Ou a lápide
não se aplica aqui — e o motivo precisa ficar escrito —, ou ela se aplica e a onda 3 não
acontece. Decidir isso num PR de tela seria mudar uma decisão Tier 0 no calado.

## Os fatos medidos (não impressão)

Medido no `main`, tree `4ebb8d193a74`, em 2026-08-24:

1. **`RetentionCleanupCommand` existe e está registrado** em
   [`ArquivosServiceProvider.php:43`](../../../Modules/Arquivos/Providers/ArquivosServiceProvider.php).
2. **Ele NÃO está agendado.** `git grep` por `retention-cleanup|RetentionCleanupCommand` no repo
   inteiro não devolve nenhuma entrada de schedule — só o registro, os testes, a config e a
   documentação. Hoje a varredura só roda se alguém digitar o comando.
3. **A política já está escrita** em [`Config/retention.php`](../../../Modules/Arquivos/Config/retention.php):
   `strategy` default `hard_delete`, `grace_period_days` 30, `notice_period_days` 30,
   `bucket_override.sensitive` 365 dias — com base legal por `sub_destination`.
4. **O aviso ao titular nunca foi implementado.** O docblock do `notice_period_days` diz que o
   comando *"pode emitir notificação"* — é config aspiracional, sem código que a consuma.
5. **`ArquivosRetentionService` já expõe o caminho de leitura**: `summary()`, `preview()`,
   `run($biz, $dias, $dryRun = true, $purge = false)` e `report()`.

**Consequência do fato 2:** construir a UI da onda 3 não "melhora um job que já roda". Ela
**liga por outra porta** uma varredura que hoje está parada. É essa a mudança de estado que
precisa da decisão de [W].

## A distinção que [CC] propõe (e que [W] confirma ou rejeita)

A lápide de 2026-07-27 fala de *"entidade do ERP ou da camada Jana que carregue **texto digitado
por usuário**"* — conversa, memória, nota. Arquivos é outra coisa:

| | Jana (o que foi descartado) | Arquivos (o que se propõe) |
|---|---|---|
| Objeto | texto digitado (conversa, memória) | ficheiro anexado (XML, foto, PDF, contrato) |
| Prazo | não há prazo legal pra apagar | prazo legal **de guarda** por `sub_destination` |
| Direção da lei | guardar é o default seguro | guardar **além** da finalidade é o risco (LGPD Art. 16) |
| Quem pede o apagar | ninguém | LGPD Art. 18 §VI + o WARN do `HealthCheckCommand` check #4 |

Se essa distinção **não** convencer, o desfecho honesto é: a onda 3 morre, o
`RetentionCleanupCommand` fica como está (registrado, não agendado, manual), e as ondas 0-2
entregam uma tela que **só lê** a retenção. Isso é um desfecho aceitável — não um fracasso.

## O que a onda 3 construiria, se aprovada

**PR-8 — rodar a retenção pela tela, em dry-run forçado.**
`POST arquivos/retencao/simular` com `RetentionRunRequest` (que já existe e já traz
`dry_run` default `true`). O controller **força** `dry_run = true`; `purge` não é aceito nesta
rota. Resultado por fila, nunca no request. Nada é escrito.

**PR-9 — aviso ao titular.** É o único item que mexe em schema:
- migration `titular_avisado_at` (nullable) em `arquivos`;
- valor `notice` no enum de `arquivos_audit_log.action` (3º alargamento — os dois anteriores
  são as migrations `2026_07_02_000001` e `2026_08_10_000001`);
- envio por canal de Notification, na janela `notice_period_days` (30d) antes do vencimento,
  **só** para `bucket = sensitive` **com titular identificado**.
- Avisar **não apaga nada** — cumpre o prazo de aviso e grava a linha na trilha.

**PR-10 — purge atrás de portão. É o ÚNICO dos três que apaga.**
[W] escolheu em 2026-08-24 a *"onda 3 inteira"*, então o purge está **dentro do escopo** — o
que esta ADR fixa não é *se* pode, é *sob que portão*. Exige, cumulativamente: `purge = true`
+ `dry_run = false` + `motivo` ≥ 10 caracteres (a Request já cobra) + confirmação dupla na UI
+ permissão de governança + registro `hard_delete` na trilha + onda 2 mergeada + lane verde +
screenshot aprovado por [W].

> Recuar disso na cunhagem continua sendo prerrogativa de [W] — mas o default escrito aqui é
> o que ele já respondeu, não uma pergunta reaberta.

### Resumo de quem apaga o quê (pra não restar dúvida)

| | Apaga o arquivo? | Recuperável? |
|---|---|---|
| PR-8 simular (`dry_run` forçado) | não | — |
| PR-9 aviso ao titular | não | — |
| PR-10 purge | **sim** | não |
| soft-delete da onda 2 (PR-7) | não — marca `deleted_at` | sim, no grace de 30d |
| `RetentionCleanupCommand` (hoje, manual) | **sim** (`strategy = hard_delete`) | não |
| `arquivos_audit_log` (a trilha) | **nunca**, em cenário nenhum | — |

## As guardas que valem em qualquer cenário aprovado

- **Multi-tenant Tier 0 ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)):** `business_id`
  vem da sessão, nunca do request; nenhum job disparado daqui cruza tenant. Espelhar
  `MultiTenantTest` para cada rota nova.
- **A trilha nunca é purgada.** `arquivos_audit_log` é append-only por
  [ADR 0123 §8](../0123-modules-arquivos-backbone.md), mesmo quando o arquivo é apagado.
- **Zero PII nas vistas de governança** (LGPD Art. 37): nome de arquivo, `storage_path` e MD5
  ficam só na trilha.
- **A tela nunca apaga no request.** Toda escrita irreversível passa por fila com motivo.
- **`biz=4` (ROTA LIVRE) nunca em teste ou smoke** — tenant fictício 98
  ([ADR 0358](../0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

## Decisões que [W] fecha ao cunhar (ou rejeitar)

1. **A distinção acima vale?** Arquivos sai do alcance da lápide de 2026-07-27, ou não?
2. ~~A UI pode purgar (PR-10)?~~ — **já respondido em 2026-08-24: sim, onda 3 inteira.** Fica
   aqui só como registro. O que a cunhagem fixa é o portão (ver PR-10 acima), não o *se*.
3. **Papel de governança:** `arquivos.access` basta para retenção/cofre/trilha, ou purge e aviso
   exigem uma segunda permissão (`arquivos.governanca`)? — medido: `arquivos.access` tem hoje
   **1 ocorrência no repo inteiro**, que é a própria declaração em `DataController:36`; zero
   consumidores.
4. **O `RetentionCleanupCommand` deve passar a ser agendado?** Está fora do escopo das ondas,
   mas a resposta muda o sentido da tela: uma tela que mostra "o que o agendado faria hoje"
   quando não há agendado é uma tela que descreve um futuro que ninguém marcou.

## Se rejeitada

As ondas 0-2 seguem e entregam: acervo, trilha, retenção **em leitura pura** (`summary()` +
`preview()`), cofre, classificar e soft-delete/restore no grace. A vista de retenção passa a
dizer, com todas as letras, que a execução é do comando manual — o que é a verdade hoje. Nenhum
schema muda, nenhuma migration nasce, e esta proposta vira registro histórico do que foi
considerado e por quê.
