# THREAD 07 — `DataTablePro`: resizer de 7px e header sortável sem semântica

> **Estado: BLOQUEADA.** Não executar antes de [W] fechar o §6 da **ADR UI-0032** (`status: proposto`).
> Registrada agora porque o Code mandou o achado pelo `CODE_NOTES.md` e ele não tem dono do lado dele — `_ds_bundle.js` é cache derivado do DS, e remendo no espelho evapora no próximo export (ADR 0374).
> Origem: `CODE_NOTES.md` §[ACHADO 2026-09-05] · ADR UI-0032 §10.7.

---

## 1 · O que o Code mandou, e o que eu medi aqui

| item | o que ele disse | medido neste build (2026-09-21) |
|---|---|---|
| `width: 7` no resizer | 2 ocorrências no `_ds_bundle.js` | **1** ocorrência (`:3228-3230`, `width:7 · height:100% · cursor:col-resize · translateX(3px)`) |
| consumidores do `DataTablePro` | 13 arquivos do protótipo | **5**: `estoque-contagem` · `crm-blade` · `patrimonio-page` · `dash-legacy-page` · `relatorios-page` |
| header sortável | `span` com `onClick`, sem `role`/`tabindex`, ~15px | confirmado como forma do DS — **não** reproduzido por medição de DOM neste turno |

**A divergência tem causa provável, não é contradição:** `DataTablePro` virou **alias fino de `DataGrid`** na fusão de 2026-08 (`_ds_bundle.js:3328` — *"ALIAS de compatibilidade. A implementação única é DataGrid"*). Duas implementações viraram uma, então o `width:7` passou de 2 para 1 lugar. O corpus dele também é outro: ele mediu o espelho em `prototipo-ui/cowork/Wagner/`, eu medi o projeto vivo. **Quem executar re-conta no estado do dia.**

## 2 · Por que está bloqueada, e não só "pendente"

O conserto do resizer é **literalmente** o item de §7.3 da UI-0032, que começa com *"O que muda, e onde (só se [W] escolher (a))"*. A ADR está `proposto`. Subir `width: 7` para 24 antes da decisão é adotar a WCAG 2.2 AA por conta própria num componente que serve o ERP inteiro — o oposto do §9 dela (*"Não criar gate/lint de tamanho de alvo"*, *"não subir botão em tela nenhuma antes da decisão"*).

**Se [W] escolher (a):** esta thread executa, e fecha os 5 consumidores daqui num arquivo só.
**Se [W] escolher (b):** a thread morre e o `width: 7` vira exceção escrita, não silêncio.

## 3 · O header sortável é OUTRO eixo — não entra nesta thread

O `span` com `onClick` sem `role`/`tabindex` **não** é dívida de tamanho, e o Code provou por mutação: dando `role="button" tabindex="0"` aos 18 de `essenciais`, o conjunto avaliado subiu de 93 → 111 nós e as violações de `target-size` **continuaram 7** — passam pela exceção de espaçamento. O defeito é **SC 2.1.1 (Teclado)** e **4.1.2 (Nome/Papel/Valor)**, nível **A** — mais severo, e já contabilizado em `config/a11y-baseline.json` (`click-events-have-key-events: 79`).

**Não bloqueado pela UI-0032** (que é de tamanho), mas também não é meu: o markup é do DS. O padrão certo já existe no corpus — `hrm-extras.jsx` §Feriados usa `button.mod-sort` **dentro** do `th`, e a thread `08-feriados-puxar` já o cita como RESÍDUO 4. **Roteie por lá, não por aqui**, para não abrir duas filas pro mesmo átomo.

## 4 · Instrução de execução (só após [W] = (a))

```
THREAD 07 — resizer de coluna do DataGrid passa de 7px para 24px de alvo
  ONDE                : o componente DataGrid do DS (a fonte, NÃO o _ds_bundle.js
                        deste projeto nem prototipo-ui/design-system/, que são
                        cache derivado — patch neles evapora no export)
  REUSAR              : o próprio span do resizer, que já tem
                        position:absolute · height:100% · cursor:col-resize
  CRIAR               : nada
  PASSO A PASSO       : 1) largura do alvo 7 → 24, com o `translateX` reajustado
                           pra manter o centro na divisa da coluna (hoje +3px pra
                           uma largura de 7; com 24 o centro pede -12)
                        2) a LINHA VISÍVEL fica fina: o alvo cresce, o desenho não.
                           Um ::before de 1–2px centrado preserva a aparência
                        3) provar que dois resizers vizinhos não se sobrepõem em
                           coluna estreita — 24 de alvo com coluna de 44 (a menor
                           medida neste build) ainda deixa passo suficiente
                        4) re-contar os consumidores no dia (aqui deu 5, o Code
                           contou 13 no espelho dele)
  DADO                : nenhum
  PARAR SE            : [W] escolher (b) no §6 · ou se a coluna mínima do DataGrid
                        for menor que 48px em alguma tela (aí dois alvos de 24
                        colidem e o conserto vira outro desenho)
  NÃO FAZER           : não editar `_ds/**` nem `prototipo-ui/design-system/**`
                        (cache derivado) · não instalar gate/lint de tamanho
                        (§9 da UI-0032, e sem FP medido no corpus real) · não
                        misturar o header sortável aqui (§3)
```

## 4-bis · Corrigido no build daqui (não vira pedido) — 2026-09-21

O que falha no ALVO se conserta aqui, não se exporta. Dois defeitos, o segundo achado pelo primeiro:

1. **`otimiza-ondas.css` §4 setava só `min-height`.** Alvo tem duas dimensões: num botão redondo de 13px isso produzia um **oval de 13×44** — alto o bastante pra passar num censo de altura, estreito demais pra dedo nenhum. Agora a mesma regra (mesmo seletor, mesmos dois eixos `pointer:coarse` + `[data-toque="tablet"]`) leva `min-width:44px` junto. **Estendido, não recriado** — o mecanismo já era o decidido (`app.jsx` liga o atributo pelo Tweak).
2. **`oficina-os-page.css` tinha um segundo mecanismo, keyed por `max-width:760px`**, com o comentário prometendo ≥44 e entregando 26. Tablet de chão de oficina em 1024px landscape é `coarse` e **não** é estreito — o mecânico ficava de fora. O bloco foi repontado pros dois eixos certos, e guarda só GEOMETRIA: botão 44×44, ponto 26px, `gap:0` (passo de 34 faria dois alvos de 44 se sobreporem).
3. **A pintura da luz estava no BOTÃO.** Com a cor no botão, o piso de 44px transformava cada luz num **disco sólido** de 44 — alvo certo, desenho destruído. A pintura foi pra **base, no `::before`** (`--card-2`/`--line` + as 3 cores de estado); o botão é sempre transparente e sem borda. Cobre `on` **e** `off` — o reset paralelo que eu tinha escrito cobria só o `on`. O ponto tem tamanho fixo em px nos dois modos (13 na base, 26 no toque): com `width:100%` ele crescia junto com o botão.

**Medido depois, em modo toque, nas 9 telas da errata §10.7** (alvos visíveis `button · a[role=button] · [role=tab] · select · input[checkbox]`):

| tela | alvos | <24 | círculo deformado | linha com overflow-x |
|---|---:|---:|---:|---:|
| `oficina-os` | 67 | **0** | 0 | 0 |
| `prod-lista` | 80 | **0** | 0 | 0 |
| `prod-massa` | 66 | **0** | 0 | 0 |
| `crm-leads` | 86 | **0** | 0 | 0 |
| `rep-folhas` | 106 | **0** | 0 | 0 |
| `est-ajustes` | 76 | **0** | 0 | 0 |
| `est-transferencias` | 77 | **0** | 0 | 0 |
| `pat-bens` | 117 | **0** | 0 | 0 |
| `hrm-licencas` | 76 | **0** | 0 | 0 |

Semáforo da vistoria, antes→depois em modo toque: `13×44` (oval) → **`44×44` de alvo com ponto de 26px**, sem sobreposição entre os 3, linha de **67px** (não cresceu), desktop **inalterado em 13×13**.

⚠️ **Instrumento que mente, registrado:** `getComputedStyle(el,'::before').width` devolveu **sempre o valor da regra BASE**, ignorando o override que de fato pinta — e isso produziu dois vereditos de defeito inexistente. Provado por três vias no mesmo estado: `getComputedStyle(el).width` = `44px`, `gap` da faixa = `0px`, `min-width` do nome = `0px` (as três do mesmo bloco `[data-toque="tablet"]`, logo o bloco aplica), e o **pixel** mostra o ponto pequeno dentro do alvo grande. **Pseudo-elemento se julga por pixel, não por `getComputedStyle`.**

⚠️ **O desktop segue como está** — 24×24 no mouse é o §6 da UI-0032, decisão [W] em aberto. O que foi aplicado aqui é o piso de **toque**, que já é lei escrita pelas personas (`CLAUDE_DESIGN_BRIEFING`, `Patrimonio.charter.md`), e só vale sob `pointer:coarse`.

Fora do corpus, declarado: `venda-v3/02-shell/css/shell-responsivo.css` tem alvo de toque keyed por `max-width` (o mesmo vício), mas **o host não carrega esse arquivo** — ele carrega `venda-v3.css`. É pasta-fonte de um build, não tela servida; não mexi.

## 5 · Não medido, declarado

- **Não medi o DOM** do resizer nem do header sortável neste turno — o que afirmo do bundle é leitura de fonte (`grep` com o par `width: 7` + `col-resize` casando no mesmo bloco).
- **Não li o `DataGrid` fonte** do DS (só o compilado), então não afirmo onde a linha vive no arquivo de origem.
- **Não re-rodei a mutação** do Code; o §3 repete a medição dele, creditada.
- **Não sei a coluna mínima real** das 5 telas consumidoras — o `PARAR SE` existe por isso.
