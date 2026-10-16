# Tela 02 · Apontamento por QR (P8) ✅

> ✅ **Rota no ERP desde o PR 06 do playbook `propostas-app`** (2026-10-15). Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6). Origem: handoff `propostas-app` do Design, conferido em 2026-10-05; revisado no mesmo dia por decisão [W] para gravar no PCP. O QR impresso no PDF da OS é o PR 07.
>
> **Implementado em:** `Modules/Producao/Http/Controllers/Api/AppApontamentoController.php` · `app/Services/AppLojas/EtiquetaProducao.php` · `routes/api/app/producao.php` · teste `tests/Feature/Sells/AppProducaoApontamentoContratoTest.php` (UC-APPAPT-01..10, lane `sells-pest`). Motivos de pausa: `ApontamentoOperadorService::motivosDePausa` (2026-10-16).

## Onde grava
Decisão [W] 2026-10-15: o apontamento do app grava no módulo de Produção (PCP, ADR 0431 — camada única de apontamento), pela regra do operador `ApontamentoOperadorService` (thread 04 do playbook de Produção). Esta API só autentica, lê o QR, confere o ajuste da empresa e traduz as recusas do serviço em HTTP. O `business_id` vem do usuário do token em toda chamada — a sessão não existe aqui.

O apontamento web antigo da Comunicação Visual (`/comunicacao-visual/api/apontamentos`, US-COMVIS-004) não é usado pelo app.

- **Pedido** = a OS (`comvis_os.id`; no PCP, `os_tipo = comvis_os`).
- **Etapa** = linha de `pcp_programacoes`: uma OS num posto, com responsável. O app manda o `programacao_id` que a etiqueta devolveu.
- Só o responsável aponta na etapa. Uma etapa de pessoa aberta por operador; etapa de máquina pode ficar rodando enquanto ele faz outra.
- Append-only: nada é apagado nem reescrito; toque errado vira pedido de correção para o gestor.

## Leitura

`GET /api/app/producao/etiqueta/{codigo}` (throttle 60/min) — QR ou número digitado:
```json
{ "pedido": { "id": 4812, "numero": "OS-2026-04812", "resumo": "Fachada ACM + Letra caixa", "cliente": "Ótica Visão Clara" },
  "etapa": { "programacao_id": 77, "status": "pendente", "posto": { "id": 3, "nome": "Impressão", "tipo": "pessoa" },
             "inicio_previsto": "2026-10-15T08:00:00-03:00", "responsavel": { "id": 12, "nome": "Diego" } },
  "pode_apontar": true }
```
- QR: `oimpresso:p:<business_hash>:<os_tipo>:<os_id>` (ex. `…:comvis_os:501`), impresso no PDF da OS (`GET /producao/os/{id}/etiqueta`, PR 07). `business_hash` = 12 hex de um HMAC da APP_KEY sobre o id da empresa (`EtiquetaProducao::codigo`), para o QR não expor o id. Hash de outra empresa ou `os_tipo` que o app não aponta (hoje só `comvis_os`) → 404, sem consultar o pedido. O formato antigo sem tipo (`oimpresso:p:<hash>:<os_id>`) segue lido como `comvis_os`. É o mesmo formato que a tela do operador do PCP (thread 04) lê; o QR por etapa da etiqueta térmica é da thread 05.
- Número digitado: o id da OS ou o número (`OS-2026-00012`, sem diferença de maiúscula).
- A etapa devolvida é a do operador naquela OS. Se a OS só tem etapa de outro, vem essa, com `pode_apontar: false` e o responsável — o app mostra com quem está e não grava nada.
- `resumo` = até 3 descrições dos itens do orçamento da OS, unidas por " + "; `cliente` = contato do orçamento.

`GET /api/app/producao/apontamentos/em-andamento` → o que está rodando agora (E04 A1):
```json
{ "pessoa": null | { "id", "tipo": "pessoa", "pedido": {…}, "etapa": {…}, "iniciado_em",
                      "motivos_pausa": [ { "id": 31, "nome": "Troca de bobina" }, { "id": 30, "nome": "Setup" } ] },
  "maquinas": [ { "id", "tipo": "maquina", "pedido", "etapa", "iniciado_em", "motivos_pausa": [ … ] } ],
  "ponto": null | { "entrada", "saida", "presenca_minutos" } }
```
`ponto` é a linha do próprio operador, lida do Ponto (nunca escrita). `motivos_pausa` (2026-10-16, campo acrescentado) = motivos de pausa ativos do posto **daquele** apontamento, na ordem do cadastro — a mesma lista do `GET motivos-pausa?posto_id=`; posto sem motivo → `[]`. Vai aqui e não na etiqueta porque a pausa sai do apontamento aberto; a etiqueta serve para iniciar.

`GET /api/app/producao/motivos-pausa?posto_id={id}` → motivos de pausa ativos da empresa do token (UC-PCV-33, cadastro do posto — thread 03 do PCP):
```json
{ "motivos": [ { "id": 31, "posto_id": 3, "nome": "Troca de bobina" },
               { "id": 30, "posto_id": 3, "nome": "Setup" } ] }
```
- Só `tipo = pausa` e `ativo`; motivos de refugo não entram. Ordem: posto, depois a ordem do gestor, depois o nome.
- Com `posto_id`, só os daquele posto. Sem ele, todos os postos da empresa (o app filtra por `posto_id` de cada item).
- Posto de outra empresa ou inexistente → `200 { "motivos": [] }` (não diz se o posto existe). `posto_id` não numérico → `422 campos.posto_id`.
- É exatamente o conjunto que o `pausar` aceita para um apontamento daquele posto. O app guarda a lista por posto no aparelho para pausar sem internet; se o gestor desativar o motivo antes do envio, o `pausar` da fila volta `422 campos.motivo_id`.
- Leitura: sem `Idempotency-Key`, sem throttle próprio (o do grupo `api`).

## Escrita
Todas com header `Idempotency-Key` (até 64 caracteres; vira a `chave_idempotencia` do PCP, única por empresa) e throttle 30/min.

**Fila sem internet — exceção à regra 8 do `PLAYBOOK-PRS.md` (offline trava a escrita, sem fila), só para o apontamento.** [W] 2026-10-15, textual: *"Apontamento pode ficar numa fila sem internet e ser enviado depois, sem duplicar. A regra 8 do playbook do app ganha essa exceção só para o apontamento; o resto continua travando sem internet."* Confirmado no mesmo dia: *"Confirmo: o apontamento grava pelo Modules/Producao, pode ficar numa fila sem internet sem duplicar (só ele)"*.

O app guarda o toque e envia depois, com o `momento` do toque (ISO 8601 com fuso, ex. `2026-10-15T14:03:00-03:00`) e a mesma `Idempotency-Key` gerada no aparelho em toda tentativa. Sem `momento`, vale a hora do servidor. Limites, do serviço: até 5 min à frente do relógio do servidor e até 72 h para trás; fora disso, `422 campos.momento` ("Hora do aparelho errada; acerte o relógio" / "Envio vencido; peça a correção ao gestor"). Hora fora do turno não é recusada: o turno (ou a saída do ponto) fecha sozinho o que ficou aberto, e uma conclusão da fila com hora anterior a esse fechamento vira pedido de correção — regra do serviço (thread 04), não desta API.

Reenvio com a mesma chave e o mesmo corpo → a mesma resposta com `200` (a primeira vez é `201`). Mesma chave com corpo diferente → `422 idempotencia_conflito`.

`POST /api/app/producao/apontamentos/iniciar` `{ programacao_id, momento?, codigo? }` → `201 { id, programacao_id, iniciado_em }`
- `codigo` (o texto do QR) é obrigatório quando a empresa liga `app_apontamento_exige_qr`; tem de ser desta empresa e da OS desta etapa, senão 404.
- Também serve para retomar depois de uma pausa.

`POST /api/app/producao/apontamentos/{id}/pausar` `{ motivo_id, momento? }` → `201 { id, encerrado_por: "pausa", origem, encerrado_em, minutos }` (motivo de pausa ativo do posto do apontamento, cadastro da thread 03; outro → `422 campos.motivo_id`)
- `motivo_id` do cadastro de motivos do posto.

`POST /api/app/producao/apontamentos/{id}/concluir` `{ momento? }` → `201 { id, encerrado_por: "fim", origem, encerrado_em, minutos }`
- Conclui a etapa; a OS segue para a próxima e o próximo responsável é avisado.
- `minutos` = hora do encerramento − hora do início. O cronômetro do app é só exibição.
- Se o turno ou a saída do ponto já tinha fechado o apontamento e a conclusão (da fila) é anterior a esse fechamento, o serviço registra a hora real como correção para o gestor e devolve o fechamento existente (`200`, `origem: "turno"` ou `"ponto"`).

`POST /api/app/producao/apontamentos/{id}/corrigir` `{ momento, justificativa }` (5–500 letras) → `201 { id, apontamento_id, situacao: "aguardando_gestor" }`
- Pedido de correção de horário: o par original fica; o gestor aplica (mexe no custo, regra mestre).

`{id}` é sempre o da linha de **início** do apontamento. Só o próprio operador pausa, conclui ou corrige o seu; o de outro operador dá o mesmo 404 do de outra empresa.

## Erros

| Código | Quando |
|---|---|
| `401` | sem token |
| `403 sem_permissao` | módulo Produção fora do pacote da empresa ou sem `producao.access` |
| `404 nao_encontrado` | etiqueta/OS/etapa de outra empresa ou inexistente; apontamento de outra empresa ou de outro operador |
| `409 etapa_alheia` | a etapa está com outro responsável — vem `etapa` com posto e responsável atuais; nada gravado |
| `409 em_andamento` | o operador já tem uma etapa de pessoa aberta — vem `apontamento_id` |
| `422 validacao` `{campos}` | campo inválido; sem `Idempotency-Key`; `momento` fora dos limites; sem `motivo_id`; sem justificativa; número digitado com `app_apontamento_exige_qr` |
| `422 bloqueado` | etapa já encerrada ou apontamento já encerrado |
| `422 idempotencia_conflito` | chave reusada com outro corpo |
| `429` | throttle |

Corpo de erro: `{ erro, mensagem }` (mais `campos` na validação).

## ERP — etiqueta
O PDF da OS ganha o QR com o código acima (`EtiquetaProducao::codigo($bizId, $osId)`). PR 07, no módulo de impressão.

## App
Câmera para ler o QR, pedida no primeiro uso. Número digitado como alternativa, se a empresa não exigir o QR. Para iniciar, o app manda o `codigo` lido quando a empresa exige QR.

## Ajuste da empresa
`app_apontamento_exige_qr` (padrão `false`), lido por `AjustesDaEmpresa::valor` nesta API (o serviço do PCP não o confere). Com `true`, o app esconde "Digitar número"; o servidor recusa número em `GET /producao/etiqueta/{codigo}` e exige no `iniciar` o `codigo` do QR da OS da etapa → `422 campos.codigo`.

## Fora deste contrato
- Lista "minhas etapas de hoje" (UC-PCV-19): o serviço tem `minhasEtapasDeHoje`, mas a tela 02 do app abre pela etiqueta; fica para quando o app ganhar a lista.
- Área `producao` no `areas` de `/api/app/inicio` (§6): o `InicioController` é dos PRs 01/02.
