# As 44 "sem rota derivável" do lote — 35 são do shell, 2 são nossas, e o resto é ruído meu

> **O que é:** diagnóstico medido de por que `design-diff-lote --dry` recusa 44 das 130 telas
> `anchored` com `✗ rota do shell: sem rota derivável`. **Não é limitação do derivador** — a
> hipótese que eu testei primeiro e que o próprio docblock do driver sugeria (§3 "a derivação
> cobre `route === "x"` exato"). É o shell do protótipo não montar o arquivo declarado.

## O número, e por que ele é um salto

O docblock do [`design-diff-lote.mjs`](../../../scripts/design/design-diff-lote.mjs) §3 registra
**1 de 62** em 2026-09-06. Medido em 2026-09-18: **44 de 130**. De 1,6% para 34% — isso não é a
limitação estável que o §3 descreve, é regressão de cobertura.

## A causa, com controle positivo

As 44 saem de **14 arquivos-fonte**, e nenhum deles é montado pelo shell. Predicado: o
`app.jsx` do espelho contém `<window.X` para algum `window.X =` definido no arquivo.

| arquivo | exports de página | montados pelo shell |
|---|---|---|
| `ponto-telas.jsx` (17 telas) | 1 | **0** |
| `oficina-forms.jsx` (5) | 1 | **0** |
| `financeiro-telas-extras.jsx` (4) | 5 | **0** |
| `fiscal-actions.jsx` | 9 | **0** |
| `vendas-extras.jsx` | 7 | **0** |
| + 9 outros | — | **0** |

**Controle positivo** (sem ele o "0" não prova nada — §5 2026-08-01):
`vestuario-page.jsx :: VestuarioPage MONTADO` — uma âncora que o plano marca `●`.

⚠️ **Erro meu no caminho, registrado:** a primeira medição contou `window.matchMedia` como
"export de página" e reportou `jana-merge.jsx` com 4 refs no `app.jsx`. É API do browser, não
rota. A tabela acima já exclui os globais do browser.

## O split das 43 analisadas (1 do lote não tem charter)

| causa | n | de quem é |
|---|---|---|
| charter e lote **concordam** na fonte, e o shell não a monta | **35** | **Cowork** — rotear no `app.jsx` ou declarar o arquivo como companheiro |
| charter nomeia o export por fragmento — `essenciais-extras.jsx#BaseConhecimento` — e o lote **descarta o `#`** | 1 | repo |
| `Fiscal/Cockpit`: charter diz `fiscal-page.jsx`, lote resolveu `fiscal-actions.jsx` | 1 | repo |
| charter com prosa no campo `related_prototype` (meu parser leu `a`) | 3 | nenhum — artefato da minha sonda |
| charter traz `related_prototype_nota` nomeando o export (ex.: `(TelaDRE)`) | 4 | repo — o dado existe e a máquina não lê |

## O pedido ao lado Cowork (35 telas)

Os arquivos acima existem no espelho e **não têm rota no shell**. Enquanto não tiverem, essas
telas não podem ser medidas por render — nem por este driver nem por qualquer outro, porque não
há o que renderizar. Duas saídas, e a escolha é de lá:

1. **Rotear** no `app.jsx` (`route === "<token>"` → `<window.XPage/>`), como já é feito para as 77
   que o plano marca `●`; ou
2. **Declarar** que o arquivo é companheiro (fragmento importado por uma página), e então o
   charter da tela deve apontar para a **página**, não para o companheiro.

Quatro arquivos não exportam página nenhuma (`jana-telas-novas`, `jana-merge`,
`manufacturing-producao`, `jana-pro`) — para esses, a saída 2 é a provável.

## O que dá pra ganhar aqui (5 telas, sem depender do Cowork)

O derivador ignora dois campos que **já carregam a resposta**: o fragmento `#Export` no
`related_prototype` e o `related_prototype_nota`. Ensiná-lo a ler os dois cobre 5 das 43.
Não está feito — é PR do dono do driver, com FP medido antes.

## Reproduzir

```bash
node scripts/design/design-diff-lote.mjs --dry
```


---

## ERRATA do mesmo dia — 41, não 43; e as "2 nossas" não eram defeito

Medido depois de publicar a tabela acima, provocado por [W] perguntando se estava correto.

**O que estava certo:** a atribuição fonte↔tela. Conferido — os 130 blocos do `--dry` **todos**
trazem linha `fonte` (0 sem), então não houve carry-over de parser, que era a minha suspeita.

**O que estava ERRADO:** o relatório tem **mais de uma entrada para a mesma tela** quando duas
fontes a reivindicam. São **130 blocos para 126 telas distintas**. Consequência direta:

| | |
|---|---|
| telas com bloco `sem rota derivável` | 43 |
| **...que TAMBÉM têm bloco executável (gêmea)** | **2** |
| **travadas de fato** | **41** |

E as 2 gêmeas são **exatamente** as que a tabela acima classificou como "repo — defeito nosso":

- `Essentials/Knowledge/Index` — o bloco `○` é o de `essenciais-extras.jsx`; existe outro,
  `●`, com `essenciais-page.jsx`.
- `Fiscal/Cockpit` — o bloco `○` é o de `fiscal-actions.jsx`; existe outro, `●`, com
  `fiscal-page.jsx`, que é o que o charter declara nos dois campos (`related_prototype` e
  `bundle_source`) e o que o `ancora.mjs` resolve.

Ou seja: **não havia divergência charter↔resolução.** O `ancora.mjs` resolve certo nos dois — e
resolve inclusive o fragmento `#BaseConhecimento` com range de linhas (`:127-188`), que a tabela
acima supôs descartado. O que existe é uma tela reivindicada por duas fontes, e o lote enumera
as duas.

**O que sobrevive da tabela:** as 35 em que charter e lote concordam e o shell não monta o
arquivo — essas seguem sendo pedido ao Cowork, e são a massa do problema. O pedido não muda.

**O que morre:** a linha "ensinar o derivador a ler `#Export` + `_nota` cobriria 5 das 43".
O `#Export` já é lido. Se sobra ganho aí, ele não foi medido — e não vale propô-lo sem medir.
