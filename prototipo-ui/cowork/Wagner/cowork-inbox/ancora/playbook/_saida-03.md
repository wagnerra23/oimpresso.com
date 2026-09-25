---
sessao: "_saida-03"
thread: "03 · --list prova o arquivo e mede o fallback component"
dono: "[C]"
data: 2026-09-25
tipo: recibo retroativo (a thread já estava entregue) + 1 achado que ela não pegou
entregue_em: "#7111 + #7152 (2026-09-09)"
base_lida: wagnerra23/oimpresso.com@main 45a687387
---
# _saida-03

## O que já estava entregue
O placar mostrava `03 [proximo] (sem recibo)`: as provas estavam verdes e faltava só este
arquivo. A thread foi executada em 2026-09-09 e o recibo nunca chegou ao `main`.

- **#7111** (mergeado 2026-09-09 14:06Z, `88f088ec1`) — cada linha do `--list --json` ganha
  `caminho` e `existe`, no fim da linha. `caminho` é o que `caminhoDaAncora` resolve; `existe` é
  `true`/`false`/`null`, e `null` não é o mesmo que `false`. `hasSource` e os campos antigos
  ficaram idênticos (o PR comparou o `--list` do `main` e o novo contra a mesma árvore: 226
  linhas, 0 campos antigos alterados). A saída de texto ganha o sufixo `⚠️ NÃO ABRE: <path>`
  só quando `existe === false`. O selftest ganhou o BITE e os controles pedidos, mais um quarto:
  `n/a` cuja prosa cita arquivo real segue `caminho:null`, porque 7 charters citam um arquivo
  justamente para dizer que não se ancoram nele.
- **#7152** (mergeado 2026-09-09) — mata o fallback `mockupJsx(fm.component)`. A D-COMPONENT
  pedia um número, e ele foi medido: `via:'component'` = **0** de 226. Nenhuma tela perdeu
  `hasSource`; o `--list --json` saiu byte-idêntico antes e depois.

O pedido diz *"se o `--list` já emite `existe`, não execute — reporte e pare"*. Não
reimplementei nada. Este PR só acrescenta este recibo.

### O recibo que existiu e não foi restaurado
Achei um `_saida-03.md` anterior em `b1b2e9be5` (2026-09-09 13:31Z), na branch
`claude/import-cowork-handoff-0909`, que **nunca entrou no `main`**. Não o restaurei: ele
descreve outra implementação, de uma sessão paralela, escrita 35 minutos antes do merge do #7111
(o bite-test dele muta `const existe = alvo ? …`, expressão que não existe no código mergeado) e
os números dele não batem com o código que está no `main` (`caminho:null` 124 contra 110 no
#7111). Restaurar poria em canon a descrição de um código que não rodou.

## As 3 contagens, remedidas hoje em `45a687387`
`node scripts/design/ancora.mjs --list --json` → rc=0, 227 linhas.

| contagem | hoje | no #7111 (2026-09-09) |
|---|---:|---:|
| `via == 'component'` | **0** | 0 |
| `existe == false` | **0** (mas veja o achado abaixo) | 0 |
| `caminho == null` | **104** | 110 |
| `existe == true` | 123 | 116 |
| `isNa == true` e `caminho != null` | 0 | 0 |
| linhas sem `hasSource` no JSON | 0 | — |

Os 104 `caminho:null` são: 100 `n/a` · 3 charters de componente sem fonte (`kb/_components/…`,
marcados `auxiliar:true`) · **1** que não devia estar aqui (o achado).

## Provas medidas
1. `node scripts/design/ancora.mjs --selftest` → **rc=0**, `SELFTEST OK`. Passam
   `BITE list: fonte que nao abre`, `CONTROLE list: caminho real da o existe true`,
   `CONTROLE list: n/a nao vira existe false` e o controle da prosa.
2. **Mutação**: `const existe = … ehArquivo(resolve(repoRoot, caminho))` trocado por `… : true`
   → **rc=1**, cai o `BITE list: fonte que nao abre`. Restaurado de cópia, sha256 conferido
   (`f9c894931d4aa989` antes e depois).
3. Consumidor: `node scripts/qa/design-coverage.mjs` → rc=0 (`vínculo QUEBRADO: 0`). A
   varredura `rg --hidden -g '!.git/**' 'ancora\.mjs.*--list'` confirma o `design-coverage`
   como o único script que consome o `--list --json`. Nada mudou no arquivo, então não há
   veredito a comparar.
4. A prova `contem: existe` do índice é fraca: a palavra `existe` está na linha 4 do arquivo
   desde junho, em prosa. A prova que discrimina é o BITE, que nasceu no #7111. O índice foi
   importado às 13:10Z do mesmo dia (`75e71fe21`), antes da entrega, então ele não descreve um
   estado que já existia.

## Achado: um valor que nomeia arquivo e não abre sai como `existe:null`
`Financeiro/ProvaViva` declara
`related_prototype: prototipo-ui/cowork/Wagner/legado/financeiro-prova-viva/Financeiro - Prova Viva (primitivos).html`.
O arquivo **foi apagado do repo pelo #7445** (2026-09-16, `D` no `--name-status`, sem destino).
O `--list` devia dizer `existe:false`. Diz `caminho:null`, `existe:null`, ou seja, "não há o
que abrir".

A causa está em `caminhoDaAncora`: o formato 1 (valor cru) só vale se o arquivo **existe**; sem o
arquivo, cai no `tokenDeArquivo`, que não aceita nome com espaço e devolve nada, e o valor é
classificado como formato 4 ("não nomeia arquivo"). O comando de 1 tela tem o mesmo defeito:
`ancora.mjs Financeiro/ProvaViva` responde *"o valor não nomeia arquivo .jsx/.html/.css/.tsx"*
para um valor que termina em `.html`. As duas portas concordam, e as duas erram.

Isso é o D3 escapando por um formato que o playbook citou como coberto (*"`Financeiro - Prova
Viva (primitivos).html` com espaços e parênteses, que só passa porque o valor cru é testado
antes do regex"*). Só passa enquanto o arquivo existe. Quando o arquivo some, o defeito vira
silêncio.

**Não consertei aqui**, por três motivos: o pedido manda parar; `caminhoDaAncora` é API pública
(o hook `post-merge-ui-smoke-required.mjs` o importa e degrada em silêncio se quebrar) e a Lei
da pasta não deixa mudar API numa thread aditiva; e o conserto muda o que o hook e o comando de 1
tela dizem, o que é outra thread.

**E há uma segunda metade que não é da máquina.** O `canon_reference` do mesmo charter diz que
o arquivo *"vive versionado em `prototipo-ui/cowork/Wagner/legado/financeiro-prova-viva/` desde
2026-09-01 — a cópia do projeto Cowork foi aposentada por decisão [W]"*. O #7445 apagou essa
cópia uma semana depois. Se a decisão valia, a tela perdeu a única fonte de design que tinha, e
isso é para o [W] ver.

**Proposta de thread nova** (para o índice, no Cowork; não editei o índice no espelho):
`caminhoDaAncora` passa a devolver o valor cru quando ele tem forma de caminho (tem `/` e termina
em extensão de design) mesmo sem o arquivo existir, para o `⚠️` dizer qual caminho falhou. Com
BITE (valor com espaço e arquivo ausente → `existe:false`) e controle (mesmo valor com o arquivo
presente → `existe:true`). O FP a medir antes: quantos `n/a` do corpus têm prosa com `/` e
extensão, porque o `ehDeclaracaoNa` vem antes e os protege, mas o hook chama `caminhoDaAncora`
direto.

## Erratas para o índice (registradas aqui, não no espelho)
- **D-COMPONENT** segue `respondida: false` no `00-INDICE.md`, mas foi respondida e executada
  no #7152 (via `component` = 0; a condição que exigia o [W] não se realizou). O #7152 não mexeu
  no índice de propósito, e eu também não: o índice é do Cowork.
- **Caminho**: o corpo da thread 03 cita `scripts/design/ancora.mjs`, e é o arquivo que existe
  hoje (115.794 B, não 49.089 B). O `_PATCH-INDICE-2026-09-16.md` §1 mostra origem e destino
  iguais na troca de caminho; o nome antigo se perdeu no texto dele.
- **Arquivo solto**: `rg` achou um `_saida-07.md` versionado na **raiz do repo**, fora de
  `cowork-inbox/`. Não mexi nele.

## Placar
`entregue 1 de 1 (pelo #7111 + #7152) · este PR: recibo · ausentes: o conserto do achado, por ser
API pública e fora do pedido`.
