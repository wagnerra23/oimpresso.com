---
page: /documentacao/programa
component: resources/js/Pages/Documentacao/Programa.tsx
related_runbook: memory/requisitos/Documentacao/RUNBOOK-programa.md
owner: wagner
status: draft
last_validated: "2026-09-25"
parent_module: Documentacao
related_prototype: prototipo-ui/cowork/Wagner/programa-doc-page.jsx
related_us: [US-DOC-002]
tier: B
charter_version: 1
---

# Page Charter — Documentacao/Programa (DRAFT · carimbado do PT-04)

> Nascida do Padrão de Tela **PT-04 Dashboard** via `criar-tela.mjs` (UI-0013 — herança de padrão,
> NÃO bespoke). Golden do arquétipo: [PT-04](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-04-Dashboard.md),
> ainda `draft` — o ciclo-completo não fecha antes de o Design terminá-lo.
>
> **A `.tsx` é só o esqueleto do carimbo** — a tela real é a thread 02 do playbook `programa-doc`. Hoje
> `/documentacao/programa` é **Blade** (`DocumentacaoController::programa` +
> `resources/views/documentacao/programa.blade.php`); o contrato de paridade dela é a seção 6 da
> [lista anti-regressão](../../../../memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md)
> (`AR-DOC-060`–`AR-DOC-069`). Sobe de `draft` → `live` só com screenshot aprovado por [W].
>
> **Sem bloco `alcance:` de propósito.** O carimbo o gera no formato de módulo nWidart
> (`Modules/Documentacao/…DataController`, permission `documentacao.access`) e nada disso existe: a
> rota mora em `routes/web.php`, atrás de `auth`, e a entrada é o rail da própria `/documentacao`.
> Declarar o bloco seria afirmar permissão e menu inventados. Decide-se na thread 02.

## Mission

Mostrar o programa de documentação (Trilha D) como ele está de fato: a estrutura lida da § Trilha D
do plano mestre em git e o estado de execução lido das tasks do MCP — as duas fontes que markdown
sozinho não cruza (SPEC `US-DOC-002`).

## Goals — Features (faz)

- Lê a § Trilha D do plano mestre **no servidor, em runtime** e apresenta ciclo de 11 estações, ondas D0–D10, caminho por tipo, batimento e definição de pronto (seções `D.3`–`D.7`, recortadas pelo código, não pelo título)
- Mostra o estado de execução vindo das tasks MCP com `parent_plan=programa-ondas`; sem MCP, mostra **indisponível**
- Falha honesta quando a fonte falta ou muda de forma: 503 nomeando o arquivo ou a estrutura ausente
- Link para o plano no git e data de atualização lida do frontmatter do próprio plano
- PT-BR em todo label, placeholder e mensagem

## Non-Goals — Features (NÃO faz)

_Aprovados por [W] em 2026-09-28 (chat, "aprovo"): fusão das duas listas que circulavam — a do
trio de 2026-08-06 e a do rascunho do Cowork — mantendo só o item que um UC defende._

- ❌ **Não grava nada** — não marca onda, DoD nem task pela UI; só navegação (`UC-PROGRA-04`)
- ❌ **Não é fonte de estado** — `todo`/`doing`/`done` vem só das tasks MCP; nada escrito à mão no plano, no parser nem no `.tsx` (`UC-PROGRA-01`, ADR 0070)
- ❌ **Não guarda cópia do plano** — lê a § Trilha D do disco a cada acesso; sem parágrafo do plano como literal de código e sem manifesto commitado (`UC-PROGRA-02`, ADR 0256)
- ❌ **Não adivinha a estrutura** — o parse é no servidor e falha alto se o plano mudar de forma (`UC-PROGRA-05`)
- ❌ **Não expõe tenant, host nem segredo** — conteúdo global de governança (`UC-PROGRA-06`, ADR 0093)
- ❌ **Não cria índice, gate, agente nem roadmap novo** — a Trilha D reusa as máquinas que existem

## Automation Anti-hooks

- ❌ Nenhum agente deduz status de onda lendo o plano — sem MCP, a tela mostra indisponível (`UC-PROGRA-01`/`03`)
- ❌ O parser não completa campo ausente: o que não está no plano volta vazio (`UC-PROGRA-02`)
- ❌ A tela não abre, fecha nem cria task no MCP (`UC-PROGRA-04`)
- ❌ A tela não dispara `documentation-loop`, `system-map` nem outro detector — o batimento roda no momento dele

## UX Targets

- Cabe em 1280px sem scroll horizontal
- As 11 estações e as 11 ondas legíveis sem zoom nem scroll horizontal na tabela

## Refs

- Padrão de Tela: PT-04 Dashboard (KpiGrid + KpiCard) · Constituição UI v2: UI-0013
- SPEC: [US-DOC-002](../../../../memory/requisitos/Documentacao/SPEC.md) · RUNBOOK: [RUNBOOK-programa.md](../../../../memory/requisitos/Documentacao/RUNBOOK-programa.md)
- Fonte do conteúdo: [PLANO-MESTRE § Trilha D](../../../../memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md)
- Contrato de paridade: AR-DOC-060 a AR-DOC-069
