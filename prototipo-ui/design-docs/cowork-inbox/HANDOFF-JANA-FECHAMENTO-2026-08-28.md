# HANDOFF — fechar o módulo Jana (pacote único, zero-toque)

**De:** [CC] Cowork · **Para:** [CL] Claude Code · **Data:** 2026-08-28
**Lido no `main` NESTE turno** (árvore `7f4ce3675e19`, 17:03Z): `resources/js/Pages/Jana/**` (26 arquivos, com tamanho) · `Modules/Jana/Http/routes.php` (26.043 B, íntegro) · `prototipo-ui/contrato/**` (15 arquivos) · `prototipo-ui/cowork/**` filtrado por `jana` (10 arquivos).
**Não verificado neste turno** (marcado ⬜ onde aparece): conteúdo dos `.tsx`, `permissions.php`, workflows, scorecards, testes. Onde este pacote afirma algo sobre eles, a fonte é doc anterior — está citada.

**Este arquivo substitui** como plano de execução: `JANA-REFAZER-ZERO-TOQUE-2026-08-26` (§5 ordem de PRs), `JANA-ERRATA-CAMADA-ESQUECIDA-2026-08-27` (§O que muda no plano) e a §4 do `JANA-CAMADAS-TELAS-NOVAS-2026-08-27`. Os três continuam válidos como **fonte de detalhe** (mapa componente→arquivo, contrato de dados, campos dos FormRequests) — não os reescrevi aqui.

---

## 0. Duas coisas que mudaram desde os pacotes anteriores

**(a) O drift do espelho acabou.** Medido hoje, UTF-8, os 10 arquivos Jana de `prototipo-ui/cowork/` são **byte-idênticos** entre o `main` e este projeto:

| Arquivo | Bytes | Arquivo | Bytes |
| --- | --- | --- | --- |
| `jana-merge.jsx` | 59.985 | `chat-jana.css` | 25.952 |
| `jana-merge.css` | 22.089 | `jana-pro.jsx` | 8.222 |
| `chat-jana.jsx` | 33.321 | `jana-pro.css` | 7.516 |
| `jana-metas.jsx` | 24.187 | `jana-telas-novas.jsx` | 35.454 |
| `jana-metas.css` | 3.087 | `jana-telas-novas.css` | 4.004 |

Ou seja: **o PR-1 "trazer o espelho e resolver a âncora" da errata está resolvido** — não há nada a puxar nem a exportar. A fonte de desenho está no git, disponível ao Code.

**(b) As telas novas já desceram.** `jana-telas-novas.jsx/.css` (Alertas · Ações · Plataforma) estão no `main`. O que falta nelas é **porte pra Inertia+DS**, não desenho.

---

## 1. O que ainda falta, medido hoje

| # | Pendência | Prova (leitura de hoje) |
| --- | --- | --- |
| 1 | `Pages/Jana/components/` (pasta minúscula) ainda existe, em paralelo a `_components/` | `components/FabJana.tsx` (972 B) + `components/JanaAreaHeader.tsx` (9.292 B) |
| 2 | `AssistantUiChat.tsx` sem decisão | 16.112 B em `_components/` — chat paralelo ao `Chat.tsx` (25.449 B) |
| 3 | `JanaCockpit.tsx` acima do teto de 1.000 linhas | 45.358 B — a regra dura nº 7 do pacote de 26/08 |
| 4 | 3 dos 4 contratos de tela não existem | `contrato/` tem **só** `jana-painel.contract.json` (9.410 B). Sem `jana-chat`, `jana-memoria`, `jana-pro` |
| 5 | Alertas · Ações · Plataforma sem contrato e sem Page | rotas existem (`jana.alertas.index/config/config.update`, `jana.acoes.previa/aprovar`, `jana.superadmin.metas`, `jana.install.*`); nenhuma Page Inertia correspondente em `Pages/Jana/` |
| 6 | A fila `/ia/acoes` não existe como rota | o `routes.php` declara, literal: *"O DISPARO (WhatsApp/e-mail) e a fila `/ia/acoes` são PR próprio — por isso o CTA diz 'Revisar'"* |
| 7 | Persistência da config de alertas | `AlertasController@updateConfig` existe; que ela **descarta** o payload é achado de 27/08 (US-COPI-061) ⬜ não reconferido hoje |
| 8 | 8 UCs de permissão sem teste | `JANA-CASOS-EMENDA-PERMISSAO-2026-08-27` — todos ⬜, e o G-2 exige o teste no mesmo PR do UC |
| 9 | PR-0 de nome ("Copiloto" vivo) | escopo já corrigido em 27/08: `lang/pt/copiloto.php`, `menus/topnav.php`, 8 views `copiloto::…`, botão do `Cliente/Index.tsx` — **não** `permissions.php` |
| 10 | 13 `style={{}}` no `Pro.tsx` | patch pronto em `JANA-PRO-SEM-INLINE-2026-08-26` (só o bloco do card de prova + 9 linhas de `cockpit.css`). `Pro.tsx` hoje = 23.223 B ⬜ inline não reconferido |

---

## 2. Bloqueios — não começam sem resposta

**B1 · P0 de tenancy (bloqueia a tela Plataforma).** `SuperadminController::metas` confere `can('jana.superadmin')` **e** roda `withoutGlobalScope(ScopeByBusiness)`; o próprio `routes.php` registra que `Gate::before` devolve `true` em qualquer ability pra quem tem `Admin#{business_id}` — logo dona de empresa passa no gate. É ADR 0093 Tier 0, decisão de tenancy, não de desenho. **Nenhuma fachada nova em `/ia/superadmin/metas` antes disso.**

**B2 · Os 3 `_pendente_w` do `jana-painel.contract.json`** (título "Jana — Dashboard" vs aba "Painel" · botão "Exportar relatório (em breve)" · config de brief/áudio/retenção que o back não honra). Sem eles o Painel não fecha o contrato.

**B3 · Charter/casos: emendar, não reescrever.** [W] pediu "reescrever junto"; [CL] e [CC] recomendam **emendar** — os `casos.md` da Jana somam ~116 KB só no `main` de hoje (`Chat.casos.md` 49.993 · `Index.casos.md` 42.732 · `Memoria.casos.md` 13.803 · `Pro.casos.md` 8.314) e os UCs são citados de fora (contrato de tela + scorecards). Reescrever zera referência cruzada. Se [W] mantiver "reescrever", que seja por escrito, ciente do custo.

---

## 3. Ordem de execução (numeração única — não abrir outra)

| PR | Escopo | Entra junto | Gates |
| --- | --- | --- | --- |
| **J-0** | Nome: `lang/pt/copiloto.php` → `jana.php`, `topnav.php`, 8 views `copiloto::…`, label do botão em `Cliente/Index.tsx` + `_drawer/IATab.tsx`. **Chaves `jana.*` e route names intocados** (#4853 é a cicatriz) | UC-JNAME-01 + `JanaPermissionGroupNomeTest` | lint · testes que casem a string `Copiloto` |
| **J-1** | Faxina: mata `Pages/Jana/components/` (move `FabJana` + `JanaAreaHeader` pra `_components/`); decide `AssistantUiChat` com `rg` provando zero import; `JanaCockpit.tsx` quebrado por bloco (brief · kpis · análises · ações), ≤1000 L cada | — | lint · typecheck · 4 rotas abrindo |
| **J-2** | Permissão como camada: os 8 UCs de `JANA-CASOS-EMENDA-PERMISSAO` **com** seus Pest (`IaPermissaoGrupoTest`, `MetasPermissaoTest`, `CustosVazamentoTest`, `ConversaAcessoTest`, `MemoriaPermissaoTest`, `ProPreviewPermissaoTest`) + as props `podeConversar` / `podeGerenciarMetas` no payload | emenda nos 4 `casos.md` | Pest do módulo · `JanaAccessGateTest` |
| **J-3** | Painel (`Index.tsx` + seção Metas + `JanaCockpitSkeleton` + `JanaSubNav`) alinhado ao protótipo; **estende** `jana-painel.contract.json`, não sobrescreve as 5 seções / 7 strings pinadas | resolve B2 | `PainelContratoTest` · visreg do Painel · `prototipo-readiness` |
| **J-4** | `Chat.tsx` + `ConverseComJana` + propostas; cria `contrato/jana-chat.contract.json` | — | `jana-chat-conversas.test.tsx` (não quebrar) · visreg |
| **J-5** | `Memoria.tsx` — **dono é `Modules\KB`** (`MemoriaController`, `FontesController`), fronteira do ADR 0366 vale; cria `contrato/jana-memoria.contract.json` | — | visreg · `MemoriaPermissaoTest` |
| **J-6** | `Pro.tsx`: aplica o patch de 26/08 (13 → 0 inline, `.ilha-dark*` em `cockpit.css` reusando `--sb-*`); cria `contrato/jana-pro.contract.json` | regravar baseline visreg de `jana/pro` (mudança de hue declarada) | `ds/no-inline-raw-color` 4→0 · `ProContractTest` · `jana-pro-voltar.test.tsx` |
| **J-7** | Alertas (`/ia/alertas` + config) em Inertia+DS a partir de `jana-telas-novas.jsx`; `contrato/jana-alertas.contract.json`. Se a US-COPI-061 não entrar junto, a tela nasce **com** o aviso de que a rota não grava | — | contrato de tela · visreg |
| **J-8** | Fila `/ia/acoes` (rota nova + Page), reusando `AcaoHitlService`; prévia continua vindo do servidor; nada promete disparo | 5 chaves/rótulos byte-idênticos | contrato de tela |
| **J-9** | Plataforma (`/ia/superadmin/metas` + `/ia/install`) — **só depois de B1** | — | `MultiTenantIsolationTest` |

Gates a rodar em **qualquer** PR: `cowork-ssot-guard` · `prototipo-readiness` · `cowork-paridade --check` · `JanaViewsSemAndaimeTest` · `CockpitMockRemovidoTest`.

---

## 4. Leis que valem em todo PR (resumo — canon nos docs citados)

1. **Rota é `/ia`**, nunca `/jana`. Route names `jana.*` preservados. Cabeçalhos que dizem `tela: /copiloto` são ponteiro podre — corrigir ao tocar o arquivo.
2. **PT-BR** em label, placeholder, erro e empty-state. Enum de banco só no `title`.
3. **Zero `style={{…}}`** e zero cor crua. Token do DS ou classe.
4. **Ícone = `lucide-react`.** `JcIcon` do protótipo morre no handoff (mapa nome→Lucide por ícone usado).
5. **`AppShellV2` + `PageHeader`**, um `<main>` por documento (AP9), chain de overflow (AP10). **Sidebar preta nos dois modos** (UI-0023) — não "corrigir".
6. **Nenhum arquivo acima de 1.000 linhas.**
7. **`data-contract` por bloco** e `data-screen-label` por tela, com os valores do protótipo.
8. **Frota não existe** (morta por [W] em 2026-08-07). Se aparecer, é regressão.
9. **Estado vazio / carregando / erro** em toda tela; cada `EmptyState` diz **por que** e **o que fazer**.
10. **Os 6 `Analise*Service` citados no `jana-merge.jsx` não existem no repo** — a ressalva do contrato do Painel vale: as regras **visuais** do protótipo mandam; o que ele diz sobre **fonte de dado**, não.
11. **`farol` é veredito do servidor** (`ApuracaoService::farol`) — nunca calculado no front.
12. **Aba própria de Metas não vai pro vivo** — no protótipo é Tweak; o canon é seção do Painel.

## 5. Se algo aqui estiver ambíguo

O Code **para e devolve a pergunta**. Improvisar é a causa declarada do "sempre quando tento falta algo" ([W], 2026-08-26) — e, nas duas rodadas anteriores, a falha estava no pedido (rota errada, camada de permissão ausente), não no executor.
