# Protótipo F1 — kb (Base de Conhecimento OPERACIONAL)

**Status:** 🟢 PRONTO PRA F0 — Bench v2 self-score **9,40/10** (Cowork [CC] após 3 refinos)
**Exportado:** 2026-05-15 19:55 — handoff (3) (Bench v2 atualizado), arquivos JSX/CSS idênticos ao handoff (2) 18:05
**Aprovado por:** Wagner sinalizou "ficou incrível" + autorizou sync ("coloque ali eu quero")
**Charter:** ❌ **inexistente** — `resources/js/Pages/kb/Index.charter.md` precisa ser criado antes de F3
**Stories:** ⏸️ a criar — gaps mapeados em Bench KB v2.html devem virar US no MCP via skill `/comparativo`

---

> ⚠️ **Descompasso conceitual — LER ANTES DE F3:**
>
> O `Modules/KB/` em produção hoje serve **KB CANÔNICO** (browser dos 352 docs do `mcp_memory_documents` — ADRs, sessions, runbooks técnicos). Persona: Wagner / governança.
>
> O protótipo Cowork desenha **KB OPERACIONAL** — 18 artigos de gráfica (Calibrar ICC Roland VS-540, Trocar bobina HP Latex 365, Sangria banner 3×2m, Brief OS atendimento). Persona: Larissa balcão ROTA LIVRE.
>
> **Decisão pendente de Wagner** antes de F3:
> 1. Coexistir (`/kb` operacional + `/kb/canon` legacy);
> 2. Separar (criar `Modules/KbOperacional` com schema próprio);
> 3. Substituir (KB canônico migra pra `/governanca/memoria` ou similar — alto risco).

## O que está aqui

7 arquivos de referência visual extraídos do zip Cowork 2026-05-15 19:55h (handoff 3):

| Arquivo | Tamanho | Função |
|---|---|---|
| `kb-page.jsx` | 65 KB | Tela principal — tri-pane (categorias · lista · leitor) + 18 artigos seed + integração todos os módulos KB |
| `kb-page.css` | 55 KB | Tokens visuais (hue 240 PROJETOS), painel saúde 4-quadrantes, comments inline, presenter fullscreen |
| `kb-paths.jsx` | 15 KB | **Trilhas de aprendizado** — `KB_PATHS` (3 trilhas: onboarding Larissa, manutenção Mateus, emergência Eliana) + `KB_SUBCATS` (subcategorias derivadas) + `KBCommentBlock` (comentário inline por parágrafo) |
| `kb-trouble-lib.jsx` | 16 KB | **Decision-tree troubleshooters** — `KB_TROUBLES` (3 árvores: Roland VS-540, HP Latex 365, NF-e SEFAZ) + `KBPresenter` (modo apresentação slide por h2) + `kbLinkifyText` (#a3 → link clicável) |
| `kb-extras.jsx` | 17 KB | **AI RAG** — `KBAIDialog` (Resumir artigo + Perguntar ao KB com citações), `KBBlockEditor` (block editor: para/h2/list/callout), `KBComposer` (criação artigo full), `KBRelated` (top-3 por tag overlap + bonus cat/equip) |
| `Bench KB.html` | 27 KB | Bench v1 — 15 dimensões, score 8,27/10 (ponto de partida) |
| `Bench KB v2.html` | 30 KB | Bench v2 — 16 dimensões (nova: Trilhas), score **9,40/10** (+1,13 após 3 refinos) |

## Bench v2 (resumo executivo)

**Score 9,40/10** vs Notion 7,75 · Confluence 7,40 · Guru 7,95 · Slab 7,30 · Stonly 7,15 · Intercom 7,85.

| Vetor de força | Score | vs melhor concorrente |
|---|---|---|
| Custo (zero licença extra) | 10,0 | vs Guru ~R$80/u |
| Fit pt-BR + gráfica (18 artigos do domínio) | 10,0 | vs todos ~5–7 |
| Troubleshoot interativo (biblioteca 3 árvores) | 9,8 | empata Stonly |
| Densidade Larissa 1280px | 9,5 | vs Confluence 6,0 |
| Integração ERP (anexar a OS nativo) | 9,5 | vs Notion 6,0 |
| Trilhas de aprendizado (nova) | 9,5 | ninguém faz bem |

**Gaps remanescentes assumidos:** editor visual de árvore troubleshoot (hardcoded JSX hoje) + mobile/técnico de campo (tri-pane <1100px quebra).

## Status no loop F0→F4

| Fase | Status | Bloqueio |
|---|---|---|
| F0 BRIEF [W] | ⏳ pendente | Wagner precisa abrir entrada em [`COWORK_NOTES.md`](../../COWORK_NOTES.md) decidindo coexistência vs separação |
| F1 DESIGN [CC] | ✅ feito — material aqui (3 refinos, self-score 9,40) | — |
| F1.5 CRITIQUE [CD] | ⏳ pendente — rodar `design-critique` quando F0 abrir | F0 |
| F2 SCREENSHOT [W2] | ⏳ Wagner aprova abrindo arquivos no browser local | F1.5 |
| F3 CODE [CL] | ⛔ **bloqueado por charter inexistente + decisão de produto** | F0 + decisão coexistência |
| F3.5 A11Y [CA] | — | F3 |
| F4 MERGE [W2] | — | F3.5 |

## Arquitetura conceitual descoberta na leitura

O protótipo introduz **3 abstrações sobrepostas** sobre o mesmo corpus de 18 artigos:

| Estrutura | Forma | Direção | Estado do usuário | Persistência |
|---|---|---|---|---|
| **Artigo** (Article) | Lista linear de blocos (para/h2/list/callout) | Sequencial fixa | Estudando 1 tópico | Vote helpful/outdated, reads counter |
| **Trilha** (KB_PATHS) | Lista ordenada de N artigos por persona | Sequencial fixa | Onboarding proativo | Checkbox progresso por dispositivo (`oimpresso.kb.paths`) |
| **Decisão** (KB_TROUBLES) | Grafo Q→Sim/Não→Q'→...→Fix | Bifurca com base em resposta | Resolvendo problema reativo | Histórico de respostas (path no useState) |

Trilhas e Decisões são VIEWS sobre o mesmo grafo de artigos (nodes) ligados por edges semânticos (next-in-path, fix-of, related-by-tag, cross-link via `#a3`).

## Pré-requisitos pra F3 (quando F0 abrir)

1. **Wagner decide arquitetura de produto:** coexistir/separar/substituir KB canônico (ver descompasso acima)
2. **Charter:** criar `resources/js/Pages/kb/Index.charter.md` (ou nome equivalente) com Mission/Goals/Non-Goals/UX targets ([ADR 0080](../../../memory/decisions/0080-kb-charter-modulo.md))
3. **CAPTERRA-FICHA canônica:** salvar Bench KB v2 como `memory/requisitos/KB/CAPTERRA-FICHA.md` no padrão ADR 0089 — vira fonte oficial pra gerar backlog via skill `/comparativo`
4. **Schema DB:** Bench valoriza editor de blocos, comments inline, trilhas, decision-trees, AI RAG — nada disso existe em `mcp_memory_documents` (schema é de doc canônico, não de artigo operacional editável). Decidir: nova tabela `kb_articles` + `kb_paths` + `kb_decision_trees`, ou estender `mcp_memory_documents` com `type=operational`
5. **AI provider real:** protótipo usa `window.claude.complete` (Cowork fake). Produção precisa rotear via `Modules/Copiloto/Ai/` ([ADR 0035](../../../memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) — laravel/ai SDK
6. **Multi-tenant:** todo conteúdo precisa `business_id` global scope ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)). KB do Mateus PCP da ROTA LIVRE ≠ KB de outra gráfica futura

## Próximo passo

Wagner decide arquitetura de produto (descompasso canônico vs operacional) e abre F0 em [`COWORK_NOTES.md`](../../COWORK_NOTES.md) — pré-requisito pra F1.5 [CD] e F3 [CL].

---

**Histórico:**
- 2026-05-15 19:55 [W] exportou handoff (3) com Bench KB v2 atualizado (3 refinos, self-score 9,40)
- 2026-05-15 ~20:00 [CL] sincronizou 7 arquivos pra esta pasta (`prototipo-ui/prototipos/kb/`), zero impacto em código de produção
