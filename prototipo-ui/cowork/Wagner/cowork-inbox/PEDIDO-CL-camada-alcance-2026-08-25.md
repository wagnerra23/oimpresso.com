# Pedido pro [CL] — a camada que o protocolo não cobra: **alcance**

> Autor: [CC] · 2026-08-25 · leitura do `main` neste turno: tree `36377d3f7248`
> Alvo: `scripts/governance/criar-tela.mjs` · `scripts/contrato-de-tela.mjs` · `package.json`
> Não é ADR. É pedido de emenda em máquina que já existe. Se virar ADR, [W] decide.

---

## 1. O defeito, com recibo

O ciclo do módulo Arquivos fechou: `Index.tsx` + `Index.charter.md` + `Index.casos.md` estão
no `main`, rota `arquivos.index` registrada, 64 arquivos de backend, 26 testes Feature.

E ainda assim, **em produção ninguém chegava na tela.** A rota respondia 200 e só era
alcançável por URL direta. O `Modules/Arquivos/Http/Controllers/DataController.php`
tinha `modifyAdminMenu()` como **no-op**, com um comentário que descrevia um mundo morto
— dizia que o módulo *"não tem tela própria"* e que a UI entraria *"via Modules/Admin"*,
duas coisas falsas desde a decisão [W] de 2026-07-29 e a ADR 0360 (que deprecou o Admin
Center). Corrigido em 2026-08-25.

**Quem pegou foi [W], no olho, olhando o sidebar no smoke. Nenhum gate reclamou.**

Isso não é acidente do módulo Arquivos. É o furo do gerador:
`criar-tela.mjs` carimba **quatro** artefatos — `.tsx`, `.charter.md`, `.casos.md`,
`e2e/<mod>-<tela>.spec.ts`. Nenhum dos quatro é *como o humano chega na tela*.
O charter nasce com `page: /TODO-rota` — um placeholder que nenhum gate lê.

Chamo essa camada de **alcance**: rota nomeada → permissão → entrada de menu → pacote do
business. Ela é invisível pra todo gate de código, porque é a única que não é código React.

## 2. O que NÃO estou pedindo

- Não é guard novo do zero. `scripts/contrato-de-tela.mjs` já existe e já tem
  `--contract/--preflight/--omission/--map` (`contrato:*` no `package.json`). O alcance
  entra ali como modo novo, não como script paralelo.
- Não é regra nova de estilo. O padrão de veto que eu quero **já está no
  `criar-tela.mjs`**: o bloco `ÂNCORA DE DESIGN` recusa com `exit 2` quando o módulo tem
  protótipo e o autor não escolheu (`--prototipo` / `--sem-prototipo`). É esse desenho,
  aplicado à rota. Copie o formato da recusa, inclusive o texto que **nomeia o candidato**.
- Não é `if (business_id === N)`. As três camadas de habilitação
  (pacote → permission → superadmin) já estão certas no `DataController` do Arquivos —
  ele é o **golden** desta emenda, cite-o no comentário.

## 3. Emenda A — `criar-tela.mjs` passa a exigir a rota

Hoje o alvo é `<Mod/Tela> <PT-0X>`. Passa a exigir também **uma escolha explícita de rota**,
espelhando exatamente o par da âncora:

```
--rota <path>            ex: --rota /arquivos
--sem-rota "<razão>"     ex: --sem-rota "sub-tela alcançada só por drawer do PT-01"
```

Sem uma das duas → **`exit 2`** com a mesma anatomia de recusa da âncora:

```
❌ Tela nova sem rota declarada — `page:` não pode nascer como "/TODO-rota".
   Escolha explicitamente (a decisão é sua, não do gerador):
     --rota /<slug>
     --sem-rota "<por que esta tela não tem URL própria>"
   Por quê: nenhum gate lê `page: /TODO-rota`. Tela nasce, responde 200, e ninguém
   a alcança pelo menu — foi o caso de /arquivos (DataController no-op, pego a olho
   pelo [W] no smoke em 2026-08-25, não por gate).
```

Com `--rota`, o gerador:

1. escreve `page: <rota>` no charter (não mais `/TODO-rota`);
2. carimba no charter um bloco de frontmatter novo, **o contrato de alcance**:

```yaml
alcance:
  rota: /arquivos                  # ou n/a (<razão>)
  rota_nome: arquivos.index        # name() da rota — o que o guard procura
  permission: arquivos.access      # declarada em DataController::user_permissions
  menu_hook: Modules/Arquivos/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: arquivos_module          # superadmin_package
```

3. escreve no `casos.md`, junto do UC-01, **um UC de alcance obrigatório**:

```
## UC-<PREFIX>-00 · Chego na tela pelo menu, sem digitar URL
- **Persona:** Larissa — abre o sistema e encontra a tela pelo sidebar.
- **Aceite:** Dado usuário com a permission `<perm>` · Quando abre o sistema ·
  Então o item existe no sidebar e leva a `<rota>` (200, sem digitar URL).
- **Status: ⬜**
```

4. imprime nos "Próximos passos" as 4 linhas de alcance que faltam escrever à mão
   (rota no `Routes/web.php`, `can:` na rota, permission no `user_permissions`,
   `Menu::modify` no `modifyAdminMenu`) — com o `DataController` do Arquivos como
   exemplo copiável.

Com `--sem-rota`, escreve `alcance.rota: n/a (<razão>)` e **não** carimba o UC-00.
Decisão registrada, igual ao `--sem-prototipo`.

## 4. Emenda B — `contrato-de-tela.mjs --alcance` fecha a corrente no CI

Modo novo, `npm run alcance:check` → `node scripts/contrato-de-tela.mjs --alcance`.

Para cada charter que declara `alcance.rota` diferente de `n/a`, verifica a **corrente
inteira**, e reprova nomeando **qual elo** quebrou (não "alcance falhou"):

| # | Elo | Como verificar |
|---|-----|----------------|
| 1 | rota existe | `alcance.rota_nome` aparece em `->name('...')` sob `Modules/*/Routes/` ou `routes/` |
| 2 | rota casa com `page:` | o `prefix`+path da rota resolve pra `page:` do charter |
| 3 | rota é protegida | a rota tem `can:<alcance.permission>` no middleware |
| 4 | permission declarada | `<permission>` aparece em algum `DataController::user_permissions` |
| 5 | menu existe | o arquivo em `alcance.menu_hook` tem `Menu::modify` **e** `url('<rota>')` |
| 6 | menu não é fachada | o `modifyAdminMenu` do módulo **não** é corpo vazio / só comentário |

O elo **6** é o que teria pego o Arquivos: método existia, assinatura certa, corpo no-op.

**Severidade por `status:` do charter** — e isso resolve o segundo problema, o que o [W]
levantou sobre o IT2b não olhar `draft`:

- `status: draft` → **warn**, `rc=0`. Trio novo **não nasce quebrando o CI**.
- `status: live` → **fail**, `rc≠0`. Não se promove draft→live sem alcance.

Assim o gate cobra no momento certo: quando a tela é declarada pronta, não quando nasce.

## 5. Emenda C — o contrato de tela nasce junto (5º artefato)

`prototipo-ui/contrato/` tem 13 arquivos e **nenhum** é `arquivos` — o contrato nasce órfão
porque o gerador não o carimba. Emenda: `criar-tela.mjs` escreve
`prototipo-ui/contrato/<kebab(mod)>-<kebab(tela)>.contract.json` conforme
`contract.schema.json`, com as seções derivadas do arquétipo PT e `copy` como `TODO`.
Já entra no `contrato:check` existente, sem gate novo.

## 6. Selftest — na cultura do arquivo (bite + controle)

O `--selftest` do `criar-tela.mjs` já faz bite-test via `spawnSync` no CLI e mantém
controles positivo/negativo. Siga o mesmo rigor, senão não vale:

- **BITE:** `criar-tela.mjs Mod/Tela PT-01` sem `--rota`/`--sem-rota` → `status === 2`, e
  o stderr **nomeia** que é rota.
- **CN-1:** com `--rota /x` → `exit 0`, charter tem `page: /x` e bloco `alcance:`.
- **CN-2:** com `--sem-rota "razão"` → `exit 0`, `alcance.rota: n/a (razão)`, **sem** UC-00.
- **BITE do `--alcance`:** fixtura com `modifyAdminMenu` de **corpo vazio** e charter
  `status: live` → reprova apontando o elo 6. (Sem este caso o guard é decorativo — é
  exatamente o defeito real do Arquivos.)
- **CN-3:** a MESMA fixtura com `status: draft` → `rc=0` com warn.
- **CN-4:** charter com `alcance.rota: n/a (...)` → não é assunto do guard, não inventa erro.
- **Controle positivo de cada sonda `!regex`** — um `=== 0` verde também fica verde se o
  regex for cego (§5 2026-08-01).
- Nada de assert em número absoluto derivado de sistema vivo (o `--selftest` já levou esse
  prejuízo em 2026-08-24, quando a cobertura do registry subiu de 3/8 pra 7/8 e a lane
  ficou vermelha **por ter melhorado**). Cobertura de alcance: **reporte**, não asserte.

## 7. DoD

- [ ] `npm run tela:criar Mod/Tela PT-01` sem rota → `rc=2` com recusa nomeada
- [ ] `npm run alcance:check` → `rc=0` no `main` de hoje
- [ ] fixtura de menu no-op + `live` → `rc≠0` apontando o elo 6
- [ ] `node scripts/governance/criar-tela.mjs --selftest` → OK
- [ ] `npm run contrato:selftest` → OK
- [ ] `arquivos.contract.json` criado e passando no `contrato:check`
- [ ] `alcance:check` adicionado à lane (required **só** depois de o `main` estar verde nela)

## 8. O que fica pro [W], não pra máquina

`arquivos.access` nasce `default: false`. Em produção o menu só aparece depois de ligar a
permission na role (`/roles/{id}/edit`). O guard **não** deve tentar cobrar isso — é dado de
runtime, não de repo. Mas o "Próximos passos" do gerador tem que dizer isso em voz alta,
senão a próxima tela repete o "abri e não tem nada".

---

## 9. Emenda D — a âncora tem DOIS elos (protótipo **e** DS)

> Esta emenda é a segunda camada faltante. A §3–§8 trata de **alcance** (como o humano chega
> na tela). Esta trata de **ancoragem**: contra o que a tela é conferida.

### 9.1 Onde o protocolo baixa a âncora hoje

O charter de Arquivos declara `related_prototype: prototipo-ui/cowork/arquivos-page.jsx` e o
corpo dele é explícito: a tela é *"derivada do protótipo"*. O `Index.tsx` repete no comentário
do `TOM_ACAO`: *"espelha o mapa ACAO do protótipo"*.

A âncora aponta pro **Cowork** — um `.jsx` escrito por [CC]. E o gate que a protege,
`anchor-content-required.yml` (required desde 2026-07-08, ADR 0327), declara o próprio escopo
na primeira linha: **"falha o merge só em MISSING/SHELL"**. Arquivo ausente, ou âncora
apontando pro shell. Nada sobre conteúdo.

```
protótipo [CC] → âncora declarada → gate confere que EXISTE → .tsx derivado → produção
                                                                                  ↓
          ninguém compara com o DS ──────────────────────── defeito entregue com selo verde
```

### 9.2 Os quatro defeitos que passaram por aí — medidos, não supostos

| # | Defeito | Origem |
|---|---------|--------|
| 1 | buckets `common` e `public` inexistentes no enum do banco → filtro voltava sempre vazio | protótipo [CC]; pego no smoke de **produção** |
| 2 | 12 rótulos que são o próprio valor do enum em inglês (`upload`, `soft_delete`, `signed_url`, `hard_delete`…) | protótipo [CC] → herdado pela tela |
| 3 | protótipo declara tom `danger` (soft) pra `sensitive`; a tela traduziu pra `variant="destructive"` (**fill sólido**, `bg-destructive text-white`) | **tradução** [CL]; a variante certa, `danger`, está no mesmo `badge.tsx` e é usada 3 linhas abaixo no `TOM_ACAO.hard_delete` |
| 4 | `kind="frescor"` (que é IDADE) reusado pra PRAZO → pílula verde `"recente · em 1824 dias"` | protótipo [CC] |

Nenhum dos quatro é pegável por gate de código: compilam, passam no lint, no `pt-conformance`
e no `anchor-content-check`. O #3 é o mais instrutivo — **a âncora existia, o conteúdo
divergiu na travessia**, e é exatamente isso que nenhuma máquina olhava.

### 9.3 O script — escrito e testado, não proposto

`cowork-inbox/ancora-ds/ds-anchor-check.mjs` (neste pedido). Destino sugerido:
`scripts/governance/ds-anchor-check.mjs` + `"ancora:ds": "node scripts/governance/ds-anchor-check.mjs --check"`.

Seis regras, cada uma com bite e controle negativo:

- **R1** `<Badge>` com variante sólida (`default`/`secondary`/`destructive`) onde o canon é o
  par soft. Escopo deliberado: **só Badge** — `<Button variant="destructive">` é ação
  destrutiva e deve ser sólido; acusá-lo seria o falso-positivo que faz o time desligar o gate.
- **R2** enum cru como texto visível — rótulo idêntico ao valor, snake_case, ou `const X = [...] as const` renderizada direto como rótulo.
- **R3** *(lê os DOIS arquivos)* tom declarado no protótipo × variante usada na tela.
- **R4** `kind="frescor"` com `rel` de prazo. `kind="sla"` fica de fora: prazo **é** o domínio dele.
- **R5** rótulo de `<Select>`/`<option>` igual ao valor do enum. Irmã da R2, outro eixo — entrou
  depois: `visibility` (private/internal/public) passava pela R2 por não estar em mapa `l:`.
- **R6** `<StatusBadge tone={colorido}>` — ver §9.6: é a regra que reatribui a culpa.
- **R7** rótulo de coluna: (a) termo cru em inglês em `label:` de coluna; (b) **R7b**, header do
  `.tsx` sem par no protótipo. Entrou por último, e por um buraco real: as R1–R6 olham badge,
  mapa de domínio, option e tone — nenhuma olhava **cabeçalho de tabela**, que é texto grande
  na tela. Foi assim que `label: "Payload"` sobreviveu a tudo.

**Severidade por `status:`**, igual à emenda de alcance: `draft` → warn `rc=0`; `live` → fail
`rc≠0`. Trio novo não nasce quebrando o CI; ninguém promove draft→live com âncora infiel.

### 9.4 Resultado da execução (2026-08-25, arquivos reais)

Rodei a lógica das 4 regras contra o `arquivos-page.jsx` deste espelho e contra os excertos
verbatim do `Index.tsx` do `main` (`36377d3f7248`):

```
=== PROTÓTIPO (arquivos-page.jsx) ===
  12× rótulo é o próprio valor do enum (sensitive, common, public, upload, download,
      signed_url, soft_delete, restore, hard_delete, classify, anonymize, notice)
   1× kind="frescor" com rel de PRAZO

=== TELA VIVA (Index.tsx) ===
   3× <Badge> com variante sólida (destructive ×2, secondary ×1)
   1× const BUCKETS = [sensitive, active, memory, discard] como rótulo de chip

=== R3 · fidelidade da tradução ===
   1× ÂNCORA INFIEL: "sensitive" — protótipo declara "danger", a tela usa "destructive"

=== SELFTEST ===
   15/15 (4 bites R1 · 5 R2 · 3 R3 · 4 R4, com controle negativo em cada)
```

O gate **morde nos dois lados da ponte** — no meu arquivo e no dele. É o desenho certo:
se ele só olhasse o `.tsx`, eu continuaria sendo a origem sem gate.

### 9.5 DoD da emenda D

- [ ] `node scripts/governance/ds-anchor-check.mjs --selftest` → 26/26
- [ ] `npm run ancora:ds` roda no `main`
- [ ] charter de Arquivos segue `draft` → **warn**, `rc=0` (não trava PR nenhum hoje)
- [ ] **`Index.tsx`:** os 2 `variant="destructive"` do acervo (órfão, `sensitive`) → soft; rótulo PT-BR pros 4 buckets e pras ações da trilha; header "Onde está preso" → "Vinculado a" (a **R7b já acusa** esta divergência: o protótipo já diz "Vinculado a", a tela ainda não)
- [ ] **[W] decide** a lacuna do §9.6 (caminho `tone` vira soft, ou nasce família `estado-*`)
- [ ] required **só** depois de o `main` estar verde na lane
- [ ] se `dominio:check` ou `ds:canon:check` já cobrem R1/R2/R5 — **estenda a existente** e me diga qual era

### 9.6 O achado que reatribui a culpa — DRIFT espelho↔repo

Corrigindo o protótipo, medi o `StatusBadge` do espelho (`_ds_bundle.js`) contra o `badge.tsx`
do repo. Eles **discordam sobre o que `danger` significa**:

| | `danger` | sólido chama-se |
|---|---|---|
| espelho DS (caminho `tone`) | `{ bg: var(--color-destructive), fg: '#fff' }` — **FILL SÓLIDO** | — |
| repo (`badge.tsx`) | `bg-destructive-soft text-destructive-fg` — **SOFT** | `destructive` |

Mesma palavra, renderização oposta. Consequência direta: quando a travessia trocou o
`tone="danger"` do protótipo pelo `variant="destructive"` da tela, ela foi **visualmente fiel
ao espelho**. O AP7 ("fundo tintado 6% + borda 22%, nunca fill") já estava violado na fonte.

Eu acusei [CL] de erro de tradução na §9.2 item 3. **Retiro a acusação de autoria** — ele
preservou a aparência que o espelho manda. A R3 segue válida (a divergência de palavra precisa
aparecer), mas a origem é upstream, e quem a nomeia é a R6.

**A lacuna a decidir ([W]):** as únicas famílias soft do espelho são as namespaced (`sla-*`,
`fresc-*`, `tipo-*`, `canal-*`), alcançadas por `kind`+`value`. **Não existe família soft
genérica de severidade.** Por isso, no protótipo corrigido, classificação e ação de trilha usam
`kind="sla"` **emprestando a paleta** e sobrescrevendo o rótulo — declarado em comentário pra
ninguém ler `sla` e achar que é prazo. O conserto de verdade é no espelho: ou o caminho `tone`
passa a ser soft, ou nasce uma família `estado-*`.

### 9.7 O que eu corrigi no protótipo (2026-08-25)

A âncora era a fonte, então consertei a fonte antes de pedir qualquer coisa:

- `common`/`public` → os 4 buckets reais do CuradorEngine, nos **dois** arquivos (6 ocorrências nos dados)
- 12 rótulos de enum em inglês → PT-BR (Sensível · Em uso · Histórico · Descartar; Envio · Baixa · Link assinado · Exclusão · Restauração · Exclusão definitiva · Classificação · Anonimização · Aviso ao titular), com o valor técnico no `title`
- `visibility` → Restrito · Equipe · Aberto
- `kind="frescor"` (idade) → `kind="sla"` pra prazo; a contagem saiu de dentro da pílula
- "Onde está preso" → "Vinculado a"
- `tone` colorido → família soft via `kind`+`value`
- **removida a faixa de copy de PROCESSO** que debatia Admin Center × destino próprio e citava US-ARQ-013 e um Tweak dentro da UI — a pergunta está decidida, e usuário não lê ADR na tela
- trilha: 9 cores → cor só no excepcional (link assinado, exclusão, restauração), resto chip mudo
- **densidade** — linha do acervo de **122/104/86px → 64px uniforme** (trilha, de 1 linha, mede 41px). Três causas, todas medidas: nome longo quebrando em 3 linhas (agora 1 linha + ellipsis, nome completo no `title`), `Venda #14022` quebrando na coluna do dono (nowrap), e a contagem de prazo que eu tinha tirado de dentro da pílula criando uma **3ª linha** na célula (61px — a mais alta da tabela, era ela que definia a altura da linha).
- **`Payload` → `Detalhe`** no cabeçalho da trilha — inglês cru na tela **e** divergência da tela viva, que já usa "Detalhe". Os dois defeitos que este pedido combate, no meu próprio arquivo.
- **corte vertical** — `.arq-lista` tinha `overflow-x:auto`, e pela spec CSS isso força `overflow-y` a computar `auto`; num pai `flex-direction:column` o item ficava `flex:0 1 auto` e **encolhia** até a sobra (clientHeight 147 × scrollHeight 687 — 2 linhas de 10 visíveis, e um scroller dentro de página que já rola). É o AP10 do CLAUDE.md. `flex-shrink:0` → **687/687**, um scroller só.
- **largura** — a tabela estava em **1268px** contra a UX Target do charter ("cabe em 1280px sem scroll horizontal — monitor da Larissa"). Causa: sem `table-layout:fixed`, os `width` das colunas eram sugestão e a coluna de ações comia a sobra (190 declarado → 288 renderizado). Com `fixed` + redistribuição, a soma das 7 colunas é **972px** — exatamente o que sobra em 1280 depois da sidebar (260) e do padding (48). Zero estouro de célula.
- **"em 1824 dias"** saiu quando o prazo é distante: a data já está ao lado e a pílula já diz No prazo/Vencendo/Vencido — o número dito duas vezes era slop, e era ele que estourava a célula (144px de conteúdo em 116px). Agora aparece só quando decide algo (≤90 dias ou vencido).

Estado final medido: linhas **64px uniformes** nas 10 (eram 122/104/86), tabela 972px, `687/687` sem scroll interno, nenhum estouro, console limpo.

Depois: `ds-anchor-check` limpo nos dois arquivos, e a tela renderiza no host (conferido).

---

### Nota de honestidade

Duas buscas minhas por guard já existente de menu/contrato (`scripts/`) foram **truncadas
pelo orçamento de scan** — não afirmo que não existam. Se algum destes elos já é coberto por
máquina que eu não achei, **estenda a existente** e me diga qual era; não crie a segunda.
