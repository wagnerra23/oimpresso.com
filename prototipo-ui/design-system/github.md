# github.md

repo: wagnerra23/oimpresso.com
branch: main
path: (repo inteiro — foco em resources/css, resources/js, memory/, prototipo-ui/, scripts/)

## Last sync

date: 2026-09-17
commit: (nao registrado — o PR #7463 acabou de subir CI; sha do merge desconhecido aqui)

### Updated in this project

- **`github.md` do repo alinhado ao vivo — PR #7463, 28 insercoes / 0 delecoes.** O arquivo no repo
  tinha a errata do lado de la mas nao a secao deste lado; vivo e espelho divergiam. Com o #7462 ja
  mergeado nao havia mais risco de conflito, e o hash do arquivo commitado bate exatamente com o do
  vivo: `c8fc2d89946e8697`. Saiu de uma branch nova criada de `origin/main` — a anterior ficou
  `atras=2 frente=1` porque o #7462 foi merge squash, logo o commit local nao e o do `main`.
- **Correcao de medicao (sonda do baseline).** A segunda sonda usou `.files.length` e devolveu
  `undefined` — `files` e objeto, nao array. Remedido: **251 entradas**, que confirma o numero dito
  antes; a sonda, nao o numero, estava errada.
- **`ibm-plex-sans-{500,600,700}.woff2` revertidos no PR #7462**, mantendo os 8 tokens. Ver ERRATA
  abaixo — os tres pesos nao existem de verdade em nenhum dos lados.

### Sync anterior · 2026-09-16T22:50:00Z · commit 5c55e4f96f1 (merge do PR #7456)

- **`colors_and_type.css` — push git->espelho dos 8 tokens que estavam divergentes.** O git avancou 2x
  (v1.2.0 em 02/09, v1.3.0 em 08/09) e o espelho ficou no valor pre-conserto; o `ds-mirror-drift`
  acusava 8 vs baseline 0 em 15 runs seguidas, todas `success` por ser advisory. Valores agora iguais
  ao canon: `--color-success-foreground` e `--color-warning-foreground` (light e dark) em
  `oklch(0.20 0.02 h)`; `--accent-soft`/`--pos`/`--neg`/`--warn` do `.cockpit[data-theme="dark"]`.
  Efeito visivel: pilulas de status ganham tinta escura sobre chip solido (conserto de contraste do
  `StatusBadge`). Medicao de a11y anterior a esta data foi feita contra tokens velhos.
- **3 `@font-face` corrigidas de carona.** Os pesos 500/600/700 do IBM Plex Sans apontavam para
  `ibm-plex-sans-400.woff2` na copia do repo. O scaffold do push foi o arquivo do handoff 22 (leitura
  do vivo), entao o vivo manteve os pesos certos e o repo recebeu o conserto.
  - ⚠️ **ERRATA 2026-09-17 — esta bullet e FALSA e fica registrada, nao apagada.** Nao houve conserto:
    (a) no espelho do repo os arquivos `ibm-plex-sans-{500,600,700}.woff2` **nao existem** —
    `git ls-files assets/fonts/` devolve 4 (mono-400/500/600 + sans-400), so isso; logo as 3 linhas
    que eu escrevi apontam pra arquivos AUSENTES e o preview servido deste diretorio (ADR 0401 E2)
    perde a fonte e cai no fallback do `font-family`. (b) No Cowork os 3 arquivos existem, mas sao
    **copias byte-identicas do 400** (mesmo sha256, 45.712 B) — medido pela sessao irma no PR #7461
    ao recusar o handoff DS por R4. Ou seja: o DS nao tem esses pesos, e **3 pesos caindo no arquivo
    400 era a forma CORRETA**, feia e funcional. Revertido no PR #7462, que mantem os 8 tokens.
    Os 3 arquivos NAO foram trazidos de proposito: seriam duplicata que o R4 proibe — a
    desduplicacao e da origem, ja pedida no #7461. Causa do erro: reconheci o padrao
    `peso 500 -> arquivo 400` como bug e nao conferi se o alvo existia.
- `cockpit_domains.css` reescrito sem diferenca de conteudo (write do mesmo par).
- Verificacao: `ds-push` VALOR:0 vs canon · `write_files` written:2 · releitura com 8 de 8 valores
  presentes · `ds-mirror-drift` drift 0 baseline 0 nos 4 escopos.

## Achados do Code — pendentes neste projeto

### 2026-09-17 · o handoff de hoje não pôde ser importado

Detalhe completo, com as medições: [`CODE_NOTES-2026-09-17-import-recusado-r4.md`](CODE_NOTES-2026-09-17-import-recusado-r4.md).

- **Delta do pacote = 2 arquivos**, e os dois são os pushes do Code de ontem (#7456, #7457) voltando
  de carona. Nenhum trabalho de design novo entrou neste ZIP — se houve ciclo depois do push, vale
  reexportar.
- **Import recusado por R4** (zero duplicata de bytes): 7× `ds-base.js`, 6× `support.js`,
  4× `ibm-plex-sans-*.woff2` e 2× `Norte - Fluxo do Caminhão.html`, todos byte-idênticos. Aplicar
  deixa o espelho fiel (251/251) **e** reprova o gate — as duas coisas não fecham juntas. A
  desduplicação é daqui: uma cópia única de `support.js`/`ds-base.js` num lugar comum resolve, e
  os `.dc.html` passam a apontar pra ela.
  - **Status 2026-09-17:** confirmado como trabalho **deste lado** (Cowork); o pedido esta neste
    projeto com as medicoes. **FEITO nesta rodada** — ver `Desduplicacao R4` abaixo.
- **Sobre os pesos 500/600/700** — a ERRATA acima (do lado do repo) e esta medição (do lado daqui)
  são a mesma história vista das duas pontas: no repo os 3 `.woff2` **não existem**; aqui eles
  existem mas são **cópias byte-idênticas do 400** (mesmo sha256, 45.712 B). Some os dois e o DS
  não tem esses pesos em lugar nenhum. O conserto de verdade é baixar os `.woff2` reais dos três
  pesos; enquanto isso não acontece, apontar os três ao 400 é feio e honesto.
  - **Status 2026-09-17:** os `.woff2` reais dos três pesos também são trabalho deste lado, e foram
    **resolvidos nesta rodada** — 4 arquivos distintos de `IBM/plex@78cd4223d8de`, medidos abaixo.
    O repo ainda tem só o subset do 400 e precisa receber os 4.

### 2026-09-17 · Desduplicacao R4 (feita neste projeto)

Os 4 pares do §2 do `CODE_NOTES` foram fechados deste lado:

| par | antes | agora |
| --- | --- | --- |
| `ds-base.js` `a1546261e159` | 7 cópias, uma por template | 1 única em `templates/_shared/ds-base.js`; os helmets usam `../_shared/ds-base.js`. O `base = '../..'` continua válido (resolve contra a página, que segue em `templates/<slug>/`). `oficina-auto` nem usava — carrega `_ds_bundle.js` direto; a cópia dele era morta. |
| `support.js` `e174915b9873` | 6 cópias | 1 única em `templates/_shared/support.js`; os 6 `<head>` usam `../_shared/support.js`. O `support.js` próprio do `oficina-auto` (`fab925b9a2ec`) ficou onde estava — legítimo. O da raiz (`acb671965eac`) é outro arquivo, não entra no par. |
| `ibm-plex-sans-{500,600,700}.woff2` | 4 cópias do mesmo byte | 4 arquivos **reais e distintos**, baixados de `IBM/plex@78cd4223d8de` (`packages/plex-sans/fonts/complete/woff2/`); as 3 `@font-face` apontam cada uma pro seu peso. |
| `Norte - Fluxo do Caminhão.html` | `Norte/` + `uploads/` | cópia de `uploads/` removida (byte-idêntica); `Norte/` é a canônica — é a pasta que o bundle referencia. |

- Verificação: `check_design_system` sem issues, 7 templates reconhecidos, 63 cards, 245 tokens;
  preview do `pt-01-lista` renderizando com os caminhos novos.
- ⚠️ **Cuidado de manutenção:** o `<script src="./support.js">` do `<head>` faz parte do wrapper que
  o `dc_write` monta. Um `dc_write` completo em qualquer um desses 6 templates volta a escrever
  `./support.js` e recria a cópia local — nesse caso, repontar pra `../_shared/support.js` e apagar
  a cópia. Edições incrementais (`dc_html_str_replace`/`dc_js_str_replace`) não mexem no wrapper.
- Falta só baixar os `.woff2` reais dos 3 pesos; a R4 em si já deve passar com o próximo export.

### 2026-09-17 · Pesos reais do IBM Plex Sans (fecha a §3 do CODE_NOTES)

Os 4 `.woff2` do Sans agora são arquivos distintos, vindos de `IBM/plex@78cd4223d8de`
(`packages/plex-sans/fonts/complete/woff2/`), com sha256 curto e bytes medidos:

| peso | arquivo de origem | bytes | sha256 |
| --- | --- | --- | --- |
| 400 | `IBMPlexSans-Regular.woff2` | 63.020 | `ba711a3085ff` |
| 500 | `IBMPlexSans-Medium.woff2` | 66.740 | `5660f8a658f8` |
| 600 | `IBMPlexSans-SemiBold.woff2` | 67.060 | `f78048030eab` |
| 700 | `IBMPlexSans-Bold.woff2` | 63.012 | `fa7130d854a6` |

- O `-400` **também foi trocado** de propósito: o anterior tinha 45.712 B (subset) e misturar
  subset com charset completo daria métricas e cobertura diferentes entre pesos. Os 4 saem do mesmo
  build `complete`. Custo: ~17 kB a mais no 400.
- Efeito visível: medium/semibold/bold deixam de renderizar como regular ou como negrito sintetizado
  pelo browser — o ritmo tipográfico do DS passa a ser o real.
- **O repo precisa receber estes 4 arquivos**: hoje o espelho tem só 4 fontes em `assets/fonts/`
  (mono-400/500/600 + sans-400) e o `-400` de lá é o subset velho. Com isso, as §2 e §3 do
  `CODE_NOTES-2026-09-17` estão fechadas deste lado — vale reexportar o handoff e rodar
  `handoff-changed --update` se o import fechar.
- **6 ponteiros podres**: a documentação daqui descreve a árvore do repo anterior ao #7224
  (`prototipo-ui/COWORK_NOTES.md`, `prototipo-ui/ds-guard.mjs`, `prototipo-ui/integrity-check.mjs`,
  `prototipo-ui/cowork/venda-v3/…`, `prototipo-ui/LICOES_F3_…`, `prototipo-ui/cowork/ds-v6`) —
  6 de 6 não existem mais no `main`. Os vivos (`SKILL.md`, `HANDOFF.md`, `ColumnManager.jsx`) são
  os que importam: eles instruem quem for trabalhar agora.

Nada foi promovido no repo: o espelho segue como estava e os gates passam.


## Sync history

### 2026-08-31T20:40:00Z · tree 84b62eb785e8

- **Reset dos documentos.** `HANDOFF.md` reescrito do zero contra `84b62eb785e8`: só o vigente, mapa separando 3 pares medidos de 40+ herdados, §5 "Não verificado" explícita. `README.md` limpo da arqueologia (errata de paths, `AppShell.tsx`, `shared/ponto/`, `ModuleTopNav`, `inertia.css` como SSOT). Versões antigas em `arquivo/`.
- `TabBar` — o `<nav>` virou o contrato: `...rest`, `className` somado, `ariaLabel`, `pad`, `size`, `off`, `icon`, `inset`. Wrapper removido dos 3 templates que usavam o padrão PT-01.
- `PageHeader` — `leading` (alinhado ao slot homônimo do canon, não caixa), `context`, `freshness`/`freshnessRel` reusando `StatusBadge kind="frescor"`.
- `HANDOFF-2026-08-31-tabbar-pageheader.md` novo — handoff da rodada, com auditoria do `main` (linha medida) e bloco de COWORK_NOTES.

### 2026-08-24T20:10:00Z · tree 29a59c1ce1d3

- `HANDOFF.md` novo — contrato de handoff zero-touch espelho→repo (regras duras, mapa componente→arquivo, DoD por máquina, bloco COWORK_NOTES).
- README: primary corrigido pra roxo no parágrafo de abertura (blue do shadcn legado é superseded).
- README: direção do loop corrigida — claude.ai/design é NÃO-fonte (ADR 0315/0299); push git→design via `ds-push.mjs`, design→git só com opt-in.
- README: adicionada a regra **sidebar PRETA dark-fixo** (UI-0023), AP7/AP9/AP10, e errata de paths do `main` (AppShellV2, sem `shared/ponto/`, sem `ModuleTopNav`).

## Screen map

| Tela / template daqui | Arquivos-fonte no repo |
| --- | --- |
| `templates/clientes-crm/` | `resources/js/Pages/Cliente/Index.tsx` + `Index.charter.md` + `_drawer/` + `_show/` |
| `templates/financeiro/` | `resources/js/Pages/Financeiro/Unificado/`, `Financeiro/ProvaViva.tsx` |
| `templates/oficina-auto/`, `templates/pt-07-os-detail/` | `resources/js/Pages/OficinaAuto/ServiceOrders/`, `OficinaAuto/Vehicles/` |
| `templates/pt-01-lista/` | padrão PT-01 — `Cliente/Index.tsx`, `Produto/Index.tsx`, `Sells/Index.tsx` |
| `templates/pt-05-dashboard/` | `resources/js/Pages/Home/Index.tsx`, `Pages/governance/Dashboard.tsx` |
| `templates/atendimento/` | sem par 1:1 no `main` (adjacentes: `Pages/Jana/Chat.tsx`, `Pages/Whatsapp/_components/`) |
| `components/*` (46 componentes) | `resources/js/Components/ui/*` (32), `resources/js/Components/shared/*` (16), `resources/js/Components/PageHeader/*` (canon v3.8), `resources/js/Layouts/AppShellV2.tsx` — mapa completo em `HANDOFF.md` §3 |
| `colors_and_type.css`, `cockpit_domains.css` | `resources/css/tokens/*.tokens.json` → `_generated-*.css`, `resources/css/cockpit.css`, `foundations.css` |
