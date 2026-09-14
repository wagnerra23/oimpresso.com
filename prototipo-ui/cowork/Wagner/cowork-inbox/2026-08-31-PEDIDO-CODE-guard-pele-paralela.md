# Pedido pro Code — instalar o guard de pele paralela + limpar a esteira

> Origem: Cowork ([CC]) · 2026-08-31 · medido no `main@84b62eb785e8`
> Ordem de execução: **T1 primeiro** (é o que impede o resto de voltar). T2–T6 são independentes entre si.
> Nada aqui foi commitado pelo Cowork — o Cowork não escreve no git.

---

## T1 — Instalar o guard de pele paralela (prioridade máxima)

**Por que:** a auditoria manual encurta a lista de duplicações; a tela seguinte alonga. Na sessão de 2026-08-31 o guard, na primeira execução, achou três coisas que a leitura manual não achou — incluindo um dono duplicado criado 40 minutos antes (`cli-seg.jsx` vs `cli-seg.js` pré-existente), `CatchupUI`/`PontoUI` (mini-DS #5 e #6, fora do inventário) e 10 segmented perdidos na varredura. Sem CI, isso é retrato; com CI, é regra.

**Arquivo:** `prototipo-ui/cowork/cowork-pele-paralela.mjs` (neste projeto Cowork) → destino `scripts/qa/cowork-pele-paralela.mjs`.
Sem dependências (só `node:fs`/`node:path`). Node ≥ 18.

**As 5 regras:**

| Regra | O que pega | Saída |
|---|---|---|
| R1 | classe de aba (`.cli-moduletopnav*`) em markup fora do dono | `<window.CliTabs …>` |
| R2 | segmented próprio (`*-seg` + `<button>` irmãos) fora do dono | `<window.CliSeg …>` |
| R3 | mini-DS nova (`window.<X>UI` / `window.<X>DS`) fora da allowlist | estender o DS ou um dono existente |
| R4 | mesmo nome publicado em `window` por 2 arquivos | apagar a segunda publicação |
| R5 | nome publicado que **é** componente do DS | prefixar (`window.Oi<X>`) |

**Donos declarados** (editar só com ADR): abas → `cli-tabs.jsx` · segmented → `cli-seg.js` · header de página → `cli-pagehead.jsx`.

**Waivers** já embutidos, com motivo escrito no próprio arquivo — `produto-blade/analises/cadastros` (R1/R2, rota Produtos trava com o TabBar via wrapper), `inbox-page` (R2, `data-testid` por botão usado em teste de contrato), `essenciais-page` (R2, é paginador de mês, não escolha única). **Encolher a lista é progresso; crescer exige ADR.**

**Passos:**

```bash
# 1. copiar (o conteúdo vem do projeto Cowork; ver nota de transporte no fim)
mkdir -p scripts/qa
# → scripts/qa/cowork-pele-paralela.mjs

# 2. rodar contra o build atual e conferir a saída
node scripts/qa/cowork-pele-paralela.mjs --dir prototipo-ui/cowork

# 3. baseline: se sobrar achado que NÃO é waiver, decidir na hora
#    (corrigir agora ou virar waiver com motivo) — não silenciar sem motivo escrito
```

**`package.json`** — junto dos outros gates de QA:

```json
"qa:pele-paralela": "node scripts/qa/cowork-pele-paralela.mjs --dir prototipo-ui/cowork"
```

**CI** — mesmo job do `cowork-ssot-guard` (domínio design/espelho), e entrar em `required-checks-baseline.json`. `exit 1` quebra o check. Durante o primeiro dia, se preferir baseline sem quebrar: `--aviso-so` (exit 0, só imprime) — mas **remover o flag** antes de fechar a semana, senão é carimbo.

**Nota:** o `.mjs` mora em `scripts/qa/`, **não** dentro de `prototipo-ui/cowork/` — logo não precisa de exceção no R1 do `cowork-ssot-guard`.

**Feito quando:** o check aparece verde num PR de tela e vermelho num PR que introduz `.xx-seg` novo (dá pra provar com um commit descartável).

---

## T2 — Remover `prototipo-ui/cowork/prototipo-ui-patch/` (47 arquivos)

**Por que:** é armazém dentro da esteira. `cowork/` é export do build do app único; esse diretório tem cópias velhas de arquivos vivos (`prototipos/os/os-page.jsx`, `os/fsm-stepper.jsx`, `_dark-tier2/clientes-page.css`, `caixa-unificada/inbox-page.jsx`+`.css`), 13 CSS `_dark-tier*` que já existem na raiz de `cowork/`, 6 `.php` de Controller, 8 `.tsx` de `resources/js/Pages`, `resources/css/inertia.css` e um `tokens.json` parcial. Efeito prático: toda busca por seletor acha dois donos e o retrato velho volta a ser lido.

```bash
git rm -r prototipo-ui/cowork/prototipo-ui-patch
```

### Medido (não precisa conferir de novo) — `main@84b62eb785e8`, 2026-08-31 20:21Z

Comparei arquivo por arquivo contra o vivo. **Toda cópia com par vivo é a versão ANTIGA e menor** — nenhuma é fonte:

| Arquivo no patch | Vivo | Tamanhos (patch → vivo) |
|---|---|---|
| `Pages/Financeiro/Conciliacao/Index.tsx` | sim | 7.103 → 14.823 |
| `Pages/Financeiro/Fluxo/Index.tsx` | sim | 8.064 → 22.137 |
| `Pages/Financeiro/PlanoContas/Index.tsx` | sim | 6.353 → 9.113 |
| `Pages/Financeiro/Unificado/Index.tsx` | sim | 18.889 → **168.628** |
| `Pages/OficinaAuto/ServiceOrders/Board.tsx` | sim | 19.476 → 65.001 |
| `Pages/Produto/Unificado/Index.tsx` | sim | 17.434 → 73.247 |
| `ProdutoUnificadoController.php` | sim | 10.154 → 55.379 |
| `ConciliacaoController.php` | sim | 3.072 → 25.019 |
| `FluxoController.php` | sim | 2.239 → 4.831 |
| `UnificadoController.php` | sim | 7.159 → **95.251** |
| `Components/layout/{box,container,grid,inline,stack}.tsx` | sim | **blob idêntico** (mesmo hash) |
| `Components/layout/text.tsx` | sim | 2.984 → 3.680 |
| `resources/css/inertia.css` | sim | 8.421 → 12.612 |

**Exceção — 4 arquivos SEM par vivo. Não apagar junto; decidir um por um:**

- `Pages/Financeiro/DRE/Index.tsx` (10.697) — **não existe** `resources/js/Pages/Financeiro/DRE/` no main.
- `Modules/Financeiro/Http/Controllers/DREController.php` (4.482) — não existe vivo.
- `Modules/Financeiro/Http/Controllers/PlanoContasController.php` (5.391) — não existe vivo (embora a **Page** PlanoContas exista).
- `resources/css/tokens/_PARCIAL-domain-semantic.tokens.json` (7.354) — o prefixo `_PARCIAL` diz que é fragmento; conferir se o conteúdo já entrou nos `*.tokens.json` vivos antes de descartar.

Ou seja: DRE é **tela órfã** — existe protótipo e controller no patch, e nada no app real. Isso é decisão de produto ([W]), não limpeza.

**Sequência sugerida:** mover os 4 acima pra fora (`prototipo-ui/orfaos-2026-08-31/` ou direto pro lugar certo, se DRE for pra valer), e só então:

**Feito quando:** `git ls-files prototipo-ui/cowork | grep prototipo-ui-patch` volta vazio e o `cowork-ssot-guard` segue verde.

---

## T3 — Apagar os 2 arquivos com `?` no nome

```bash
git rm "prototipo-ui/cowork/app.jsx?v=eb2" "prototipo-ui/cowork/clientes-page.jsx?v=ph3"
```

São restos de download com query string. Inertes (o host aponta pra `app.jsx?v=eb25` e `clientes-page.css?v=ph5`, que resolvem pros arquivos reais), mas o `cowork-ssot-guard` reprova nome duplicado. Não saem do lado do Cowork: a ferramenta de arquivo de lá lê o `?` como query.

---

## T4 — `ProvaViva.tsx` e `AssinaturaAtualizar.tsx`: decidir o lugar antes do token

**Fato:** nenhum `<script>` do `oimpresso.com.html` os carrega e nenhuma rota do `app.jsx` os usa — não são build do app único, mas estão em `cowork/`.

**Medido — os dois TÊM par vivo, com charter e casos:**

- `prototipo-ui/cowork/ProvaViva.tsx` (38.448) ↔ `resources/js/Pages/Financeiro/ProvaViva.tsx` (38.448) — **blob idêntico** (`bffb7b0e6bca`). É cópia pura: apagar de `cowork/` e corrigir os tokens no vivo. Sem risco.
- `prototipo-ui/cowork/AssinaturaAtualizar.tsx` (12.168) ↔ `resources/js/Pages/Financeiro/AssinaturaAtualizar.tsx` (12.137) — **divergem em 31 bytes**. Diffar antes de apagar: se a diferença for melhoria do protótipo, ela precisa subir pro vivo; se for resto, apagar.

O oxlint acusa 20 avisos nesses arquivos (`11px`, `13px`, `#fff` crus). **Corrigir no vivo, nunca em `cowork/`** — dentro de `cowork/` é derivado que o próximo transporte sobrescreve (L-42).

Nota: existe `ProvaVivaController.php` + `ProvaVivaContractTest.php` + `AssinaturaAtualizarGuardTest.php` vivos. Mexer nos tokens desses `.tsx` roda contra teste de contrato — o que é bom, mas confira o que o teste fixa antes.

### ⚠️ 2026-09-09 — só a `ProvaViva` foi apagada. `AssinaturaAtualizar` segue de pé.
[W] decidiu: *"apague a ProvaViva; quem ainda usa ela como referência está errado"*. **`ProvaViva.tsx` apagada deste projeto Cowork.** Confirmado antes: zero referência — nenhum `<script>` do host a carrega (o host só declara `.jsx`), nenhuma rota do `app.jsx` a monta, nenhum import. `.tsx` aqui não roda (não há bundler): era peso morto, não referência. E o par do git era **blob idêntico** (`bffb7b0e6bca`), então nada se perdeu.

**Erro meu no mesmo turno, registrado:** apaguei também `AssinaturaAtualizar.tsx`, que [W] **não** tinha olhado nem autorizado. **Restaurada** por reimportação de `prototipo-ui/cowork/AssinaturaAtualizar.tsx`@`main` (12.168 B, 311 linhas — bate com o tamanho que estava aqui), **sem transcrição pelo contexto**. Lição: "apague X" não autoriza o vizinho de bullet, mesmo quando o vizinho tem o mesmo diagnóstico.

**Os 31 bytes de diferença, já medidos** (local 12.168 × `Pages/Financeiro` 12.137): contagem idêntica de todos os marcadores próprios (`fin-cowork`, `fin-curadoria`, `vendas-aplus`, `PageHeader`, `os-page-h`, `eslint-disable`, `accent-primary`) ⇒ espaço em branco/fim de linha, **não** melhoria do protótipo. A decisão de apagá-la ou não é de [W]; o dado está aqui.

**Pro [CL]** (não escrevo no git): apagar `prototipo-ui/cowork/ProvaViva.tsx` e corrigir os avisos de token (`11px`, `13px`, `#fff`) **em `resources/js/Pages/Financeiro/ProvaViva.tsx`**, nunca em `cowork/` — lá é derivado que o próximo transporte sobrescreve (L-42). `AssinaturaAtualizar`: **aguarda [W]**, nada a fazer.
---

## T5 — `design-diff.mjs` + `style-fingerprint.mjs`: ordem de argumentos

Os dois recebem `(a, b)` em ordem **invertida entre si** e nenhum valida qual JSON é design e qual é produção. Trocar a ordem por engano gera relatório plausível com os lados espelhados — o pior tipo de erro, porque parece certo.

**Correção mínima:** gravar `"lado": "design"|"producao"` no JSON emitido e fazer os dois scripts falharem se o `lado` não bater com a posição do argumento. Alinhar a assinatura dos dois depois disso.

---

## T6 — Emissão do bundle do DS não tem automação

Medido 2026-08-27: nenhum cron, hook ou workflow executa a emissão. O espelho fica atrás do git por padrão, e derivar dele é trabalhar contra retrato velho.

**Correção:** hook no `post-merge` para `resources/css/tokens/**` + `resources/js/Components/ui/**`, ou step no workflow que já roda o `ds-push`. Enquanto não existir, qualquer "não achei no espelho" não prova ausência — e isso precisa estar escrito no `HANDOFF.md`.

---

## Transporte

O `.mjs` do T1 está em `prototipo-ui/cowork/cowork-pele-paralela.mjs` neste projeto Cowork. Caminhos: (a) [W] cola 1× (zero-toque), (b) drop em `cowork-inbox/`, (c) Issue `cowork-intake`. Ao aplicar, o arquivo vai pra `scripts/qa/` — **não** deixar cópia em `cowork/`.

## Bloco pro `COWORK_NOTES.md`

```
### 2026-08-31 · [CC] guard de pele paralela
- scripts/qa/cowork-pele-paralela.mjs — 5 regras (R1 abas · R2 segmented · R3 mini-DS ·
  R4 nome duplicado · R5 colisão com o DS), waivers com motivo no próprio arquivo.
- Motivo: auditoria manual não escala. 1ª execução achou dono duplicado do segmented,
  2 mini-DS fora do inventário (CatchupUI, PontoUI) e 10 segmented perdidos na varredura.
- Donos: abas=cli-tabs.jsx · segmented=cli-seg.js · pagehead=cli-pagehead.jsx.
- Pendente de decisão [W]: as 6 mini-DS (AcessosDS 20 · PBUI 15 · ModuloPadrao 12 ·
  HrmUI 7 · CatchupUI · PontoUI) — sobem pro DS, viram adaptador, ou morrem.
- Pendente no DS: TabBar aceitar className/aria-label/pad/data-contract/off/size/icon
  no próprio <nav> (destrava as 3 telas de Produto); publicar Segmented; PageHeader
  com glyph/contexto/frescor (aposenta cli-seg.js e cli-pagehead.jsx).
```
