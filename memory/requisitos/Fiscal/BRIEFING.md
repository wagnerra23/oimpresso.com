---
id: requisitos-fiscal-briefing
module: Fiscal
status: parcial
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Fiscal (verdade destilada)

## Estado atual
O módulo Fiscal atua como um cockpit unificado, integrando informações de NF-e/NFC-e e NFSe com status parcial. Atualmente, está em fase piloto, com o módulo `NfeBrasil` já ativo. A exportação para o SPED está barrada por uma restrição relacionada ao GAP-FISCAL-003. Implementações recentes incluem melhorias na configuração fiscal e ações de risco estão bem documentadas.

## Capacidades
- Visualização de KPIs, sparklines e alertas (incluindo vencimento de certificado).
- Listagens e detalhes de NF-e/NFC-e; funcionalidade limitada para NFS-e (apenas listagem).
- Manifesto DF-e com pílula de prazo e funcionalidades de manifestação em lote.
- Exportação de eventos em formato append-only.
- Gerenciamento do certificado A1 e ambientes de operação com permissões refinadas.
- Geração e visualização de SPED & Livros; prévia da geração está pendente.
- Acesso e cancelamento de notas dentro de janelas específicas.
- Interface de busca rápida de notas via palette.

## Gaps
- SPED incompleto devido a hardcodes no gerador e travas de exportação ativas (GAP-FISCAL-003).
- Desenvolvimentos para biz=4 ainda em pré-canary, aguardando uma fase de testes e validações.

## Última mudança
As mais novas são de DESIGN e de CI, não de feature — datas e números conferidos no git (committer date), não estimados: **#7110 (2026-09-09)** devolveu as 7 abas aos rótulos e à ordem do protótipo; **#6977 (2026-09-08)** vinculou os 8 inventários de paridade do Fiscal + NfeBrasil e fechou as 2 telas silenciosas; **#6902 (2026-09-06)** fez a lane MySQL passar a EXECUTAR os 3 testes da tela `Fiscal/Dfe` (antes ela existia e não rodava). Antes disso, a **onda 10 do SPED** (#6740, 2026-09-04) trouxe a manifestação DF-e em lote com falha parcial nomeada e tirou o botão inerte da tela — esse fato fica, é ele que a destilação anterior descrevia.

## Proveniência (destilado de)

- audit `requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md` — AUDIT-SENIOR-2026-05-25.md
- audit `requisitos/Fiscal/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/Fiscal/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-14-refutacao-gt-g5-lote-7262-r2.md` (2026-09-14) — 2026-09-14-refutacao-gt-g5-lote-7262-r2.md
- session `sessions/2026-09-13-fiscal-e2e-rede-thread01.md` (2026-09-13) — 2026-09-13-fiscal-e2e-rede-thread01.md
- session `sessions/2026-09-04-fiscal-onda10-sped-goals-cowork.md` (2026-09-04) — 2026-09-04-fiscal-onda10-sped-goals-cowork.md
- handoff `handoffs/2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md` (2026-09-04) — 2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md
- session `sessions/2026-09-03-fiscal-onda9-sped-regua-golden.md` (2026-09-03) — 2026-09-03-fiscal-onda9-sped-regua-golden.md
- handoff `handoffs/2026-09-03-2040-fiscal-onda9-sped-regua-golden.md` (2026-09-03) — 2026-09-03-2040-fiscal-onda9-sped-regua-golden.md
- handoff `handoffs/2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md` (2026-09-02) — 2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md
- session `sessions/2026-09-01-fiscal-f0-screen-coverage.md` (2026-09-01) — 2026-09-01-fiscal-f0-screen-coverage.md
- handoff `handoffs/2026-08-28-1040-design-sync-recibos-fiscal.md` (2026-08-28) — 2026-08-28-1040-design-sync-recibos-fiscal.md
