---
slug: 0442-ibscbs-automatico-regime-normal-emenda-0321
number: 442
title: "IBS/CBS automático para todo emissor do regime normal (CRT 3) — emenda a 0321"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-26"
accepted_at: "2026-10-26"
accepted_via: "[W] 2026-10-26, por pergunta direta na sessão coordenadora das lacunas: 'ligar automático para todo emissor CRT 3'. O merge deste PR é a ratificação."
module: nfebrasil
quarter: 2026-Q4
related_adrs: [0321-pin-sped-nfe-dev-master-ibs-cbs, 0093-multi-tenant-isolation-tier-0, 0062-separacao-runtime-hostinger-ct100]
supersedes: []
amends: [0321]
---

# ADR 0442 · IBS/CBS automático para todo emissor do regime normal (emenda a 0321)

## Contexto

A [ADR 0321](0321-pin-sped-nfe-dev-master-ibs-cbs.md) ligou o grupo UB (IBS/CBS) da NF-e **só por opt-in**:
`nfe_business_configs.reforma_tributaria_modo` em `full`/`hybrid_2026`, com `legacy` como padrão de todos.
Na época o grupo era preparação; hoje ele é obrigação:

- o preenchimento de IBS/CBS é obrigatório para fatos geradores desde **03/08/2026** para o regime normal
  (Ato Conjunto RFB/CGIBS nº 4/2026);
- a rejeição automática por falta do grupo (regra UB12-10) foi **suspensa** na NT 2025.002 v1.51, sem nova data —
  a SEFAZ aceita a nota sem o grupo, mas a empresa fica em falta;
- o Simples Nacional só passa a destacar em 2027.

Fontes conferidas em 2026-10-26: Taxcel, Systax e Portal SPED Brasil (secundárias; o portal oficial não foi lido).

## Decisão

**Todo emitente do regime normal (CRT 3) usa o schema `PL_010_V1` sempre, sem opt-in.** O grupo UB sai no item
quando a regra fiscal tem CST IBS/CBS e cClassTrib (o cálculo e a serialização já existem desde a 0321).
**CRT 1 e 2 (Simples) seguem a flag `reforma_tributaria_modo` como antes, com XML byte-idêntico.**

O resto da 0321 continua valendo: o pin da `sped-nfe`, o gate de merge (suíte de emissão verde no CT 100) e a
REGRA MESTRE de valor (tabela antes → depois).

## Consequências

**Positivas:** o regime normal cumpre a obrigação sem depender de alguém lembrar de ligar a flag; o código não
muda para o Simples.

**Negativas / riscos:**
- Regra do regime normal **sem** CST IBS/CBS continua saindo sem o grupo (o sistema não inventa CST — lei 4 do
  módulo). Cabe ao contador cadastrar; a Saúde fiscal deve apontar a falta (follow-up).
- Para CRT 3 a flag `legacy` deixa de ter efeito; quem precisasse desligar o grupo para um CRT 3 não tem mais como.
  Medido em 2026-10-26: não há emitente CRT 3 em produção (`nfe_business_configs` só tem o biz=1, Simples).

## Rollback

Reverter o `if ($crt === 3)` de `NfeService::schemaReforma`. Nada no banco muda.

## Verificação

`Modules/NfeBrasil/Tests/Feature/SerializacaoIbsCbsTest.php` (UC-FNFE-47/48) e `ReformaSchemaModoTest`, na lane
`nfebrasil-pest`, e a suíte de emissão no CT 100.
