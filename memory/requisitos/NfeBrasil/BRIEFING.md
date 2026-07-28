---
id: requisitos-nfe-brasil-briefing
module: NfeBrasil
status: producao
updated_at: "2026-07-28"
distilled_at: "2026-07-28"
distilled_by: "jana:distill-module-truth (2026-07-23) + redestilação PARCIAL manual por sdd-from-source (2026-07-28 — só as seções Estado atual / Gaps / Última mudança, a partir do SDD; o resto segue a foto de 23/07)"
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
- **Integração com SPED Fiscal/EFD**: o gerador existe em `Modules/Fiscal` (`SpedIcmsIpiGeneratorService`), travado por feature flag — ver [SDD do Fiscal](../Fiscal/SDD-cockpit-fiscal-v1.0.md) `CU-FISC-15`. O que falta aqui é a Fase 2 do motor tributário (Strategy por regime).
- **Gates de permissão nas mutações de tributação**: 3 das 5 (`destroy`, `toggleAutoEmission`, `aplicarTemplate`) não checam permissão — medido em 2026-07-28, [SDD §5.4.1](SDD-emissao-fiscal-v1.0.md).
- **Fronteira de tenant do import CSV**: o fluxo de 2 passos resolve o business duas vezes; trocar de negócio entre preview e aplicar grava no tenant errado — [SDD §5.3 F8](SDD-emissao-fiscal-v1.0.md).
- **A 1ª nota real nunca foi emitida**: `US-NFE-054` (homologação SEFAZ-SC) e `US-NFE-059` (prod) seguem `todo`. O pipeline está armado; o smoke, não.

## Última mudança
**2026-07-28** — nasce o [`SDD-emissao-fiscal-v1.0.md`](SDD-emissao-fiscal-v1.0.md) (13 CU), primeiro do módulo, com os `casos.md` de 4 telas (`NfceStatus`, `Manifestacao/Index`, `RegraForm`, `ImportCsv` — 19 UC) e 3 arquivos Pest de contrato. Dois UC nascem **vermelhos por desenho** (gate de `destroy` e tenant do import CSV) — o `❌` é o achado, e a correção é decisão [W]. Onda 5 do [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).

Antes disso: motor IBS/CBS da reforma tributária (US-FISCAL-021, PRs #3771/#3774/#3778), abas Config/DF-e (#4287) e fechamento dos charters das telas (#4142) — julho/2026.

## Proveniência (destilado de)

- **sdd** `requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md` (2026-07-28) — origem das seções Estado atual / Gaps / Última mudança nesta revisão parcial
- audit `requisitos/NfeBrasil/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/NfeBrasil/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-07-03-capterra-fiscal.md` (2026-07-03) — 2026-07-03-capterra-fiscal.md
- session `sessions/2026-07-03-capterra-nfse.md` (2026-07-03) — 2026-07-03-capterra-nfse.md
- handoff `handoffs/2026-07-03-1015-onda-21-compras-capterra.md` (2026-07-03) — 2026-07-03-1015-onda-21-compras-capterra.md
- handoff `handoffs/2026-07-03-1730-dente-calculo-fiscal-motor-tributario.md` (2026-07-03) — 2026-07-03-1730-dente-calculo-fiscal-motor-tributario.md
- session `sessions/2026-06-23-ancora-improvada-design-final.md` (2026-06-23) — 2026-06-23-ancora-improvada-design-final.md
- session `sessions/2026-06-23-arte-ancora-changelog-notafiscal.md` (2026-06-23) — 2026-06-23-arte-ancora-changelog-notafiscal.md
- session `sessions/2026-06-23-nfebrasil-mysql-lane-achados.md` (2026-06-23) — 2026-06-23-nfebrasil-mysql-lane-achados.md
