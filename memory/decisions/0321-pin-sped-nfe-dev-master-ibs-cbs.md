---
slug: 0321-pin-sped-nfe-dev-master-ibs-cbs
number: 321
title: "Pin sped-nfe em dev-master (SHA fixo) pra IBS/CBS — grupo UB gated por feature flag, byte-idêntico pra legado"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-03"
accepted_at: "2026-07-03"
accepted_via: "Wagner 2026-07-03 no chat: escolheu 'Pin sped-nfe dev-master (full)' via AskUserQuestion (fork de escopo US-FISCAL-021) + aprovou o plano de 4 PRs (PR-A..D). Redação por [CL]."
module: nfebrasil
quarter: 2026-Q3
related_adrs: [0093-multi-tenant-isolation-tier-0, 0062-separacao-runtime-hostinger-ct100, 0101-tests-business-id-1-nunca-cliente]
supersedes: []
---

# ADR 0321 · Pin sped-nfe dev-master pra IBS/CBS (grupo UB gated)

## Contexto

IBS/CBS é o único P0 zerado da CAPTERRA-FICHA Fiscal (nota 75), com **produção obrigatória 03/08/2026** pra CRT 3 (Regime Normal), NT 2025.002-RTC. Hoje o oimpresso tem só o **schema scaffold** (`nfe_fiscal_rules` colunas `c_class_trib/cst_ibs/cst_cbs/aliquota_ibs/aliquota_cbs`, model pronto — migration `2026_05_26_000001`), conforme [ADR ARQ-0004](../requisitos/NfeBrasil/adr/arq/0004-schema-flexivel-cbs-ibs-reforma-tributaria.md) (schema flexível + ativação por feature flag quando a lib suportar).

**Bloqueio:** a lib `nfephp-org/sped-nfe` (composer.lock hoje **v5.2.5**) NÃO tem o grupo UB (`det/imposto/IBSCBS`). O suporte vive só na branch `master` (issue [#1274](https://github.com/nfephp-org/sped-nfe/issues/1274) aberta, **sem release**). Serializar `<IBSCBS>` à mão falha o XSD SEFAZ (`Make::montaNFe()` rejeita tag desconhecida). Sem a lib, o P0 não fecha antes do prazo.

## Decisão

**Pinar `nfephp-org/sped-nfe` num commit fixo de `dev-master` (SHA travado no composer.lock) e implementar IBS/CBS fim-a-fim (cálculo + serialização), 100% atrás de feature flag por business, default OFF.**

- **Pin reproduzível:** `composer.json` → `"nfephp-org/sped-nfe": "dev-master#<sha>"` (SHA vive no config, não nesta ADR — re-pin é mudança de config, não de decisão). `minimum-stability: dev` + `prefer-stable: true` já existem no projeto.
- **Schema PL_010_V1 gated:** emissão usa `new Make('PL_010_V1')` + `tagIBSCBS()`/`tagIBSCBSTot()` **só** quando o business está em modo `full`/`hybrid_2026` ([ARQ-0004](../requisitos/NfeBrasil/adr/arq/0004-schema-flexivel-cbs-ibs-reforma-tributaria.md) `reforma_tributaria_modo`). Business em `legacy` (todos hoje) → `new Make()` schema `PL_009_V4`, **XML byte-idêntico** ao de hoje.
- **Motor:** `MotorTributarioService` popula `TributoCalculado.{c_class_trib,cst_ibs,cst_cbs,aliquota_ibs,aliquota_cbs,valor_ibs,valor_cbs}` a partir das regras; fallback Simples → null/0 (biz=1/biz=4 hoje não destacam até 2027).
- **Gate de merge (Tier 0):** subir a lib dev-master pode mexer em ICMS/PIS/COFINS. A suíte de emissão NfeBrasil INTEIRA tem que ficar **verde no CT100** ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md)/[0101](0101-tests-business-id-1-nunca-cliente.md)) na lib nova ANTES de qualquer merge + snapshot de XML real biz=1 idêntico (flag OFF). Pareado com a REGRA MESTRE de cálculo/valor (`memory/proibicoes.md`): apresentar antes→depois antes de ligar serialização.

Relação com ARQ-0004: **implementa** a ativação que a ARQ-0004 previu (não supersede — a estratégia schema-flexível+flag continua canônica). Os nomes de coluna reais (`c_class_trib` etc.) prevalecem sobre os nomes previstos na ARQ-0004 (`cbs_aliquota` etc.).

## Consequências

**Positivas:**
- Fecha o P0 regulatório antes de 03/08/2026 sem esperar release upstream indefinido.
- Flag OFF por default → risco ZERO pra emissão live biz=1 (byte-idêntico provado por snapshot).
- Pin em SHA fixo → build reproduzível (não flutua com commits novos do master).

**Negativas / riscos:**
- **dev-master é bleeding-edge** (PL_010 ainda em fluxo na NT 2025.002): re-pin quando estabilizar. Mitigado por SHA fixo + flag.
- **Hostinger shared hosting:** `composer install` do lock precisa resolver o commit dev — validar platform reqs no CLI Hostinger ANTES de deploy (cf. incidente ext-sodium), staging CT100 primeiro. Se instável, avaliar vendorizar a lib.
- API exata do `Make`/`tagIBSCBS` da dev-master a confirmar contra o commit pinado ao instalar no CT100.

## Rollback

Reverter o pin pra `"^5.2"` no composer.json + `composer update nfephp-org/sped-nfe`. Como tudo é flag-gated OFF, nenhum business perde emissão (legado nunca dependeu do grupo UB).

## Verificação

Ver plano US-FISCAL-021 (PR-A..D): back-compat CT100 + XSD PL_010_V1 + cross-check numérico motor↔XML + confirmação Hostinger CLI. Rastreado em `memory/requisitos/Fiscal/SPEC.md#US-FISCAL-021`.
