---
id: requisitos-financeiro-briefing
module: Financeiro
status: producao
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Financeiro (verdade destilada)

## Estado atual
O módulo Financeiro oferece uma visão unificada de Contas a Receber, Contas a Pagar, Fluxo de Caixa e Cobrança, com a tela de Boletos aposentada. Está atualmente em produção, mas o código do frontend ainda não foi migrado para o módulo próprio, o que requer atenção ao editar as telas. A auditoria funcional é um documento de referência para a cobertura do módulo.

## Capacidades
- Integração com bancos como Inter, C6, Asaas e BcbPix para geração de boletos.
- Conciliação de extratos com match sugerido por score e registro de auditoria.
- Workflow de aprovação que conecta as visões de AR/AP.
- Ações em lote na visão unificada, com capacidade de confirmar até 500 títulos por vez.
- Bridge para converter despesas em títulos, corrigida após falha em produção.

## Gaps
- Aguardando credenciais sandbox para integração com Sicoob.
- Funcionalidades de Mobile/PWA e notificações de vencimento ainda não foram implementadas.
- Importação de arquivos CSV ainda em desenvolvimento.
- Testes em quarentena, resultando em falta de cobertura de testes e bugs abertos, necessitando de reavaliação.
- Identificação de defeitos em preocupações não rastreadas atualmente.

## Última mudança
Em 2026-09-09, as 7 telas de dinheiro ganharam fonte de design (#7148); em 2026-09-08 a Onda 7 fechou a paridade Financeiro + RecurringBilling (#6975) e em 2026-09-06 saíram os `gap.md` + `map.json` de 7 telas, junto com o conserto do ponteiro podre no charter da Cobrança (#6919). Desde 2026-08-18 vinham as atualizações de segurança, melhorias de testes E2E e saídas da quarentena, com correções em várias rotas.

## Proveniência (destilado de)

- audit `requisitos/Financeiro/AUDIT-FUNCOES-2026-05-19.md` — AUDIT-FUNCOES-2026-05-19.md
- audit `requisitos/Financeiro/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-08-onda7-financeiro-recurring-paridade-medida.md` (2026-09-08) — 2026-09-08-onda7-financeiro-recurring-paridade-medida.md
- handoff `handoffs/2026-09-08-1200-onda7-financeiro-recurring-paridade-e-2-fixes.md` (2026-09-08) — 2026-09-08-1200-onda7-financeiro-recurring-paridade-e-2-fixes.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6919-r1.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6919-r1.md
- session `sessions/2026-09-05-arte-folha-encargos-br.md` (2026-09-05) — 2026-09-05-arte-folha-encargos-br.md
- session `sessions/2026-08-20-visreg-narrativa-do-comentario.md` (2026-08-20) — 2026-08-20-visreg-narrativa-do-comentario.md
- session `sessions/2026-08-17-financeiro-prototipo-medido-e-o-boletos-aposentado.md` (2026-08-17) — 2026-08-17-financeiro-prototipo-medido-e-o-boletos-aposentado.md
- session `sessions/2026-08-17-visreg-relogios-divergentes-e-pedidos-cowork.md` (2026-08-17) — 2026-08-17-visreg-relogios-divergentes-e-pedidos-cowork.md
- handoff `handoffs/2026-08-17-1615-financeiro-prototipo-ja-aplicado-boletos-aposentado.md` (2026-08-17) — 2026-08-17-1615-financeiro-prototipo-ja-aplicado-boletos-aposentado.md
