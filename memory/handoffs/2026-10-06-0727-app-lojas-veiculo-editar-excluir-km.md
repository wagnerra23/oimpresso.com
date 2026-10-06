---
date: "2026-10-06"
time: "07:27 BRT"
slug: app-lojas-veiculo-editar-excluir-km
tldr: "Continuação do handoff 2026-10-05 14:20: editar veículo (#8708), excluir veículo com recusa para OS em andamento (#8717) e histórico de km (#8732) em produção; app #72/#73/#74 ligados. Achado: o merge automático é ligado nos PRs desta sessão minutos após o gh pr create (origem provável: app desktop), e foi assim que o #8655 (valor) entrou sem o ok do [W]."
decided_by: [W]
prs: [8704, 8708, 8717, 8732]
next_steps:
  - "[W]: conferir nas configurações da sessão de coordenação se o merge automático está ligado; com ele, PR de valor/estoque entra sozinho quando fica verde"
  - "[W]: 5 placas duplicadas da empresa 164 (10 veículos cacamba_avulsa) — lista entregue no chat, fora do git"
  - "[W]: fornecedor de consulta de placa; cobrança do GitHub (CI do app); teste com usuário real das escritas da Oficina e do veículo; envio às lojas; D9 do DECISOES.md"
---

# App das lojas — editar, excluir e histórico de km do veículo

Continua [2026-10-05 14:20](2026-10-05-1420-app-lojas-garantia-veiculo-placa.md).

## O que foi feito (ERP, oimpresso.com)

- **#8708 — editar veículo.** `GET/PUT /api/app/veiculos/{id}`; validação de POST e PUT num método só,
  com a regra única `PlacaVeiculo`; placa conferida só quando muda (como a web, #8697); km menor aceito
  (decisão [W]). As OS guardam o próprio cliente e km; placa/tipo exibidos na OS vêm do veículo. App: #72.
- **#8717 — excluir veículo.** `DELETE /api/app/veiculos/{id}`, soft delete como a web (sem restauração).
  Diferença, decisão [W]: com OS em andamento (etapa não terminal, ou mecânica sem pipeline) → 409
  `em_uso`. Permissão = a da `VehiclePolicy::delete` (`oficinaauto.vehicle.delete`, sem superadmin). App: #73.
- **#8732 — histórico de km.** Não há tabela de leituras; `GET /veiculos/{id}/os` ganhou `km` por OS
  (`mileage_at_service`), `km_cadastro` e `cadastrado_em`. Só aditivo. App: #74.
- **#8704:** handoff anterior, mergeado.

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ativo em COPI (consultado 2026-10-06 10:28Z).
- `my-work` (@wr23): sem tasks ativas.
- PRs do app abertos no ERP: nenhum. Deploys de #8708 (18:03Z), #8717 (18:44Z) e #8732 (22:10Z do dia 05)
  confirmados por ancestralidade e 401 sem token.

## Caveats

- **Merge automático:** o timeline do GitHub mostra `auto_squash_enabled` (conta wagnerra23) minutos
  após o `gh pr create` em todos os PRs desta sessão — #8654, #8655, #8687, #8697, #8708, #8717 — sem
  que a sessão tenha rodado `--auto`. Onda D e fila de merges negaram. A ADR 0427 (#8682, 15:08Z) não
  explica o #8655 (12:57Z). Origem provável: o app desktop (Auto-fix ligado na sessão). Vários merges
  que eu relatei como meus foram, na verdade, esse auto-merge.
- Incidente do GitHub Actions em 2026-10-05 a partir de 19:11Z (runners atrasados, ~586 runs na fila):
  o #8732 levou ~2h30 para fechar os checks.
