---
page: /documentacao/buscar
component: resources/js/Pages/Documentacao/Busca.tsx
related_runbook: memory/requisitos/Documentacao/RUNBOOK-documentacao.md
owner: wagner
status: draft
last_validated: "2026-08-06"
parent_module: Documentacao
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
tier: B
charter_version: 2
---

# Page Charter — Documentacao/Busca (DRAFT · carimbado do PT-01)

> Migração Blade→Inertia (`busca.blade.php`) sob o contrato de paridade
> [ANTI-REGRESSAO-documentacao-blade.md](../../../../memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md).
> É a única das quatro cujo golden (PT-01 Lista) já está `live`.

## Mission

Achar qualquer documento do acervo pelo texto, inclusive por termo curto que o índice full-text
sozinho descartaria.

## Goals — Features (faz)

- Busca no corpus por `MATCH … AGAINST` **ou** `LIKE` no título — o `LIKE` é rede de segurança para termos abaixo do mínimo do índice ("NFe", "MCP"), não substituto
- Ordena por relevância e respeita o teto de resultados por página
- Cada resultado mostra título, tipo, módulo, caminho no git e trecho com o termo destacado
- Termo com menos de 2 caracteres não consulta o banco
- Sem corpus, diz **"índice indisponível"** com HTTP 200 — não finge resultado vazio nem devolve erro
- PT-BR em todo label, placeholder e mensagem

## Non-Goals — Features (NÃO faz)

_Declarados por [W] em 2026-08-06._

- ❌ **Não troca a fonte de dados** — continua o mesmo índice full-text já existente no corpus; nenhum índice novo é criado
- ❌ **Não converte markdown no cliente** — o trecho vem pronto do servidor
- ❌ **Não gera manifesto de navegação commitado**
- ❌ **Não é tela de escrita** — read-only; a busca navega, não altera nada
- ❌ **Não constrói o gate automático de paridade da Onda 0d**

## Automation Anti-hooks

- ❌ Nenhum agente troca o par `MATCH … AGAINST` + `LIKE` por só um dos dois "para simplificar" — sem o `LIKE`, termo curto volta vazio

## UX Targets

- Cabe em 1280px sem scroll horizontal
- Estado vazio e estado indisponível são visivelmente diferentes um do outro

## Refs

- Padrão de Tela: PT-01 Lista · Constituição UI v2: UI-0013
- RUNBOOK da superfície: [RUNBOOK-documentacao.md](../../../../memory/requisitos/Documentacao/RUNBOOK-documentacao.md)
- Contrato de paridade: AR-DOC-020 a AR-DOC-025
