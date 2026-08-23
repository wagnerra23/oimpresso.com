---
page: /documentacao/programa
component: resources/js/Pages/Documentacao/Programa.tsx
related_runbook: memory/requisitos/Documentacao/RUNBOOK-documentacao.md
owner: wagner
status: draft
last_validated: "2026-08-06"
parent_module: Documentacao
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
tier: B
charter_version: 2
---

# Page Charter — Documentacao/Programa (DRAFT · carimbado do PT-04)

> Tela **nova** — não migra Blade nenhum. Existe porque cruza duas fontes que markdown sozinho não
> cruza: o plano em git e o estado das tasks no MCP. Golden do PT-04 ainda `draft` — o
> ciclo-completo não fecha antes de o Design terminá-lo.

## Mission

Mostrar o programa de documentação (Trilha D) como ele está de fato: as ondas e o ciclo lidos do
plano em git, com o estado de execução lido das tasks do MCP.

## Goals — Features (faz)

- Lê a § Trilha D do plano mestre e apresenta camadas, ciclo de 11 estações, ondas D0–D10, caminho por tipo, batimento e definição de pronto
- Mostra o estado de cada onda (`todo` · `doing` · `done`) vindo das tasks MCP com `parent_plan=programa-ondas`
- Quando o MCP não responde, mostra **estado indisponível** — nunca um estado inventado
- Traduz o vocabulário técnico para rótulo humano só na borda da tela, com o termo canônico preservado no dado
- PT-BR em todo label, placeholder e mensagem

## Non-Goals — Features (NÃO faz)

_Declarados por [W] em 2026-08-06._

- ❌ **Não troca a fonte de dados** — plano vem do git, estado vem do MCP; a tela não é dona de nenhum dos dois
- ❌ **Não converte markdown no cliente** — o parser roda no servidor e falha alto se o plano mudar de forma, em vez de adivinhar
- ❌ **Não gera manifesto commitado** do plano nem do estado — seria a terceira cópia, e cópia drifa (ADR 0256)
- ❌ **Não é tela de escrita** — read-only; **não** marca onda, DoD ou task pela UI, e por isso não tem painel de ação FSM (removido do arquétipo PT-04 de propósito)
- ❌ **Não constrói o gate automático de paridade da Onda 0d**
- ❌ **Não chumba status no markdown nem no `.tsx`** — estado de execução mora nas tasks MCP (ADR 0070); `doing` escrito à mão aqui é defeito, não conteúdo

## Automation Anti-hooks

- ❌ Nenhum agente preenche status de onda a partir de leitura do plano — status só vem do MCP, ou a tela mostra indisponível
- ❌ Nenhum agente "conserta" o parser inventando campo ausente: o que não está no plano volta vazio

## UX Targets

- Cabe em 1280px sem scroll horizontal
- As 11 estações e as 11 ondas são legíveis sem exigir zoom nem scroll horizontal na tabela

## Refs

- Padrão de Tela: PT-04 Dashboard · Constituição UI v2: UI-0013
- RUNBOOK da superfície: [RUNBOOK-documentacao.md](../../../../memory/requisitos/Documentacao/RUNBOOK-documentacao.md) §8 — as duas fontes e as seções reais D.1–D.7
- Fonte do conteúdo: [PLANO-MESTRE § Trilha D](../../../../memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md)
