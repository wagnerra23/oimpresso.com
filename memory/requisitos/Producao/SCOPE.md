---
module: Producao
purpose: "Camada única de PCP: apontamento de produção por operação, pessoa e tempo, postos com capacidade e custo-hora por empresa, programação e gargalo. Em 2026-10-15 é só esqueleto — instalação, pacote do superadmin e permissões; nenhuma tabela, tela ou rota de negócio."
migracao_ui: "n/a — módulo novo, nasce Inertia (sem Blade)"
contains:
  - "DataController — 3 hooks UltimatePOS (pacote superadmin, permissões; sem item de sidebar enquanto não houver tela)"
  - "InstallController — instalação 1-clique (ADR 0024)"
excludes:
  - "Dono da OS: a OS continua em Repair, ComunicacaoVisual e OficinaAuto (D-RELACAO, ADR 0431)"
  - "Receita e lista de materiais: Manufacturing segue fonte de material"
  - "Encaixe de mídia por algoritmo (versão 2)"
  - "Análise de gargalo por IA (depois da regra simples da V1)"
depends_on: []
owner: wagner
status: em construção
url_prefixes:
  - /producao/* (hoje só install 1-clique, Routes/web.php)
---

# Modules/Producao

## Por que existe

O PCP ficou como feature-wish na [ADR 0152](../../decisions/0152-modules-pcp-feature-wish.md)
por falta de piloto. Em 2026-10-15 [W] autorizou a construção e escolheu o nome
([ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md)).

## Fronteira

Produção registra **quem fez qual etapa, em que posto, quando e por quanto tempo**, e usa isso
para programar e medir. Não é dona da OS: Comunicação Visual, Repair e OficinaAuto continuam
criando e fechando a OS; a Produção lê as etapas delas e grava os apontamentos.

## Referências

- [SPEC](SPEC.md) · [ROADMAP](ROADMAP.md) · [proposta aceita](ADR-PROPOSAL.md)
- [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
