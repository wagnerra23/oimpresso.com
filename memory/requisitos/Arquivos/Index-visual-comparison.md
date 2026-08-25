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

## D4 — Escala do badge de vencimento: **DIVERGE (bug)**

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

## O que falta pra fechar

| Dimensao | Estado | Bloqueio |
|---|---|---|
| D1 rede / partial-reload | nao medida | precisa render de producao |
| D3 icones | nao medida | idem |
| D5 footer / somatorios | parcial — o rodape existe nos dois, texto diferente | — |
| D6 CSS computado | **nao medida** | 403 sem `arquivos.access` |
| D8 contraste par-a-par | nao medida | idem |

Sequencia pra destravar: marcar `arquivos.access` numa funcao -> `design-diff.mjs --probe` ->
injetar a MESMA sonda nos dois lados -> `--compare prod.json design.json --check`.

## Refs

- Ancora: `prototipo-ui/cowork/arquivos-page.jsx`
- Charter: [`Index.charter.md`](../../../resources/js/Pages/Arquivos/Index.charter.md)
- RUNBOOK: [`RUNBOOK-index.md`](RUNBOOK-index.md)
- Protocolo: [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
