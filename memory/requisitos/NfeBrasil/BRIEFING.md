---
distilled_at: "2026-07-02"
distilled_by: jana:distill-module-truth
module: NfeBrasil
---

# BRIEFING — NfeBrasil (verdade destilada)

# BRIEFING — `NfeBrasil`

## Estado atual
O módulo NfeBrasil é um emissor fiscal completo para o Brasil, incluindo NFC-e, NF-e, NFS-e, cancelamentos, e funcionalidades de tributações. Atualmente, seu funcionamento está consolidado em aproximadamente 75%, com implementações operacionais ativas e documentação completa.

## Capacidades
- **Emissão Fiscal**: Suporte a NFC-e e NF-e com homologação e produção em SEFAZ-SP.
- **Configuração Segura**: Upload de certificados A1 criptografados e alternância entre ambientes.
- **Gerenciamento de Manifestos**: Funções para confirmar, desconhecer e sync de eventos do destinatário.
- **Tributação Completa**: Motor fiscal com regras de ICMS, PIS e COFINS, importação de CSV NCM.
- **Cancelamento Automatizado**: Cancela NFC-e e NF-e integrado com notificações ao cliente.
- **Correção de Notas**: Permite a emissão de Carta de Correção.
- **Monitoramento em Tempo Real**: Atualizações de status em tempo real após vendas.

## Gaps
- **Contingência EPEC**: Implementação pendente (Fase 4 do plano original).
- **Suporte a MDF-e e CT-e**: Necessário para operações logísticas (Fase 6).
- **Integração com SPED Fiscal/EFD**: Implementação mensal ainda a realizar (Fase 7).
- **Charters Faltantes**: Completar 6 de 10 telas restantes.
- **Registro de Atividades**: Log de mutações em desenvolvimento.

## Última mudança
Revisões recentes focaram na melhoria do design e funcionalidade do NfeBrasil, com a finalização de um novo layout e refinamento das interações com o banco de dados, conforme discutido nas sessões de melhoria e auditoria entre 8 e 23 de junho de 2026.

## Proveniência (destilado de)

- audit `requisitos/NfeBrasil/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/NfeBrasil/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-06-23-ancora-improvada-design-final.md` (2026-06-23) — 2026-06-23-ancora-improvada-design-final.md
- session `sessions/2026-06-23-arte-ancora-changelog-notafiscal.md` (2026-06-23) — 2026-06-23-arte-ancora-changelog-notafiscal.md
- session `sessions/2026-06-23-nfebrasil-mysql-lane-achados.md` (2026-06-23) — 2026-06-23-nfebrasil-mysql-lane-achados.md
- session `sessions/2026-06-21-blueprint-sdd-vertical-viva.md` (2026-06-21) — 2026-06-21-blueprint-sdd-vertical-viva.md
- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
- session `sessions/2026-06-08-mapa-telas-projeto.md` (2026-06-08) — 2026-06-08-mapa-telas-projeto.md
