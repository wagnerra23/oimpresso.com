---
id: requisitos-pcp-briefing
module: Producao
status: em-construcao
status_nota: "PCP construído desde a ADR 0431 (2026-10-15): tabelas pcp_*, 7 telas Inertia (6 abas + Ficha da OS) e contratos Pest; nasce desligado (pacote producao_module default false) e sem cliente piloto ligado"
updated_at: "2026-11-16"
---

# BRIEFING — Produção (PCP) · em construção

> **Função única:** resumo executivo e índice. Os números de arquivos vivem na
> [SUPERFICIE](SUPERFICIE.md) (gerada); o estado de cada requisito vive no [SPEC](SPEC.md).

## O que é

A camada única de PCP do oimpresso. Comunicação Visual, Assistência (Repair) e Oficina seguem
donos da OS; as ordens deles aparecem no quadro da Produção.

- **Por que nasceu:** [W] autorizou o PCP em 2026-10-15 ([ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md)),
  substituindo o feature-wish da [ADR 0152](../../decisions/0152-modules-pcp-feature-wish.md) no ponto do PCP.
- **Pasta:** era `memory/requisitos/## Estado atual (medido em 2026-11-16, `origin/main` `f20adefd1c`)

> A auditoria do módulo (lotes 0 a 8, aprovados por [W] em 2026-11-05) mudou boa parte do que a
> versão de 2026-10-26 dizia. Aquela versão fica no histórico do git como fato datado.

- **Dados:** as migrations em `Modules/Producao/Database/Migrations/` criam as tabelas `pcp_*`
  (postos, operações, roteiros e etapas, ordens, apontamentos, consumos, refugos, romaneios,
  aprovação de arte, aproveitamento de mídia, turnos, colunas do quadro, operadores por posto e
  custo-hora do colaborador). O custo-hora é decimal, sem float (lote 8.2, #10668).
- **Telas (Inertia, em `Modules/Producao/Resources/js/Pages/Producao/`):** as 6 abas Painel,
  Quadro, Linha do tempo, Roteiros, Postos e Apontamento, mais a **Ficha da OS** (`Os/Show`,
  `/producao/ordens/{id}`). A Ficha tem Liberar, Refazer e Cancelar item, e as abas Custo (só
  leitura), Materiais e Arte (lote 3). O cartão do Quadro e o bloco da Linha abrem a Ficha.
- **Ciclo da OS:** a OS nasce da venda (`CriarOsDaVendaJob`); venda cancelada cancela as OS dela;
  o job e o fechamento de turno falham de forma visível (lote 2).
- **Arte:** a prova tem versão (v1, v2…), só a mais recente vale e o operador vê qual arte está
  aprovada no apontamento (lotes 1 e 4).
- **Casos de uso:** cada tela tem charter e `.casos.md`. Desde o #11014 o Status de cada UC vem
  do veredito no manifesto `scripts/casos-test-results.json`; o retrato vivo é
  `npm run casos:report`. Os poucos que seguem 🧪 dizem por quê no próprio caso.
- **Testes:** contratos Pest em `Modules/Producao/Tests/Feature/` na lane `verticais-pest`;
  isolamento entre empresas nas tabelas que faltavam (lote 5.1, #10653); vitest das regras de
  tela (filtro, link da Ficha, quantidade no apontamento, erro do Painel) no job Frontend
  (lote 5.2, #10716).
- **Requisitos:** o [SPEC](SPEC.md) teve as âncoras reconciliadas com o código em 2026-11-05
  (lote 6a). Operação (chaves, fila, comandos) no [RUNBOOK](RUNBOOK.md).
- **Ligado para quem:** ninguém por padrão. O pacote `producao_module` nasce `default => false`
  (`Modules/Producao/Http/Controllers/DataController.php`); o item de menu aparece só com o
  pacote e a permissão `producao.access`.

## O que ainda falta

- **US sem implementação:** as que trazem `_pendente_` na âncora do SPEC
  (`grep -n "_pendente_" SPEC.md`). Em 2026-11-16 eram US-PCP-005, 006, 011, 012, 013, 014, 016,
  018, 019, 020, 026, 028 e 033.
- **Filtro entre abas:** a aba da Produção abre a Linha sem a query, então o filtro do Quadro não
  passa pela aba (UC-PCV-06, item `[BACKLOG]` no casos do Quadro).
- **Navegador e acessibilidade:** E2E com axe nas telas está em andamento (lote 5.3); a medição
  contra o protótipo (lote 5.4) ainda não tem o lado da produção.
- **Protótipo:** os pedidos ao Cowork (lote 7) dependem de opt-in do [W] para escrever lá.
- **Decisões [W] abertas da auditoria:** B1 a B13, entre elas B3 e B7 (movimento de estoque).
- Controle de qualidade e não conformidade: hoje só existe o registro de refugo
  (`RefugoService`).
- Custo real unificado com a Fabricação (`Modules/Manufacturing`): a conta única existe
  desligada (US-PCP-034, `App\Domain\Producao\CustoDoFabricado`); falta ligar a receita e a OS
  nela (regra mestre). A receita única por `product_bom` é decisão [W] de 2026-10-13 registrada no
  [BRIEFING da Fabricação](../Manufacturing/BRIEFING.md), ainda sem ADR nem código.

a sem ADR nem código.

## Portas canônicas

- **Fronteira/ownership:** [SCOPE.md](SCOPE.md)
- **Superfície derivada de código:** [SUPERFICIE.md](SUPERFICIE.md)
- **Requisitos:** [SPEC.md](SPEC.md) (US-PCP-*) · ordem das fases no [ROADMAP](ROADMAP.md)
- **Telas:** `Modules/Producao/Resources/js/Pages/Producao/`
