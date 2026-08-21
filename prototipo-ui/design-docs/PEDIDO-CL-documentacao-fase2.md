# Pedido ao [CL] — fase 2 da /documentacao (o que sobe a nota de 77 → 93)

> [CC] F1. **Não commitado.** Cole pro Code ou abra Issue `cowork-intake`.
> Base: leitura do `main` em 2026-08-03 (controller, blades, schema, indexador, placement).
> Fase 1 já está no `main` (rail derivado, lente, tokens do DS, 5 domínio + 3 fluxo).

## 1. Aplicar os 10 documentos prontos  · maior ganho, zero código

Ficam em `memory/reference/`, no mesmo padrão dos `DOMINIO-*` existentes (frontmatter completo
pro `reference.schema.json`, `type: reference`, `authority: canonical`).

| Arquivo | `nav_group` | `nav_order` | `lente` |
|---|---|---|---|
| `COMO-LER-ESTA-DOCUMENTACAO.md` | start | 10 | operar, construir |
| `TECNICO-ARQUITETURA.md` | tecnico | 10 | construir |
| `TECNICO-DADOS-MULTITENANT.md` | tecnico | 20 | construir |
| `TECNICO-FRONTEND-TOKENS.md` | tecnico | 30 | construir |
| `TECNICO-CONTRATO-DE-TELA.md` | tecnico | 40 | construir |
| `TECNICO-QUALIDADE-CI.md` | tecnico | 50 | construir |
| `TECNICO-MCP-AGENTES.md` | tecnico | 60 | construir |
| `GOV-DECISAO-VIRA-LEI.md` | governanca | 10 | construir |
| `GOV-CONHECIMENTO-INDEXADO.md` | governanca | 20 | operar, construir |
| `GOV-O-QUE-E-OBSERVADO.md` | governanca | 30 | operar, construir |

Os `id` já seguem o slug determinístico do indexador (`reference-<caminho-slugificado>`), então o
link do rail (`/documentacao/{id}`) resolve assim que o webhook sincronizar.

**Antes de commitar:** rodar `node scripts/memory-schemas/validate.mjs memory/reference/*.md` e
`npm run docs:relink -- --detect` (os documentos linkam ADRs e scripts por caminho relativo).

---

## 2. Gate de documentação no `docs:loop`  · vira catraca

Três checagens novas em `scripts/governance/documentation-loop.mjs`, sobre os arquivos de
`memory/reference/*.md`:

| Checagem | Reprova quando | Por quê |
|---|---|---|
| **nav_group inválido** | valor fora do enum do schema | typo silencioso tira a página do menu sem avisar ninguém |
| **id ≠ slug do indexador** | `id` do frontmatter ≠ `reference-<basename slugificado>` | é exatamente o caso que faz o link do próprio rail dar 404 |
| **link relativo morto entre docs de nav** | destino não existe no disco | o `docs:relink --detect` já acha; falta ser **fail**, não relatório |

Forma sugerida (mesma assinatura das fontes já normalizadas pelo loop):

\`\`\`js
// nav-integrity — o rail é derivado do frontmatter; frontmatter torto = menu torto.
// NÃO checa "todo doc tem nav_group": entrar no menu é OPT-IN (os ~130 references
// legados não viram menu por acidente). Checa quem JÁ optou e optou errado.
export function navIntegrityIssues(root = ROOT) { /* … */ }
\`\`\`

Promover a `required` só depois de rodar em modo relatório e medir FP = 0 — a régua da casa
(ADR 0336) é mordida provada antes de promoção.

---

## 3. Fallback de leitura no disco  · mata o 404 do próprio menu

Hoje: o rail lê do **disco** (`File::glob(memory/reference/*.md)`) e `documento()` lê do
**corpus** (`mcp_memory_documents`). Em ambiente sem webhook sincronizado — ou nos minutos entre
o merge e a indexação — o menu lista uma página que abre 404.

Em `DocumentacaoController::documento()`, antes do `abort(404)`:

\`\`\`php
// FALLBACK — o rail é derivado do disco; o corpus é cache. Se o cache ainda não tem o
// documento (webhook atrasado, ambiente sem índice), servir o arquivo é MAIS correto que
// um 404: a fonte existe, quem não existe é a cópia. Escopo deliberadamente estreito —
// só o que o próprio rail já expõe (PASTA_NAV + nav_group), nada de servir memory/ inteiro.
if (! $doc) {
    $arquivo = $this->arquivoDoNav($slug);   // null se não estiver no rail
    if ($arquivo !== null) {
        return view('documentacao.doc', [
            'doc' => $this->docSintetico($arquivo),  // slug/type/title/git_path do frontmatter
            'nav' => $this->navegacao($this->lenteAtiva($request)),
            'atual' => $slug,
            'html' => $this->paraHtml(File::get($arquivo), self::PASTA_NAV),
            'fonteDisco' => true,   // a view mostra "servido do arquivo; índice ainda não sincronizou"
        ]);
    }
}
\`\`\`

O mesmo vale pro `abort(503)` do topo: sem corpus, a busca continua indisponível (honesto), mas a
**leitura** não precisa cair junto.

---

## 4. Faceta de módulo + ⌘K na busca  · achabilidade

- A consulta já traz `module`. Falta a fileira de chips na `busca.blade.php`, como
  `?q=…&modulo=fiscal` — link real, sem JS, coerente com o resto da tela.
- ⌘K opcional: abre um overlay com os itens do rail + os 8 primeiros resultados. Progressivo —
  sem JS, a busca do rail continua sendo o caminho.

---

## Fora de escopo
Portar `documentacao-page.jsx` (Cowork) pra produção. Ele é protótipo de arquitetura de
informação; o que atravessa é a estrutura, não o código.
