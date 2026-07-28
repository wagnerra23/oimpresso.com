---
id: requisitos-nfe-brasil-briefing
module: NfeBrasil
status: producao
updated_at: "2026-07-23"
distilled_at: "2026-07-23"
distilled_by: jana:distill-module-truth
---

# BRIEFING — NfeBrasil (verdade destilada)

## Estado atual
O módulo NfeBrasil é um emissor fiscal para o Brasil, englobando NFC-e, NF-e, NFS-e e cancelamento. Código em prod; o pipeline está armado pra homologação SEFAZ-SC (biz=1, ambiente=2, cert ativo), mas o smoke de homologação (US-NFE-054) e o smoke de produção end-to-end (US-NFE-059) estão ambos `_pendente_`.

## Capacidades
- **Emissão Fiscal**: NFC-e e NF-e com pipeline armado pra homologação SEFAZ (biz=1/SC, ambiente=2); UF dinâmica no código. Smoke ainda não executado.
- **Configuração Segura**: Upload de certificados A1 criptografados e troca entre ambientes.
- **Gerenciamento de Manifestos**: Funções para confirmação, desconhecimento e sincronização de eventos do destinatário.
- **Tributação Completa**: Motor fiscal com regras de ICMS, PIS e COFINS, além de importação de CSV NCM.
- **Cancelamento Automatizado**: Permite o cancelamento de NFC-e e NF-e com notificações ao cliente.
- **Correção de Notas**: Emissão de Carta de Correção disponível.
- **Monitoramento em Tempo Real**: Atualizações de status em tempo real após vendas.

## Gaps
- **Contingência EPEC**: Implementação pendente (Fase 4 do plano original).
- **Suporte a MDF-e e CT-e**: Necessário para operações logísticas (Fase 6).
- **Integração com SPED Fiscal/EFD**: A implementação mensal ainda precisa ser realizada (Fase 7).

## Última mudança
Motor IBS/CBS da reforma tributária (US-FISCAL-021, PRs #3771/#3774/#3778), abas Config/DF-e (#4287) e fechamento dos charters das telas (#4142) — julho/2026.

## Proveniência (destilado de)

- audit `requisitos/NfeBrasil/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/NfeBrasil/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-07-03-capterra-fiscal.md` (2026-07-03) — 2026-07-03-capterra-fiscal.md
- session `sessions/2026-07-03-capterra-nfse.md` (2026-07-03) — 2026-07-03-capterra-nfse.md
- handoff `handoffs/2026-07-03-1015-onda-21-compras-capterra.md` (2026-07-03) — 2026-07-03-1015-onda-21-compras-capterra.md
- handoff `handoffs/2026-07-03-1730-dente-calculo-fiscal-motor-tributario.md` (2026-07-03) — 2026-07-03-1730-dente-calculo-fiscal-motor-tributario.md
- session `sessions/2026-06-23-ancora-improvada-design-final.md` (2026-06-23) — 2026-06-23-ancora-improvada-design-final.md
- session `sessions/2026-06-23-arte-ancora-changelog-notafiscal.md` (2026-06-23) — 2026-06-23-arte-ancora-changelog-notafiscal.md
- session `sessions/2026-06-23-nfebrasil-mysql-lane-achados.md` (2026-06-23) — 2026-06-23-nfebrasil-mysql-lane-achados.md
