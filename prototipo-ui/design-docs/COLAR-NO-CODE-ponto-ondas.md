# Ponto — ponte do módulo · reescrita 2026-09-09 (2ª volta, com leitura do `main`)

> **Este arquivo é ponteiro, não pedido.** O canon do módulo é o playbook `prototipo-ui/design-docs/cowork-inbox/ponto/playbook/` (00-INDICE + 12 threads + `_saida-NN.md`; placar derivado por `placar-indice.mjs`). Aqui ficam só: o que o ciclo mudou no **build**, o que ainda **não** desceu, e a fila de [W].
> **Reexport com ancoragem dupla (2026-09-09):** threads **13 · 14 · 15** emitidas em `cowork-inbox/ponto/playbook/` + `_delta-indice-13a15.md` (patch do `00-INDICE`: 3 objetos no §7.threads, 2 no §7.decisoes, 3 linhas no §2). Elas dependem de `cowork-inbox/ds-atomos/playbook/` 01–03. As outras **17** Pages estão medidas na ficha do delta e **não** foram emitidas de propósito.
> Anti-scatter (§2-ter): este ciclo **não** emitiu `COLAR-NO-CODE-*` novo nem thread nova. Reescreveu este. **Zero pedido pro Code** — o motivo está em "Contagem" abaixo, e ele é o oposto do que eu supunha.

## Contagem — quantos arquivos, quantos blocos (a pergunta de [W])
**Zero de cada.** Medido no `main` neste turno (árvore `a0db7b0177b8`, 2026-09-09):

- **O Ponto de produção é React inteiro e JÁ compõe o DS.** 21 Pages `.tsx` + 21 charters em `resources/js/Pages/Ponto/` (Dashboard · Espelho Index/Show · Intercorrências Index/Create/Edit/Show · Aprovações · BancoHoras Index/Show · Colaboradores Index/Edit · Escalas Index/Form · Importações Index/Create/Show · Relatórios · Configurações Index/Reps · Welcome), 24 `Inertia::render`, 2 contratos **vigentes** (`ponto-espelho`, `ponto-painel`). Os imports são `@/Components/ui/{button,card,input,label,textarea,badge,alert,skeleton,switch}` + `@/Components/shared/{KpiGrid,KpiCard,StatusBadge,EmptyState,PageFilters,BulkActionBar}` + `PageHeaderPrimary` + `@/Components/layout`.
- Logo o pedido que eu ia orçar ("Ponto usa os primitivos do DS na produção") **não tem objeto**: já está feito, e está feito antes de mim.
- **O outlier era o meu protótipo**, não o módulo. As 4 ondas de DS deste ciclo são **PUXAR** (FRESCOR regra 1: 🔵 produção à frente), não direção nova.

Se algum dia houver pedido de seção, a forma **não** muda e é esta: **1 arquivo por thread** com **4 blocos** (A identidade/ancoragem dupla · B não inventar · C alvo medido · D como validar) + §4-ter + pré/pós §13.5; **1 índice** por módulo (≤8 KB de prosa, JSON embutido) — o Ponto **já tem**, com 12 threads; **1 `_saida-NN.md`** por thread. Os **10 blocos** (§4-quater `0`–`9`) são do **pacote/índice**, não de cada thread. Número de threads sai da **ficha de capacidade** (§13.2), nunca de palpite.

## Ciclo 2026-09-09 — DS vivo nos átomos do Ponto (saída ① build, 5 arquivos)
Antes: o módulo consumia do bundle **só `Drawer`/`DrawerSection`**; o resto era `pt-*` bespoke em `ponto-ui.jsx` + `ponto-page.css`. Quatro ondas, **sem arquivo novo, sem CSS novo, sem rota nova** — a API de `window.PontoUI` não mudou, então nenhuma tela mudou de chamada.

| onda | de (bespoke) | para (bundle do espelho) | n |
|---|---|---|---:|
| 1 | `Pill*` · `Card` · `Kpi` · `Nota` · `Vazio` · `Pager` · `Voltar` | `StatusBadge` · `Widget` · `KpiCard` · `Alert` · `EmptyState` · `Pagination` · `Button` | 7 átomos |
| 2 | `<button className="pt-btn">` | `Button` (ghost/primary/danger) + `Tooltip` no `title` | 62 |
| 3 | `.pt-fld` (label+input+small) | `Input` · `Select` · `Textarea` | 38 |
| 4 | `.pt-check` · `.pt-toolbar` | `Checkbox` · `Toolbar` | 8 + 7 |

**Decisões de forma tomadas no build** (não são pedido — §5-bis): rótulo de apuração/importação em **sentence case** (o CAIXA-ALTA mono era vício do AdminLTE) · `Card` manda contagem `(N)` pro **badge** do `Widget` e frase de apoio pro `note` (antes disputavam o `h3`, que trunca), e sangra `flush` quando o único filho é `Tabela` · `Vazio` com POR QUE (título por variante) + O QUE (descrição) · KPI-filtro pelo dicionário do `variant="filter"` — ver divergência W14 · `maxLength` e a trava "data não pode ser futura" viraram guarda de tela.

**Fallback declarado:** todo átomo mantém o caminho `pt-*` se `window.OfficeImpressoPontoWR2DesignSystem_019dd0` não existir (o bundle carrega `defer`) — degrada em vez de cair em branco.

**Destino (§14):** `prototipo-ui/cowork/` — `ponto-ui.jsx` · `ponto-page.jsx` · `ponto-telas.jsx` · `ponto-fechamento.jsx` · `ponto-mobile.jsx` + `oimpresso.com.html` (bump `?v=`). Sha/bytes saem por `MAPA EXPORT` no chat, **nunca aqui**.

## Mapa espelho → `main` (é isto que as threads 08/09 precisam, e substitui pedido)
Lido no turno. Nomes iguais NÃO são o mesmo componente — a coluna do meio é o bundle do espelho, a da direita é o arquivo real.

| meu átomo | bundle do espelho | arquivo real no `main` | casa? |
|---|---|---|---|
| `Btn` | `Button` | `Components/ui/button.tsx` | sim |
| `Campo`/`Escolha`/`Texto` | `Input`/`Select`/`Textarea` | `ui/input.tsx` · `ui/select.tsx` · `ui/textarea.tsx` (+ `ui/label.tsx`) | sim — produção faz `{...props}`, o espelho **não** |
| `Check` | `Checkbox` (booleano) | `ui/checkbox.tsx` (Radix, `onCheckedChange`) | adaptador é meu, não desce |
| `Card` | `Widget` | `ui/card.tsx` (`Card/CardHeader/CardTitle/CardContent`) | **não** — produção não tem `badge`/`note`/`flush` |
| `Kpi` | `KpiCard` (+`variant="filter"`) | `shared/KpiCard.tsx` — já tem `onClick`+`aria-pressed`+`selected`; tons `default/success/warning/danger/info` | **parcial** — ver W14 |
| `Pill*` | `StatusBadge` | `shared/StatusBadge.tsx` | conferir `kind` por domínio |
| `Vazio` | `EmptyState` | `shared/EmptyState.tsx` | sim |
| `Nota` | `Alert` | `ui/alert.tsx` (`Alert/AlertTitle/AlertDescription`) | forma difere |
| `Barra` | `Toolbar` | `shared/PageFilters.tsx` (chips ativos + grid de campos + "Limpar tudo") | **não** — produção é filtro com chips, não toolbar de 3 zonas |
| `Pager` | `Pagination` | — (produção pagina no servidor, `LengthAwarePaginator`) | conferir por tela |
| — | `BulkBar` | `shared/BulkActionBar.tsx` — `selectedCount`/`onClear`/**`children`** | ver W12 |
| abas | `TabBar` | `_shared/PontoSubNav.tsx` → `shared/PageHeaderTabs`, `maxVisible={5}` + `⋯ Mais`, ADR 0182 | ver W9 |

## Retratações — duas, do que eu afirmei neste mesmo ciclo
1. **"lacuna do `Input` do DS" não existe no `main`.** `ui/input.tsx` e `ui/textarea.tsx` fazem `{...props}` — `min`/`max`/`step`/`maxLength`/`accept` passam em produção. O fechado é o **componente compilado do espelho** (lista de props sem spread). Zero pedido pro Code; as guardas de tela existem por limitação minha.
2. **"não existe `PontoSubNav`" era verdade só do meu build.** Existe em produção há tempo (`_shared/PontoSubNav.tsx`, 2.296 B, ADR 0182): lê `shell.menu` via `LegacyMenuAdapter`, renderiza tablist ARIA, **5 ghosts visíveis + `⋯ Mais`** + primary "Bater ponto", hue 295. Eu respondi pelo protótipo e falei como se fosse do módulo — erro de escopo, e é exatamente o que o `main` responde e o protótipo não.

## Colisão de prefixo — declarada, não escondida
As reescritas de call-site caíram em arquivos reservados por Lei 1: `ponto-page.jsx`/`ponto-telas.jsx` (threads **08**, **09**) e `ponto-mobile.jsx` + bump do host (thread **10**). O ciclo foi cross-cutting no átomo compartilhado (`ponto-ui.jsx`), o que arrasta call-site por construção — quem abrir 08/09/10 **remede antes de escrever** (o `base:` delas é `e86130722de1`; os 5 arquivos mudaram depois).

**Thread 10 continua aberta e violada:** `ponto-mobile.jsx` ainda tem selfie (`:38` estado · `:72-76` botão/copy · `:184` coluna "Selfie (hash)" · `:261` nota · `LIMITES.selfie_min_kb`) contra a **ADR 0383**. A onda de DS trocou 6 botões e nada de domínio. Enquanto a 10 não roda, o REP-P não vira pedido (thread 06).

## O que o doc de 04/09 já tinha errado (preservado — evidência)
Frente 4 (6 `casos.md`) **já estava feita** (21/21) · testes 16 → 44, nº de UC **não** remedido · RESÍDUO 6 (copy da selfie, "LGPD Art. 9º") morto desde 27/08 pela ADR 0383 (base é Art. 5º II + Art. 11) · RESÍDUO 5 (GPS ruim) também respondido (accuracy > 500 m recusa; geofence sinaliza) · API REP-P tem **7** rotas `abort(501)`, e `Api/MobileMarcacaoController.php` existe **sem rota** · pedidos de 23/08 já executados; `cowork-inbox/ponto-dashboard/Index.casos.md` é resíduo (thread 11).

## RESÍDUO Ponto — fila [W] (canônica em `00-INDICE.md §6`)
W1–W4 fechamento (travam 04·05) · W7 ordem AFD/AFDT/AEJ (12) · W8 `/ponto/react` fica? · W10 ratificar REP-P sem selfie (06). ~~W5~~ ~~W6~~ respondidas pela ADR 0383. **~~W12~~ morre com a leitura de hoje:** `BulkActionBar` aceita `children`, então o campo "motivo do lote" cabe dentro dela — quem tem de mudar é o meu `.pt-bulk`, não o DS.
**W9 (afiada, virou divergência medida):** meu protótipo tem **13 abas planas**; produção tem **5 + `⋯ Mais`** com a fonte no `shell.menu`. Ou o protótipo adota o teto de 5 + overflow, ou [W] declara que o Ponto passa a 13 ghosts no menu legado. **É correção de build (minha), não pedido** — e cai na thread 08/09.
**W11 (mantida):** as 19 `Tabela`+`usePagina` migram pro `DataGrid` do bundle? Produção pagina no **servidor** (`LengthAwarePaginator`), então isto é decisão do protótipo, 1 tela por PR.
**W13 (mantida):** `ponto-mobile.jsx` é simulação de aparelho — migra pro DS ou fica com CSS próprio?
**W14 (nova, é a mais séria):** o `KpiCard` do bundle tem `variant="filter"` com paleta `primary/amber/rose/emerald/violet` (o look Clientes/CRM); o `shared/KpiCard.tsx` de produção tem `tone default/success/warning/danger/info` e **nenhum** `variant`. Meu build usa o primeiro. Qual vocabulário é o canon do KPI-filtro? Enquanto não houver resposta, é **divergência declarada** — não regredir a produção pelo meu lado, nem o contrário.

## Placar (render 2026-09-06, não remedido neste turno)
`Ponto: entregue 0 de 12 · próximo 5 · pendente 4 · bloqueada 3` — **PRÓXIMO: 01 · 02 · 08 · 10 · 11.** `_saida-01.md` e `_saida-02.md` já existem no `main` (9,5 KB · 12,5 KB) e são **posteriores** a esse render: o placar acima está velho e **não** foi rerodado daqui. Quem abrir thread roda `PLACAR Ponto`.

## Ciclo fechado SEM pacote (bloqueio do canal)
Os 5 arquivos estão abaixo do piso de ~48 KB do `DesignSync.get_file`, logo não descem pela rota avulsa — exigem o pacote. **Não regenerei** (`gerar-payload-partes.mjs` quer os arquivos em disco):
```
node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
```
Depois: subir `sync/bundle.manifest.json` + as partes e escrever no `github.md` `bundle regenerado (2026-09-09 · N arquivos)` (ADR 0387). Sem isso o ciclo está fechado no Cowork e **ausente no `main`** — e quem cobra é o `cowork-mirror-freshness.mjs --absent-local`.
