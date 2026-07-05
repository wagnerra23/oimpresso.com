---
slug: auditoria-seguranca-escopo
title: "Escopo da auditoria de segurança defensiva — Onda 3 do plano de aprofundamento"
date: "2026-07-05"
status: proposto
authors: [F, C]
related_adrs: ["0093", "0101", "0264", "0275"]
topic: "escopo/checklist da auditoria de segurança defensiva do próprio código (Onda 3) — isolado do plano-mãe"
parent_plan: PLANO-APROFUNDAMENTO-AVALIACOES.md
---

# Escopo da auditoria de segurança defensiva (Onda 3)

> **Por que este arquivo é separado:** este é o detalhe da Onda 3 do
> [`PLANO-APROFUNDAMENTO-AVALIACOES.md`](PLANO-APROFUNDAMENTO-AVALIACOES.md). O conteúdo aqui é
> **revisão de segurança defensiva do próprio código** (autoria/dono = Wagner). Fica em doc
> apartado pra manter o plano-mãe enxuto — ler o plano não exige carregar este escopo.
> Só é aberto quando a Onda 3 for de fato executada (com autorização explícita do Wagner).

## Status vivo

- status: proposto
- reviewed_at: 2026-07-05
- proximo_passo: só executar com autorização explícita do Wagner (é o dono do app; revisão defensiva).

## Objetivo

Primeira revisão de superfície completa do app (defensiva, sobre o próprio código). Guards
pontuais já existem (isolamento multi-tenant, gitleaks, XSS ratchet, PII scan); falta a
varredura consolidada por dimensão.

## Pré-reqs / limites (Tier 0)

- **Autorização Wagner explícita** pro escopo (dono do app; revisão do próprio código = defensiva).
- **Sem tocar prod pra "testar"**: apenas leitura de código + no máximo smoke em staging CT100. Nada que gere carga/escrita/indisponibilidade em produção.
- Base ferramental: skill `/security-review` + agente de pesquisa por dimensão (com override `model` pro tier da sessão — Regra global #9 do plano-mãe).

## Baseline existente (não é lente zero)

`memory/audits/2026-05-pre-sales/03-security-review-quick.md` (2026-05-09) já registrou achados —
notadamente **A-1** na rota de instalação (`routes/install_r.php`), que segue aparecendo sem
middleware de auth em `origin/main`. **1º item da onda:** confirmar se A-1 foi mitigado (revisão
do controller `Install/InstallController::installAlternate` + do wrap de middleware) e, se ainda
aberto, tratar como correção prioritária fora da fila normal.

## Dimensões a revisar (checklist)

1. **Auth/sessão** — login, remember, reset de senha, 2FA (se houver), fixação de sessão.
2. **Isolamento multi-tenant (risco nº 1)** — `withoutGlobalScopes` sem `// SUPERADMIN:`, queries cruas sem `business_id`, IDs sequenciais expostos em rota (acesso cross-tenant). Provar cada achado com Pest cross-tenant biz=1 vs biz=99.
3. **Permissions (260+ Spatie)** — rota sem `can()`/middleware, escalonamento horizontal role#biz, superadmin sem 2ª barreira.
4. **Entradas não confiáveis** — `DB::raw`/`whereRaw` com input do usuário, `dangerouslySetInnerHTML` em Inertia, fetchers externos que aceitam URL/host de input (Asaas/Inter/WhatsApp/SEFAZ).
5. **Superfície externa** — uploads (`Modules/Arquivos`), webhooks (idempotência/anti-replay), APIs públicas (ConsultaOs/ConsultaNfe).
6. **Segredos** — token em código/log, `.env` versionado, MCP exposto no Hostinger (proibicoes.md).
7. **Fiscal/pagamento** — anti-replay de webhook, refund atrás de flag, valor não manipulável no submit (REGRA MESTRE valor/estoque).

## Verificação adversarial

Cada achado alto/crítico passa por 1 refutador (o caminho é real e concreto, ou é só teórico?).
Descartar o que não se sustenta — evitar "achado" inflado sem caminho demonstrável.

## Máquina (catraca)

Pra cada classe confirmada, propor 1 gate advisory determinístico (ex.: estender o gate de
`withoutGlobalScopes` sem comentário; gate "rota sem middleware de auth"). Registrar em
`gates-registry.json` com `promote_by`. Sentinela conta, catraca morde (ADR 0264/0275).

## DoD

`memory/requisitos/_Governanca/AUDITORIA-SEGURANCA-2026-07.md` com: achados por **severidade**
(sem nota 0-100 inventada — severidade só, decisão do adversário #4) + caminho concreto de cada
crítico + tasks propostas (humano-gated). Todo crítico com caminho demonstrado OU refutado; ≥1
catraca proposta.

**Esforço:** ~2-3 sessões.
