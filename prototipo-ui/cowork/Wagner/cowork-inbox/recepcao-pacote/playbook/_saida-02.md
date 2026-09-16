---
sessao: "_saida-02"
thread: "02 · Pacote v2 regerado (2026-09-16)"
dono: "[CL]"
data: 2026-09-16
prefixo_tocado: prototipo-ui/cowork/Wagner/cowork-inbox/recepcao-pacote/playbook
base_lida: wagnerra23/oimpresso.com@main (07b1a7658571)
natureza: RECIBO DE MEDIÇÃO — nada foi promovido, e o §3 diz por quê
---
# _saida-02

## 0 · Veredito em uma frase

**O pacote está bom e não há nada para aplicar.** O `sync/` que veio no ZIP passou o contrato
(`[2] CONFORME` — o de 07/09 saía `FORA-DO-CONTRATO`), e o gerador canônico, rodado sobre a árvore
que veio junto, fechou um bundle com **`bundleId` idêntico ao do bundle já ativo no repositório**:
delta `+0 ~0 -0 =278`. O espelho já estava no estado-alvo antes deste ZIP chegar.

## 1 · O que foi rodado, e o que saiu

A lista de tarefas pedia a receita manual (limpar o lote velho → receber 45 arquivos → `aplicar-payload`
→ aplicar). Essa receita **foi substituída por máquina em 2026-09-10**, por decisão de [W] no dia
(*"o objetivo é eu exportar uma única vez, sem depender de uma receita manual em cada importação"*):
a rota é `receber-handoff.mjs`, que orquestra o que já existe sem reimplementar nada. Ela contém os
passos 1–4 da lista e acrescenta quatro que a receita manual não tinha — auditoria do `sync/` que veio,
classificação por 3 pontos, **guarda de regressão** e reconciliação do `_ds/` pelo dono.

```
node scripts/design/protocolo.config.mjs --selftest                              exit 0
node scripts/design-sync/receber-handoff.test.mjs                                exit 0 · 22/22
node scripts/design-sync/receber-handoff.mjs --zip "<handoff 20>.zip" --conta w   exit 0
```

Saída literal dos passos que decidem:

```
  [1] EXTRAIR      819 arquivo(s) - CRC-32 conferido em todos
  [0] DE QUEM      indeterminado - conta w   (o id do projeto de telas nao aparece em path nenhum)
  [2] PACOTE sync/ CONFORME
                   id c8a070942fa6cfb7 - snapshot - gerado 2026-09-16T13:46:19.902Z
  [3] TRES PONTOS  ZIP-FORA-DO-BUNDLE=2 - IGUAL=276
  [3c] LIVE-ONLY   59 de 818 paths do export nunca desceram pro espelho
                   destes, prototipo de TELA: 0
  [4] DS           _ds/.../_ds_bundle.js        zip 332092 B -> espelho 348179 B
  [4] DS           _ds/.../colors_and_type.css  zip  19917 B -> espelho  19917 B
  [5] REGERAR      BUNDLE v2: b19625fb6c0fe1c11f7f52fc7c0a1adae304e6ab16ce4476fb73a56a05a1b615
                   DELTA: +0 ~0 -0 =278 · 0.0 KiB baixaveis
  [6] VALIDAR      dry-run VALIDADO
  [7] APLICAR      nao pedido
```

Prova independente de que o espelho já estava no alvo — o `bundleId` regerado hoje **é o mesmo** que o
`state/active-bundle.json` carrega desde 2026-09-14:

```
ativo    b19625fb6c0fe1c11f7f52fc7c0a1adae304e6ab16ce4476fb73a56a05a1b615  delta  2026-09-14T19:23:26Z
regerado b19625fb6c0fe1c11f7f52fc7c0a1adae304e6ab16ce4476fb73a56a05a1b615  delta  2026-09-16T13:52:27Z
```

## 2 · Os 281 do pacote × os 278 do gerador — a divergência inteira são 5 arquivos, todos `_ds/**`

O §1 da thread anunciou 281 arquivos; o gerador canônico fecha **278**. A diferença não é perda de
tela nenhuma:

**3 arquivos que o pacote declara e o grafo não alcança** — três `woff2` de 45.712 B cada:

```
_ds/office-impresso-.../assets/fonts/ibm-plex-sans-500.woff2
_ds/office-impresso-.../assets/fonts/ibm-plex-sans-600.woff2
_ds/office-impresso-.../assets/fonts/ibm-plex-sans-700.woff2
```

Medido nos dois lados: o `colors_and_type.css` **do pacote** e o **do espelho** referenciam, os dois,
só `ibm-plex-sans-400.woff2`. Ninguém aponta para 500/600/700 — logo elas ficam fora do fechamento
do `entry`, e o gerador está certo em excluí-las. Corroboração: elas já constavam da lista `live-only`
do ledger de frescor em **2026-09-14**, antes deste ciclo. Não é regressão nem achado novo.

Isto identifica, com precisão, o ponto onde a réplica auditada do §1 divergiu do motor: o `files` do
gerador é o **fechamento a partir do `entry`**, não a árvore filtrada por papel. Quem enumera por papel
e calcula o `missing` pelo grafo obtém 281; quem deriva os dois do mesmo fechamento obtém 278.

**2 arquivos com o mesmo path e sha256 diferente** — `_ds_bundle.js` e `colors_and_type.css`. Resolvidos
**por regra, não por frescor**: `_ds/**` tem papel `preview-cache` e o dono é o projeto Design System
(#7096), então o espelho vence. Foi o passo `[4]`. É também por isso que o `bundleId` regerado difere do
`c8a0709...`: o manifesto do repositório carrega o hash do espelho nesses dois, não o do ZIP.

## 3 · Por que NÃO rodei o `--apply` (passo 4 da lista)

Porque não há byte a promover, e aplicar deixaria rastro afirmando o contrário. Com delta `+0 ~0 -0`,
o applier não escreveria conteúdo nenhum — mas reescreveria `state/active-bundle.json`,
`application-report.json` e `applications.json` com carimbo de hora novo. O commit resultante diria
"importado" sobre uma importação que não aconteceu. O `--apply` fica disponível e sem efeito: se [W]
quiser o registro no ledger de frescor por este ciclo, o comando é o mesmo com `--apply`.

## 4 · `cowork-inbox/**`: 21 novos e 17 divergentes que NÃO desceram, e não é omissão

Fora do fechamento do shell, o ZIP traz material de PEDIDO. Contado:

| família | novos no ZIP | divergentes |
|---|---|---|
| `cowork-inbox/ponto/playbook/` | 19 (inclui `ATA-DECISOES-2026-09-14.md`) | `00-INDICE.md` |
| `cowork-inbox/recepcao-pacote/playbook/` | 2 (esta thread + o patch de índice) | — |
| `cowork-inbox/{cms,connector,governance,hrm,notificacoes,patrimonio,sidebar,compras,fiscal,ancora-ds}/` | — | 15 |

**Desceram apenas os 2 da `recepcao-pacote`** — o pedido que este recibo responde e o patch de índice
dele, já aplicado ao `00-INDICE.md`. Os outros 19 + 17 ficaram de fora seguindo o precedente
documentado do ciclo anterior ([PR #7272](https://github.com/wagnerra23/oimpresso.com/pull/7272),
2026-09-14, textual): *"o que NÃO entrou, de propósito: os arquivos de `cowork-inbox/**` do zip. São
PEDIDO, não build — o contrato build-only os recusa (ADR 0390), e medido por conteúdo o espelho está
A FRENTE do zip em vários deles. Aplicar o lote os regrediria."*

Precedente preserva o fato do dia, não o estado de hoje — então **remedi**. Continua valendo: dos 17
divergentes, **9 têm o espelho à frente**, e o maior é exatamente o exemplo que o #7272 citou:

```
cowork-inbox/patrimonio/playbook/00-INDICE.md   zip  9.533 B   espelho 23.052 B   (+13.519 B)
cowork-inbox/sidebar/playbook/00-INDICE.md      zip 26.514 B   espelho 35.082 B    (+8.568 B)
cowork-inbox/governance/playbook/00-INDICE.md   zip 10.377 B   espelho 11.991 B    (+1.614 B)
```

Os outros 8 têm o ZIP à frente por 4 a 58 B. Trazer o lote inteiro reverteria os 9 para ganhar os 8, e
a leva do `ponto` pertence à thread do Ponto, não à da recepção. **O que fazer com esses 36 arquivos é
decisão de [W]** — e o caminho barato, se a resposta for "traga", é um PR próprio por módulo, com a
direção medida arquivo a arquivo como acima.

## 5 · Thread 01 já tem dono, e ele é melhor que o pedido

O verificador de recepção que a `01-recepcao-regenera.md` especifica **existe desde 2026-09-10** —
um dia antes de a thread ser escrita. Detalhe e residual honesto em [`_saida-01.md`](_saida-01.md).

## 6 · O que segue pendente, e de quem

- **`D-RECEPCAO-FALHA` e `D-QUEM-REGENERA`** ([W]): seguem abertas. O `receber-handoff.mjs` já responde
  as duas *de fato* — falha fechada (qualquer passo que não fecha aborta antes de escrever) e não
  regenera pacote por conta própria (regenera o **bundle** localmente, a partir da árvore que o ZIP
  trouxe; nunca commita pacote). Se [W] ratificar esse comportamento, as duas decisões fecham sem código.
- **A rotina do lado Design** (regerar o bundle ao fim de todo ciclo) cumpriu neste ciclo: o pacote veio
  `CONFORME`, contra o `FORA-DO-CONTRATO` de 07/09. É a primeira vez que a recepção não precisou
  descartar o `sync/` que veio.
