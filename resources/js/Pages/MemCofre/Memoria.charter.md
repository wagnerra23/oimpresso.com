---
page: /memcofre/memoria
component: resources/js/Pages/MemCofre/Memoria.tsx
related_prototype: n/a (explorador de arquivos bespoke — árvore + preview markdown, não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: MemCofre
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /memcofre/memoria (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/SRS/Http/Controllers/MemoriaController@index` (rota `memcofre.memoria`) + `@file` (JSON, rota `memcofre.memoria.file`), prefixo `/memcofre`, stack admin UltimatePOS + `throttle:60,1`. Usa `MemoryReader` pra ler o filesystem (primer/projeto/Claude). Módulo `Modules/SRS` ("Cofre de Memórias") — ferramenta interna Wagner de uso raro, em deprecação segundo o BRIEFING.
>
> Classificação: **SILENCIOSO** — é um explorador master-detail (árvore de arquivos + preview markdown read-only), não um dos 5 Padrões de Tela; o `grid-cols` presente é só layout.

---

## Mission
Navegar, num só lugar e read-only, três raízes de memória do projeto — Primer, memória versionada do projeto (`memory/`) e memória persistente do Claude — via árvore de arquivos com busca e um painel de preview que renderiza markdown ou mostra o texto cru.

---

## Goals — Features (faz)
- Seletor de raiz (Primer / Projeto / Claude) com contagem de arquivos por raiz (`stats`).
- Árvore de diretórios recursiva com expandir/colapsar e filtro por nome/preview (auto-abre nós no filtro).
- Preview do arquivo selecionado: markdown renderizado (`SimpleMarkdown`, frontmatter removido) ou `<pre>` pra não-markdown; mostra tamanho, mtime e metadados.
- Carregamento sob demanda do conteúdo via `fetch` JSON (`?key=...`) com atualização de URL (`replaceState`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita, cria nem apaga arquivos — é estritamente read-only (declarado no próprio subtítulo da tela).
- ❌ Não é dado multi-tenant: lê o filesystem do servidor (git canon + `~/.claude`), igual pra qualquer business — ferramenta interna, não escopada por `business_id`. _Confirmar com Wagner que a exposição desse conteúdo interno é intencional._
- ❌ Não expõe caminho fora das raízes configuradas (o `MemoryReader` valida o `key` e retorna 404 em caminho inválido).

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb "Cofre › Memória").

---

## Automation hooks (faz)
- `MemoryReader` monta a árvore e resolve/valida o `key` a cada leitura de arquivo.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem re-scan automático do filesystem.
- ❌ Não escreve nada — nenhum GET/JSON aqui muta estado.
- ❌ Não segue caminho arbitrário (path traversal barrado pelo reader).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — árvore + preview markdown
- [ ] **Bug latente:** `openFile()` chama `fetch('/docs/memoria/file?key=...')` (prefixo stale); a rota real é `/memcofre/memoria/file`. Nenhum prefixo `/docs` existe em `routes.php`. Alinhar antes de live.
- [ ] Confirmar com Wagner se expor `~/.claude/.../memory` na UI é desejado dado o estado de deprecação do módulo
