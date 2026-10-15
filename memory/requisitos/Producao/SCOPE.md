---
module: Producao
purpose: "Camada única de PCP: apontamento de produção por operação, pessoa e tempo, postos com capacidade e custo-hora por empresa, programação e gargalo. Nasceu em 2026-10-15 como esqueleto (ADR 0431); o fundo veio na thread 02 e os cadastros na 03."
migracao_ui: "n/a — módulo novo, nasce Inertia (sem Blade)"
contains:
  - "DataController — 3 hooks UltimatePOS (pacote superadmin, permissões; sem item de sidebar enquanto não houver tela)"
  - "InstallController — instalação 1-clique (ADR 0024)"
  - "RoteirosController — roteiros por família, até 2 trilhas com junção nomeada (UC-PCV-13, thread 03)"
  - "PostosController — postos, máquinas e veículos, com os motivos de pausa e de refugo do posto (UC-PCV-33, E03)"
  - "FerramentasController — ferramentas com código QR (E03)"
  - "ColaboradorCustoController — custo-hora da pessoa, só com producao.desempenho.ver (E03)"
  - "ApontamentoController — celular do operador (PWA, thread 04): /producao/apontamento, escritas em JSON para a fila offline; a regra mora no ApontamentoOperadorService"
  - "FecharTurnoCommand — producao:fechar-turno a cada 15 min, fecha o cronômetro esquecido no fim do turno ou na saída do ponto"
not_contains:
  - "Presença e saída do ponto (o apontamento só LÊ, D-PONTO) → Modules/Ponto"
excludes:
  - "Dono da OS: a OS continua em Repair, ComunicacaoVisual e OficinaAuto (D-RELACAO, ADR 0431)"
  - "Receita e lista de materiais: Manufacturing segue fonte de material"
  - "Encaixe de mídia por algoritmo (versão 2)"
  - "Análise de gargalo por IA (depois da regra simples da V1)"
depends_on: []
owner: wagner
status: em construção
url_prefixes:
  - /producao/* (install 1-clique e os cadastros da thread 03: roteiros, postos, ferramentas, custo-hora — Routes/web.php)
  - /producao/apontamento/* (celular do operador, thread 04)
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
