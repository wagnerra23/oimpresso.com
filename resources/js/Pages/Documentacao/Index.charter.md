---
page: /documentacao
component: resources/js/Pages/Documentacao/Index.tsx
related_runbook: memory/requisitos/Documentacao/RUNBOOK-documentacao.md
owner: wagner
status: draft
last_validated: "2026-08-06"
parent_module: Documentacao
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)
tier: B
charter_version: 2
---

# Page Charter — Documentacao/Index (DRAFT · carimbado do PT-03)

> Nascida do Padrão de Tela **PT-03 Detalhe** via `criar-tela.mjs` (UI-0013 — herança de padrão,
> NÃO bespoke). Migração Blade→Inertia sob o contrato de paridade
> [ANTI-REGRESSAO-documentacao-blade.md](../../../../memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md).
> Sobe de `draft` → `live` só com screenshot aprovado por [W] — e o golden do PT-03 ainda está
> `draft`, então o ciclo-completo não fecha antes de o Design terminá-lo.

## Mission

Dar a quem entra no sistema uma leitura guiada e navegável do Guia do Sistema, com rail derivado do
próprio acervo e sumário da página.

## Goals — Features (faz)

- Renderiza `memory/GUIA-DO-SISTEMA.md` convertido **no servidor**, com sumário recalculado a cada acesso
- Rail derivado do frontmatter dos documentos, com lentes `operar` / `construir` e ordinal contínuo na lente ativa
- Falha honesta quando a fonte falta: 503 nomeando o arquivo ausente
- Oferece a busca apenas quando o corpus está acessível
- PT-BR em todo label, placeholder e mensagem

## Non-Goals — Features (NÃO faz)

_Declarados por [W] em 2026-08-06._

- ❌ **Não troca a fonte de dados** — rail continua saindo do frontmatter em disco e o conteúdo do corpus; o que muda é só a camada de render
- ❌ **Não converte markdown no cliente** — a conversão é do servidor, e sai dele o HTML já sanitizado
- ❌ **Não gera manifesto de navegação commitado** — seria cópia da estrutura, e cópia drifa (ADR 0256)
- ❌ **Não é tela de escrita** — read-only; nada aqui dispara mutação, e por isso **não** tem painel de ação FSM (o arquétipo PT-03 traz um; foi removido de propósito)
- ❌ **Não constrói o gate automático de paridade da Onda 0d** — que segue `proposto`; a paridade desta migração é a lista anti-regressão

## Automation Anti-hooks

- ❌ Nenhum agente marca esta tela como validada sem o smoke real das rotas e o preenchimento da coluna de verificação da lista anti-regressão

## UX Targets

- Cabe em 1280px sem scroll horizontal
- Rail, coluna de leitura e sumário legíveis sem zoom

## Refs

- Padrão de Tela: PT-03 Detalhe · Constituição UI v2: UI-0013
- RUNBOOK da superfície: [RUNBOOK-documentacao.md](../../../../memory/requisitos/Documentacao/RUNBOOK-documentacao.md)
- Contrato de paridade: AR-DOC-001 a AR-DOC-014, AR-DOC-040, AR-DOC-050
