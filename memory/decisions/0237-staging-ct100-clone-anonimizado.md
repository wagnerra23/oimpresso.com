---
slug: 0237-staging-ct100-clone-anonimizado
number: 237
title: "Ambiente de Staging no CT 100 — clone anonimizado da produção"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-29"
proposed_at: "2026-05-29"
module: infra
quarter: 2026-Q2
supersedes: []
related:
  - 0062-separacao-runtime-hostinger-ct100
  - 0045-hostinger-dns-api-endpoint-canonico
  - 0058-reverb-substituido-por-centrifugo-frankenphp
  - 0093-multi-tenant-isolation-tier-0
tags: [infra, staging, ct100, lgpd, anonimizacao, traefik, mariadb, homologacao]
pii: false
---

# ADR 237 — Ambiente de Staging no CT 100: clone anonimizado da produção

## Status

**Aceito** — 2026-05-29 (Wagner autorizado).

> ⚠️ **Renumerado `0235` → `0237` em 2026-05-30.** Colidia com [`0235-ds-v4-accent-roxo-universal`](0235-ds-v4-accent-roxo-universal.md) — os dois foram aceitos em paralelo no mesmo dia (2026-05-29). Renumerei o **staging** (tinha menos refs inbound) e preservei `0235` pro **DS v4 roxo**. A decisão de conteúdo é **inalterada** — só o número mudou. Conforme [ADR 0236](0236-governanca-evolucao-doc-design.md) (governança: evitar colisão de número) + lição ADR 0180.

Implementado na branch `feat/staging-ct100`:
artefatos [`docker/oimpresso-staging/`](../../docker/oimpresso-staging/) + [RUNBOOK](../requisitos/Infra/RUNBOOK-staging-ct100.md)
+ skill [`criar-staging`](../../.claude/skills/criar-staging/SKILL.md). `staging.oimpresso.com` no ar, login testado fim-a-fim.

## Contexto

A equipe (Felipe/Maiara/Eliana/Luiz) e o Wagner precisam de um lugar pra **testar e treinar** o ERP
— rodar fluxos, ver telas, importar arquivos, validar features — **antes de mexer com produção**.

Hoje o caminho é rodar local (Herd + Laragon no PC), o que é lento e "destrói/reconstrói" o ambiente a
cada uso, e pesa nas máquinas. O CT 100 tem RAM/infra ociosa (Traefik, Docker, Node, MariaDB) e poderia
hospedar um clone, mas a [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) diz explicitamente que
**"CT 100 não serve o domínio web"**. Daí a necessidade desta emenda.

Restrição dura: o clone usa dados de produção, onde há **cliente real pagante** (biz=4 ROTA LIVRE / Larissa)
— então PII tem que ser tratada (LGPD, [ADR 0093](0093-multi-tenant-isolation-tier-0.md)) e o ambiente
não pode disparar ação no mundo real (cobrança Asaas, NF-e SEFAZ, WhatsApp).

## Decisão

**O CT 100 passa a servir UM subdomínio web de staging (`staging.oimpresso.com`)** — exceção registrada
à ADR 0062 (continua valendo pro domínio principal `oimpresso.com`, que é só do Hostinger).

O staging é um **clone fiel da produção** com 4 travas inegociáveis:

1. **Banco separado e anonimizado.** Container MariaDB dedicado (`oimpresso-staging-db`, mesma engine da
   prod — MariaDB 11.8, não o MySQL 8.0 do `mysql-workers`). Populado por dump de prod que passa por
   `anonymize.sql` (PII de pessoas → fake determinístico) + **validação 0-PII que aborta** se sobrar dado real.
2. **Integrações neutralizadas.** `.env` derivado do real de produção (pra manter as flags `MWART_*` =
   telas idênticas), mas com `MAIL=log`, `QUEUE=sync`, WhatsApp/Asaas/NF-e desligados, e **credenciais/
   tokens/certificados TRUNCADOS** (são criptografados com a APP_KEY de prod → inúteis no staging, e
   impedir que o staging cobre/emita/conecte de verdade).
3. **APP_KEY própria** (nunca a de produção) e **runtime FrankenPHP clássico** (`php-server`, sem workers
   Octane — o UltimatePOS não é Octane-safe).
4. **Acesso isolado.** Login com senha de staging compartilhada (`staging2026`); é sandbox — nada que se
   faça nele volta pra produção.

O processo é **reproduzível e auto-documentado**: scripts versionados (`deploy.sh`, `seed-from-prod.sh`,
`anonymize.sql`), [RUNBOOK](../requisitos/Infra/RUNBOOK-staging-ct100.md) com as 10 pegadinhas, e a skill
`criar-staging` que dispara sozinha por intenção — **o agente sabe replicar sem ninguém explicar**.

## Justificativa

1. **Custo zero adicional relevante** — reusa Traefik, Docker, Node e a imagem `oimpresso/mcp:latest` que
   já estão no CT 100. Banco de prod é pequeno (~607 MB).
2. **Fidelidade > performance** em staging — daí FrankenPHP clássico (igual ao PHP-FPM do Hostinger) e
   `.env` derivado do real (as flags `MWART_*` definem quais telas são Inertia; sem elas o visual diverge).
3. **LGPD por construção** — anonimização é gate com validação automática; o staging anonimizado deixa de
   ser "tratamento de dado pessoal" se bem feito, e os dados reais nunca ficam expostos.
4. **Não-regressão da 0062** — a separação Hostinger≠CT 100 continua: o CT 100 serve só staging (web de
   teste), nunca o ERP de produção.

## Consequências

**Positivas:**
- Equipe testa/treina sem tocar prod e sem pesar a máquina local; acesso por URL (sem VPN).
- Processo reproduzível por qualquer agente via skill + RUNBOOK.

**Trade-offs / Riscos:**
- Mais um ambiente pra manter atualizado (deploy manual por ora; re-seed sob demanda). Não há auto-deploy
  da `main` ainda (gap conhecido — candidato a evolução).
- Disco do CT 100 (estava 77% cheio) — staging consome ~2-3 GB. Monitorar.
- URL pública + senha compartilhada simples: sem PII real, mas qualquer um com link+senha entra. Restrição
  (basic auth / IP allowlist no Traefik) fica como opção se necessário.
- O dump completo num pipe único **trava** (Hostinger dropa conexão longa) — contornado com dump em
  partes; `seed-from-prod.sh` deve evoluir pra dump em blocos.

**Riscos mitigados:**
- Anonimização + validação 0-PII impede vazamento de dado de cliente real.
- Credenciais truncadas + integrações `log`/desligadas impedem ação real (cobrança/NF-e/WhatsApp).

## Referências

- [RUNBOOK-staging-ct100.md](../requisitos/Infra/RUNBOOK-staging-ct100.md) — passo a passo + 10 pegadinhas + troubleshooting
- [docker/oimpresso-staging/](../../docker/oimpresso-staging/) — compose, entrypoint, deploy.sh, seed-from-prod.sh, anonymize.sql
- [skill criar-staging](../../.claude/skills/criar-staging/SKILL.md) — auto-trigger do processo
- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — separação de runtime (emendada por esta)
- [ADR 0045](0045-hostinger-dns-api-endpoint-canonico.md) — DNS API · [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [INFRA-ACESSO-CANON.md](../reference/INFRA-ACESSO-CANON.md) · [lgpd-mapa-tratamento.md](../reference/lgpd-mapa-tratamento.md)
