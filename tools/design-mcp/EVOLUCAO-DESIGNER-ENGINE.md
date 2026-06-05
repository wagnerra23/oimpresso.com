---
doc: EVOLUCAO-DESIGNER-ENGINE
camada: meta-protocolo / tooling
status: proposta · aguarda ADR de adoção (Tier 0 · Wagner)
created: 2026-06-05
autor: Claude Design System (sessão Wagner)
parent_adr: UI-0013 (Constituição UI v2) · ADR-0050 (soberania de cor)
related: [PROTOCOL/MWART, AUTOMATION-ROADMAP, UI-LINT-USAGE, PRE-MERGE-UI, GOVERNANCE.md]
target: virar a prancheta F1 (design) do loop, nativa de Claude Code, que padroniza TODAS as telas do sistema
artefato_de_partida: uploads/canvas-prototype.html (standalone, canon vivo deste repo)
---

# EVOLUÇÃO · Oimpresso Designer Engine

> **Pra quem:** o Claude Code dev do Wagner (papel `Claude Code` da `GOVERNANCE.md`), que conhece a estrutura de `oimpresso.com`.
> **De:** Claude Design System, após análise linha-a-linha do protótipo e cruzamento com o canon vivo (`01-fundacoes/tokens.css`, `ref/oimpresso-codebase/`).
> **O que é:** o documento que transforma o protótipo de *compositor de uma tela* numa **engine de padronização de telas** plugável na IA dev — sem reinventar o que o repo já tem.
> **Status:** proposta. A adoção é **tooling → exige ADR aprovada por Wagner (Tier 0)**. Nada deste doc commita no repo sozinho.

---

## 0 · TL;DR

O protótipo (`canvas-prototype.html`) já prova o conceito: canvas + camadas + inspector + ações executáveis + auditor + 4 exports. Mas ele monta **uma tela por vez**, com os tokens **copiados** dentro do HTML, e o auditor vive isolado do `ui:lint` real.

A evolução tem **um objetivo** e **três alavancas**:

**Objetivo:** ser a prancheta **F1 (design)** do loop MWART — onde a tela nasce, em co-design humano+IA — e produzir artefatos que o resto do pipeline (`ui:lint`, `ui:judge-pr`, visual-regression, MWART F2..F4) **já sabe consumir**.

1. **De 1 tela → o sistema inteiro.** Modelo de dados vira um *projeto* de N telas + as *navegações* entre elas (fluxo). Figma tem páginas/frames; aqui é projeto/telas/fluxo, mas travado no canon.
2. **Fonte única, não cópia.** Tokens, componentes e padrões são **lidos** de `oimpresso.com` em runtime — nunca replicados. O que muda no repo aparece na engine.
3. **Auditor = espelho do `ui:lint`.** As regras do canvas são as mesmas R1–R5 + AP1–AP8 do CI. **Verde no Designer ⇒ verde no PR.** Esse é o diferencial que nenhum Figma tem.

---

## 1 · De onde partimos (o protótipo)

Análise do `canvas-prototype.html` (1.401 linhas, autocontido, runtime verificado).

### Reaproveitar (lógica sólida)
- **Árvore de nós** screen→containers→componentes; seleção; **Camadas** com drag-reorder (before/after/into); undo/redo (80 passos).
- **Inspector por tipo** (~24 tipos) com edição de props ao vivo.
- **Modo ▶ Visualizar + Trigger→Ação** (clique/hover/change → toast/drawer/modal/navigate/bulk/selectRow/stub) que **executam**. Fecha "botão morto" (PRE-MERGE-UI · Parte D).
- **6 estados** (cheia/loading/vazia/sem-resultado/erro).
- **Export** em 4 formatos (Pedido / YAML / JSON / HTML) com ações + soberania embutidas.
- **Auditor** heurístico (cor hardcoded, soberania, storage, fonte, raio, gradiente, emoji) + checklist C3.

### Corrigir / superar
| Item | Hoje | Alvo |
|---|---|---|
| Tokens | cópia em `.oi-scope{…}` (vai stale) | **ler** `resources/css/{cockpit,inertia}.css` + `01-fundacoes/tokens.css` |
| Escopo | 1 tela em `localStorage` | **projeto** de N telas + fluxo, em `project.json` no disco |
| Componentes | registry inventado no HTML | **derivado** de `resources/js/Components/shared/` + UI Kit |
| Padrões | templates lista/dashboard/form/detalhe/config ad-hoc | **derivados** de `padroes-tela/PT-01..05.md` (slots reais) |
| Amostra de dados | sempre "faturas/NF-e" | amostra por **módulo/persona** (`personas-por-modulo.yml`) |
| Auditor | regex isolado no HTML | **espelho** de `php artisan ui:lint` (R1–R5) + AP1–AP8 |
| Export | HTML/pedido genérico | **`Pages/<X>/Index.tsx`** nos slots do PT + imports do shared |
| Plugabilidade | HTML solto | **servidor MCP** (convenção `.mcp.json`) |

> ⚠️ **Não é bug, é soberania (ADR-0050):** accent magenta (hue 330, Oficina) **aprovado pelo Wagner**, sidebar dark, status fixos, roxo 295 = Jana. O swatch de marca por vertical é canon — o auditor trata magenta 330 como **marca permitida**, nunca violação.

---

## 2 · A mudança de escopo · 1 tela → o sistema

O pedido do Wagner é padronizar **todas** as telas. Isso muda o modelo de dados de uma árvore única para um **projeto**:

```
project (oimpresso ERP)
├── screens[]            ← N telas (cada uma = a árvore que o protótipo já faz)
│   ├── id, name, module, persona, pattern (PT-01..05)
│   ├── tree (screen→containers→componentes)  ← reaproveita 100% do protótipo
│   └── states[] (cheia/loading/vazia/sem-resultado/erro)
└── flows[]              ← arestas de navegação entre telas (o "protótipo" do Figma)
    └── { from: screenId, via: nodeId, trigger, to: screenId }
```

Duas capacidades novas decorrem disso:

- **Biblioteca de telas:** abrir/duplicar/versionar qualquer tela do sistema; navegar entre módulos. A engine vira o **mapa vivo** do ERP, não um editor de arquivo solto.
- **Fluxo navegável:** no modo ▶ Visualizar, a ação `navigate` deixa de ser um toast e **troca de tela de verdade** seguindo a aresta `flow`. É o "prototype mode" do Figma — só que cada tela é canônica por construção.

---

## 3 · Princípios invioláveis

1. **Canon-locked.** Não existe desenho livre. Só dá pra inserir componentes do registry, que consomem `var(--token)`. Impossível produzir tela off-system — é o oposto do canvas vetorial do Figma, e é de propósito.
2. **Fonte única da verdade.** Tokens/componentes/padrões **lidos** do repo. Zero replicação. (Resolve o drift que o protótipo tem hoje.)
3. **Soberania de cor (ADR-0050).** Accent por vertical (incl. **magenta 330**), sidebar dark, status e Jana-295 são intocáveis por tema/marca.
4. **PT-BR na UI · sem emoji (SVG lucide) · prefixo `oimpresso.` em storage · grade 4/8 · OKLCH.** (CLAUDE.md.)
5. **A engine é tooling.** Adoção, mudança de regra de lint, entrada no `.mcp.json` = **Tier 0 (Wagner) + ADR**.

---

## 4 · Arquitetura alvo

A engine é a prancheta que falta no **F1 do loop**, empacotada como **servidor MCP** (qualquer Claude Code da equipe opera) + canvas web, compartilhando um `project.json` em disco.

```
   Humano + IA  ⇄  [MCP: oimpresso-design]  ⇄  project.json  ⇄  canvas web (live-reload)
    conversam        ferramentas (§6)          (fonte única)     humano vê / arrasta
        │                                           │
        └──── lê canon (read-only) ────────────────►├─ resources/css/*.css        (tokens)
                                                     ├─ resources/js/Components/   (registry)
                                                     └─ padroes-tela/PT-01..05.md  (slots)
        exporta ──────────────────────────────────► prototipos/<tela>/ (page.tsx + spec)
                                                     └─► entra no loop MWART F2..F4
```

**Onde encaixa no loop existente (não duplica):**

| Fase | Hoje | Com a engine |
|---|---|---|
| **F1 · design** | nasce no Cowork web / à mão | **nasce aqui** (co-design humano+IA, canon-locked) |
| **F2 · spec/scaffold** | manual | export gera `page.tsx` + spec nos slots do PT |
| **F3 · implementação** | Claude Code | recebe artefato já-canônico → menos retrabalho |
| **F4 · gates** | `ui:lint` + `ui:judge-pr` + visual-regression | a engine **já rodou** o espelho do `ui:lint` no F1 → chega verde |

**Por que MCP e não HTML solto / Electron:** HTML solto não dá "mãos" à IA do Wagner; Electron+chat é pesado e fora do padrão. MCP é o mecanismo oficial e **o repo já distribui MCP via `.mcp.json`** — plugar é `git pull`.

---

## 5 · Modelo de dados (`project.json`)

Schema mínimo. Reaproveita o nó do protótipo (`{id,type,props,children,actions,hidden}`).

```jsonc
{
  "project": "oimpresso-erp",
  "canon_ref": { "repo": "oimpresso.com", "commit": "<sha>", "tokens": "resources/css/cockpit.css" },
  "screens": [
    {
      "id": "fin-faturas-lista",
      "name": "Faturas",
      "module": "financeiro",
      "persona": "jana-financeiro",          // de personas-por-modulo.yml
      "pattern": "PT-01",                      // PT-01..05
      "brand_hue": 145,                        // accent da vertical (soberano, ADR-0050)
      "tree": { "type": "screen", "props": { "pattern": "lista" }, "children": [ /* ... */ ] },
      "sample": "financeiro"                   // amostra de dados por módulo
    }
  ],
  "flows": [
    { "from": "fin-faturas-lista", "via": "<nodeId-da-linha>", "trigger": "click", "to": "fin-fatura-detalhe" }
  ]
}
```

**Persistência:** disco (`project.json`), não `localStorage`. Canvas faz **live-reload** (polling/SSE) pra co-criação aparecer ao vivo enquanto a IA muta a árvore.

---

## 6 · Superfície de ferramentas MCP

Servidor `oimpresso-design` (stdio · zero-dep ou `@modelcontextprotocol/sdk`). Multi-tela desde o dia 1.

| Tool | Assinatura | Faz |
|---|---|---|
| `read_project` | `()` | retorna o `project.json` (a IA "vê" o sistema todo) |
| `read_screen` | `(screenId)` | retorna uma tela + estados |
| `create_screen` | `(name, module, pattern, persona?)` | nova tela já instanciada no PT |
| `add_component` | `(screenId, parentId, type, props?)` | insere nó (só tipos do registry) |
| `update_node` | `(screenId, nodeId, props)` | edita props |
| `move_node` | `(screenId, nodeId, target, mode)` | reordena (before/after/into) |
| `remove_node` | `(screenId, nodeId)` | remove |
| `set_action` | `(screenId, nodeId, {trigger, do, arg})` | liga Trigger→Ação |
| `link_screens` | `(from, via, trigger, to)` | cria aresta de fluxo |
| `list_components` | `()` | registry canônico (lido de `Components/shared/`) |
| `get_tokens` | `()` | **lê** tokens do repo → nunca stale |
| `get_pattern` | `(pt)` | slots + regras do `PT-0X-*.md` |
| `audit` | `(screenId \| code)` | espelho do `ui:lint` (§7) |
| `screenshot` | `(screenId, state?)` | render pra IA conferir visualmente |
| `export` | `(screenId, format)` | gera artefato F1 (`page.tsx` + spec) (§8) |

**Loop de co-design:** o humano arrasta no canvas → muta `project.json`; a IA chama `add_component`/`update_node` → muta `project.json`; ambos veem o resultado ao vivo. As duas mãos no mesmo barro.

---

## 7 · Auditor = espelho do `ui:lint` (o diferencial)

O auditor do protótipo é bom, mas vive isolado. O alvo é **mapear 1:1 as regras do `php artisan ui:lint`** (UI-LINT-USAGE.md) + os anti-padrões de `PRE-MERGE-UI.md`, pra garantir que **o que passa no canvas passa no CI**.

| Regra CI | Detecta | No Designer |
|---|---|---|
| **R1** | cor crua (`#hex`≠fff/000, `bg-cor-NNN`) | impossível por construção (tudo via token) + lint no export |
| **R2** | FontAwesome | registry só usa lucide |
| **R3** | emoji em UI | bloqueia emoji, exige SVG lucide |
| **R4** | PT-01 sem `<PageHeader>`(slot 1) / `<DataTable>`(slot 5) | valida slots da tela contra `get_pattern` |
| **R5** | 6ª origin badge | trava em OS/CRM/FIN/PNT/MFG |
| **AP1** | cor hardcoded | = R1 |
| **AP2** | componente reinventado | só insere do shared |
| **AP5** | gradiente decorativo | bloqueado |
| **AP7** | status badge com bg-fill | badge sem-fill (canon) |
| **AP8** | copy não-PT-BR | aviso no inspector |

> **Modo ratchet:** `audit` deve reportar no mesmo formato do `ui:lint --baseline` (delta vs baseline), e o `export` deve nascer com **delta 0** — uma tela criada na engine nunca regride o baseline. Para o que o lint sintático não pega (drawer-sobre-modal, PT quebrado em essência), a engine continua oferecendo o checklist C3 manual + pode acionar `ui:judge-pr` no artefato exportado.

---

## 8 · Export · alimenta o loop, não um beco

O export deixa de ser "HTML de referência" e passa a emitir o que o **MWART F2/F3 consome**:

- **`prototipos/<tela>/Index.tsx`** — JSX nos **slots do PT** (`<PageHeader>`, `<DataTable>`, etc.), importando de `@/Components/shared/`, zero cor crua. Pontua alto no `ui:judge-pr` (slot adherence + token canon).
- **`prototipos/<tela>/spec.yaml`** — anatomia + tokens + ações + estados (evolução do YAML atual).
- **Pedido (Parte F)** — mantido pra quando o handoff é via chat.

Quem commita é **humano** (governança). A engine **gera**; não escreve no repo.

---

## 9 · "Figma ou melhor" — comparativo honesto

| Capacidade | Figma | Oimpresso Designer |
|---|---|---|
| Canvas, zoom, camadas, drag-reorder | ✅ | ✅ (protótipo já tem) |
| Múltiplas telas + fluxo navegável | ✅ páginas/frames + prototype | ✅ projeto/telas/flows (§2) |
| Componentes / auto-layout | ✅ genérico | ✅ **só os do sistema** (canon-locked) |
| Desenho livre / vetor | ✅ | ❌ **de propósito** (impede drift) |
| Tokens | plugin/variables | ✅ **lidos da fonte única** do repo |
| Ações que **executam** | ❌ (só link) | ✅ Trigger→Ação roda no preview |
| **Lint = CI** ("verde aqui = verde no PR") | ❌ | ✅ espelho do `ui:lint` (§7) |
| Export de **código na stack** (`page.tsx`) | parcial/aprox. | ✅ slots do PT + shared (§8) |
| **IA com mãos** no mesmo arquivo (co-design) | beta/limitado | ✅ via MCP (§6) |
| Multiplayer humano | ✅ | ⏳ futuro (1 humano hoje) |

**Veredito:** não competimos em "tela bonita genérica". Vencemos em **fidelidade ao sistema, ação executável, lint=CI, export-na-stack e IA-no-loop**. É "melhor que Figma" *para o caso do Oimpresso* — não em abstrato.

---

## 10 · Roadmap faseado

Estilo "ondas" do AUTOMATION-ROADMAP. Cada onda é PR isolado. Coluna "Repo" = toca `oimpresso.com`.

| Onda | Entrega | Repo? | Gate |
|---|---|---|---|
| **1 · Pacote** | servidor MCP + canvas compartilhando `project.json`; reaproveita a lógica do protótipo. Roda em `tools/design-mcp/` standalone. | ❌ | — |
| **2 · Canon vivo** | `get_tokens`/`list_components`/`get_pattern` **lendo** `resources/css/*.css` + `Components/shared/` + `PT-0X.md`. Fim da cópia stale. | só leitura | — |
| **3 · Multi-tela + fluxo** | `create_screen`/`link_screens`; biblioteca de telas; modo ▶ navega entre telas. | ❌ | — |
| **4 · Auditor=CI** | `audit` espelha `ui:lint` R1–R5 + AP1–AP8, formato baseline/ratchet. | só leitura | alinhar c/ UI-LINT-USAGE |
| **5 · Export-na-stack** | `export` gera `prototipos/<tela>/Index.tsx` nos slots + spec.yaml; opção de acionar `ui:judge-pr`. | gera, humano commita | MWART F2 |
| **6 · Adoção** | **ADR de adoção** + entrada no `.mcp.json` + onboarding equipe. | **decisão Wagner** | **Tier 0** |

**Ordem recomendada:** 1 → 2 (+ correção de amostra por módulo) → 3 → 4 → 5 → **6 (ADR)**. Não pular pra 3/5 antes de 2 — construir andar com fundação que mente sobre cor.

---

## 11 · Governança & integração

- **A engine é tooling.** Pela `GOVERNANCE.md` + CLAUDE.md do repo, **lógica de lint/TOOLING e entrada no `.mcp.json` são Tier 0** → exigem **ADR aprovada por Wagner**. Por isso ondas 1–5 podem nascer standalone (`tools/design-mcp/`), mas a **adoção (onda 6)** espera o ADR.
- **Papéis (GOVERNANCE):** Claude Design System propõe/documenta (camadas 1–3); **Claude Code** implementa a engine e os módulos (camada 4); Wagner aprova. A engine **gera** artefatos; **humano commita**.
- **Não quebrar invariantes:** PT-BR, sem emoji, cor só por token, prefixo `oimpresso.`, `git` = SSOT, append-only em ADR/CHANGELOG.

---

## 12 · Decisões em aberto · escalar pro Wagner

1. **Onde mora o pacote** quando adotado: `tools/design-mcp/` ou `prototipo-ui/designer/`? (ADR, append-only.)
2. **Engine substitui ou complementa o F1 do Cowork?** Sugestão: **complementa** — é a prancheta local com IA-no-loop que emite os mesmos artefatos do MWART.
3. **Export:** pedido+spec pro Claude Code portar (casa com MWART/F3) **vs** gerar `Index.tsx` direto? (Sugestão: gera `.tsx` como rascunho, humano revisa/commita.)
4. **Brain do `ui:judge-pr` acionável da engine:** gpt-4o-mini (default, ~$0.002) ou Sonnet (qualidade, ~$0.03)?
5. **`project.json` único do ERP inteiro** ou um por módulo? (Único = mapa global; por módulo = PRs menores.)

---

## 13 · Refs

- **Partida:** `uploads/canvas-prototype.html` · análise nesta sessão (CHANGELOG 2026-06-05).
- **Canon vivo:** `01-fundacoes/tokens.css` · ADR-0050 (soberania de cor) · `DESIGN-SYSTEM.md`.
- **Loop & gates:** `ref/oimpresso-codebase/.../AUTOMATION-ROADMAP.md` · `UI-LINT-USAGE.md` · `PRE-MERGE-UI.md` · `adr/ui/0013-constituicao-ui-v2-camadas.md`.
- **Padrões:** `ref/oimpresso-codebase/.../padroes-tela/PT-01..05.md`.
- **Governança:** `05-protocolo/GOVERNANCE.md` · `CLAUDE.md` (raiz).
- **Predecessor:** `uploads/HANDOFF-121d8b24.md` (visão MCP original).

---

**Última revisão:** 2026-06-05 · proposta inicial · zero ondas executadas.
**Próxima revisão:** quando Wagner decidir §12 ou abrir o ADR de adoção (Tier 0).
