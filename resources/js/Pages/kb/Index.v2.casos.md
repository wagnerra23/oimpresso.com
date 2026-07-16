---
casos: SOPs / KB Unificado V2 (tri-pane) · /kb/v2 + /sops
irmaos: Index.v2.charter.md (lei) · Index.charter.md (V3 atual, coexiste)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
status_tela: viva-gate-visual (roteada /kb/v2 + /sops; render mock-only — indexV2 backend pendente)
last_run: "2026-07-16"
---

# Casos de uso — /kb/v2 (SOPs · KB Unificado tri-pane)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde CT100) · ⬜ não verificado · ❌ quebrou.

> Derivados do charter `Index.v2.charter.md` (Goals + Automation Anti-hooks + "Métricas vivas (Pest GUARD)") + protótipo Cowork `kb-page.jsx`. Persona principal: Wagner / governança (1440px). Secundária: Larissa balcão (1280px).
>
> **Contexto de maturidade (âncora honesta):** a rota `/kb/v2` (`kb.v2`) e o alias `/sops` (`sops.index`) são **closures inline** que fazem `Inertia::render('kb/Index.v2')` **sem props** — o Controller `KbController@indexV2` do charter **nunca foi implementado**. Logo, em prod a tela roda 100% em **modo mock** (`usingMock = !props.nodes` → sempre `true`). Os UCs abaixo blindam o **contrato da rota viva** (auth, render, read-only, sem side-effects, Tier 0) — NÃO o contrato de dados backend, que fica pendente da ONDA 1. Não redesenham a tela.

## UC-KBV2-01 — Rota viva exige autenticação
Status: 🧪 (KbIndexV2ContractTest V1 — GET anônimo redireciona login)
Um visitante não autenticado que abre `/kb/v2` ou `/sops` é barrado pela stack middleware
canônica (`auth`) — nunca vê o conteúdo. Âncora: rotas KB registradas com middleware `['web',
'SetSessionData', 'auth', ...]`; ADR 0093 (nada de dado exposto sem sessão).
**Pronto quando:** GET anônimo em `/kb/v2` e `/sops` retorna redirect (302) OR 401/403 — nunca 200 nem 500.

## UC-KBV2-02 — Renderiza o componente Inertia kb/Index.v2
Status: 🧪 (KbIndexV2ContractTest V2 — component + rota nomeada)
Wagner autenticado (biz=1) abre `/kb/v2` e recebe a página Inertia `kb/Index.v2` (tri-pane
SOPs). O alias `/sops` renderiza o **mesmo** componente (coexistência /kb V3 · /kb/v2 gate ·
/sops atalho). Âncora: charter `component: kb/Index.v2.tsx` + rotas `kb.v2` / `sops.index`.
**Pronto quando:** `/kb/v2` responde 200 com `assertInertia(component == 'kb/Index.v2')`; `Route::has('kb.v2')` e `Route::has('sops.index')` são true; ambas resolvem pro mesmo componente.

## UC-KBV2-03 — GET é read-only (não muta estado)
Status: 🧪 (KbIndexV2ContractTest V3 — nenhuma escrita no render)
Abrir a tela é leitura pura: nada é escrito no banco no render (`reads_count++` só acontece no
endpoint `show`, nunca no `Inertia::render`). Âncora: charter Automation Anti-hook "NÃO escreve
no DB no render (read-only)".
**Pronto quando:** o count de linhas de `kb_nodes` (e de `kb_node_versions`) é idêntico antes e depois do GET `/kb/v2`.

## UC-KBV2-04 — Abrir a tela não dispara Jobs nem IA
Status: 🧪 (KbIndexV2ContractTest V4 — Queue::fake sem push)
Renderizar `/kb/v2` não enfileira nenhum Job e não chama Brain B/Sonnet — a IA RAG só roda na
ação explícita "Perguntar ao KB". Âncora: charter Anti-hooks "NÃO dispara Jobs ao abrir" +
"NÃO chama Brain B/Sonnet". Também cobre "NÃO envia emails/SMS/WhatsApp ao abrir".
**Pronto quando:** com `Queue::fake()`, GET `/kb/v2` resulta em `Queue::assertNothingPushed()`.

## UC-KBV2-05 — Tier 0: rota não vaza nós de outro business_id
Status: 🧪 (KbIndexV2ContractTest V5 — biz=1 vs biz=99 cross-tenant)
A rota nunca serve nós de outro tenant. Hoje o render é mock (sem props), então o piso a
provar é que a rota **não expõe** dados de `kb_nodes` de biz=99 quando aberta por um usuário
biz=1 (o mock não injeta dado real de tenant nenhum). Quando o Controller `indexV2` real chegar
(ONDA 1), este UC vira a asserção forte de `has('nodes')` scopado. Âncora: charter Anti-hook
"NÃO acessa nodes de outro business_id (ADR 0093)".
**Pronto quando:** com nó seedado em biz=99, o payload Inertia servido a um usuário biz=1 NÃO contém o slug/título desse nó (hoje: prop `nodes` ausente → vazia por construção; futuro: prop scopada por `business_id`).

## UC-KBV2-06 — Fallback mock declarado enquanto backend ausente
Status: 🧪 (KbIndexV2ContractTest V6 — render OK sem props)
Enquanto `KbController@indexV2` não existir, a tela renderiza com `MOCK_NODES` e o
`PageHeader.description` sinaliza "MOCK (Agent A pendente)" — sem 500. Âncora: charter Goal 7
"Fallback MOCK_NODES quando rotas backend ausentes" + `usingMock = !props.nodes`.
**Pronto quando:** GET `/kb/v2` autenticado responde 200 mesmo sem nenhuma prop passada pela closure (sem exceção de "prop undefined").

## UC-KBV2-07 — Persistência client-side é localStorage prefixado
Status: 🧪 (kbIndexV2Client.spec.tsx — prefixo + sobrevive remount + zero sessionStorage; aguarda run verde CT100)
Favoritos, recentes e categorias expandidas persistem via `localStorage` prefixado
`oimpresso.kb.*` (nunca `sessionStorage`). Anti-pattern do charter: `sessionStorage`.
**Pronto quando:** favoritar um SOP + reload mantém o favorito; as chaves gravadas são prefixadas `oimpresso.kb.` e `sessionStorage` fica intocado.

> **Errata 2026-07-16 (medido, não suposto):** este UC afirmava prefixo `oimpresso.kb.v2.*`.
> As chaves REAIS são `oimpresso.kb.favs.v1` · `oimpresso.kb.recent.v1` · `oimpresso.kb.paths.v1`
> (`_lib/useKbFavorites.ts:12` · `useKbRecent.ts:9` · `useKbPathProgress.ts:14`) — **sem** o `v2`.
> O `v2` era afirmação nunca verificada (o UC nascera ⬜). O código está CERTO e o texto errado:
> favorito é do KB, não da versão da tela — compartilhar a chave com a V3 é o que faz o favorito
> sobreviver ao cutover, e renomear a chave apagaria os favoritos já salvos de quem usa hoje.
> Corrigido o PERDEDOR (este casos.md), conforme a regra de precedência (proibicoes.md §Precedência:
> teste > casos > charter > SPEC). O contrato travado é o **prefixo** `oimpresso.kb.`, não a chave
> exata (o sufixo é versionamento interno; travá-lo engessaria sem proteger nada).

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

---

> **Decisão pendente (metabolismo — reportada ao parent MV batch 2026-07-06):**
> `Index.v2` é **viva mas incompleta**: roteada por 2 caminhos com auth real, porém sem
> Controller (`indexV2`) e sem backend — roda mock-only, `status: draft`, gate visual ADR 0114
> nunca fechado (desde 2026-05-16). Três saídas possíveis (Wagner decide): (a) **promover** —
> Agent A implementa `KbController@indexV2` + cutover /kb → V2; (b) **manter feature-flag** de
> gate visual (status quo, mas então fechar o gate de screenshot); (c) **arquivar** V2 se a V3
> (`kb/Index`, nota 78) for a direção mantida. Enquanto indefinido, estes UCs blindam o piso de
> segurança da rota viva (auth + read-only + sem side-effects + Tier 0).
