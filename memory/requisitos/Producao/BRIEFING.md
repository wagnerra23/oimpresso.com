---
id: requisitos-pcp-briefing
module: Producao
status: em-construcao
status_nota: "PCP construído desde a ADR 0431 (2026-10-15): tabelas pcp_*, 6 telas Inertia e contratos Pest; nasce desligado (pacote producao_module default false) e sem cliente piloto ligado"
updated_at: "2026-10-26"
---

# BRIEFING — Produção (PCP) · em construção

> **Função única:** resumo executivo e índice. Os números de arquivos vivem na
> [SUPERFICIE](SUPERFICIE.md) (gerada); o estado de cada requisito vive no [SPEC](SPEC.md).

## O que é

A camada única de PCP do oimpresso. Comunicação Visual, Assistência (Repair) e Oficina seguem
donos da OS; as ordens deles aparecem no quadro da Produção.

- **Por que nasceu:** [W] autorizou o PCP em 2026-10-15 ([ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md)),
  substituindo o feature-wish da [ADR 0152](../../decisions/0152-modules-pcp-feature-wish.md) no ponto do PCP.
- **Pasta:** era `memory/requisitos/Pcp/` até 2026-10-15.

## Estado atual (medido em 2026-10-26, `origin/main` `93d6a5d11c`)

> **Correção:** a versão de 2026-10-15 dizia "sem tabela, sem tela, sem rota de negócio". Era
> verdade no dia do esqueleto e deixou de ser nos dias seguintes; fica registrado aqui como fato
> datado, não como estado.

- **Dados:** as migrations em `Modules/Producao/Database/Migrations/` criam as tabelas `pcp_*`
  (postos, operações, roteiros e etapas, ordens, apontamentos, consumos, refugos, romaneios,
  aprovação de arte, aproveitamento de mídia, turnos, colunas do quadro, operadores por posto e
  custo-hora do colaborador).
- **Telas (Inertia, em `Modules/Producao/Resources/js/Pages/Producao/`):** Apontamento, Linha,
  Painel, Postos, Quadro e Roteiros.
- **Rotas:** `Modules/Producao/Routes/web.php` — apontamento por QR, cadastros, custo e material
  da ordem, previsto × realizado, prazo pela fila, aprovação de arte pública por token, romaneio,
  alocação de etapa e quadro.
- **Entregue com âncora no SPEC:** US-PCP-015 (programação na linha), US-PCP-017 (painel),
  US-PCP-021 (baixa do material apontado pelo custo do lote), US-PCP-022 (regra de custo da compra
  na OS aberta), US-PCP-023 (romaneio), US-PCP-024 (aproveitamento de mídia), US-PCP-025 (libera
  só com sinal pago), US-PCP-027 (refugo e retrabalho somam custo), US-PCP-029 (alocador),
  US-PCP-030 (operadores por posto), US-PCP-031 (reserva na venda, baixa na produção) e
  US-PCP-032 (aguardando material). Parciais: US-PCP-026 e US-PCP-028.
- **Testes:** contratos em `Modules/Producao/Tests/Feature/` (um por capacidade acima).
- **Ligado para quem:** ninguém por padrão. O pacote `producao_module` nasce `default => false`
  (`Modules/Producao/Http/Controllers/DataController.php`); o item de menu aparece só com o
  pacote e a permissão `producao.access`.

## O que ainda falta

- As US-PCP-001..014 e 016/018/019/020 do SPEC ainda trazem âncora `_pendente_` escrita no tempo
  do feature-wish ("módulo não construído"). Parte delas foi entregue com outro nome (as tabelas
  `pcp_*`, os cadastros de postos e o apontamento). Reconciliar essas âncoras é trabalho do SPEC,
  um requisito por vez, cada uma com o teste que a prova.
- Controle de qualidade e não conformidade: hoje só existe o registro de refugo
  (`RefugoService`).
- Custo real unificado com a Fabricação (`Modules/Manufacturing`): a conta única existe
  desligada (US-PCP-034, `App\Domain\Producao\CustoDoFabricado`); falta ligar a receita e a OS
  nela (regra mestre). A receita única por `product_bom` é decisão [W] de 2026-10-13 registrada no
  [BRIEFING da Fabricação](../Manufacturing/BRIEFING.md), ainda sem ADR nem código.

## Portas canônicas

- **Fronteira/ownership:** [SCOPE.md](SCOPE.md)
- **Superfície derivada de código:** [SUPERFICIE.md](SUPERFICIE.md)
- **Requisitos:** [SPEC.md](SPEC.md) (US-PCP-*) · ordem das fases no [ROADMAP](ROADMAP.md)
- **Telas:** `Modules/Producao/Resources/js/Pages/Producao/`
