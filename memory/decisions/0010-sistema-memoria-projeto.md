# ADR 0010 — Sistema de memória do projeto (CLAUDE.md + /memory/)

**Status:** ✅ Aceita
**Data:** 2026-04-18

## Contexto

A Eliana trabalha com assistentes de IA (Claude e outros) em sessões curtas. Cada sessão começa sem contexto — o assistente não lembra de decisões anteriores, preferências, ou do estado do projeto.

Em projetos de software típicos, contexto vive em PRs, issues, ADRs e READMEs. Mas assistentes de IA precisam de algo **otimizado para eles:** curto, estruturado, com ponto de entrada óbvio, em formato Markdown plano (sem ferramentas específicas).

A comunidade de agentes de IA está convergindo em um padrão: arquivo `CLAUDE.md` (ou `AGENTS.md`) na raiz do repo como "primer" do projeto, complementado por documentação específica.

## Decisão

**Adotar um sistema de memória em 3 camadas:**

### Camada 1 — Entrada obrigatória

- `CLAUDE.md` na raiz — primer do projeto. Todo assistente de IA (e todo desenvolvedor novo) começa aqui
- `AGENTS.md` na raiz — mesmo conteúdo resumido, convenção emergente da comunidade agent

### Camada 2 — Memória estruturada em `/memory/`

Arquivos numerados, cada um com responsabilidade clara:

- `00-user-profile.md` — quem é a Eliana, como ela gosta de trabalhar
- `01-project-overview.md` — problema, escopo, stakeholders
- `02-technical-stack.md` — stack e justificativas
- `03-architecture.md` — patterns, estrutura, DDD layers
- `04-conventions.md` — code style, naming, commits
- `05-preferences.md` — preferências explícitas + citações literais
- `06-domain-glossary.md` — CLT, Portaria 671, UltimatePOS, siglas
- `07-roadmap.md` — fases, status, checkboxes
- `08-handoff.md` — **estado mais recente, sobrescrito a cada sessão**

### Camada 3 — Histórico em subpastas

- `memory/decisions/` — ADRs formais (este arquivo é um deles)
- `memory/sessions/` — log de cada sessão, imutável, com data no nome

## Consequências

### Positivas

- Qualquer assistente de IA (ou humano) pode produzir trabalho consistente após ler `CLAUDE.md` + `08-handoff.md`
- Decisões ficam rastreáveis. "Por que usamos UUID aqui?" → ADR 0005
- Preferências da Eliana ficam codificadas e não se perdem entre sessões
- Roadmap vivo — `07-roadmap.md` vira o radar compartilhado

### Negativas

- Exige disciplina de manter atualizado ao final de cada sessão
- Pode ficar desatualizado se alguém pular a etapa de update (INDEX.md lista regras)
- 15+ arquivos de Markdown — se não estruturado, vira barulho. Mitigação: `INDEX.md` é o mapa

### Regra de ouro

**Ao final de toda sessão que altera o projeto, atualizar `08-handoff.md`.** Se decisão arquitetural, criar/atualizar ADR. Se sessão importante, adicionar log em `sessions/`.

## Referências

- Filosofia inspirada em: padrão `CLAUDE.md` da comunidade de agentes, práticas de ADR do Michael Nygard, documentação de projetos open-source maduros (Linux `Documentation/`, Rust RFCs)
