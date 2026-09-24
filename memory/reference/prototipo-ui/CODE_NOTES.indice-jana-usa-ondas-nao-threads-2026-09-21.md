# Dois pedidos do ciclo da Jana: o índice usa `"ondas"`, e o item 9 do DoD não fechou

> **O que é:** devolutiva `[CL]` → `[CC]` do ciclo de 2026-09-21 (ondas 01 e 02 do playbook da
> Jana, ambas mergeadas). **Dois pedidos, um destinatário — por isso vêm juntos, e não em dois
> turnos de lá:**
>
> 1. **O `00-INDICE.md` declara `"ondas"`; o consumidor in-repo exige `"threads"` com `provas`.**
>    Como o agregador avalia os 13 índices num `map`, um que ele não entende lança e **mata a
>    medição dos outros 12**. Não é preferência de vocabulário — é contrato de máquina.
> 2. **O item 9 do DoD das DUAS fichas não fechou:** o `github.md` não tem a linha deste ciclo, e o
>    bundle não foi regenerado. Desde 2026-09-06 isso é **rotina decidida**, não pedido.
>
> **Nenhum dos dois eu conserto daqui**, e a razão é de processo, não preguiça: as três vias do (1)
> estão medidas abaixo, e o (2) roda no lado que tem os arquivos em disco.
>
> ⚠️ **O conteúdo das fichas está fora da crítica** — as duas ondas saíram delas sem ambiguidade.
> O que falha é o índice e o fechamento, não o pedido de design.

## O sintoma

`placar-de-lista (advisory)` ficou vermelho no [#7579](https://github.com/wagnerra23/oimpresso.com/pull/7579)
e segue vermelho no `main` desde o merge (2026-09-21 11:09:45Z):

```
NÃO MEDI: índice sem threads — "0 de 0" não é placar
Process completed with exit code 2
```

## A causa, com três controles

| # | teste | exit |
|---|---|---|
| 1 | com o `cowork-inbox/jana/playbook/00-INDICE.md` | **2** — `índice sem threads` |
| 2 | renomeando só a chave `"ondas"` → `"threads"` | **2** — `thread 01 sem "provas"` |
| 3 | **controle**: removendo o diretório `jana/` do corpus | **0** — placar completo |

O (3) prova que a causa é exatamente esse arquivo. **O (2) é o que muda a conclusão: não é typo de
chave, é outro contrato.** Sinonimizar só move o erro de linha.

Contado nos 13 índices de `cowork-inbox/*/playbook/`:

| chave | módulos |
|---|---|
| `"threads"` | **12** — ancora · compras · ds-atomos · financeiro · fiscal · governance · hrm · patrimonio · ponto · recepcao-pacote · shell-usermenu · sidebar |
| `"ondas"` | **1** — **jana** |

## O contrato que o consumidor exige

[`scripts/qa/placar-indice.mjs`](../../../scripts/qa/placar-indice.mjs), `validarIndice()`:

```js
if (!Array.isArray(indice.threads) || !indice.threads.length)
  throw new NaoMedi('índice sem threads — "0 de 0" não é placar');
…
for (const t of indice.threads) {
  if (!t || !t.id) throw new NaoMedi('thread sem id');
  if (ids.has(t.id)) throw new NaoMedi(`thread duplicada: ${t.id}`);
  if (!Array.isArray(t.provas)) throw new NaoMedi(`thread ${t.id} sem "provas"`);
}
```

Uma `thread` aceita, exemplo real do `hrm`:

```json
{ "id": "01",
  "titulo": "Build: TABS do HRM (−Presença · +Departamentos/Cargos)",
  "dono": "CC",
  "provas": [ { "tipo": "nao_contem",
                "path": "prototipo-ui/cowork/hrm-page.jsx",
                "padrao": "id:\"hrm-presenca\"" } ] }
```

A `onda` que desceu traz `{ id, secao, ficha, arquivos, estado }` — **sem `provas`**, que é o campo
do qual o estado é DERIVADO.

⚠️ **O `estado: "aberta"` do índice da Jana é sintoma do mesmo desencontro.** O placar existe
justamente para **não** ler estado escrito: *"o estado de cada thread é DERIVADO do repo — ninguém
escreve estado (Lei 2)"*. Um campo `estado` no índice é ignorado pelo consumidor e, pior, sugere
uma fonte de verdade que ele não usa.

## As três vias de conserto daqui — todas caem

1. **Fabricar as `provas` que faltam.** É escrever o dado que o medidor consome — o oposto exato da
   Lei 2 que o próprio script defende. Inventaria cobertura.
2. **Editar o `00-INDICE.md` no espelho.** [ADR 0374](../../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md):
   `prototipo-ui/cowork/Wagner/**` é build-only. O próprio placar imprime isso na saída dele
   (*"editar aqui seria desfeito no próximo pacote"*), e é a lápide §5 2026-08-14.
3. **Tornar o agregador resiliente** (índice incompatível vira "NÃO MEDIDO", os 12 bons são
   medidos). Considerado e descartado: hoje o vermelho é **o que fez alguém investigar**; depois
   dele, um índice incompatível passa despercebido num summary de job advisory que ninguém abre.
   É a lápide §5 2026-07-09 — *advisory = não bloqueia o merge, **nunca** = não pode ficar vermelho*.

## O pedido

**O gerador do playbook deve emitir `threads` com `provas`, como nos outros 12.** Para as 2 ondas
da Jana, as provas naturais são estruturais e já existem no repo — as duas ondas mergearam
([#7587](https://github.com/wagnerra23/oimpresso.com/pull/7587) e
[#7591](https://github.com/wagnerra23/oimpresso.com/pull/7591)), então um `contem` sobre o
`JanaCockpit.tsx` fecharia as duas.

⚠️ **Não estou ditando as provas** — quem as escolhe é quem gera o playbook, e escolher por vocês
seria a mesma fabricação que recusei no §4.1. O que este documento fixa é o **contrato**: `threads`
(array não-vazio) · cada uma com `id` único e `provas` (array).

## O que NÃO é pedido aqui

- **Nada sobre o conteúdo das 2 fichas.** `01-painel.gating-pro.md` e `02-painel.estado-vazio.md`
  estavam corretos e executáveis — as duas ondas foram implementadas a partir deles sem ambiguidade,
  e o `PARAR SE` de ambas foi medido e não disparou. O defeito é só do índice.
- **Nada sobre a âncora.** Medido em 2026-09-21: `jana-merge.jsx` é **byte-idêntico** entre o
  espelho e o vivo (sha256 `7bb8e713130a9f80`, 60.716 bytes, 1141 linhas).

## Segundo pedido, do mesmo turno: **o item 9 do DoD não fechou nas duas ondas**

O §9 das **duas** fichas termina no mesmo item, palavra por palavra:

> 9. `github.md` com a linha do ciclo + `bundle regenerado (<data> · N arquivos)`.

**Medido no `main` em 2026-09-21, depois das duas ondas mergearem:**

| verificação | resultado |
|---|---|
| `github.md` → `## Last sync` | `date: 2026-09-18T10:36:16Z` |
| linha do ciclo de **21/09** (o que gerou este playbook) | **ausente** |
| `bundle regenerado` no arquivo | 3 ocorrências — **todas de ciclos anteriores** |

O próprio `00-INDICE.md` já nascia declarando isto, e a honestidade dele é o motivo de eu não
tratar como defeito escondido:

> Este ciclo fecha **sem** pacote regenerado — o gerador exige os arquivos em disco e **não roda do
> lado do agente** (ADR 0374), então **não afirmo que regenerei**.

⚠️ **Mas isso já não é "pedido", é rotina decidida.** O painel executável
(`scripts/design/protocolo.config.mjs`) registra, textual:

> DECISÃO [W] 2026-09-06 (*"2 e 3 ok pode fazer"*): regenerar o bundle ao **FIM DE TODO CICLO** do
> Cowork é **ROTINA obrigatória** do lado do design, **não pedido**.

### Por que isso importa mais do que parece

O ciclo fecha **em código** e não fecha **em registro**. As duas ondas estão no `main` com trio
completo e mordida provada; o que falta é o rastro pelo qual o próximo ciclo sabe o que já desceu.
A consequência é a que o painel já descreve: **o espelho fica atrás do vivo por padrão**, e daí
*"não achei no espelho" nunca prova ausência*.

Nesta sessão isso já produziu um efeito concreto: o `--sla` do
`cowork-mirror-freshness` devolveu **⬜ INCONCLUSIVO** — o `--compare` estava dentro do SLA
(última rodada 2026-09-17, 705 sync · 0 stale), mas **5 arquivos existem no vivo e não no espelho**
(`.gitignore`, `.thumbnail`, e 3 do cache `_ds/` incluindo `styles.css`). Nenhum deles é `jana-*`,
então **não afetou este trabalho** — mas é o mesmo mecanismo, e ele piora a cada ciclo que fecha
sem regenerar.

### O que fecha o item 9

1. `github.md` ganha a entrada do ciclo de **2026-09-21** (as 2 ondas da Jana).
2. `gerar-payload-partes.mjs --root <design-vivo> --out sync/ --previous sync/bundle.manifest.json`,
   e a linha `bundle regenerado (<data> · N arquivos)` como recibo (ADR 0387).

Os dois rodam do lado que **tem os arquivos em disco** — por isso vêm neste canal, e não como
commit meu.

## Dois achados menores das mesmas fichas, para o próximo ciclo

1. **`variant="first"` não existe.** A ficha 02 pede `EmptyState` com essa variante; o componente
   declara `'default' | 'search' | 'error' | 'success'`
   ([`Components/shared/EmptyState.tsx`](../../../resources/js/Components/shared/EmptyState.tsx)).
   Ficou no `default`, declarado no UC-JPAIN-29.
2. **A dúvida do §8 da ficha 02 estava respondida pelo código vivo.** Ela pedia confirmar que
   `topClientes`/`methodsAgg` chegam `[]` e não `null`; as linhas imediatamente acima do predicado
   já faziam `.reduce()` direto nos dois, sem guard, desde sempre — com `null` a tela estaria
   quebrada hoje em qualquer business. Vale como padrão: antes de marcar "não medido" no §8,
   conferir se o código já depende da resposta.

---

**Canal:** PROTOCOL §10.2 (`[CL]` → `[CC]`) · **Origem:** sessão de 2026-09-21, ondas 01 e 02 do
playbook da Jana · **Recibo do vermelho:**
[#7579 comentário](https://github.com/wagnerra23/oimpresso.com/pull/7579#issuecomment-5759577169)

---

## RECIBO — pedido 1 atendido (2026-09-21, mesma data)

> **O que fecha:** o **pedido 1** (`"ondas"` → `threads` com `provas`). O **pedido 2** (item 9 do
> DoD) **segue aberto** e continua sendo do lado que tem os arquivos em disco — nada aqui o toca.

O gerador do playbook emitiu a **2ª emissão** e ela desceu pelo transporte do painel
(`protocolo.config.mjs` §PONTE/INTAKE: `DesignSync.list_files` → `get_file`), não por edição à mão
no espelho. `.md` **não** entra no `--export-from` — o `exportPlan` build-only recusa
([`cowork-mirror-freshness.mjs:244`](../../../scripts/governance/cowork-mirror-freshness.mjs)) —,
então a escrita foi a rota inline da ADR 0389, com as 4 condições cumpridas e declaradas:
origem única resolvida por `list_files` **antes** (3 arquivos em `cowork-inbox/jana/playbook/`,
zero homônimo em outro caminho) · `truncated: false` · verificação rodando o consumidor (abaixo) ·
escrita inline declarada no PR.

### Antes → depois, medido no mesmo checkout limpo

| | `node scripts/qa/placar.mjs --indice --todos` |
|---|---|
| **antes** | `rc=2` · `NÃO MEDI: índice sem threads — "0 de 0" não é placar` · **0 módulos medidos** |
| **depois** | `rc=0` · **13 módulos medidos** · cobertura cumulativa `1 de 63 (1.6%)` |

A jana entrou com as 2 threads e **as 5 provas passando** — nenhuma linha de prova falha ao lado
delas (contraste: `Hrm 01` lista `hrm-page.jsx (ainda contém "id:"hrm-presenca"")`):

```
jana: entregue 0 de 2 · próximo 2 · em curso 0 · pendente 0 · bloqueada 0
  01 [proximo  ] Painel: o tier Pro governa brief, análises e ações — sem _saida
  02 [proximo  ] Painel sem histórico mostra um estado de página, não 6 caixas vazias — sem _saida
```

`próximo` (e não `feito`) é o veredito **correto**: falta `_saida-01.md` / `_saida-02.md`, que é
recibo de quem executa. A própria 2ª emissão antecipa isso e recusa escrevê-los por [CL] — seria
escrever o dado que o medidor consome, a via que o §4.1 acima já tinha recusado.

### A pasta desceu inteira — e só 1 dos 3 arquivos mudou

Conferido arquivo a arquivo contra a origem, por probes literais (não por olho), com controle
negativo provando que a sonda discrimina:

| arquivo | veredito | método |
|---|---|---|
| `00-INDICE.md` | **substituído** (+69 −38) | `"ondas"`/`estado` → `threads`/`provas` + `variaveis` |
| `01-painel.gating-pro.md` | inalterado | 6/6 probes HIT · 12.217 B · 148 linhas |
| `02-painel.estado-vazio.md` | inalterado | 6/6 probes HIT · 9.204 B · 127 linhas |

### Duas verificações que o formato novo exigia, e que foram feitas antes de escrever

1. **O consumidor expande `${VAR}`.** A 2ª emissão usa `variaveis` + `${COCKPIT}`/`${CASOS}`; o
   `resolverPath()` ([`placar-indice.mjs:120`](../../../scripts/qa/placar-indice.mjs)) resolve, e
   `${VAR}` não decidido vira `indefinida` — nunca path chutado.
2. **As 5 provas batem no `main`**, contadas uma a uma antes do write: `1 · 1 · 1 · 2 · 1`.

### Resíduos declarados (nenhum é consertável deste lado)

- **Pedido 2 — item 9 do DoD: aberto.** `github.md` sem a linha de 21/09, bundle não regenerado.
  Rotina do lado do design desde a decisão [W] de 2026-09-06.
- **`variant="first"` não foi corrigido na ficha 02** (§5 e §6 seguem pedindo). O índice o marca
  como *"achado de [CL] pendente no próximo ciclo"* — está declarado, não esquecido.
- **`_saida-01.md` / `_saida-02.md`** não escritos, pela razão acima.
- O `estado: "aberta"` da 1ª emissão foi o que fez uma sessão **re-despachar as duas ondas já
  mergeadas** (#7587 · #7591) como se estivessem abertas. É a Lei 2 cobrando o preço: campo de
  estado escrito à mão numa ponte vira instrução errada pra quem lê depois. A 2ª emissão não o
  tem.
