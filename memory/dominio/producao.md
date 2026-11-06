---
dominio: Producao — PCP, camada única de produção (postos, apontamento, programação)
fonte_unica: este arquivo é a fonte canônica do vocabulário de domínio do módulo (ADR 0264 G-4)
gate: dominio:check (scripts/domain-dict-guard.mjs) — enum de migration ⇔ bloco `json` abaixo
owner: wagner
related_adrs: [0264-governanca-executavel-trio-dominio-e2e, 0431-pcp-sai-do-desejo-modules-producao, 0093-multi-tenant-isolation-tier-0]
---

# Dicionário de domínio — Producao

> **Fonte única do vocabulário de domínio** do PCP (ADR 0264 G-4). Nasceu com o fundo do módulo
> (thread 02 + E02 do playbook de Produção, 2026-10-15). **Editar aqui é uma decisão de domínio.**

## Semântica

- **Posto** (`pcp_postos.tipo_custo`): `maquina` custa pela hora da máquina e não ocupa a pessoa
  (veículo também é `maquina`); `pessoa` custa pela hora de quem trabalha (decisão D-CUSTO).
- **Apontamento** (`pcp_apontamentos.tipo`, append-only): `inicio` abre o trecho; `pausa` fecha o
  trecho sem concluir a etapa (retomar é um `inicio` novo); `fim` conclui. Nunca se edita.
- **Origem** (`pcp_apontamentos.origem`): `qr` pelo celular, `manual` com justificativa (D3),
  `turno` fechado no fim do turno (D-TURNO), `ponto` fechado pela saída no ponto (D-PONTO).
  "Fechado automaticamente" = `turno` ou `ponto`.
- **Programação** (`pcp_programacoes.status`): `pendente → iniciada → concluida`, ou `cancelada`.
- **Eventos do apontamento** (`pcp_eventos.tipo`, texto livre, sem enum — fora do bloco `json`;
  constantes `EV_*` do `ApontamentoOperadorService`, thread 04): `etapa_iniciada`, `etapa_concluida`,
  `aviso_proximo` (aviso ao próximo responsável, D-AVISO), `fechado_automaticamente` (aviso ao PCP),
  `correcao_solicitada` (correção com justificativa; o par original fica) e `pendencia_gestor`
  (horas de pessoa acima da presença + 30 min, D-PONTO).
- **Situação da OS por item** (`pcp_ordens.current_stage_id`, processo `pcp_ordem_item` no motor FSM,
  ADR 0436): `aguardando_liberacao → na_fila → em_execucao ⇄ pausada`, `retrabalho → em_execucao`,
  `concluida` e `cancelada`; liberada sem material, `na_fila ⇄ aguardando_material` (US-PCP-032, D3). Programações da OS por item têm `os_tipo = pcp_ordem`. Evento
  `retrabalho_aberto` (`SituacaoDaOrdem::EV_RETRABALHO`): reprovação no controle reabre a etapa de
  origem como etapa nova.
- **Aprovação da arte pelo cliente** (`pcp_aprovacoes_arte.decisao`, UC-FLX-02 · US-COMVIS-NEW-004):
  nula = aguardando o cliente; `aprovada` conclui a etapa "Aprovação do cliente" e a OS segue para a
  Impressão; `recusada` reabre a etapa de arte como etapa nova "(alteração)" com o comentário. Uso
  único; o link vale 7 dias. Eventos (texto livre, `AprovacaoArteService::EV_*`):
  `arte_aprovacao_solicitada`, `arte_aprovada`, `arte_recusada`.
- **Motivo** (`pcp_motivos.tipo`, por posto): `pausa` é o que o operador escolhe ao pausar (A4);
  `refugo` é o motivo obrigatório da perda (A5, UC-PCV-17). Lista fechada por posto (UC-PCV-33,
  emenda E03 da thread 03).
- **Eventos do apontamento** (`pcp_eventos.tipo`, texto livre, sem enum — fora do bloco `json`;
  constantes `EV_*` do `ApontamentoOperadorService`, thread 04): `etapa_iniciada`, `etapa_concluida`,
  `aviso_proximo` (aviso ao próximo responsável, D-AVISO), `fechado_automaticamente` (aviso ao PCP),
  `correcao_solicitada` (correção com justificativa; o par original fica) e `pendencia_gestor`
  (horas de pessoa acima da presença + 30 min, D-PONTO).

- **Situação da compra que o Producao lê** (`transactions.status`, `vocab`): a coluna é da espinha
  de vendas e o dono do vocabulário é [`vendas.md`](vendas.md) — a lista abaixo é cópia dela, não
  vocabulário novo. O Producao só a usa para a baixa do rolo escolhido aceitar lote de compra
  `received`, o mesmo filtro do `mapPurchaseSell` do núcleo (US-COM-025).

## Fluxo de ponta a ponta: venda → OS → apontamento → material → arte → conclusão

> Medido no código em `origin/main` `7d997591d7` (2026-11-05). Descreve o que **roda hoje**; o que a
> auditoria do módulo tem em andamento está marcado como pendente, nunca como feito. Os estados e
> ações são os de `Modules/Producao/Services/ProcessoDaOrdem.php` (`ESTADOS`, `ACOES`), o processo
> `producao` que a aba Processos de `/fluxos` mostra (`app/Domain/Fsm/Modelos/CatalogoProcessos.php`).

1. **Venda aprovada → OS por item.** A venda de um processo de **modelo** (não o legado
   `venda_com_producao`) entra na etapa `aprovado`; o listener de `SaleStageHistory` no
   `ProducaoServiceProvider` despacha o `CriarOsDaVendaJob` depois do commit. `CriarOsDaVenda` cria
   uma `pcp_ordens` por linha **cujo produto tem roteiro ligado** (`pcp_produto_roteiro`) e copia
   uma `pcp_programacoes` por etapa do roteiro. Linha sem roteiro não gera OS (o roteiro padrão é
   decisão [W], US-PCP-026). Na mesma hora: reserva do insumo da receita se a chave
   `producao_reserva_insumo` estiver ligada (US-PCP-031) e a data prometida (US-PCP-028).
2. **Liberar → `na_fila`.** Hoje só o automático chega a produção: o `TransactionPayment::saved`
   chama `SituacaoDaOrdem::liberarPeloSinalDaVenda` (ação `liberar_pelo_sinal`, sem papel), que só
   age se a empresa tiver sinal mínimo configurado e todas as etapas abertas tiverem responsável
   (US-PCP-025). O `Liberar` de tela (`SituacaoDaOrdem::liberar`, papel `producao.liberar`) existe
   no serviço e não tem rota nem tela: `git grep -n -- "->liberar(" -- Modules app routes` fora de
   testes só acha o `ReservaDeInsumoDaOs::liberar`, que é outra coisa. Ligar o Liberar manual é
   trabalho em andamento da auditoria (lote 2).
3. **Material na liberação.** Com a chave `producao_aguarda_material` ligada e faltando insumo, a OS
   vai de `na_fila` para `aguardando_material`; `OrdemMaterialController` (conferir, papel
   `producao.liberar`) a devolve à fila quando o material chega (US-PCP-032). Chave desligada: nada
   muda.
4. **Alocação.** O PCP troca responsável e início previsto pelo Alocar do Quadro e pelo reprogramar
   da Linha (`AlocacaoController`, `producao.etapa.mover`, US-PCP-029/015).
5. **Apontamento.** O operador abre a etapa pelo QR (`/producao/apontamento`, PWA, ou
   `/api/app/producao/*` no app); `iniciar_etapa`, `pausar`, `retomar` e `concluir_etapa` são
   disparados pelo apontamento, sem papel no motor (`ApontamentoOperadorService` confere o
   responsável). O cronômetro esquecido fecha a cada 15 min por `producao:fechar-turno`.
6. **Material e refugo no apontamento.** Cada material apontado vira uma `production_sell` (ref
   `OS-<id>`) no local da venda, pelo custo do lote que saiu, em `pcp_consumos` (US-PCP-021). Refugo
   com motivo do posto vai para `pcp_refugos`; com o papel `producao.qualidade`, `reprovar` leva a
   OS para `retrabalho` e reabre a etapa de origem como etapa nova (US-PCP-027). `refazer`
   (`retrabalho → em_execucao`) e `cancelar_item` existem no serviço sem rota nem tela, como o
   Liberar do passo 2.
7. **Arte.** A etapa "Aprovação do cliente" é concluída pelo cliente no link `/arte/{token}`
   (`AprovacaoArteService`, US-COMVIS-NEW-004): aprovar conclui a etapa pelo mesmo caminho do
   apontamento; recusar reabre a arte como etapa nova "(alteração)". Ver *Aprovação da arte* acima.
8. **Conclusão do item.** A última etapa concluída dispara `concluir_item`
   (`SituacaoDaOrdem::concluirItemSeTerminou`): a OS fica `concluida`, com `pronta_em` e o custo real
   **congelado** em `custo_real` (horas apontadas + custo do lote, `CustoRealService`). OS concluída
   ou cancelada libera a reserva que sobrou.
9. **Venda entregue.** A venda só passa para `entregue` com todas as OS prontas: o
   `Transaction::updating` chama `OsDaVenda::guardar`, que recusa a transição inteira.

Fora deste trilho, sem código (2026-11-05): a ordem para estoque, sem venda (hoje só a
`production_purchase` do Manufacturing; ver o SCOPE do módulo), e a conclusão pelo PCP do processo
legado `venda_com_producao` (ADR 0434 item 3).

## Enums canônicos (machine-checked por `dominio:check`)

```json
{
  "module": "Producao",
  "vocab": {
    "transactions.status": ["received", "pending", "ordered", "draft", "final"]
  },
  "enums": {
    "pcp_postos.tipo_custo": ["maquina", "pessoa"],
    "pcp_programacoes.status": ["pendente", "iniciada", "concluida", "cancelada"],
    "pcp_apontamentos.tipo": ["inicio", "pausa", "fim"],
    "pcp_apontamentos.tipo_custo": ["maquina", "pessoa"],
    "pcp_apontamentos.origem": ["qr", "manual", "turno", "ponto"],
    "pcp_motivos.tipo": ["pausa", "refugo"],
    "pcp_aprovacoes_arte.decisao": ["aprovada", "recusada"]
  }
}
```
