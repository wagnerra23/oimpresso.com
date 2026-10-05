---
sessao: "06"
titulo: Páginas (superadmin::pages) — fora do placar por decisão [W] (D1, 2ª rodada)
dono: "[CL]"
data: "2026-10-05"
base: "origin/main cb1fe1d6f4"
---

# _saida-06 — Páginas: nada executado, por decisão [W]

**Placar desta thread: entregue 0 de 0.** A thread sai do placar. Nenhum PR de código.

## Por quê

- A ficha (`06-paginas.md`) diz *"Só com D1"*, e a prova do json é `execucao`:
  *"superadmin::pages redireciona para Cms Admin/Content com paridade de campos"*. Essa prova
  vem da 1ª rodada de D1 (*"fundir no Cms"*, `_DECISOES-W-2026-10-01.md`).
- A 2ª rodada (`_DECISOES-W-2026-10-01b.md`) **revoga** a 1ª: [W] mandou *"pode manter separado"*.
  O efeito registrado lá é **"sem migração de dados nem mudança de URL pública. A thread 06 sai
  do placar."** O json do índice já traz a resposta nova (`"manter separado do Cms"`), mas a
  thread 06 e a prova `execucao` continuam nele, por isso o `placar.mjs` ainda a mostra como
  `proximo`.
- O protótipo não pede tela: `superadmin-page.jsx` só tem as views `negocios`, `assinaturas`,
  `pacotes`, `comunicador` e `config` (linhas 1425-1429). Não há `sa-paginas` nem âncora de design
  para as páginas do site.

Executar a prova como está escrita (redirecionar para o Cms) contradiz a decisão vigente. Migrar
as 4 Blades para Inertia seria escopo que nem a ficha nem o protótipo pedem.

## Estado medido em `origin/main cb1fe1d6f4`

- `PageController` segue em Blade: `pages.index` (:46), `pages.create` (:57), `pages.show` (:110,
  rota pública `/page/{slug}`), `pages.edit` (:125). Nenhum commit no controller ou nas views desde
  a base do índice (2026-09-30).
- Acesso: o `Route::resource('/frontend-pages')` está no grupo com middleware `superadmin` +
  `throttle:superadmin` (`Routes/web.php:13` e `:58`), então `create`/`edit` (que não checam
  `can('superadmin')` no corpo) já estão fechados para quem não é superadmin.
- `SuperadminFrontendPage` é tabela global do site, sem `business_id`, e o controller não usa
  `withoutGlobalScopes`. Nada de Tier 0 a consertar aqui.

## Pedido ao Cowork (o Code não edita o índice)

No json do `00-INDICE.md`: retirar a thread `06` (ou marcá-la descartada) e a prova `execucao`
que ainda manda redirecionar para o Cms. A §2 do índice também ainda descreve D1 como
`superadmin::pages × Cms Admin/Content`, sem a resposta.

## Pendente

- Se um dia as páginas do site forem para Inertia, isso é pedido novo, com protótipo próprio.
