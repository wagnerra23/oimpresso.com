# ADR ARQ-0007 (MemCofre) · MemCofre como framework de program comprehension (software archaeology)

- **Status**: accepted
- **Data**: 2026-04-23
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Amarra**: ADRs 0004 (ChatGPT conceito), 0005 (rastreabilidade tripla), 0006 (estrutura expandida), arq/0001 (upgrade Laravel), arq/0006 (Meilisearch+TanStack)

## Contexto

Wagner identificou o padrão que estávamos reproduzindo sem nomear: **"entender profundamente um sistema existente"** — disciplina acadêmica conhecida como **program comprehension** (Ben Shneiderman, 1980) ou **software archaeology** (Andy Hunt, 2005). 40+ anos de literatura; zero ferramenta comercial que cobre o ciclo completo.

Wagner também deu o framework em 5 fases (estrutural → comportamental → funcional → intencional → histórica). Este ADR **posiciona o MemCofre** nesse mapa conceitual e identifica o que já fazemos × o que falta.

## Decisão

**MemCofre é um framework de program comprehension aplicado ao OI Impresso**. Não é gerador de documentação — é **grafo de afirmações verificáveis sobre o sistema, com evidência e confiança, mantido vivo por automação + curadoria humana**.

### Posicionamento nas 5 fases

#### Fase 1 — Mapeamento estrutural (determinístico)

| Item do framework | Status no MemCofre |
|---|---|
| Árvore de arquivos | ✅ `MemoryReader` + Glob/Grep em `memory/requisitos/` |
| Grafo de dependências | ❌ falta — sem `madge`/`pydeps` integrado |
| Call graph estático | ❌ falta — sem extração AST |
| Pontos de entrada (rotas, CLI) | 🟡 parcial — `sync-pages` acha `.tsx`, mas não rotas PHP nem Artisan |
| Fronteiras externas (DB, APIs) | 🟡 parcial — `ARCHITECTURE.md` manual descreve |
| Esquema DB + migrations | ✅ `diagrams/er.md` por módulo |

#### Fase 2 — Identificação de funcionalidades (IA + heurísticas)

| Item | Status |
|---|---|
| Catálogo de funcionalidades | ✅ `US-{AREA}-NNN` em `SPEC.md` |
| Partir de pontos de entrada | ✅ `@docvault` nas telas `.tsx` — **40/40 anotadas** |
| Partir de testes | 🟡 `MultiTenantIsolationTest` + `SpatiePermissionsTest` (22 testes) |
| Partir do modelo de dados | ✅ diagrams/er.md |
| Partir da UI | ✅ anotações `@docvault` casam tela → story → rule |
| Agrupamento automático de peças | ❌ falta — é manual via `docs_links` |

#### Fase 3 — Reconstrução da intenção

| Item | Status |
|---|---|
| Git blame + histórico | ❌ não integrado — MemCofre não lê `git log` ainda |
| PR / Issues / Changelog | 🟡 `CHANGELOG.md` por módulo (manual) |
| Comentários TODO/FIXME | 🟡 `docvault:audit` tem check `C15 NO_EMPTY_PLACEHOLDERS` |
| Nomes / convenções | 🟡 GLOSSARY captura jargão |
| Hipóteses com confiança | ❌ falta — ADRs são assertivos, não hipotéticos |
| **ADRs formais** | ✅ **diferencial** — 10+ ADRs já registram "por quê" com alternativas |

#### Fase 4 — Validação dinâmica

| Item | Status |
|---|---|
| Rodar suíte de testes | ✅ 22/22 passando (MultiTenant + Spatie) |
| Cobertura de código | ❌ falta — PHPUnit coverage.xml não integrado |
| Tracing/instrumentação | 🟢 Sentry instalado (ADR 0005), aguarda DSN |
| Property-based testing | ❌ falta — Pest aceita, não adotado |
| Mutation testing | ❌ falta — Infection/Pest-mutate não instalado |
| Validação da doc contra código | ✅ `docvault:validate` (5 checks) + `docvault:audit-module` (15 checks) — **diferencial** |

#### Fase 5 — Síntese navegável

| Item | Status |
|---|---|
| Índice de funcionalidades | ✅ `/docs` dashboard |
| Grafo navegável feature ↔ código | 🟡 parcial — `docs_pages` tem linking, falta visualização |
| Glossário de domínio | ✅ `GLOSSARY.md` por módulo (MemCofre, PontoWr2, Essentials, Accounting, Crm, Manufacturing, Project, Repair, _DesignSystem = 9 glossários) |
| Perguntas em aberto / anomalias | ❌ falta — validator acha issues mas não distingue "aberto" de "erro" |
| Busca semântica | ✅ `/docs/chat` com OpenAI + Scout |
| Modelo mental verificável | ✅ `audit_score` por módulo prova que docs↔código alinham |

### Score global do MemCofre vs framework ideal

- ✅ **~65% implementado** em conceitos-chave
- 🟡 **~20%** parcial (existe mas incompleto)
- ❌ **~15%** não iniciado (principalmente integração git + AST parsing + mutation testing)

## Gaps priorizados (backlog de framework completo)

### P0 — Alto impacto, esforço médio

1. **Integração git log/blame** (Fase 3)
   - Comando `docvault:trace-history <arquivo>` mostra commits + autores + PR refs
   - Viewer ganha tab "História" por arquivo
   - Fecha lacuna #1 de "reconstrução de intenção"

2. **Grafo visual feature ↔ código** (Fase 5)
   - react-force-graph ou Cytoscape.js renderiza `docs_links`
   - Clicar em story → mostra tela + testes + ADRs conectados
   - Diferencial UX sobre ferramentas existentes

3. **Perguntas/Anomalias explícitas** (Fase 5)
   - Tabela `docs_questions` com `status: open | answered | dismissed`
   - Validator detecta "stories sem página implementada > 90 dias" → cria question
   - Chat pode responder "liste perguntas abertas no módulo X"

### P1 — Valor alto, esforço maior

4. **AST parsing básico** (Fase 1)
   - PHP: `nikic/php-parser` (já transitivo do Laravel) — extrai classes/métodos
   - TS: `typescript` compiler API ou `@babel/parser`
   - Popula `docs_entities` com simbolos → alimenta call graph

5. **Hipóteses com confiança** (Fase 3)
   - ADRs ganham campo `confidence: high | medium | low | hypothesis`
   - IA pode gerar ADR rascunho status `hypothesis` pra humano revisar
   - Tracking de quantos `hypothesis` viraram `accepted`

6. **Cobertura de testes integrada** (Fase 4)
   - PHPUnit gera `storage/coverage.xml`
   - Audit lê e adiciona check C16_COVERAGE por módulo
   - Dashboard: % cobertura por módulo

### P2 — Nice-to-have, sinaliza maturidade

7. **Mutation testing** (Fase 4) — Infection-PHP
8. **Property-based testing** (Fase 4) — Pest plugin
9. **Timeline de decisões** — eixo cronológico de ADRs (quando X foi decidido, superseded por Y)
10. **Self-healing model** — MemCofre detecta drift entre código e doc, sugere PR

## Princípio guia extraído da mensagem do Wagner

> "Entendimento profundo não é um documento, é um **modelo mental navegável e verificável**. A IA não está escrevendo um relatório — está construindo um grafo de afirmações sobre o sistema, cada uma com evidência e confiança, que pode ser consultada, questionada e refinada."

Este é o **norte arquitetural** do MemCofre daqui pra frente. Toda nova feature deve responder: "isso torna o modelo mental **mais navegável**, **mais verificável**, ou **mais vivo**?". Se não responde sim pra nenhum, não entra.

## Consequências

**Positivas:**
- Nome e conceito formais pro que estamos construindo. Facilita onboarding, PRs, reviews.
- Backlog priorizado P0/P1/P2 (10 itens) guia próximas 5-10 sessões.
- Evita reinventar roda — literatura de program comprehension existe desde 1980.

**Negativas:**
- Ambição aumenta. Tentação de atacar tudo de uma vez. Mitigação: P0 primeiro, medir impacto, só aí avança P1.

## Ferramentas comparáveis (análise competitiva)

| Ferramenta | Cobre o quê | MemCofre diferencial |
|---|---|---|
| **Sourcegraph Cody** | Busca+chat sobre código | MemCofre tem **ADRs + validação** que Sourcegraph não tem |
| **Cursor "codebase"** | Chat contextual | Cursor é IDE-bound; MemCofre é versionado no repo (sobrevive change de IDE) |
| **Greptile** | Doc inference automática | Greptile é SaaS; MemCofre é self-hosted + editável |
| **GitHub Copilot Workspace** | Planejamento IA | Copilot não tem estrutura persistente de doc |
| **Sweep** | PR automation | MemCofre mantém *estado* entre PRs |
| **Swimm** | Docs inline no código | Swimm é doc linear; MemCofre é grafo de afirmações |

Nenhuma ferramenta atual cobre as 5 fases **E** é file-based versionada **E** permite validação contínua. **MemCofre ocupa esse nicho.**

## Alternativas consideradas

- **Adotar uma ferramenta pronta (Sourcegraph, Cursor)**: rejeitado — nenhuma persiste ADRs nem valida docs contra código.
- **Ficar em doc Wiki/Confluence**: rejeitado (ADR 0002 original).
- **Só confiar na memória Claude**: rejeitado — morre a cada sessão.

## Sinais de conclusão (quando esse ADR vira "implemented")

- [ ] P0-1 `trace-history` implementado
- [ ] P0-2 grafo visual feature ↔ código no `/docs`
- [ ] P0-3 tabela `docs_questions` + UI
- [ ] 3 módulos completando ciclo das 5 fases (MemCofre, PontoWr2, + 1 ativo)
- [ ] Audit score ≥90 nos 8 módulos (hoje 88 max)

Esse ADR é **living document** — atualizar quando P0/P1/P2 forem implementados.
