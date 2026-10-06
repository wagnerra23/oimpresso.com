---
id: resources-js-pages-suporte-log-charter
page: /suporte/log
component: resources/js/Pages/Suporte/Log.tsx
related_prototype: prototipo-ui/cowork/Wagner/suporte-page.jsx
related_runbook: memory/requisitos/Suporte/RUNBOOK-log.md
page_id: suporte-log
owner: wagner
status: draft
parent_module: Suporte
related_adrs:
  - 0305-modo-suporte-cross-tenant-exceto-operador
  - 0308-modo-suporte-fase-a-acessar-como-login-as-guardado
  - 0309-modo-suporte-operadora-e-o-time-de-suporte
  - 0093-multi-tenant-isolation-tier-0
mission: "Deixar o time de suporte ler a trilha append-only de acessos às empresas-cliente — quem entrou, onde, quando e como quem — sem caminho de escrita e sem nunca mostrar a operadora."
---

# Charter — Suporte / Log de acessos

> Contrato vivo da tela. Lei sobre os [casos](Log.casos.md). **Draft** até [W] aprovar o
> screenshot. Receita: [RUNBOOK-log](../../../../memory/requisitos/Suporte/RUNBOOK-log.md).
> Fonte visual: `LogAcessos` em `prototipo-ui/cowork/Wagner/suporte-page.jsx` (vista `log`).
> Nasceu na thread 02 do playbook `modulos-faltantes`.

## Mission

RF3 ([ADR 0305](../../../../memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md))
exige que todo acesso do suporte a um cliente fique registrado em `support_access_logs`. A
trilha já era gravada pelo middleware `EnsureSupportAccess` e pela porta `acessarComo`, mas só
se lia por SQL. Esta tela é a leitura: uma lista paginada, do mais recente para o mais antigo.

## Goals

- G1. Responder em um relance "quem do suporte entrou em qual cliente, quando, e virou quem".
- G2. Distinguir os três tipos de linha: **só leitura** (entrou na Visão), **acessou como**
  (login-as, com o usuário-alvo) e **negado** (tentativa barrada).
- G3. Paginação no servidor (50 por página), lista deferida (`Inertia::defer`).

## Non-Goals

Derivados das proibições já registradas para o Modo Suporte (ADR 0305 e o contrato-fonte
`prototipo-ui/cowork/Wagner/cowork-inbox/modulos-faltantes/suporte.contract.json §proibicoes`)
— não são escolha desta tela:

- ❌ Editar, apagar ou corrigir uma linha — append-only; o Model barra update/delete (ADR 0305).
- ❌ Mostrar a empresa operadora (biz=1) — inclusive as tentativas negadas contra ela.
- ❌ Escopar por sessão — o escopo vem de `SupportAccessService::accessibleBusinessIds()`.

## Lacunas declaradas (o protótipo mostra, o schema não grava)

- **Motivo declarado** e **Duração** — colunas do protótipo. `support_access_logs` não tem
  nenhum dos dois (migration `2026_06_23_130000` + `2026_06_24_120000`). Exigem schema novo e
  um fluxo que capture o motivo antes do "Acessar como". Fora do escopo desta thread.
- **Botão "Log de acessos"** na lista de empresas e na Visão (protótipo) — esses arquivos são
  `nao_toca` da thread. Até lá a tela é alcançada pela URL `/suporte/log`.

## UX targets

- AppShellV2 + PT-01 Lista read-only lean. PT-BR. Cabe em 1280px.
- Tokens semânticos; `Badge` warning para "acessou como", danger para "negado".
- Estados: carregando (skeleton) · cheia · vazia · 403 para não-agente.

## Anti-hooks

- Nenhuma rota de escrita sob `/suporte/log` — só `GET`.
- A leitura da própria trilha não grava linha de auditoria (a rota não tem `{business}`).
