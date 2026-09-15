---
id: requisitos-oficina-auto-briefing
module: OficinaAuto
status: piloto
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — OficinaAuto (verdade destilada)

## Estado atual
O módulo OficinaAuto é uma solução focada no reparo de veículos pesados, em operação no piloto LIVE desde 13 de maio de 2026 para a empresa Martinho. A ativação formal ocorreu em 20 de maio de 2026, com dados importados do Firebird. O estado do piloto foi medido em maio e não foi atualizado desde então. O domínio se restringe a serviços de mecânica e manutenção, com erradicação da locação confirmada.

## Capacidades
- Gestão de ordens de serviço (OS) via quadro Kanban, permitindo transições por arrasto.
- Implementação de Vistoria Digital (DVI) com aprovações e fotos por item.
- CRUD completo de fotos e laudos no nível da OS, além de itens de serviço.
- Aprovação pública via WhatsApp com segurança criptografada.
- CRUD de veículos com busca de placas configurável.
- Importação de dados do Firebird e comandos de limpeza/sanity-check.

## Gaps
- Falta de proteção de transições FSM no modelo de `ServiceOrder`.
- Catálogo de peças OEM ainda não implementado.
- Limitação no apontamento de mecânicos, com cada OS vinculada a um único mecânico.
- Pendências relacionadas à dívida F3 no domínio e ajustes nos accessors residuais.
- Texto incongruente em UI relacionado a locações ativas, já que não há mais ocorrências no código.

## Última mudança
Na auditoria recente, foram revisados os requisitos da CAPTERRA-FICHA, reforçando a necessidade de atualização das informações e abordagem de gaps pendentes.

## Proveniência (destilado de)

- audit `requisitos/OficinaAuto/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
