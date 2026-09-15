---
id: requisitos-cliente-briefing
module: Cliente
status: producao
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Cliente (verdade destilada)

## Estado atual
O módulo "Cliente" permite o cadastro de clientes, tanto pessoas físicas quanto jurídicas, com validações específicas para o Brasil. Está em produção para o biz=4 (ROTA LIVRE) e outros tenants, integrando cadastro e gerenciamento de contatos a partir de um drawer de 760px. Embora faça parte do sistema de CRM, possui um código separado em `Modules/Crm/` e `Pages/Cliente/`, com funcionalidades em andamento.

## Capacidades
- Cadastro de PF/PJ com validação de CPF/CNPJ.
- Lookup de CEP e CNPJ utilizando serviços como ViaCEP e BrasilAPI.
- Interface de usuário com autosave e abas configuráveis.
- Auditoria conforme LGPD, garantindo exclusão de PII em logs e exportações.
- Multi-tenant básico, utilizando filtros manuais para negócios.
- Suporte a múltiplos endereços por contato.
- Mapa de clientes integrado ao OpenStreetMap.

## Gaps
- Implementação de dropdown para seleção de endereços em vendas.
- Necessidade de migrar RUNBOOKs e documentação relacionada para a estrutura do módulo "Cliente".
- Gaps visuais em relação ao protótipo que não foram atendidos em certos componentes do UI.
- Atrasos em algumas funcionalidades listadas no backlog do SPEC.

## Última mudança
Em 2026-09-06, foram atualizados elementos do prototype no Index e removida a aba "Copiloto" da interface, além da mudança do sistema de mapas do Google para o OpenStreetMap. A última mudança visível foi a introdução do recurso de Import com drag-and-drop em agosto.

## Proveniência (destilado de)

- audit `requisitos/Cliente/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/Cliente/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r2.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r2.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r4.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r4.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r6.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r6.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r7.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r7.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897.md
- session `sessions/2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r1.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r1.md
- session `sessions/2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r2.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r2.md
- handoff `handoffs/2026-09-06-2330-redestilacao-13-portas-floor-distiller-13-0.md` (2026-09-06) — 2026-09-06-2330-redestilacao-13-portas-floor-distiller-13-0.md
