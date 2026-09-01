---
id: requisitos-arquivos-index-visual-comparison
title: "Comparacao design x producao — Arquivos/Index"
module: Arquivos
tela: Arquivos/Index
owner: W
status: rascunho
last_validated: "2026-08-25"
---

# Comparacao design x producao — `Arquivos/Index`

> **Ancora computada, nao escolhida no olho** (`node prototipo-ui/ancora.mjs Arquivos/Index`):
> `prototipo-ui/cowork/arquivos-page.jsx` (579 linhas), declarada em `related_prototype` do
> [`Index.charter.md`](../../../resources/js/Pages/Arquivos/Index.charter.md).
>
> Primeiro registro desta tela. Medido em **2026-08-25**, apos o [W] mandar o screenshot do
> prototipo vivo pedindo *"compare com css e todas as camadas"*.

## O que este documento NAO e

Nao e veredito de pixel. A dimensao **D6 (CSS computado / render pareado)** do
[PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md) **nao foi
medida**, e a razao e concreta: a rota `/arquivos` exige `can:arquivos.access`, permission que
nasce `false` (`DataController:36`). Sem ela a producao devolve **403**, entao nao ha render de
producao pra injetar a sonda `design-diff.mjs --probe`. Medir um lado so nao e comparar.

Destravar D6 depende de marcar a permission numa funcao em `/roles/{id}/edit` — o que ja tem
chip proprio. Ate la, o que segue e **comparacao estrutural**, contada dos dois fontes.

## D2 — Estrutura da tabela: **IGUAL**

As 6 colunas do Acervo batem, **na mesma ordem**, extraidas de cada lado:

| # | Prototipo (`arquivos-page.jsx`) | Producao (`Index.tsx`) |
|---|---|---|
| 1 | Arquivo | Arquivo |
| 2 | Onde esta preso | Onde esta preso |
| 3 | Classificacao | Classificacao |
| 4 | Disco | Disco |
| 5 | Tamanho | Tamanho |
| 6 | Vence em | Vence em |

## Emenda de 2026-08-25 · **paridade EXECUTADA contra o protótipo VIVO** (não o espelho)

> Pedido do [W]: *"confira com protótipo atualizado e compare com espelho a paridade, liste e
> execute"*. As duas partes foram feitas, e a primeira mudou o entendimento da segunda.

**O espelho NÃO está infiel ao bundle — o bundle é que está atrás do projeto.** Medido com hash,
não com impressão:

| | sha256 | bytes |
|---|---|---|
| `arquivos-page.jsx` no bundle `5023b274` (gerado 2026-08-24T22:49Z) | `08a8bebe…` | 35 842 |
| `arquivos-page.jsx` no espelho local | `08a8bebe…` | 35 842 |

São o mesmo arquivo. Mas o `arquivos-page.jsx` **do projeto Cowork vivo** (lido por
`DesignSync.get_file` em 2026-08-25) traz comentários datados de 25/08 e **0 de 7** marcadores
dele existem no espelho. Conclusão: o [CC] editou a fonte em 25/08 e **não regerou o payload**;
o [#6260](https://github.com/wagnerra23/oimpresso.com/pull/6260), que sincronizou por esse mesmo
bundle, não tinha como trazer esta tela.

**Atualizar o espelho não foi possível nesta sessão, e o motivo é de transporte, não de vontade.**
O próprio `cowork-mirror-freshness` declara: a rota avulsa (`get_file` → `--export-from`) só serve
para arquivo que volte PERSISTIDO, e abaixo de ~36 KB ele volta INLINE no contexto — escrever dali
é transcrição, com fidelidade não provada. O `arquivos-page.jsx` tem 35 842 bytes, exatamente
abaixo do teto. A rota boa (payload em partes) depende de o payload ser regerado no lado Cowork.
É o mesmo teto que a decisão [W] aberta no [#5757](https://github.com/wagnerra23/oimpresso.com/pull/5757)
registra.

**A paridade foi executada mesmo assim** — contra a fonte viva que foi LIDA, que é o que o
protocolo PAR-1 §1 manda (*"proibido usar espelho como fonte"*). O que entrou:

| Eixo | Antes | Agora |
|---|---|---|
| Rótulo de bucket | `sensitive`, `active`… (enum cru) | **Sensível · Em uso · Histórico · Descartar** (enum no `title`) |
| Visibilidade | `private`, `business` | **Restrito · Equipe · Aberto** |
| Ação da trilha | `upload`, `signed_url_consumed`… | **Envio · Link consumido…** (com fallback pro valor cru) |
| Coluna do dono | "Onde está preso" | **"Vinculado a"** |
| Prazo | texto `em N dias`, vermelho ≤30 | **badge `No prazo`/`Vencendo`/`Vencido`** + contagem só quando ≤90d |
| Cabeçalho | sem ação | **botão "Auditoria"** → `auditoria.index` |
| Subtítulo | "N nesta página · N cifrados" | **"N arquivos · TAMANHO · N no cofre cifrado"** (acervo, não página) |
| Abas | sem contagem | **pill de contagem** (Acervo e Trilha; Cofre não leva, como no protótipo) |
| Chips de bucket | sem contagem | **contagem por bucket** |
| Cofre · achados | sem título; copy reescrita | **"Achados"** + copy do protótipo |
| Disco comum | `Disco {nome}` | **"Disco comum"** (nome técnico no `title`) |

**Fica FORA, e é decisão [W]:** a vista **Retenção** (PR-3) e as **ações por linha** (baixar,
classificar, excluir, avisar) — a onda 1 é leitura pura por contrato.

**Defeito do gate achado no caminho, com canário rodado.** O `contrato-de-tela` casa a copy como
substring no arquivo INTEIRO, **comentário incluído**. Enquanto um comentário meu citava o rótulo
antigo, o gate passou verde com a tela já renomeada; ao tirar a string do comentário ele reprovou
(`rc=1`, "copy ausente em acervo"). É a segunda porta do mesmo defeito que o contrato já
registrava (a primeira: `"Payload"` casando dentro de `TrilhaPayload`). Está anotado no
`_nota_limite` do contrato — endurecer o casamento mexeria nos 29 contratos vigentes e é decisão
[W], não conserto de passagem.

## D4 — Escala do badge de vencimento: **DIVERGE (bug)** — _fechado em 2026-08-25, ver emenda acima_

O prototipo tem **4 faixas**; a producao tem **2**.

```js
// prototipo, linha 110
r <= 30 ? "distante" : r <= 180 ? "frio" : r <= 720 ? "fresc" : "recente"
rel = r <= 0 ? "prazo vencido" : `em ${r} dias`
```

A producao so distingue `d <= 30` (destaque) de o resto, e preserva `prazo vencido`. Perde-se
`frio` (31-180) e a separacao `fresc` (181-720) x `recente` (>720) — que sao justamente as
faixas que dizem se da tempo de exportar antes do prazo legal vencer.

**Classificado como bug, nao decisao:** nenhum Non-Goal do charter recusa a escala, e o
`casos.md` declara "prazo vencido nunca vira contagem negativa" sem restringir as faixas
positivas.

## D4 — Contagem nos chips de bucket: **DIVERGE (bug)**

O prototipo mostra o numero ao lado de cada chip (linha 130):

```js
{b === "todos" ? "Todos" : b}<span className="mono">{...length}</span>
```

A producao renderiza os mesmos 4 chips (`Todos` / `sensitive` / `common` / `public`) **sem
contagem**. E informacao barata que o payload ja carrega.

> **Emenda — PR-2.** O gap segue aberto **no acervo**. Na **trilha**, que nasceu no PR-2, os
> chips ja saem **com contagem** (`upload 12`), e ela nao e cosmetica: vem de um `GROUP BY` do
> proprio log, entao so existe chip pra acao que aquele business registrou de fato — filtro
> nenhum leva a beco sem saida. Fechar o do acervo e barato pelo mesmo caminho.

## D4 — Vocabulario dos chips de bucket: **DIVERGE (achado novo, medido no PR-2)** ⚠️

Os 3 buckets que o prototipo (e a producao) oferecem **nao batem com o backend**. Contado dos
tres lados:

| Fonte | Valores |
|---|---|
| coluna `arquivos.bucket` (migration `2026_05_10_000001`) | `sensitive` · `memory` · `user` · `spec` · `ambiguous` · `discard` · `active` |
| `ListArquivosRequest` (`Rule::in`) | `public` · `internal` · `sensitive` · `vault` |
| chips da tela (= prototipo) | `sensitive` · `common` · `public` |

So **`sensitive`** existe nos tres. Consequencia hoje, na tela viva: **`common` reprova a
validacao** (nao esta no `Rule::in`) e **`public` passa e devolve sempre zero linhas** (nenhuma
linha pode ter esse valor — nao esta no ENUM da coluna). `common` nao existe em lugar nenhum do
codigo alem do chip e do shim `Config/retention.php` (varredura contada: 2 ocorrencias).

**Nao consertado no PR-2, e de proposito:** a correcao e escolher **qual vocabulario de bucket a
UI oferece** — decisao de dominio, nao typo, e fora do intento daquele PR (trilha). As 3 saidas
possiveis (alinhar a UI ao ENUM · alinhar o `Rule::in` ao ENUM · renomear o dominio) mexem em
coisas diferentes e so uma delas e cosmetica.

## D7 — Acoes por linha: **PROD-A-FRENTE por decisao declarada**

O prototipo tem 5 (`onBaixar` · `onExcluir` · `onClassificar` · `onAvisar` · `onRestaurar`);
a producao tem **zero**.

**Nao e gap** — e escopo declarado. O charter poe a tela como **leitura pura** na onda 1, e o
`ArquivosAdminControllerTest` tem assert que reprova se `->delete(` / `->save(` / `dispatch(`
aparecerem no controller. Mutacao entra na onda 2+; purge depende de decisao [W] na proposta
`arquivos-retencao-ui-aviso-titular.md`.

## D7 — Abas: **PROD-A-FRENTE por decisao declarada**

Prototipo: 4 (`acervo` · `retencao` · `cofre` · `trilha`, linhas 19-22). Producao: 1.

Mesmo motivo, e o RUNBOOK e explicito: *"a barra de abas nasce com elas, nao antes — aba que
nao leva a lugar nenhum e promessa, nao navegacao"*.

> **Emenda — PR-2 (onda 1 · vista Trilha).** O retrato acima e o de antes do PR-2 e fica como
> registro. **Producao passou de 1 pra 2 vistas** (`acervo` + `trilha`) e **a barra de abas
> existe**, montada com `PageHeaderTabs` canon e navegando por rota (`?tab=`), como Financeiro
> / Fiscal/Dfe / Cliente. Faltam `retencao` (PR-3, depende de decisao [W] na proposta
> `arquivos-retencao-ui-aviso-titular`) e `cofre` (PR-4) — logo **2 de 4**, e o `PROD-A-FRENTE`
> segue valendo pelas duas que faltam, pelo mesmo motivo declarado.
>
> Divergencia deliberada, pra nao virar "bug" na proxima leitura: **as abas nao tem badge de
> contagem**. O prototipo mostra uma porque tem tudo em memoria; aqui custaria um `COUNT` eager
> na tabela inteira pra pintar numero em aba que ninguem abriu. O numero da vista aberta vai no
> subtitulo, de graca, vindo do paginador que ja veio.

> **Emenda — PR-4 (onda 1 · vista Cofre).** Producao passou de 2 pra **3 de 4** vistas
> (`acervo` · `cofre` · `trilha`). A ordem das abas segue a do prototipo (linhas 19-22), com o
> lugar da `retencao` guardado entre Acervo e Cofre em vez de a nova ser emendada no fim.
> `PROD-A-FRENTE` segue valendo pela que falta, pelo mesmo motivo declarado.
>
> Tres divergencias deliberadas nesta vista, registradas aqui pra nao virarem "bug" na proxima
> leitura — as duas primeiras sao **medidas**, nao gosto:
>
> - **Sem barra de progresso nos cards de disco.** No prototipo ela e `Progress value={bytes /
>   (5 * 1073741824)}` (linha 297) — 5 GB e numero do mock. Conferido em
>   `Modules/Arquivos/Config/config.php`: **nao existe quota por disco** (as chaves sao
>   `disk_default`, `disk_vault`, `upload_max_mb`, `vault_max_file_size_mb`, retencao e signed
>   URL). Barra sem denominador sugere um teto que ninguem definiu. Volta com significado no dia
>   em que houver quota configurada.
> - **Sem o botao "Rodar dry-run do cleanup"** (prototipo, linha 305) e sem a secao
>   `data-contract="dry-run"` que ele revela. E a **onda 3** — PR-8 da proposta
>   `arquivos-retencao-ui-aviso-titular` —, e a onda 1 inteira e leitura pura. Escopo declarado,
>   como as acoes por linha do acervo.
> - **O duplicado nao afirma economia de disco.** O prototipo agrupa por MD5 e para ai; aqui o
>   grupo carrega tambem `caminhos` (caminhos de storage distintos), porque o caminho de gravacao
>   e derivado do proprio hash — copias do mesmo mes apontam pro MESMO arquivo fisico. Sem esse
>   numero, somar bytes e chamar de economia seria inventar. **Producao a frente do prototipo
>   aqui**, nao atras.
>
> O que **bate** com o prototipo: os 2 blocos (`cofre-discos` cards + `cofre-achados` lista) e os
> 3 achados na mesma ordem — acima do cap · orfao · conteudo repetido —, com a mesma explicacao
> de dominio ao lado de cada um (OOM/ADR 0126 · "ou vincula ou apaga" · "o MD5 so aponta").
>
> **D6 continua NAO medida** pelo mesmo bloqueio de sempre: `arquivos.access` nasce `false`, a
> producao devolve 403, e nao ha render pra injetar a sonda. Comparacao estrutural, contada dos
> dois fontes — nao veredito de pixel.

## D6-parcial — CSS: **DIVERGE (decisao NAO declarada)** ⚠️

Este e o achado que o [W] pediu ao dizer "compare com css".

O prototipo carrega **11 classes proprias** com prefixo `arq-`, contadas no fonte:

```
arq-fine (5) · arq-card-l (5) · arq-card (5) · arq-lista (4) · arq-disk (4)
arq-bloco-h (3) · arq-ach-t (3) · arq-ach-file (3) · arq-rows (2) · arq-row-m (2) · arq-nota (2)
```

**Nao existe `resources/css/cowork-arquivos-bundle.css`** — conferido: os bundles Cowork no repo
sao de Financeiro, Compras e PaymentGateway. A tela de producao foi construida com DS canon
(`PageHeader` · `DataTable` · `Badge` · `Stack`/`Inline` · `Skeleton` · `EmptyState`) e **zero**
classe `arq-*`.

Isso colide com a proibicao Tier 0 de `memory/proibicoes.md` §"Design System / Pacote Cowork
novo", que manda: *primeira aplicacao = copiar o `styles.css` INTEIRO do bundle*, e bane
cherry-pick incremental de classes.

**Duas leituras, e a escolha e [W]:**

- **(a) O desvio e legitimo** — a tela usa so componentes canon, entao nao ha bundle a copiar,
  e a regra mira o caso em que se cata classe do bundle uma a uma. Se for isso, **o charter
  precisa declarar**, senao a proxima sessao le a proibicao e "corrige" o que estava certo.
- **(b) O visual do prototipo nao foi aplicado** — e o bundle `arq-*` deve descer inteiro antes
  de qualquer refino.

Nao decido isto: token/componente novo do DS e soberania [W].

## Emenda de 2026-09-01 · **D6 MEDIDA** — e o bloqueio do 403 era FALSO

> Teste prático de ponta a ponta do processo design→produção ([W] escolheu esta tela em
> 2026-09-01). O primeiro achado não foi da tela: foi **deste documento**.

**O "403 bloqueia D6" nunca valeu para quem lê a tela.** As três rodadas anteriores (PR-2, PR-4 e
a emenda de 25/08) declararam *"D6 continua NÃO medida pelo mesmo bloqueio de sempre: `arquivos.access`
nasce `false`, a produção devolve 403, e não há render pra injetar a sonda"*. O `default => false`
é verdade (`DataController:46`) e o `can:arquivos.access` na rota também (`Routes/web.php:37`) — mas
o `Gate::before` de [`AuthServiceProvider:34`](../../../app/Providers/AuthServiceProvider.php)
**libera qualquer ability não-superadmin para quem tem `Admin#{business_id}`**. O próprio repo diz
isso em três sites independentes (`SalesCommissionAgentController:25-26`, `EnsureSupportAccess:18-20`,
`SupportAuditService:17-19`). Medido em 2026-09-01: `https://oimpresso.com/arquivos` **renderizou**,
sem 403, com as 4 seções `data-contract`. O [#6345](https://github.com/wagnerra23/oimpresso.com/pull/6345)
já tinha medido "produção **logada** × protótipo vivo" em 27/08 — o recibo existia e este documento
seguiu afirmando o contrário.

Lição de classe conhecida (§5 2026-07-17 · LC-08): **a permissão foi lida no lugar certo, mas o
oráculo era o runtime** — e um doc canon que afirma bloqueio inexistente não é inércia, é instrução
ativa pra próxima sessão desistir.

**Como foi medido** (protocolo D1–D8, sem eyeball): tema `dark` nos dois lados · sonda canônica
`design-diff.mjs --probe` · protótipo servido do espelho (`--preview-ds` exit 0, 10 deps repostas)
· equivalência das duas cópias da sonda provada por controle A×B no mesmo DOM (`SONDAS EQUIVALENTES`,
5 952 bytes idênticos) · guarda de URL na medição (a primeira tentativa foi descartada porque a aba
navegou para `/ia` no meio — a D0 pegou).

**Veredito do comparador** (`--compare prod.json design.json --check` → `exit 1`, 5 `DIVERGE(bug)`).
A triagem separa defeito de escopo, porque a máquina não conhece o charter — detalhe por item em
[`arquivos-index-gap.md`](arquivos-index-gap.md):

| Item medido | Classificação |
|---|---|
| col2 e col5: pílula **sem `dot`** | **Defeito real, e não é desta tela.** AP7 ([PRE-MERGE-UI](../_DesignSystem/PRE-MERGE-UI.md):69) exige *"dot + texto colorido"*; `Badge`/`StatusBadge` do repo não implementam dot (0 ocorrências). Raio medido: **66 telas**. **Nenhum gate mede AP7** — o único script que o cita (`prototipo-ui/audit/backlog.mjs`) não é invocado por workflow nenhum. Decisão [W]. |
| col2: sub-linha sem `mono` | Divergência de forma, local. Decisão. |
| col0 sem `mono` | **NÃO é defeito** — é o refino A6 aplicado de propósito (`<code>` slug → `CONTEXTO_PT`). Prod à frente; o protótipo é que está atrás. |
| col6 herda cor / 0 blocos | **NÃO é defeito** — leitura pura da onda 1; ações são onda 2+. |
| `colunasDeclaradas` 7 × 0 | **Prod à frente** (declara `<colgroup>`; o protótipo não). |

**O pedido do Cowork já estava 13/13 aplicado**, verificado item a item no arquivo vivo: os 10 refinos
de `ARQUIVOS-REFINOS-PRODUCAO-2026-08-26.md` (A1 e A8 no primitivo — `DataTable` com as longhands
`cw-input-icon-*`, `Badge` nos pares SOFT) e os 3 achados de `ARQUIVOS-PARIDADE-3-ACHADOS-2026-08-26.md`,
entregues por [#6325](https://github.com/wagnerra23/oimpresso.com/pull/6325) ·
[#6339](https://github.com/wagnerra23/oimpresso.com/pull/6339) ·
[#6345](https://github.com/wagnerra23/oimpresso.com/pull/6345) ·
[#6350](https://github.com/wagnerra23/oimpresso.com/pull/6350). O que **não** havia fechado era o
**recibo**: a tela estava `ANCHORED` no lifecycle da [ADR 0384](../../decisions/0384-design-sync-recibos-executaveis-por-tela.md)
com o trabalho em produção há 5 dias. Agora está `APPLIED` (mapa + evidência versionados).

⚠️ **`tested` NÃO foi marcado, e o motivo é medição, não escolha:** o CT 100 — único lugar onde Pest
pode rodar (Tier 0) — está **offline há 4 dias** (`tailscale status`: *"offline, last seen 4d ago"*;
`rc=255` em duas tentativas). A catraca confirma e se recusa a mentir: `--check-lifecycle --minimum
applied` → `exit 0`; `--minimum tested` → `exit 1`.

## O que falta pra fechar

| Dimensao | Estado | Bloqueio |
|---|---|---|
| D1 rede / partial-reload | nao medida | — (o render existe; falta medir) |
| D3 icones | nao medida | idem |
| D5 footer / somatorios | parcial — o rodape existe nos dois, texto diferente | — |
| D6 CSS computado | ✅ **MEDIDA em 2026-09-01** | — (o "403" era falso: `Gate::before`) |
| D8 alinhamento / contraste | ✅ medida junto (D8 kpi align IGUAL; col1/col6 `A`×`BUTTON` = impl) | — |
| SHELL (sidebar) | nao medida | falta declarar `window.__SB_ROLES` nos dois lados (`--shell-roles`) |

## Refs

- Ancora: `prototipo-ui/cowork/arquivos-page.jsx`
- Charter: [`Index.charter.md`](../../../resources/js/Pages/Arquivos/Index.charter.md)
- RUNBOOK: [`RUNBOOK-index.md`](RUNBOOK-index.md)
- Protocolo: [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
