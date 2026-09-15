---
id: resources-js-pages-cms-content-index-casos
casos: Conteúdo do site (páginas · blog · depoimentos) · /cms/cms-page
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — página de sistema não poder ser excluída, e endereço sair do título, não muda no refactor.
owner: wagner
last_run: "—"
---

# Casos de Uso & Aceite — Conteúdo do site

> Onda de tradução Blade → Inertia (o público já é Inertia; o painel não). Teste-âncora proposto: `Modules/Cms/Tests/Feature/CmsPainelAdminTest.php` (criado nesta onda).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-CMS-01 · Abrir o índice de conteúdo (Inertia, não o Blade velho)
- **Persona:** Wagner — vai revisar o site antes de uma campanha; abre o conteúdo e vê tudo com situação e ordem.
- **Aceite:** Dado sessão de superadmin · Quando `GET /cms/cms-page?type=page` · Então renderiza Inertia **`Cms/Content/Index`** com `pages[]` ordenado por `priority` asc e contadores por tipo no cabeçalho.
- **Teste:** `CmsPainelAdminTest::test_index_renderiza_inertia_cms_content_index`
- **Regressão que defende:** cair no Blade `cms::page.index` em silêncio.
- **Status: ⬜**

## UC-CMS-02 · Usuário comum não entra
- **Aceite:** Dado usuário autenticado sem `superadmin` · Quando `GET /cms/cms-page` · Então 403 e nenhuma prop de conteúdo no corpo.
- **Teste:** `CmsPainelAdminTest::test_usuario_sem_superadmin_recebe_403`
- **Status: ⬜**

## UC-CMS-03 · Visitante sem sessão
- **Aceite:** Quando `GET /cms/cms-page` sem sessão · Então 401/redirect de login (nunca 200).
- **Teste:** `CmsPainelAdminTest::test_visitante_sem_sessao_nao_acessa_painel`
- **Status: ⬜**

## UC-CMS-04 · Criar sem título é recusado
- **Persona:** Larissa — clica em salvar antes de escrever o título.
- **Aceite:** Quando `POST /cms/cms-page` sem `title` · Então erro de validação em `title`, nada gravado em `cms_pages`.
- **Teste:** `CmsPainelAdminTest::test_store_sem_titulo_falha_validacao`
- **Status: ⬜**

## UC-CMS-05 · Descrição de busca vazia é preenchida do conteúdo
- **Aceite:** Dado `meta_description` vazia · Quando salvo com conteúdo HTML · Então grava os **160 primeiros caracteres em texto puro** (sem tags).
- **Teste:** `CmsPainelAdminTest::test_meta_description_vazia_recebe_160_chars_do_conteudo`
- **Regressão que defende:** hoje isso é JS de página; na tradução some se ninguém trouxer pro servidor.
- **Status: ⬜**

## UC-CMS-06 · HTML colado com script é declarado e removido
- **Persona:** Wagner — cola um trecho vindo de outro site.
- **Aceite:** Dado conteúdo com `<script>` e `onclick=` · Quando abro o editor · Então a tela lista o que será removido; Quando o público renderiza · Então nem `<script>` nem `onclick` aparecem no HTML final.
- **Teste:** `CmsPainelAdminTest::test_conteudo_publico_nao_carrega_script_nem_evento`
- **Status: ⬜**

## UC-CMS-07 · Mudar o título quebra o link antigo — e a tela avisa antes
- **Aceite:** Dado página publicada em `/c/page/sobre-nos` · Quando renomeio para "Sobre a empresa" e salvo · Então `/c/page/sobre-a-empresa` responde 200 e `/c/page/sobre-nos` responde **404**; a interface avisa isso antes de salvar.
- **Teste:** `CmsPainelAdminTest::test_renomear_pagina_muda_endereco_e_antigo_da_404`
- **Status: ⬜**

## UC-CMS-08 · Blocos da home só na home
- **Aceite:** Dado página com `layout=home` · Quando abro o detalhe · Então aparecem os blocos `feature` e `industry` com seus itens (ícone/título/descrição); Dado página livre (`layout` nulo) · Então nenhum bloco aparece.
- **Teste:** `CmsPainelAdminTest::test_edit_home_expoe_page_meta_feature_e_industry`
- **Status: ⬜**

## UC-CMS-09 · Página de sistema não pode ser excluída
- **Aceite:** Dado `layout=contact` · Quando `DELETE /cms/cms-page/{id}?type=page` · Então a exclusão é recusada e o registro continua existindo (a interface também não oferece o botão).
- **Teste:** `CmsPainelAdminTest::test_destroy_recusa_pagina_de_sistema`
- **Regressão que defende:** hoje o bloqueio é só visual (`@if(empty($page->layout))`) — a rota aceita.
- **Status: ⬜**

## UC-CMS-10 · Excluir página livre apaga registro, arquivo e deixa rastro
- **Aceite:** Quando excluo página livre com imagem de destaque · Então o registro sai, o arquivo sai do disco e o log `cms.page.deleted` é gravado.
- **Teste:** `CmsPainelAdminTest::test_destroy_pagina_livre_remove_registro`
- **Status: ⬜**

## UC-CMS-11 · Trocar imagem de destaque não deixa órfão
- **Aceite:** Dado página com imagem A · Quando envio a imagem B · Então A deixa de existir no disco e o registro aponta pra B.
- **Teste:** `[BACKLOG]` — exige teste de upload com disco fake.
- **Status: ⬜**

## UC-CMS-12 · Reordenar por arrasto
- **Persona:** Wagner — quer "Planos" antes de "Sobre".
- **Aceite:** Quando arrasto a linha · Então `priority` grava a nova sequência e o site respeita; Dado filtro ou busca ativos · Então o arrasto é bloqueado com o motivo à vista.
- **Teste:** `[BACKLOG]` — exige lane de teste de interação (Playwright).
- **Status: ⬜**

## UC-CMS-13 · Ação em lote respeita página de sistema
- **Aceite:** Dado 3 selecionadas, uma delas de sistema · Quando aciono "Excluir" em lote · Então nenhuma de sistema é excluída e a tela diz por quê.
- **Teste:** `[BACKLOG]` — depende do endpoint de lote (não existe hoje).
- **Status: ⬜**

## UC-CMS-14 · Ambiente demo não grava
- **Aceite:** Dado `APP_DEMO` ligado · Quando salvo qualquer conteúdo · Então bloqueio com mensagem clara e nada gravado.
- **Teste:** `CmsPainelAdminTest::test_modo_demo_bloqueia_escrita`
- **Status: ⬜**

## UC-CMS-15 · Formulário público gera lead e notifica
- **Persona:** visitante do site.
- **Aceite:** Dado `notifiable_email` configurado · Quando `POST /c/submit-contact-form` com nome, e-mail e mensagem · Então 200 e a notificação sai; Dado `notifiable_email` vazio · Então a submissão não falha, mas o lead fica marcado como **não notificado**.
- **Teste:** `CmsPainelAdminTest::test_contato_publico_notifica_quando_ha_email_configurado`
- **Status: ⬜**

## UC-CMS-16 · Bot que preenche o campo-armadilha cai fora
- **Aceite:** Quando `POST /c/submit-contact-form` com `_gotcha` preenchido · Então a submissão é rejeitada, sem notificação e sem lead.
- **Teste:** `CmsPainelAdminTest::test_honeypot_preenchido_rejeita_submissao`
- **Status: ⬜**

## UC-CMS-17 · Excesso de requisições por IP
- **Aceite:** Quando a 61ª requisição no mesmo minuto chega do mesmo IP · Então 429.
- **Teste:** `[BACKLOG]` — exige harness de throttle (limpar `RateLimiter` entre casos).
- **Status: ⬜**

## UC-CMS-18 · `cms:health` acusa site vazio
- **Aceite:** Dado nenhuma página com `is_enabled=1` · Quando `php artisan cms:health --notify` · Então exit 1 e `cms:health ALERT` no log; com `--detail`, uma linha por check.
- **Teste:** `CmsPainelAdminTest::test_cms_health_falha_sem_pagina_publicada`
- **Status: ⬜**

## UC-CMS-19 · Os três tipos do domínio podem ser salvos
- **Persona:** Wagner — escreve um post e depois cadastra um depoimento.
- **Aceite:** Quando salvo com `type` em `page`, `blog` e `testimonial` · Então os três gravam; nenhum cai em "Tipo inválido".
- **Teste:** `CmsPainelAdminTest::test_store_aceita_os_tres_tipos_do_dominio`
- **Regressão que defende:** 🔴 o `main` valida `in:page,post,banner` (`StoreCmsPageRequest:31`, `UpdateCmsPageRequest:37`) — blog e depoimento **são recusados hoje**, e `post`/`banner` não são lidos por nenhuma consulta do módulo. Ver pedido §3.b.
- **Status: ❌** — reprova de propósito até a PR 3.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Paginação do blog** — não existe endpoint paginado no `index()` (hoje `get()` sem limite).
- **[BACKLOG] Persistência de lead** — `CmsLeadService` só notifica e loga; sem tabela não há o que assertar. **Decisão pendente de [W]:** `cms_leads` no Cms ou lead direto no `Modules/Crm`?
- **[BACKLOG] Importador WP pela interface** — o comando tem `--dry-run`; falta a ponte HTTP.

## Rastreabilidade (UC → regra do charter)

| UC | Regra |
|---|---|
| UC-CMS-01 | R1 · R6 |
| UC-CMS-02/03 | R13 |
| UC-CMS-04/05 | R7 |
| UC-CMS-06 | R8 |
| UC-CMS-07 | R9 |
| UC-CMS-08 | R4 |
| UC-CMS-09 | R3 |
| UC-CMS-10/11 | R10 · R11 |
| UC-CMS-12/13 | R6 · R3 |
| UC-CMS-14 | R12 |
| UC-CMS-15/16/17 | R13 |
| UC-CMS-18 | — (observabilidade) |
| UC-CMS-19 | R1 (divergência vigente) |

## Como rodar a suíte
1. **Pest/PHPUnit:** `docker exec oimpresso-staging php artisan test --filter=CmsPainelAdminTest` no CT100 (nunca local/Hostinger).
2. **Manifesto:** `npm run casos:results` → 🧪 vira ✅.
3. **Cadência:** rodar ao fim de toda mexida em `CmsPageController` ou em `Cms/Content/*`.

## Trilha do tempo
- 2026-08-19 · [CC] criado junto do F1 (`prototipo-ui/cowork/cms/cms-page.jsx`). Fonte lida: espelho local do módulo `Modules/Cms`, **não** o `main` — [CL] revalida no git antes de aplicar.
