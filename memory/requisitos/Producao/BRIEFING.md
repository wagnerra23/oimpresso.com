---
id: requisitos-pcp-briefing
module: Producao
status: em-construcao
status_nota: "esqueleto — ADR 0431 (2026-10-15) tirou o PCP do feature-wish da ADR 0152; sem tabela e sem tela ainda"
updated_at: "2026-10-15"
---

# BRIEFING — Produção (PCP) · em construção

> **Estado em 2026-10-15:** o módulo `Modules/Producao` existe só como esqueleto — instalação
> 1-clique, pacote do superadmin (`producao_module`, `default => false`) e três permissões
> (`producao.access`, `producao.etapa.mover`, `producao.desempenho.ver`). Não há tabela, tela nem
> rota de negócio.

- **Por que nasceu:** [W] autorizou o PCP em 2026-10-15 ([ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md)),
  substituindo o feature-wish da [ADR 0152](../../decisions/0152-modules-pcp-feature-wish.md) no ponto do PCP.
- **O que é:** a camada única de PCP. Comunicação Visual, Assistência (Repair) e Oficina seguem
  donos da OS; as OS deles aparecem no quadro da Produção.
- **Próximo passo:** thread 02 do playbook (fundo: postos, operações, apontamentos append-only,
  programação, eventos, turno e custo-hora por empresa), na ordem do [ROADMAP](ROADMAP.md).
- **Contrato funcional:** [SPEC.md](SPEC.md) (US-PCP-*) e, para as telas, o `Producao.casos.md` do
  pedido do Cowork (UC-PCV-*).
- **Pasta:** era `memory/requisitos/Pcp/` até 2026-10-15.
