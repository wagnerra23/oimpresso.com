---
sessao: "12"
titulo: Saída da thread 12 — "Garantia crítica" vira recorte de Bens, no servidor
dono: "[CL]"
medido_em: 2026-09-29
base_medida: 5606344ca (origin/main fresco; a ficha citava a71c2f2d052f)
arquivos_de_producao_tocados: 2
invalida: "nada — a D-GARANTIAS foi respondida; a versão com tela/rota/controller próprios segue revogada"
---

# 12 · Saída — o recorte "Garantia crítica" dentro de Bens

## Decisão que destravou
**D-GARANTIAS → filtro dentro de Bens, sem tela própria** ([W] 2026-09-29).
Nada nasceu em `Pages/Patrimonio/Garantias/`, nenhuma rota nova, `_shared/` intocado.

## O que mudou
- `AssetController@index`: `?recorte=garantia` (whitelist `todos|garantia`; valor fora dela vira
  `todos`). O filtro entra só no ramo Inertia (`buildBensPayload`) — o DataTables do Blade não muda.
- Predicado: **o mesmo** do KPI do Painel (`contaGarantiaCritica`: `DATEDIFF(end_date, CURDATE()) <= 30`),
  logo vencida ou vencendo em 30 dias; sem registro não entra.
- Tier 0: `asset_warranties` não tem `business_id`; a subconsulta filtra `assets.business_id` por join,
  além da consulta externa.
- Prop deferida `recortes_contagem.garantia`: contagem do **conjunto** (independe dos filtros, como o
  `n` do protótipo, `:357`), respeitando `permitted_locations()`.
- `Bens.tsx`: faixa `Todos · Garantia crítica` (`PageHeaderTabs`, contagem do servidor, troca por
  `router.get` com `recorte`). O selo por linha **não** deriva criticidade — o anti-hook `:108` fica.
- Charter: Non-Goal `:75` sai só para Garantia crítica ("Em manutenção" segue fora). Casos:
  UC-BENS-06; o `[BACKLOG]` `:168` fica só com "Em manutenção".

## Medições
- **CT 100** (MySQL real, worktree isolado em `/tmp` no commit do PR — o checkout compartilhado do
  container estava em `e57b78bf5`, sujo de outras sessões, e não foi tocado): UC-BENS-06
  **1 passed · 20 assertions**; suíte `Modules/AssetManagement/Tests/Feature/` **114 passed · 409 assertions**.
- **Bite-test por mutação**, arquivo restaurado conferido por hash:
  - sem aplicar o recorte → **cai**;
  - sem `business_id` no join **e** na consulta externa da contagem → **cai** (`3 ≠ 2`, o bem do adversário somou);
  - sem `business_id` só no join → **sobrevive**: mutante equivalente (a externa já restringe). Fica como 2ª defesa.
- Duas correções no próprio teste, ambas de ambiente de teste: os helpers passaram a semear
  `business.date_format` na sessão (fixture com garantia vigente fazia o `format_date` lançar) e
  `flushHeaders()` antes do GET inicial (headers `X-Inertia*` do partial anterior persistiam).

## Achados para o índice (não editei o `00-INDICE.md`)
- A tabela §2 ainda diz **12 · BLOQUEADA por D-GARANTIAS** e "tela nova"; a D-GARANTIAS está
  respondida no bloco json e a ficha foi reescrita. Atualizar no Cowork.
- A prova `Bens.tsx contém Garantia crítica` **já era verde antes desta thread**: o comentário de
  cabeçalho (`Bens.tsx:17`) citava o termo para dizer que o recorte estava **fora**. O placar marcava
  `proximo (sem recibo)` por isso. Prova melhor: `Bens.casos.md` contém `UC-BENS-06` **e**
  `BensContratoTest.php` o cita.
- Ambiguidade herdada do Painel, não decidida aqui: bem com **duas** garantias (uma velha vencida e
  uma nova vigente) entra em "Garantia crítica" — o predicado olha qualquer garantia. É o mesmo
  comportamento do KPI do Painel; mudar é decisão de produto e mexe nos dois.

## Checklist
- [x] Filtro no servidor, contagem do servidor
- [x] UC no `Bens.casos.md` citado por teste, com controle negativo e tenant cruzado
- [x] `Garantias/` ausente · `Routes/web.php` e `_shared/` intocados
- [ ] Lane `assetmanagement-pest` verde no PR
- [ ] Screenshot pós-deploy de `/asset/assets?recorte=garantia`
