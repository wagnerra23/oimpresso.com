# Pedido ao [CL] — /documentacao com âncora domínio→fluxo→tela

> [CC] F1. **Não commitado** — cole isto pro Code ou abra Issue `cowork-intake`.
> Referência visual/IA: rota `documentacao` do Cowork (`documentacao-page.jsx`, protótipo React).
> A implementação de produção é **Blade** (`resources/views/documentacao/*`), não React.

## Estado lido no `main` (2026-08-03)
- `routes/web.php:876-892` — `documentacao`, `documentacao.buscar`, `documentacao.documento`.
- `app/Http/Controllers/DocumentacaoController.php` — renderiza `memory/GUIA-DO-SISTEMA.md` em runtime (ADR 0256); busca via FULLTEXT `mcp_md_fulltext_idx`; `TIPOS_DOC = adr, reference, spec, runbook`; acesso por `admin_only` + `scope_required`.
- `resources/views/documentacao/{layout,index,busca,doc}.blade.php` — CSS num lugar só no layout; mermaid de `public/js`, sem CDN.

## A máquina de documentação que JÁ existe (usar, não recriar)
| Peça | O que faz | Consequência pra este pedido |
|---|---|---|
| `document-placement.json` | registro **declarado** de placement por área de `memory/` | área nova precisa ser **declarada** aqui, senão o classificador manda pra `review` |
| `document-relocation-{classifier,adversary,executor}` | classifica/valida/move docs | não mover nada à mão |
| `doc-id-stamp` | injeta `id:` **derivado do path** + `governance/doc-id-index.json` | o nav usa esse `id` como chave — não inventar slug |
| `doc-auto-relink` (`docs:relink`) | detecta e reescreve link-rot | links relativos dos novos docs entram nesse contrato |
| `documentation-loop` (`docs:loop`) | snapshot/compare de memory-health + briefing-staleness + doc-freshness | é **aqui** que o nav derivado se pluga, sem script novo |
| `memory-schema-gate` (AJV) | schema por família (spec/runbook/charter/reference) | campo de frontmatter novo passa pelo schema; `reference` é `[grace]` |
| `anchor-lint`, `distiller`, PII scan | gates diff-aware | tocar doc de família guardada acorda gate — respeitar os `TOXIC_PREFIXES` do stamp |

## Diagnóstico
A página é honesta e rápida, mas é **um documento só, plano**: sem rail, sem "nesta página", busca em outra tela, e a âncora mental é módulo. Falta estrutura de leitura, não estilo.

## O que fazer (ordem)

### 1. Onde os novos documentos nascem — ATENÇÃO ao placement
- ⛔ **Não usar `memory/dominio/`**: já é `protected` (dicionários de enum em path fixo, `domain-dict-guard`, ADR 0264 G-4).
- ✅ Proposta: as páginas de entidade e de fluxo nascem em **`memory/reference/`** (área `canonical`, "referência técnica", e é a única família mapeada com required no schema — frontmatter completo, não stamp mecânico):
  - `memory/reference/DOMINIO-{ESTAGIO,VENDA,OS,NOTA-FISCAL,TITULO}.md`
  - `memory/reference/FLUXO-{VENDA,CANCELAMENTO,DEPLOY}.md`
  - técnico: o que é **por módulo** fica em `memory/requisitos/` (canonical, com o módulo); o transversal (arquitetura, dados/multi-tenant, front-end/tokens, contrato de tela, qualidade/CI, MCP) em `memory/reference/`.
- Se [W] preferir subárvore própria (`memory/documentacao/`), **declarar a área** em `document-placement.json` no mesmo PR — o merge é a ratificação.
- **Fonte/capa confirmada por [W]: `memory/GUIA-DO-SISTEMA.md`.** Continua dona do "comece aqui" e do entrypoint (`<!-- documentation-entrypoint -->`); os novos docs são filhos linkados dela e a `const FONTE` do controller **não muda**.

### 2. Frontmatter de navegação — dentro do schema, não ao lado
Acrescentar ao schema `reference` (e ao que a família usar) três campos opcionais:
```yaml
nav_group: dominio        # start|dominio|fluxo|tecnico|governanca
nav_order: 20
lente: [operar, construir]
```
Regras: sem `nav_group` o doc não aparece no rail (e sai como órfão no relatório); `id:` continua sendo derivado pelo `doc-id-stamp` e é a chave do nav.

### 3. Índice de navegação derivado — no `docs:loop`, sem script novo
Nova saída do `documentation-loop.mjs` (ou consumidora de `governance/doc-id-index.json`) que emite o manifesto do rail a partir do frontmatter. Ordinal derivado da **ordem visível na lente**, nunca do array inteiro. Zero lista escrita à mão em Blade.

### 4. Layout de leitura no `layout.blade.php` (um lugar só)
- Rail esquerdo 240px agrupado, com contador por grupo.
- Coluna de leitura ~72ch (já é).
- Aside "nesta página" com scroll-spy nos `h2` (IntersectionObserver, ~15 linhas).
- Rodapé anterior/próximo pela ordem do manifesto.
- Busca **inline** no rail (mantendo `documentacao.buscar` como fallback sem JS) + `⌘K` opcional.

### 5. Lente Operar / Construir
Segmented no topo filtrando por `lente` do frontmatter; persistir em cookie (`doc_lente`). Domínio aparece nas duas — **uma página canônica por entidade, sem cópia**. Resultado de busca fora da lente cai em "tudo".

### 6. Visual — DECIDIDO por [W] 2026-08-03: opção (a)
Mantém a **coluna editorial** (serif nos títulos, 72ch, mermaid local) e troca os literais de cor/tipo do `layout.blade.php` pelos **tokens do DS vivo**: accent roxo `oklch(0.55 0.15 295)`, neutros quentes, IBM Plex Sans/Mono self-hosted. Uma paleta só no produto — sem `.cockpit` inteiro.
- `:root` do layout referencia as vars do DS em vez de `#FBFAFC`/`#17151E`/`#6D4FD1`.
- Dark segue por `prefers-color-scheme` + `data-theme`, com os valores dark do DS.
- Serif fica **só** em `h1`/`h2` da coluna — é o que separa doc de tela.

### 7. Gates
- `nav_group`/`lente` entram no schema da família (não como convenção solta).
- `docs:loop` reprova doc de documentação sem `nav_group` e link relativo morto (`docs:relink --detect` já detecta).
- Nada de HTML de documentação commitado: a página continua sendo o markdown renderizado (ADR 0256).

## Fora de escopo
Não portar `documentacao-page.jsx` pra produção: é protótipo de arquitetura de informação e interações (rail, lente, scroll-spy, ⌘K, corpus com faceta de módulo). O que atravessa é a estrutura, não o código.
