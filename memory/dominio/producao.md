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
  `concluida` e `cancelada`. Programações da OS por item têm `os_tipo = pcp_ordem`. Evento
  `retrabalho_aberto` (`SituacaoDaOrdem::EV_RETRABALHO`): reprovação no controle reabre a etapa de
  origem como etapa nova.
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
    "pcp_motivos.tipo": ["pausa", "refugo"]
  }
}
```
