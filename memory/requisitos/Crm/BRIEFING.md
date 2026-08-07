---
id: requisitos-crm-briefing
distilled_at: "2026-07-30"
distilled_by: reconciliacao-manual-2026-07-30
module: Crm
status: producao
updated_at: "2026-07-30"
---

# BRIEFING — Crm (verdade destilada)

> **Reconciliação 2026-07-30 (manual, não destilação).** O `distiller_freshness` (métrica ARMADA, GT-G3) acusou este BRIEFING como *porta atrás dos eventos* porque um doc do módulo mudou depois do último carimbo. O evento foi **uma linha** em [`DEPRECATION-PLAN-pipeline.md`](DEPRECATION-PLAN-pipeline.md): a lápide-ponteiro `Modules/Crm/BRIEFING.md` foi **deletada** ([W] 2026-07-30, *"apagar os outros e revisar os vínculos"*) e a linha da etapa E6 foi repontada pra este arquivo — a **casa única** do BRIEFING.
>
> **Conferido:** Estado, Capacidades e Gaps abaixo **seguem válidos**; nada no código do Crm mudou. O carimbo sobe porque a reconciliação foi feita de fato, não pra passar o gate — `distilled_by` declara que foi **manual**, não `jana:distill-module-truth` (o schedule do distiller segue desligado, [ADR 0291](../../decisions/0291-distiller-modulo-verdade-contrato-emenda-0270-f3.md)).

# BRIEFING — Modules/Crm

## Estado atual  
O módulo "Crm" atua na gestão de clientes, permitindo o cadastro e a consulta de informações de clientes (PJ/PF). O status atual é de silenciamento em memória, com o aplicativo ainda funcionando, mas sem evolução prevista até revisão explícita do Wagner.

## Capacidades  
- Cadastro de clientes (identificação, contato, endereço, comercial, classificação).
- Interface em formato de drawer com 8 abas para facilitar navegação.
- Funcionalidade de autosave nas edições de cadastro.
- Análise de risco com novos endpoints de IA.
- Exportação de dados em formato CSV.
- Funcionalidades de auditoria conforme LGPD.

## Gaps  
- Necessidade de revisão sobre a continuidade do módulo e a estratégia de CRM no negócio.
- Falta de integração completa com processos de vendas e marketing.
- Ausência de feedback refinado de usuários sobre a experiência da interface.
- Melhoria na documentação e no suporte ao usuário.

## Última mudança  
Nos eventos recentes, registrou-se um silenciamento do módulo, reafirmando que não deve haver evolução sem autorização, além de contínuas auditorias e análise de design em andamento.

Revisão documental em 2026-07-22: o changelog legado foi desambiguado para CRM durante a limpeza de autoridades duplicadas; nenhuma verdade funcional do módulo mudou.

## Proveniência (destilado de)

- session `sessions/2026-06-23-ancora-improvada-design-final.md` (2026-06-23) — 2026-06-23-ancora-improvada-design-final.md
- session `sessions/2026-06-13-audit-adversario-ds-cliente-cadastro.md` (2026-06-13) — 2026-06-13-audit-adversario-ds-cliente-cadastro.md
- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
