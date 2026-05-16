# KB Unificado — BRIEFING (estado consolidado 1 página)

**Última atualização:** 2026-05-15 (Wagner declarou "módulo mais importante para IA" + autorizou implementação)
**Owner:** [W] Wagner · **Persona-piloto:** Wagner governança (ONDAS 1-5) → Larissa operacional gráfica (ONDA 6+)
**Status:** ONDA 0 (fundação) em execução nesta sessão · ONDA 1+ via agents paralelos
**Critério de sucesso:** Wagner consegue (a) ver grafo navegável dos 143 ADRs + sessions + charters com edges de dependência; (b) perguntar IA "qual ADR rege X?" e receber resposta com citações; (c) usar trilha didática "Como funciona Multi-tenant Tier 0 aqui".

---

## O que é

`Modules/KB/` re-escopado como **módulo IA central** do oimpresso. Não é mais "browser de docs canônicos" — é o **cérebro consultável da empresa** sobre TODO conhecimento: ADRs, sessions, charters, runbooks, briefings, artigos operacionais de gráfica, dados ERP (OS, vendas, NFe, clientes) e arquivos externos (manuais, contratos).

Construído sobre um **grafo de conhecimento multi-tenant** (`kb_nodes` + `kb_edges`) com bridge read-only pra `mcp_memory_documents` (fotografia git intacta). Frontend tri-pane Cockpit V2 (port do protótipo Cowork [CC] handoff (5), self-score 9,40/10 Bench v2).

## Diferenciais vs mercado (Bench v5 estimado ~9,55-9,70/10)

| Vetor | Score | vs líder concorrente | Por quê único |
|---|---|---|---|
| **Integração ERP nativa** | 9,5 | Notion 6,0 | KB cita OS/cliente/NFe reais com edges `references-data` |
| **Visualização-grafo de governança** | 9,5 | Confluence 7,5 | 143 ADRs com supersedes/related/charter-of navegáveis em Cytoscape |
| **Troubleshoot interativo + editor visual** | 10,0 | Stonly 9,8 (empate técnico) | Edição WYSIWYG sem código, fix linka artigo direto |
| **Trilhas de aprendizado por persona** | 9,5 | ninguém faz bem | Larissa/Wagner/Mateus/Eliana, progresso por dispositivo |
| **Imprimir SOP balcão físico** | 10,0 | ninguém faz | Layout oficial Oimpresso pra colar ao lado da Roland VS-540 |
| **Fit pt-BR/gráfica** | 10,0 | todos ~5-7 | 18 artigos seed do domínio + vocabulário Larissa |
| **Custo** | 10,0 | Guru ~R$ [redacted Tier 0]/u | Zero licença extra — parte do ERP |
| **Densidade Larissa 1280px** | 9,5 | Confluence 6,0 | Cowork V2 sem rounded-xl, sem espaçamento desperdiçado |

Score esperado **acima de 9,5/10** — líder de categoria ERP-com-KB-IA-integrado no Brasil.

## Estrutura conceitual (grafo unificado)

| Estrutura | Forma | Quando usar | Origem do conteúdo |
|---|---|---|---|
| **Nó** | Unidade atômica (artigo, ADR, session, charter, runbook, briefing, OS, cliente, NFe, arquivo externo) | Atom de informação | `kb_nodes` ou bridge pra `mcp_memory_documents` |
| **Aresta** | Ligação tipada (next-in-path, fix-of, supersedes, charter-of, references-data, ai-related, cross-link, related-by-tag) | Relação semântica entre nós | `kb_edges` (manual ou auto-derivada) |
| **Trilha** | Sequência ordenada de nós + checkbox de progresso + persona-target | Aprendizado guiado proativo | `kb_paths` + `kb_path_steps` |
| **Decisão** | Grafo Q→Sim/Não→Q'/Fix | Resolução reativa de problema | `kb_decision_trees` + `kb_decision_tree_steps` |
| **Versão** | Snapshot append-only por edit | Histórico de artigos editáveis | `kb_node_versions` |

Trilha vs Decisão = views diferentes sobre o mesmo grafo. **Decisão pode gerar trilha curativa** (artigos referenciados nos fixes em ordem). **Trilha pode gerar decisão contextual** (qual passo aplicar agora). Função sobre o grafo, não estrutura nova.

## Inviolabilidades Tier 0 (sem ADR mãe nova é proibido)

- `business_id` global scope em TODAS as tabelas `kb_*` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- `kb_nodes` bridge canônico (`is_editable=false`) NUNCA tem versionamento local — vem só do git ([ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md))
- ADRs canon append-only via bridge — `kb_nodes.body_blocks IS NULL` pra type ADR; conteúdo vem do JOIN com `mcp_memory_documents.content_md`
- IA RAG roteia via `Modules/Copiloto/Ai/` ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) — laravel/ai SDK + MeilisearchDriver hybrid embedder. NÃO criar provider novo.
- Pest tests biz=1 + cross-tenant biz=99 obrigatórios ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- F3 do `Pages/kb/Index.tsx` segue MWART canônico 5 fases ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
- Gate visual screenshot Wagner antes de F4 merge ([ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md))
- Inertia::defer em props caras ([RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md))

## Plano em 6 ondas (~12-18 dias com agents paralelos)

```
ONDA 0 (em execução agora — eu sequencial)
   ADR 0149 proposal + CAPTERRA-FICHA + BRIEFING + SCHEMA-DB-V1 + charter

ONDA 1 (3-4d, 1-2 agents paralelos)
   Migrations + Models + Controllers + Services + bridge job
   ↘ outputs: kb_* tables + Modules/KB/Entities/ + Modules/KB/Http/Controllers/

ONDA 2 (3-4d, 1-2 agents paralelos)
   Frontend tri-pane Inertia (port kb-page.jsx → React 19/TS)
   ↘ outputs: resources/js/Pages/kb/Index.tsx + _components/

ONDA 3 (2-3d, 1 agent)
   Block editor + composer + comments + versions + editor visual de árvore
   ↘ outputs: Composer.tsx + Editor visual KBTroubleEditor

ONDA 4 (2-3d, 1 agent)
   IA RAG sobre grafo completo (KbRagService → Modules/Copiloto/Ai/)
   ↘ outputs: Modules/KB/Services/KbRagService.php + endpoints /kb/ai/*

ONDA 5 (2-3d, 1 agent)
   Visualização-grafo (Cytoscape.js) + Imprimir SOP + favoritos
   ↘ outputs: resources/js/Pages/kb/Graph.tsx + KbGraphController + KBPrintSOP

ONDA 6 (3-5d, escopo aberto)
   Dados ERP no grafo (references-data edges + reverse use)
   ↘ outputs: KbScanReferencesJob + useKbContext hook nos módulos
```

## Pré-requisitos cumpridos

- ✅ ADR proposal 0149 escrita
- ✅ CAPTERRA-FICHA porting Bench v2 + adendo v5
- ✅ SCHEMA-DB-V1 contrato técnico
- ✅ Charter `Pages/kb/Index.charter.md`
- ✅ Sync Cowork v5 commitado em `prototipo-ui/prototipos/kb/` (e601471f1)
- ✅ `Modules/KB/` skeleton existente conhecido (6 controllers, permissions parciais)

## Pré-requisitos pendentes

- ⏳ Aceite Wagner da ADR 0149 (promover de proposals → memory/decisions/)
- ⏳ Spawn dos agents paralelos (ONDA 1+)
- ⏳ Decisão sobre roles Spatie: criar role `kb-author` por business? ou reusar `Admin#{biz}` mais wide?
- ⏳ Decisão sobre persona-handler do MCP server: o servidor MCP `mcp.oimpresso.com` deve EXPOR tool `kb-search` e `kb-graph-query` pro Copiloto/Jana? (recomendação: sim, ONDA 4)

## Riscos catalogados (re-validar mensalmente)

- **R1** Duplicação acidental kb_nodes ↔ mcp_memory_documents → mitigado por invariante `is_editable=false ⇒ body_blocks IS NULL`
- **R2** Custo IA RAG explode → mitigado por cache embeddings Meilisearch + cache de respostas curtas
- **R3** UX visualização-grafo confunde Wagner → mitigado por gate visual ADR 0114
- **R4** Concurrent edit (raro mas possível) → mitigado por updated_at otimista pre-save
- **R5** Multi-tenant leak via bridge → mitigado por business_id global scope

## Arquivos canônicos relacionados (ler ANTES de tocar código)

- [ADR 0149 proposal](../../decisions/proposals/0149-kb-unificado-grafo-conhecimento-modulo-ia-central.md) — decisão arquitetural
- [SCHEMA-DB-V1.md](SCHEMA-DB-V1.md) — contrato técnico de migrations/tabelas
- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — benchmark de mercado (16 dimensões, Bench v2 + adendo v5)
- [Index.charter.md](../../../resources/js/Pages/kb/Index.charter.md) — Mission/Goals/Non-Goals/UX
- [prototipo-ui/prototipos/kb/](../../../prototipo-ui/prototipos/kb/) — material visual F1 do Cowork (9 arquivos, 280KB)
- [Modules/KB/SCOPE.md](../../../Modules/KB/SCOPE.md) — escopo atual do módulo
- [Modules/KB/Resources/permissions.php](../../../Modules/KB/Resources/permissions.php) — permissions já declaradas

## Roadmap pós-ONDA 5

- **ONDA 6** Dados ERP no grafo (cross-link OS/cliente/NFe/equipamento ↔ artigos)
- **ONDA 7** MCP server expõe tools `kb-search` + `kb-graph-query` pro Copiloto/Jana consumir programaticamente
- **ONDA 8** Skill `/kb-curate` que sugere edges ao curador (auto-detecção de cross-link em texto)
- **ONDA 9** Webhook KB → ADS pra disparar Brain B em decision-tree complexa
- **ONDA 10** Marketplace de trilhas/troubleshooters cross-business (gráficas compartilham SOPs)
