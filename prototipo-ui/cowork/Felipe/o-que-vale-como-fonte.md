# O que eu comparo quando você pede "confere se está de acordo com o DS"

> Medido em 10/09/2026. Cada linha tem arquivo e linha. Escrito depois de eu errar duas vezes nesta
> mesma pergunta: primeiro concluí sobre o repo lendo só o espelho, depois "provei" a ausência de
> `caption` com um `grep` cross-project que retorna vazio mesmo quando o termo está no arquivo.

## A ordem de autoridade — 4 camadas, não uma

| # | Camada | Onde | O que ela decide | Autoridade |
|---|---|---|---|---|
| 1 | **Repo (SSOT)** | pasta local `oimpresso.com/` — `resources/js/Components/{ui,shared}/`, `resources/css/tokens/*.tokens.json` | tudo. É o produto | **ganha de todos** — `ds-mirror-drift.mjs` L5: *"git = SSOT; o projeto claude.ai/design é ESPELHO"* |
| 2 | **Fonte viva do DS** | projeto `/projects/49a36f76-…/` — `components/<Nome>/<Nome>.jsx` + `.d.ts` + `_ds_bundle.js` | o componente e seu contrato de props | ganha do espelho local. É o que o sync de 09/09 atualizou |
| 3 | **Espelho local** | `_ds/wagner-…49a36f76/` e `_ds/office-impresso-…019dd02f/` | **nada** — é cópia | só serve para o protótipo **rodar**. Regenerado em 09/09: 9.301 → 9.355 linhas |
| 4 | **Protótipo** | este projeto: `manufacturing-*.jsx`, `oimpresso.com.html`, `templates/` | nada sobre o DS. É o auditado | é onde o desacordo aparece, nunca onde ele se resolve |

**A regra que eu quebrei duas vezes:** conclusão tirada da camada 3 sobre a 1 ou a 2 não vale. Se a
camada 1 ou 2 não estiver aberta na sessão, a frase certa é **"não medi"**, nunca "não existe".

## Qual espelho o protótipo carrega — importa, e não é o óbvio

**Desde 21/09/2026 há um espelho só.** `oimpresso.com.html` carrega
`_ds/wagner-office-impresso-design-system-49a36f76-…/_ds_bundle.js`. As pastas `019dd02f` e
`office-impresso-atual-d7f88676` foram apagadas, e com elas o shim de alias. O bundle publica
`window.OfficeImpressoPontoWR2DesignSystem_019dd0` e nada mais; é esse o nome que as páginas leem.

## Os nomes que você citou, um por um

**`_ds/`** — camada 3. Cópia para o protótipo rodar. Tem **uma** pasta:
`wagner-…49a36f76` — a vinculada pela skill e a que a página carrega.

**`prototipo-ui/`** — no projeto do DS tem **um arquivo só**: `Design System v4.html`. É histórico.
Não é fonte de nada, e não é o que eu comparo. (O README do DS trata `prototipo-ui/cowork/ds-v6`
como "snapshot congelado, referência histórica".)

**Baseline** — não é uma coisa, são várias, e nenhuma é catálogo de componente:
`scripts/design-sync/ds-mirror-drift-baseline.json` é **o piso de drift de token aceito** — o guard
acusa quando passa dele (`ds-mirror-drift.mjs` L17-19). Ele compara os tokens do git contra
`scripts/design-sync/mirror-snapshot/colors_and_type.css`, **um snapshot commitado, não o espelho
vivo** — porque o CI não tem login claude.ai (L7-11). É advisory por padrão; `--enforce` não é
chamado em lugar nenhum hoje (L28-33). E **exit 2 = não conseguiu medir**, deliberadamente separado
do código de drift (L25-27) — a mesma distinção que eu deveria ter aplicado ao meu `grep` vazio.

**`ancora-codigo-sync.mjs`** — não tem relação com aderência visual. É o auto-sync de **ponteiro de
documentação**: quando um trecho citado como `Arquivo.php:443` migra de linha, ele regrava o
endereço. O docblock L26 é explícito — *"só mexe no PONTEIRO, nunca na AFIRMAÇÃO"*, porque máquina
que reescreve asserção produz contrato tautológico. É exatamente o erro que eu cometi ao "corrigir"
o `cli` de L2989 para L2993 sem remedir: consertei o ponteiro chutando, e o número certo era L3040.

**O que de fato é o contrato de aderência legível por máquina:**
`_ds/wagner-…/_adherence.oxlintrc.json` (795 linhas) — `no-restricted-imports` listando as ~40
pastas de componente e `react/forbid-elements`. E `_ds_manifest.json` traz `namespace` +
`components[].sourcePath` (o mapa nome→arquivo, 41 entradas). **Nunca usei nenhum dos dois nas
auditorias desta semana** — as três listas saíram de leitura do bundle. Se eu tivesse partido do
manifest, teria batido no `DataTable.jsx` em vez do bundle compilado, e os quatro números não teriam
errado.

## Onde os "bubbles" entram — não sei, e não vou chutar

Procurei e achei **uma** ocorrência medida: `scripts/governance/shipped-log-generate.mjs` **L56**,
regex `DS_TITLE`, que inclui `\bbolha` entre os termos que classificam uma entrada do shipped-log
como sendo de DS. Isso é classificação de changelog, não aderência — provavelmente não é o que você
quer dizer.

Dois referentes plausíveis, e eu não tenho como decidir qual:

1. **bolhas de conversa** — o template `templates/atendimento/` do DS (thread com bolhas + compositor);
2. **as bolhas de comentário** que você deixa em cima do preview quando revisa uma tela.

Se for (2), elas não entram na conferência de DS de forma alguma hoje: são pedido seu, e valem como
instrução — não como fonte. Diga qual dos dois e eu fecho esta seção com medição.

## O que eu vou fazer daqui pra frente

1. Abrir a camada 1 ou 2 **antes** de escrever "o DS não tem". Sem elas, escrever "não medi".
2. Partir do `_ds_manifest.json` + `components/<Nome>/<Nome>.jsx`, não do bundle compilado.
3. **Nunca usar `grep` cross-project como prova de ausência** — ele devolve vazio com o termo
   presente. Cross-project é `read_file`. `local_grep` que estoura o tempo também não é ausência: o
   próprio retorno diz "results are incomplete".
4. Toda citação com arquivo e linha, remedida na hora — não reaproveitada de mensagem anterior.
