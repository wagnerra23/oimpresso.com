---
id: requisitos-cms-runbook-admin-content
title: "RUNBOOK — /cms/cms-page (Conteúdo do site · Blade → Inertia)"
module: Cms
tela: Admin/Content/Index
owner: W
status: rascunho
last_validated: "2026-09-23"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Cms/SPEC.md
---

# RUNBOOK — `/cms/cms-page` (conteúdo do site, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a
thread **Cms/01** do playbook `prototipo-ui/cowork/Wagner/cowork-inbox/cms/playbook/`. Cobre a
US-CMS-004 do [SPEC](SPEC.md).

- **Fonte de design:** [`CMS-F1-2026-08-19.md`](../../../prototipo-ui/cowork/Wagner/cowork-inbox/cms/CMS-F1-2026-08-19.md)
  §1 (peças → DS), §2 (13 regras) e §3 (18 UC). O `cms-page.jsx` citado lá **não está no
  espelho** — a âncora medida é o documento, não um render.
- **Pacote pronto do [CC]** (charter completo, 18 UC, contrato de tela, teste-âncora):
  mesma pasta, `PEDIDO-CL-cms-trio.md`. Entra por fases — abaixo.

## Fases (1 PR por fase, ≤300 linhas)

| Fase | Entrega | Blade que morre | Status |
|---|---|---|---|
| **1** | Lista `Admin/Content/Index.tsx` + `index()` → `Inertia::render` + contrato UC-CMS-01/02/03/20/21 | `page/index.blade.php` (fica órfã; delete na F5) | #7865 |
| **2** | Editor em drawer PT-02 (`_components/Editor.tsx`), derivação de `meta_description` no servidor (R7) + UC-CMS-04/05/22/23 | `page/create` (sem link; delete na F5) | #7871 |
| **2b** | Destaques (`feature`) da home no drawer **e ligados à `/`** (caminho A, [W] 2026-09-23) + UC-CMS-08/24/25 | `page/edit` + `partials/features` (sem link; delete na F5) | #7874 · `industry` fora: a home nova não tem seção de segmentos (decisão [W]) |
| **3** | `destroy` recusa `layout` preenchido no servidor (UC-CMS-09) + exclusão livre logada (UC-CMS-10) | — | #7879 · o whitelist `type` saiu antes, no #7869 (decisão [W] 2026-09-23) |
| **4a** | Detalhes do site em Inertia: Aplicação · Contato · Redes · Integrações + validação corrigida + UC-CMSD-01/02/07/09 | — (Blade segue em `?legado=1`) | este PR |
| 4b | Estatísticas · Perguntas frequentes · Chat · Botões; fim do `?legado=1` | `settings/index` + 8 partials | pendente |
| 5 | Cutover: apagar Blades órfãs + charter `live` com screenshot [W2] | todas acima | pendente |

## Fase 1 — o que muda e o que NÃO muda

- `index()` deixa de devolver `cms::page.index` e passa a `Inertia::render('Admin/Content/Index')`
  com `tipo`, `contagens` (eager, uma consulta agrupada) e `paginas` (`Inertia::defer`).
- **Ordem:** `priority IS NULL, priority ASC` — vazio no fim (R6). O Blade usava `orderBy('priority')`
  cru, que no MySQL põe NULL **primeiro**. Mudança deliberada, travada pelo UC-CMS-01.
- **Tipo fora do domínio** (`?type=banner`) cai em `page`: a aba nunca mostra enum cru (A1).
- **Excluir** chama o `destroy` existente por `fetch` com `X-Requested-With` — ele só aceita ajax
  e devolve JSON, então `router.delete` do Inertia quebraria. O botão some em página de sistema;
  a recusa **no servidor** é a fase 3.
- **Criar/Editar** continuam Blade (links diretos). Nada de TinyMCE novo.
- **Tier 0:** `cms_pages` não tem `business_id` (single-tenant por natureza, SCOPE.md). A rota
  segue `superadmin` + `throttle:60,1`; nenhuma prop sai sem passar por ela.

## Verificação

- Contrato: `Modules/Cms/Tests/Feature/CmsConteudoIndexContratoTest.php`, rodando na lane MySQL
  `verticais-pest.yml` (o módulo entrou no trigger e no `paths-filter` junto, senão a lane pula).
- Smoke pós-deploy (R1): `https://oimpresso.com/cms/cms-page?type=page` logado como superadmin →
  a lista carrega (não fica no esqueleto) e as três abas trocam.

## Fase 2 — o que muda e o que NÃO muda

- **Criar e editar abrem um drawer lateral** na própria lista (PT-02). Editar pede o prop
  `editando` por partial reload (`?editar=id`) — `Inertia::optional`, nunca calculado na carga.
- **R7 no servidor.** As Blades nunca tiveram campo de `meta_description` (o JS delas lia um
  `textarea` inexistente), então tudo que o painel criou nasceu sem descrição. Agora `store`/`update`
  derivam os 160 primeiros caracteres do conteúdo em texto puro — sem o corpo de `<script>`/`<style>`,
  que `strip_tags` sozinho deixaria passar.
- **Update sem a chave `meta_description` preserva a gravada** (UC-CMS-23). `only()` omite chave
  ausente; derivar por cima apagaria meta digitada à mão — é o caminho da Blade de edição, que segue viva.
- **Rótulos por tipo** (R2: depoimento = nome/depoimento/foto) e por layout (R5: `home`/`contact` = Descrição).
- **Aviso de endereço** ao mudar o título de página livre (A2) — o 404 do link antigo é real (R9).
- **Não entrou:** prévia computador/celular do F1, blocos da home (2b), ações em lote, arrasto de ordem.
  O corpo é `textarea` de HTML — TinyMCE só com decisão [W] (F1 §6).

## Fase 2b — destaques da home (caminho A)

- **Achado que mudou o plano:** o painel gravava `feature`/`industry` como JSON, mas a home Inertia
  (`FeatureGrid.tsx`) procurava chaves `feature_N_title` que **ninguém grava** — o site sempre caiu no
  texto fixo do componente, e editar no painel não mudava nada em oimpresso.com. `industry` não aparece
  na home nova. [W] escolheu ligar a home ao painel (A).
- **Produção medida (2026-09-23, leitura):** o `feature` da home (página 3) é o seed do UltimatePOS
  de 2022-10-20, em inglês, nunca editado. A migration `2026_09_23_180000_…` troca **só esse seed**
  pelo texto que o site já mostra (cópia literal do fallback — conferida item a item: 8/8 iguais,
  título e texto da seção iguais). Conteúdo editado fica intocado. Site idêntico antes→depois.
- `FeatureGrid` lê o registro `feature` (título, texto, itens com título); cai no fallback se não houver.
  Ícone em classe FontAwesome do seed vira ✨ em vez de aparecer como texto.
- **Efeito colateral declarado:** a home antiga `/old` (Blade) desenha o ícone como `<i class>`
  FontAwesome — com emoji ela perde os ícones dos destaques. É o fallback legado; morre na fase 5.

## Fase 3 — a recusa mora no servidor

- `destroy` recusa página com `layout` preenchido (home/contact) com **422** e `success:false`, e loga
  `cms.page.delete_recusado`. A tela já escondia o botão; a rota aceitava qualquer chamador.
- A lista mostra a mensagem do servidor quando a exclusão é recusada (o `fetch` lia o JSON só em 2xx).
- De carona, no mesmo método: a mensagem de erro genérico era a string literal
  `'__("messages.something_went_wrong")'` e ia crua para a tela.

## Fase 4a — detalhes do site (4 de 8 seções)

- **Bug de produção achado antes de escrever a tela:** o `StoreCmsSettingsRequest` (desde 2026-05-16)
  validava `contact_us`, `mail_us`, `follow_us`, `statistics` e `faqs` como **string**, mas o formulário
  sempre mandou **listas** (`contact_us[0][num]`…). Salvar os detalhes do site falhava por inteiro.
  Ninguém viu porque ninguém salvou: em produção, **nenhuma chave foi gravada desde 2022** (medido
  2026-09-23). As regras viraram `array` com limites por item.
- A gravação é **por chave** (`createOrUpdateSiteDetails` = `updateOrCreate` por `site_key`), então a
  tela nova envia só as chaves dela e não apaga as seções que ficaram na Blade. Travado no UC-CMSD-02.
- `index()` renderiza Inertia; `?legado=1` devolve a Blade até a 4b.
- De carona no mesmo controller: `catch (Exception $e)` sem barra não casava nada no namespace do
  módulo — erro no save deixava a transação aberta e sem log.
- **Não entrou:** Estatísticas, Perguntas frequentes, Chat, Botões (4b); validação de telefone BR (S5).
