# ADR 0412 — Três componentes compartilhados não cobrem os casos desta família

**Data:** 2026-09-01 · **Status:** proposto (aguarda decisão) · **Camada:** componentes do repo alvo
**Origem:** conferência da família de telas Fabricação (Manufacturing)

Uma ADR só, com três achados, porque a decisão é a mesma nos três: **estender o componente
compartilhado ou aceitar contorno declarado na tela.** Nenhum dos três autoriza recriar componente
local.

---

## 1 · `StatusBadge` não tem domínio de produção

**Medido** `[DS]` `resources/js/Components/shared/StatusBadge.tsx`: 17 domínios em `mappings`
(L40-174) — `intercorrencia`, `aprovacao`, `prioridade`, `payment`, `financeiro_titulo`,
`importacao`, `nfse`, `rep`, `vehicle`, `ads_destination`, `ads_risco`, `mcp_status`,
`admin_health`, `admin_reachable`, `licenca`, `licenca_no_acesso`, `arquivo_prazo`.
**Nenhum cobre `rascunho | finalizada`.**

Estado atual na tela alvo: `Pages/Manufacturing/Index.tsx` L292-311 tem um `StatusPill` **local**,
escrito à mão com `text-success-fg` / `text-warning-fg` + ponto de 1,5px. Funciona e respeita AP7
(dot + texto, sem fill sólido), mas é um componente de status fora do componente de status.

**O próprio componente já autoriza a saída**, terceira linha do docblock (L17):
*"Adicionar novo domínio: estender `mappings` abaixo + commitar."*

**Decisão proposta:** acrescentar `producao: { rascunho, finalizada }` ao `mappings` e apagar o
`StatusPill` local. Variante: derivar de `badgeVariants` como o resto do arquivo faz — o próprio
docblock (L20-33) registra que copiar variante à mão foi o que gerou 49 entradas em fill sólido.
**Não** escrever `className` com `bg-*` sólido.

---

## 2 · `DataTable` só existe com paginação de servidor

**Medido** `[DS]` `resources/js/Components/shared/DataTable.tsx` L113-152: `pagination:
PaginatorShape<T>` e `endpoint: string` são **obrigatórios**; busca (`?q=`, debounce 300ms) e sort
(`?sort=&dir=`) navegam por `router.get`. O componente entrega, por outro lado, exatamente o
vocabulário que esta família precisa: `meta.width` / `meta.align` / `meta.mono` por coluna
(L60-70) e `rowState` (`urgent | archived | selected`, L76-83, L129-133).

O protótipo pagina, ordena e busca **no cliente**, com 10 linhas por página, porque a cena tem 8
receitas. Em produção, "todas as receitas do business" pode ser milhares de linhas.

**Decisão proposta:** usar `DataTable` com paginador de servidor, e trazer os filtros de KPI e de
categoria como query params (o padrão de `applyFilter` que a tela alvo já usa, `Index.tsx` L56-74).
**Não** paginar no cliente. **Não** escrever tabela nova.

**Consequência declarada:** dois comportamentos do protótipo mudam de significado e precisam de
decisão explícita — "selecionar todas as filtradas" (§4.2 do README) passa a significar "todas as
da página" ou exige seleção do lado do servidor; e o KPI "Custo médio / unidade" deixa de ser média
das linhas carregadas e passa a ser agregação do servidor.

---

## 3 · `PageHeaderTabs` é navegacional; a família usa abas de estado

**Medido** `[DS]` `resources/js/Components/shared/PageHeaderTabs.tsx` L52-77: `primary` é
`{label, href, shortcut?}` e cada `ghost` é `{key, label, href, icon?, badge?}` — renderizados com
`<Link href>` do Inertia, com `role="tab"` e navegação por teclado. O `badge` cobre os contadores
das abas.

O protótipo troca de aba **sem navegar** (estado `aba`), o que preserva filtro e rolagem entre as
cinco visões.

**Decisão proposta:** como o diff do README §15.2 já cria **uma rota por aba**, usar
`PageHeaderTabs` com `href` — a navegação passa a ser real e o link fica compartilhável, que é o
comportamento correto num ERP. **Não** criar barra de abas local.

**Consequência declarada:** trocar de aba perde o estado de filtro da aba anterior, a menos que os
filtros vivam na URL. Se a decisão for preservar estado, aí sim o componente não cobre e o caso
volta para a pauta.

---

## Consequências gerais

- **Se aprovado:** um mapping novo no `StatusBadge`, zero componente novo, e a família nasce em
  cima dos compartilhados.
- **Se não aprovado:** cada tela nova de Manufacturing repete um `StatusPill` local e uma tabela
  própria — que é exatamente o que a régua de conformidade do DS mede.

## Referências

- `resources/js/Components/shared/StatusBadge.tsx` L17, L40-174
- `resources/js/Components/shared/DataTable.tsx` L60-83, L113-152
- `resources/js/Components/shared/PageHeaderTabs.tsx` L52-77
- `resources/js/Pages/Manufacturing/Index.tsx` L56-74, L292-311
- `contexto/pauta-design-system.md`
