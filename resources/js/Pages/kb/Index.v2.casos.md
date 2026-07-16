---
casos: SOPs / KB Unificado V2 (tri-pane) · EM FREEZER (rotas removidas 2026-07-16)
irmaos: Index.v2.charter.md (lei) · Index.charter.md (V3 atual, coexiste)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
status_tela: freezer ([W] 2026-07-16 — /kb/v2 · /sops · /kb/graph desroteadas; código preservado; re-rotear = 1 commit)
last_run: "2026-07-16"
---

# Casos de uso — kb/Index.v2 (SOPs · tri-pane) — **EM FREEZER**

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde CT100) · ⬜ não verificado · ❌ quebrou.

> ## 🧊 Esta tela está no FREEZER desde 2026-07-16 ([W])
>
> **As rotas foram removidas** — `/kb/v2` · `/sops` · `/kb/graph` · `/kb/graph/data` devolvem **404**. O **código está preservado** (`Index.v2.tsx` 609 ln · `_components/` · `_lib/` · schema `kb_*` · 6 controllers · `KbBridgeFromMcpJob`). **Não é arquivamento**, e o destino da V2 segue **em aberto** (promover / manter / arquivar = Tier 0 [W]).
>
> **Por quê:** rodava em prod com auth real servindo dado **fictício** — o `MOCK_NODES` é uma KB de **gráfica** (Roland VS-540 / HP Latex) num tenant que é **loja de vestuário** (biz=4, ROTA LIVRE), e 4 ações afirmavam sucesso sem persistir nada (`toast.success` sem nenhuma chamada de rede). Zero link na UI apontava pra cá — o acesso era digitar a URL. [W] 2026-07-16: *"difícil de decidir, pois estão em obras"* — obra fica atrás do tapume, não aberta ao público.
>
> **Gatilho pra reabrir** (veredito adversarial 2026-07-16): `SELECT COUNT(*) FROM kb_nodes WHERE is_editable = 1` **> 0** fora do seed = existe humano criando SOP = há demanda real (ADR 0105). Aí re-rotear é 1 commit — mas pra valer o caminho é o **Controller real** servindo `kb_nodes`, não a closure mock.
>
> **O que sobrou aqui:** [[UC-KBV2-07]] · [[UC-KBV2-08]] são contratos do **código preservado** (hooks — testados isoladamente em `tests/kbIndexV2Client.spec.tsx`, seguem verdes no freezer e protegem o trabalho pra quando/se voltar). [[UC-KBV2-09]] é dívida de código medida (68 cores cruas). O contrato do freezer em si é [[UC-KBV2-11]].
>
> **Pendente, atrás do freezer:** o de-risk dos 4 `toast.success` mentirosos ([PR #4365](https://github.com/wagnerra23/oimpresso.com/pull/4365), draft) segue **bloqueado** — tocar o `.tsx` exige contrato visreg, e o freezer **não** muda isso (o classificador vê o path, não a rota). Ficou sem urgência: fora do ar, ninguém alcança aqueles toasts. Se a tela voltar, o de-risk entra junto — e aí ela terá contrato visreg de qualquer forma.
>
> **O que saiu:** os **UC-KBV2-01..06** (contrato da *rota viva*: auth · render · read-only · sem Jobs/IA · Tier 0 · fallback mock) foram **removidos** — perderam objeto quando a rota saiu do ar. Mantê-los citados sem testar nada seria cobertura-fantasma. Dois deles estavam podres e a revisão adversarial provou: o **UC-05** (Tier 0) passava *por construção* (sem prop `nodes`, biz=99 nunca aparecia — passaria mesmo sem multi-tenant), e o **UC-06** assertava `missing('nodes')`, ou seja **o contrato proibia a promoção** (o Controller real deixaria um teste required vermelho por sucesso). Histórico completo no cabeçalho de `KbIndexV2ContractTest.php`.

> Derivados do charter `Index.v2.charter.md` (Goals + Automation Anti-hooks) + protótipo Cowork `kb-page.jsx`. Persona declarada: Wagner / governança (1440px) + Larissa balcão (1280px) — **atenção**: a persona do corpus mock (operadora de gráfica) **não existe** no cliente real (ADR 0265 §5 da Oficina reencenada; ver veredito 2026-07-16).

## UC-KBV2-07 — Persistência client-side é localStorage prefixado
Status: 🧪 (kbIndexV2Client.spec.tsx — prefixo + sobrevive remount + zero sessionStorage; aguarda run verde CT100)
Favoritos, recentes e categorias expandidas persistem via `localStorage` prefixado
`oimpresso.kb.*` (nunca `sessionStorage`). Anti-pattern do charter: `sessionStorage`.
**Pronto quando:** favoritar um SOP + reload mantém o favorito; as chaves gravadas são prefixadas `oimpresso.kb.` e `sessionStorage` fica intocado.

> **Errata 2026-07-16 (medido, não suposto):** este UC afirmava prefixo `oimpresso.kb.v2.*`.
> As chaves REAIS são `oimpresso.kb.favs.v1` · `oimpresso.kb.recent.v1` · `oimpresso.kb.paths.v1`
> (`_lib/useKbFavorites.ts:12` · `useKbRecent.ts:9` · `useKbPathProgress.ts:14`) — **sem** o `v2`.
> O `v2` era afirmação nunca verificada (o UC nascera ⬜). Corrigido o PERDEDOR (este casos.md),
> conforme a regra de precedência (proibicoes.md §Precedência: teste > casos > charter > SPEC).
> O contrato travado é o **prefixo** `oimpresso.kb.`, não a chave exata (o sufixo é versionamento
> interno; travá-lo engessaria sem proteger nada).
>
> **Auto-errata da errata (mesmo dia, achada por revisão adversarial):** a 1ª versão desta nota
> justificou a chave com *"compartilhar a chave com a V3 é o que faz o favorito sobreviver ao
> cutover"*. Isso é **FALSO por medição**: `grep -ci fav resources/js/Pages/kb/Index.tsx` = **0** —
> a V3 **não tem favorito nenhum** pra compartilhar chave (`useKbFavorites` só é importado por
> `Index.v2.tsx`). Era racional plausível inventado sem medir, exatamente o que a lápide §5 de
> 2026-07-15 proíbe (achado/justificativa por leitura, sem varredura). O prefixo segue certo; o
> **motivo** que eu dei estava errado.

> ⚠️ **DECISÃO DE ARQUITETURA PENDENTE — este UC NÃO é contrato estável (2026-07-16):**
> existe favorito **server-side REAL e não usado**: `routes.php:100` → `KbFavoriteController@toggle`,
> que grava `kb_favorites` com `business_id` (`:48`) — cross-device e por tenant. O próprio docblock
> do hook (`useKbFavorites.ts:6-8`) declara o localStorage como **temporário**: *"quando user tiver
> permission kb.favorite + cloud sync, trocar pra POST /kb/nodes/{slug}/favorite"*.
> Logo o teste que defende este UC trava em contrato uma decisão **da era-mock** (favorito é
> device-local) contra a implementação real que já existe no servidor — é a lápide §5 de 2026-06-05
> (teste derivado do código, tautológico) na camada de UC. **Não use este UC como argumento pra não
> migrar pro favorito server-side.** Quando [W] decidir o destino da V2 (promover/manter/arquivar),
> este UC é reescrito junto: device-local vs `kb_favorites` é decisão de produto, não de teste.

## UC-KBV2-08 — ⌘K/Esc (teste) + tri-pane a 1280px sem scroll (manual)
Status: 🧪 (kbIndexV2Client.spec.tsx — ⌘K/Ctrl+K abre paleta, Esc fecha, "/" foca busca; aguarda run verde CT100)
A 1280px o layout tri-pane (sidebar + lista + leitor) não gera scroll horizontal; `⌘K` (ou `/`)
abre o CommandPalette; `Esc` fecha o leitor. 0 erros no console. Âncora: charter UX Targets
(1280px sem scroll horizontal, 0 erros JS) + Goal 5 (CommandPalette ⌘K).
**Pronto quando:** ⌘K/Ctrl+K abre a paleta e Esc fecha (automatizado); screenshot 1280px sem barra horizontal + console limpo (manual — ver limite abaixo).

> **Limite honesto (2026-07-16):** o teste cobre a metade COMPORTAMENTAL (atalhos), incluindo os
> controles-negativos que importam: `k` sem modificador NÃO abre a paleta, e atalho de letra não
> dispara enquanto se digita num input. A metade VISUAL (1280px sem scroll horizontal · console
> limpo) é irredutível em jsdom — que não tem layout engine — e segue **manual/browser**. Dividido
> em vez de fingir: um teste que "provasse" 1280px em jsdom seria teatro.

## UC-KBV2-09 — Tokens semânticos, zero cor crua (visual/manual · anti-regressão DS)
Status: ❌ (medido 2026-07-16 — a tela VIOLA: 68 ocorrências de cor crua absorvidas no baseline do `ui:lint`)
Diferente da V3 (`kb/Index`, que usa `bg-blue-100` + emojis), a V2 usa só tokens semânticos
Cockpit V2 (`text-primary`, `text-muted-foreground`, `border-border`) e ícones lucide — nenhum
`bg-(blue|red|green)-N` cru, nenhum emoji-como-ícone. Âncora: charter UX Anti-patterns "Cor crua
hardcoded sem semantic token".
**Pronto quando:** `php artisan ui:lint --path=resources/js/Pages/kb` reporta 0 violações R1 (cor crua) e R3 (emoji) nos arquivos da V2 — hoje reporta 68 R1.

> **Veredito 2026-07-16 (o ⬜ escondia um ❌):** a afirmação "diferente da V3, a V2 usa só tokens"
> é FALSA. Medido em `config/ui-lint-baseline.json`: **68 violações R1** absorvidas nos componentes
> da V2 — `NodeReader` 22 · `BlockRenderer` 18 · `NodeList` 8 · `HealthPanel` 8 ·
> `TroubleshooterDialog` 8 · `KbFavStar` 2 · `CategorySidebar` 1 · `PathsDialog` 1 (incl. o
> `bg-blue-100` que o UC cita como sendo "o defeito da V3"). Enquanto ninguém verificava, o ⬜
> parecia dívida de verificação; era dívida de código.
>
> **Dono do contrato = mecanismo que JÁ existe:** `ui:lint` R1 (cor crua) + R3 (emoji), ratchet
> **required** no CI (`UI Lint ratchet vs baseline (LEI)`, `app/Console/Commands/UiLintCommand.php`).
> Ele já impede PIORAR — as 68 estão fotografadas no baseline. Um teste novo medindo cor crua aqui
> seria régua paralela ao juiz consolidado (proibicoes.md §5, entrada 2026-07-09 "gate redundante
> com régua consolidada") — por isso este UC **NÃO** ganhou teste próprio e segue fora do G-2, de
> propósito e declarado.
>
> **Por que não corrigi agora:** trocar 68 cores cruas por token é mudança VISUAL na tela — exige
> charter + gate visual (ADR 0114, pendente nesta tela desde 2026-05-16) e decisão de design de [W],
> não escolha de agente. Fechar este UC = fechar o gate visual da V2, que é a mesma decisão pendente
> registrada no rodapé (promover / manter flag / arquivar).

## UC-KBV2-11 — As rotas do par mock NÃO respondem (contrato do freezer)
Status: 🧪 (KbIndexV2ContractTest — 3 testes: nomes de rota · 404 · V3 intacta; aguarda run verde CT100)
Quem digitar `/kb/v2`, `/sops`, `/kb/graph` ou `/kb/graph/data` recebe **404** — a tela não
serve mais SOP inventado a ninguém. As rotas nomeadas (`kb.v2`, `sops.index`, `kb.graph.page`,
`kb.graph.data`) não existem, então `route()` falha alto se alguém tentar linkar. E a **V3
(`/kb`, dado REAL)** segue viva — o freezer tirou o par mock do ar, não o KB.
**Pronto quando:** `Route::has()` é falso pros 4 nomes; GET nos 4 caminhos devolve 404; `Route::has('kb.index')` é verdadeiro e `/kb` não devolve 404.

> **Este UC é a catraca do freezer.** Se alguém re-rotear sem decisão [W], ele fica **vermelho**
> e obriga a conversa. Reabrir é legítimo — mas é ato consciente, com este contrato mudando
> junto. O 3º teste é **controle-negativo**: prova que o freezer pegou o par mock e **não** o KB
> inteiro (sem ele, deletar o módulo todo passaria verde).

---

> **Decisão pendente (metabolismo — reportada ao parent MV batch 2026-07-06):**
> `Index.v2` é **viva mas incompleta**: roteada por 2 caminhos com auth real, porém sem
> Controller (`indexV2`) e sem backend — roda mock-only, `status: draft`, gate visual ADR 0114
> nunca fechado (desde 2026-05-16). Três saídas possíveis (Wagner decide): (a) **promover** —
> Agent A implementa `KbController@indexV2` + cutover /kb → V2; (b) **manter feature-flag** de
> gate visual (status quo, mas então fechar o gate de screenshot); (c) **arquivar** V2 se a V3
> (`kb/Index`, nota 78) for a direção mantida. Enquanto indefinido, estes UCs blindam o piso de
> segurança da rota viva (auth + read-only + sem side-effects + Tier 0).
