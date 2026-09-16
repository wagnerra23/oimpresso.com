---
sessao: "_saida-01"
thread: "01 · A recepção confere o pacote e falha quando ele mente"
dono: "[CL]"
data: 2026-09-16
prefixo_tocado: nenhum — este recibo não escreveu código
base_lida: wagnerra23/oimpresso.com@main (07b1a7658571)
natureza: RECIBO DE NÃO-CONSTRUÇÃO — o papel já tem dono; ver §1
---
# _saida-01

## 0 · Veredito

**Não construí nada, e a própria thread manda não construir.** A cláusula *"Parar se"* dela diz:
*"Já existir script com esse papel → estenda aquele e reporte; não crie um segundo dono de paridade"*.
O script existe: [`scripts/design-sync/receber-handoff.mjs`](../../../../../../scripts/design-sync/receber-handoff.mjs),
criado em **2026-09-10** — um dia **antes** de a thread ser escrita (`gerado: 2026-09-11`). A premissa
*"Não vi o fluxo de recepção do ZIP no repo neste turno"* do `00-INDICE.md` já era falsa no dia.

## 1 · Item por item do pedido, contra o que a máquina faz

| pedido da thread | estado | onde |
|---|---|---|
| 1. recebe o projeto desempacotado + `sync/bundle.manifest.json` | ✅ | passos `[1]` + `[2]`; extração própria com **CRC-32 conferido em todos** os 819 arquivos |
| 2. compara por **sha256 por arquivo** e classifica | ✅ e mais | `auditarPacote()` (pacote × própria árvore) **+** `classificar()` de **3 pontos** (zip × espelho × manifesto ativo) |
| 3. invariante dos 4 baldes somando o total do projeto | ❌ **e não deve ser feito** | ver §2 |
| 4. grava recibo `_saida-01.md` com contadores + `bundleId` + data do manifesto | 🟡 | a máquina **imprime** tudo isso (`id`, `mode`, `generatedAt`, divergentes nomeados); não escreve arquivo. Este recibo é escrito pela sessão |
| 5. severidade e autoria conforme `D-RECEPCAO-FALHA` / `D-QUEM-REGENERA` | 🟡 | **de fato já resolvido**, de direito pendente de [W] — ver §3 |
| não criar 2º dono de paridade | ✅ | nada novo foi escrito |
| não regenerar o pacote sozinha | ✅ | regenera o **bundle** local a partir da árvore recebida; **nunca** commita pacote |

E a máquina faz **três coisas que o pedido não pediu**, todas valiosas:

- **`[3b]` guarda de regressão** — pergunta ao git se o conteúdo do ZIP já esteve versionado e foi
  substituído. Se sim, o ZIP está *atrás* e o `--apply` **recusa** sem `--permitir-regressao`. Era o
  furo da receita manual: o dry-run aprova um delta legítimo *para o lado errado*.
- **`[4]` reconciliação do `_ds/**` por regra** — o dono é o projeto Design System (#7096), então o
  espelho vence, sem inferência de frescor.
- **`[3c]` live-only** — mata a rotina separada que só a sessão logada conseguia rodar (`list_files`
  tem auth interativa, ADR 0315) e que por isso vencia. A lista sai da árvore extraída, de graça.

## 2 · A invariante dos 4 baldes é falso-positivo por construção — medido

O item 3 pede que `iguais + divergentes + ausentes_no_manifesto + orfaos` somem **o total de arquivos
do projeto**, com `exit 2` quando não fechar. Medido neste ciclo:

```
arquivos no project/ do ZIP ......... 818
arquivos declarados no manifesto .... 281
diferença ........................... 537
```

Os 537 não são buraco: o manifesto é o **fechamento a partir do `entry`**, não uma listagem da árvore.
`screenshots/` (48), `uploads/` (10), `sync/` (45), `resources/`, `venda-v3/`, mockups de ciclos
passados — nada disso é fonte de build, e o contrato build-only os exclui de propósito. Uma invariante
que exige a soma fechar acusaria **537 arquivos legítimos por ciclo**.

É o alerta da própria thread, um nível acima: *"o predicado absoluto de órfãos dava ~90% de falso-positivo
antes de virar delta"*. Aqui daria ~66% e pela mesma causa — comparar um **fechamento** contra uma
**árvore** como se fossem o mesmo conjunto. **Não implementar.** Reabrir exige um denominador declarado
(o subconjunto de papéis do contrato), não a árvore inteira.

Residual honesto que sobra do item 3, e é estreito: `auditarPacote()` percorre `manifesto.files`, logo
pega *entrada declarada que falta na árvore* mas **não** pega *arquivo de papel `cowork-source` presente
na árvore e ausente do manifesto*. Isso tem valor real (seria o sintoma de um gerador que esqueceu um
arquivo referenciado) — mas quem já o pega é o `missing` do gerador no passo `[5]`, que **bloqueia** o
manifesto quando o grafo não fecha. Um segundo detector do mesmo fato seria régua duplicada.

## 3 · As duas decisões de [W]: a máquina já respondeu de fato

- **`D-RECEPCAO-FALHA`** — *falhar ou avisar?* A máquina **falha fechada**: qualquer passo que não fecha
  aborta com `Nada foi promovido`. O que ela não faz é **reprovar o PR**, porque a rota é local e o CI
  não tem o ZIP. O `design-memory-gate` e o `governance-script-tests` rodam só o `--selftest` (22/22) —
  e isso está correto: gate que fingisse medir um ZIP que não existe no runner seria teatro.
- **`D-QUEM-REGENERA`** — *o bot regenera ou só detecta?* Um dono só, preservado: a máquina regenera o
  **bundle** a partir da árvore que o ZIP trouxe (não inventa conteúdo) e **não** commita pacote. Quem
  emite o pacote segue sendo o lado Design, com os arquivos em disco.

Se [W] ratificar esse comportamento como resposta, **as duas fecham sem uma linha de código**.

## 4 · O caso concreto que originou a thread já não existe

A thread nasceu de um fato datado: *"o `sync/` está congelado em 2026-09-07 enquanto o build andou em
10 e 11/09"*, com o CI verde afirmando frescor inexistente. Dois recibos, nesta ordem:

- **2026-09-14** (handoff 19, [PR #7272](https://github.com/wagnerra23/oimpresso.com/pull/7272)): a
  máquina **acusou** — *"o `sync/` que veio no pacote estava fora do contrato (bundleId divergente, 19
  sha256 diferentes do que a própria árvore declara) — a máquina o ignorou e regerou"*. É o terceiro
  caso de sanidade da thread (*"rodar contra o estado real de hoje deve acusar a defasagem"*), cumprido.
- **2026-09-16** (este ciclo): `[2] PACOTE sync/ CONFORME`, gerado no mesmo dia. É o caso de sanidade
  inverso (*"projeto e manifesto em paridade → exit 0, zero divergentes"*), cumprido.

As duas polaridades vieram de execução real, em pacotes reais. O detector não marca tudo e não passa
tudo. Recibo do ciclo de hoje em [`_saida-02.md`](_saida-02.md).
