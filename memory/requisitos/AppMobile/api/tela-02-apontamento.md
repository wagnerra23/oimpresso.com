# Tela 02 · Apontamento por QR (P8) ✅

> ✅ **Rota no ERP desde o PR 06 do playbook `propostas-app`** (2026-10-15). Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6). Origem: handoff `propostas-app` do Design, conferido em 2026-10-05. O QR impresso no PDF da OS é o PR 07.
>
> **Implementado em:** `app/Http/Controllers/Api/App/ProducaoController.php` · `app/Services/AppLojas/EtiquetaProducao.php` · `routes/api/app/producao.php` · teste `tests/Feature/Sells/AppProducaoApontamentoContratoTest.php` (UC-APPAPT-01..08, lane `sells-pest`).

## Já existia (web, sessão — `Modules/ComunicacaoVisual`, US-COMVIS-004)
- Rotas em `/comunicacao-visual/api/apontamentos`: `em-andamento`, `index`, `iniciar`, `{apontamento}/finalizar` e `{apontamento}/cancelar` (throttle 60/min).
- **Não há pausa.** A US aponta por spool de impressão.

## Com token — o mesmo registro, `comvis_apontamentos`
O "pedido" do app é a **OS de comunicação visual** (`comvis_os.id`), que é o que o apontamento referencia (`os_id`). A etapa é o `status_etapa` da OS (`arte`, `producao`, `finalizando`, `entrega`, `instalacao`, `concluida`, `cancelada`). Não usa o `ApontamentoTracker`: ele acha a empresa pela sessão, que não existe com token. As regras são as dele: um apontamento aberto por operador, duração calculada no servidor, cancelar fecha com m² 0 e `[CANCELADO] <motivo>`, nada é apagado nem reaberto (append-only; correção é lançamento novo).

`GET /api/app/producao/apontamentos/em-andamento` → `{ item: null | { id, pedido: { id, numero, resumo, cliente }, etapa: { chave, rotulo }, iniciado_em } }`

`GET /api/app/producao/etiqueta/{codigo}` (throttle 60/min) → resolve o QR ou o número digitado:
```json
{ "pedido": { "id": 4812, "numero": "OS-2026-04812", "resumo": "Fachada ACM + Letra caixa", "cliente": "Ótica Visão Clara" },
  "etapa": { "chave": "producao", "rotulo": "Produção" }, "pode_apontar": true }
```
- O QR leva `oimpresso:p:<business_hash>:<pedido_id>`. `business_hash` = 12 hex de um HMAC da APP_KEY sobre o id da empresa (`EtiquetaProducao::codigo`), para o QR não expor o id. Hash de outra empresa → 404, sem consultar o pedido.
- Número digitado: o id da OS ou o número (`OS-2026-00012`, sem diferença de maiúscula).
- `resumo` = até 3 descrições dos itens do orçamento da OS, unidas por " + "; `cliente` = contato do orçamento. OS avulsa: os dois `null`.
- `pode_apontar` = tem `comvis.apontamento.create` e a OS não está `concluida`/`cancelada`.

Escrita: header `Idempotency-Key` obrigatório (tabela `app_idempotencia`, rotas `producao.apontamento.*`), throttle 30/min. Mesma chave + mesmo corpo depois do sucesso → a mesma resposta com `200`; corpo diferente → `422 idempotencia_conflito`. Recusa (4xx) não consome a chave.

`POST /api/app/producao/apontamentos/iniciar` `{ pedido_id, etapa, codigo? }` → `201 { id, iniciado_em }`
- `etapa` é a que o app está mostrando; se a OS mudou → `409 etapa_mudou` com a `etapa` atual. Não é gravada (a tabela não tem a coluna).
- Já há um em andamento do mesmo operador → `409 em_andamento` com o `item`. O app oferece finalizar o anterior.
- OS `concluida`/`cancelada` → `422 bloqueado`.

`POST /api/app/producao/apontamentos/{id}/finalizar` `{ m2_produzido?, observacoes? }` → `200 { id, minutos }`
- `m2_produzido` opcional, texto com ponto e até 3 casas (`"4.500"`); com ele e m² orçado, calcula o drift como a web.

`POST /api/app/producao/apontamentos/{id}/cancelar` `{ motivo }` (5–500 letras, como a web) → `200 { id, cancelado: true }`

Só o próprio operador finaliza ou cancela o seu apontamento: o de outro operador dá o mesmo 404 do de outra empresa. Já encerrado → `422 bloqueado`.

| Código | Quando |
|---|---|
| `401` | sem token |
| `403 sem_permissao` | módulo Comunicação Visual fora do pacote, sem `comvis.os.view`, ou (escrita) sem `comvis.apontamento.create` |
| `404 nao_encontrado` | etiqueta/pedido de outra empresa ou inexistente; apontamento de outra empresa ou de outro operador |
| `409 em_andamento` | já há um apontamento aberto (ou a mesma chave ainda está sendo gravada) |
| `409 etapa_mudou` | a etapa enviada não é a atual da OS |
| `422 validacao` `{campos}` | campo inválido, sem `Idempotency-Key`, número digitado com `app_apontamento_exige_qr` |
| `422 bloqueado` | OS encerrada ou apontamento já encerrado |
| `422 idempotencia_conflito` | chave reusada com outro corpo |
| `429` | throttle |

## ERP — etiqueta
O PDF da OS/pedido ganha o QR com o código acima (`EtiquetaProducao::codigo($bizId, $osId)`). PR 07, no módulo de impressão.

## App
Câmera para ler o QR, pedida no primeiro uso. Número digitado como alternativa. O cronômetro é só exibição: o tempo que vale é o do servidor (`iniciado_em`). Para iniciar, o app manda o `codigo` lido quando a empresa exige QR.

## Ajuste da empresa
`app_apontamento_exige_qr` (padrão `false`), lido por `AjustesDaEmpresa::valor`. Com `true`, o app esconde "Digitar número"; o servidor recusa número em `GET /producao/etiqueta/{codigo}` e exige no `iniciar` o `codigo` do QR do mesmo pedido → `422 campos.codigo` (QR de outro pedido ou empresa → 404).

## Fora deste contrato
- Pausa, dois cronômetros, etapa de máquina e saída do ponto (ficha E04 do playbook Produção no celular): são do `Modules/Producao` (PCP), outra tabela. Esta rota é o apontamento da Comunicação Visual que já existe.
- Área `producao` no `areas` de `/api/app/inicio` (§6): não incluída aqui; o `InicioController` é dos PRs 01/02.
