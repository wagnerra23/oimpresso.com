---
slug: profissionalizar-acesso-time
title: Profissionalizar acesso do time — proteger fonte + controlar novos integrantes (topologia A + esconder cérebro de IA)
type: adr
status: proposta
authority: canonical
lifecycle: ativo
proposed_by:
  - W
  - C
proposed_at: '2026-07-14'
quarter: 2026-Q3
tags:
  - governanca
  - acesso
  - least-privilege
  - secrets
  - ip
related:
  - '0044'
  - '0030'
  - '0062'
  - '0080'
  - '0081'
  - '0093'
supersedes: []
---

# ADR (proposta) — Profissionalizar acesso do time

**Status:** 🟡 Proposta — Wagner sinalizou "aceito" no chat 2026-07-14; formalização = aprovar/mergear este PR + atribuir número ADR na aceitação.
**Data proposta:** 2026-07-14
**Autor:** Wagner [W] + Claude [C]
**Plano de referência:** [`memory/requisitos/Infra/PLANO-profissionalizar-acesso-time.md`](../../requisitos/Infra/PLANO-profissionalizar-acesso-time.md) (mergeado em #4254)

---

## Contexto

O time cresce de 5 → ~10 pessoas (entram júnior/contratado). Wagner perguntou: "como esconder o fonte? como controlar os funcionários novos?". Diagnóstico (PLANO §1): o repositório `wagnerra23/oimpresso.com` está **público** e o time **ainda não é colaborador** (CODEOWNERS com `@handle` = TODO). A camada de governança já é madura (Trust Tiers [ADR 0080], Identity Mesh [ADR 0081], branch protection com 22 required checks, Vaultwarden [ADR 0044], Tailscale least-privilege).

Enquadramento honesto: **esconder o fonte de quem o edita é impossível.** O realista é (a) segmentar acesso, (b) blindar segredo (não o código), (c) governança. Uma revisão adversarial por grep provou que a "joia" óbvia (Modules/Jana) **não é extraível** — hospeda o global scope multi-tenant Tier 0 importado por ~200+ arquivos.

## Decisão

1. **Topologia A** — 1 repositório privado + GitHub Org/Teams + CODEOWNERS + branch protection. É o suficiente e o mais barato; reusa tudo que já existe. **Zero repos "só-Wagner".** Um 2º repo só faz sentido em 6–12 meses para os **daemons/infra do CT 100** (fronteira de runtime real, [ADR 0062]), não por segredo.
2. **Esconder de verdade = só o cérebro de IA** (PLANO §2.7) — `Modules/Jana/Ai/` + `Services/Retrieval/` + `Services/Memoria/` (+ `ADS/Ai/`), ~7k LOC, só 4 consumidores externos, atrás da fachada `AiAdapter` que já existe. Mecanismo: **Nível 3 (serviço no CT 100)**; fonte em repo restrito; fallback `Null*` no dev local. É o único ponto valioso **E** separável.
3. **Controle dos novos** — permissão GitHub mínima por papel (write, nunca admin; júnior não mergeia sozinho), 2FA obrigatório, reviews≥1 nos paths sensíveis, signed commits (SSH), least-privilege de infra (prod só Wagner/Felipe; CT 100 staging não-root gravado), audit trail, **offboarding** ([RUNBOOK-offboarding-time](../../requisitos/Infra/RUNBOOK-offboarding-time.md)), e **NDA + cessão de IP** (competência da Eliana[E] — a defesa jurídica real).
4. **Ordem de execução (Fase 0 primeiro):** (1) **rotacionar** os segredos do histórico público — P0 real, Wagner-only; (2) **tornar o repo privado** — clique consciente do Wagner; (3) **fechar o P0 de DR do CT 100** antes de o cérebro virar dependência de rede.

## Consequências

- **Positivas:** estanca a exposição contínua, dá controle real por papel reusando o que já existe, e isola o único IP que dá pra isolar (cérebro de IA) sem quebrar o build.
- **Custos/limites:** o código do ERP continua legível por quem o edita — a proteção do IP restante é **contrato + auditoria**, não técnica. Nível 3 exige o CT 100 confiável (DR) e vaza pela interface/schema/telemetria (fechar acesso ao Langfuse junto).
- **Não-executado:** rotação de segredo e repo-privado são ações do Wagner (não do agente — envolvem credenciais/decisão de acesso, R10).

## Alternativas descartadas

- **Topologia C (self-host git)** — perde ecossistema GitHub Actions + vira SPOF no CT 100 já P0 de DR. Só se air-gap/soberania virar requisito duro.
- **Extrair Modules/Jana inteiro** — refutado por grep (é a fundação multi-tenant; quebra o build ou vaza tenant).
- **Obfuscation do repo de trabalho** — não protege de quem edita; quebra manutenção.
