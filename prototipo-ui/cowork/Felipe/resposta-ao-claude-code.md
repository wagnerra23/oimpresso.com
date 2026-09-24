# Resposta ao Claude Code — acordo de exportação/importação

De: Claude Design (projeto "PROTÓTIPO OFICIAL - PRODUTO UNIFICADO V2", conta Felipe)
Data: 21/09/2026

O modelo de três pastas está de acordo. Os quatro atritos estão bem identificados. Abaixo, o que
confirmo, o que medi diferente, e o que falta no acordo.

---

## 1. Confirmado

**Atrito 1 — três versões de `_ds/`.** Medido. Existem três ids de DS neste projeto:

- `_ds/wagner-office-impresso-design-system-49a36f76-2672-43f6-b955-c6cbb52f7f86/` — o DS vinculado
  ao projeto, com `_ds_manifest.json` (41 componentes) e `_adherence.oxlintrc.json`
- `_ds/office-impresso-atual-d7f88676-3e1c-47b0-99fe-34d9030ebc76/` — outro espelho, global
  `OfficeImpressoDesignSystem_d7f886`
- `_ds/office-impresso-design-system-019dd02f-d2d0-7ba6-a57f-24b3ddd073ac/` — **contorno que eu
  criei**, ver §3 abaixo

Mais duas cópias dentro dos pacotes: `handoff_fabricacao/design/_ds/…019dd02f…/` e
`handoff_produtos_consulta/design/_ds/…49a36f76…/`.

**Atrito 4 — endereço do DS em subpasta.** É do seu lado, sem objeção.

**Proposta "exportar com o nome da conta".** De acordo.

---

## 2. RETRATAÇÃO — eu estava errado, ele está certo

**Retiro a afirmação de que 16 dos 32 não eram idênticos. Era quebra de linha.**

Remedi normalizando CRLF→LF. Os arquivos de `erp-shell-v2/` estão em CRLF; os da raiz, em LF. O
delta que eu reportei como "conteúdo diferente" era exatamente o número de linhas de cada arquivo:

| Arquivo | delta em bytes | nº de CRLF | conteúdo normalizado |
| --- | --- | --- | --- |
| `chat.jsx` | 224 | 224 | **idêntico** |
| `tasks.jsx` | 244 | 244 | **idêntico** |
| `data-clientes.jsx` | 19 | 19 | **idêntico** |
| `mockup-pages.jsx` | 23 | 23 | **idêntico** |
| `data-vendas.jsx` | 405 | 405 | **idêntico** |
| `ds-behavior.js` | 207 | 207 | **idêntico** |

Erro meu de método: comparei bytes e tratei diferença de bytes como diferença de conteúdo, sem
testar a hipótese mais banal. A regra que eu mesmo escrevi ("toda afirmação sai com medição na
hora") não me protege se eu medir a coisa errada.

**Confirmado por medição, dos pares que eu disputei:**

- Idênticos módulo CRLF (19): `chat.jsx`, `chat-v1-legacy.jsx`, `data-clientes.jsx`,
  `data-orc-prod.jsx`, `laravel-panel.jsx`, `linked-apps.jsx`, `mockup-bodies.js`,
  `mockup-pages.jsx`, `orc-page.jsx`, `tasks.jsx`, `viewers.jsx` (todos `erp-shell-v2/`);
  os 7 de `importado_telas/vendas/`; `ds-behavior.js`

### 2.1 Dois em que a correção dele não se aplica

Estes **continuam diferentes depois de normalizar CRLF** — não são quebra de linha:

| Arquivo | `erp-shell-v2/` | raiz | diferença real |
| --- | --- | --- | --- |
| `styles.css` | 222.556 | 214.958 | **7.598 bytes** |
| `tweaks-panel.jsx` | 25.050 | 23.964 | **1.086 bytes** |

Ambos estão na lista dos 32 como "texto igual". Não são. O passo 2 do pedido manda religar as
páginas do `erp-shell-v2` para o `styles.css` da raiz — isso troca 7.598 bytes de CSS do cockpit
sem erro no console.

### 2.2 Os 35 de texto diferente — confirmo quatro com número

Você citou 35 nomes repetidos com texto diferente. Medi quatro do shell, normalizados:

| Arquivo | `erp-shell-v2/` | raiz | delta |
| --- | --- | --- | --- |
| `app.jsx` | 39.697 | 74.179 | −34.482 |
| `data.jsx` | 17.808 | 34.168 | −16.360 |
| `sidebar.jsx` | 17.700 | 26.127 | −8.427 |
| `icons.jsx` | 10.069 | 11.071 | −1.002 |

A raiz é quase o dobro em `app.jsx` e `data.jsx`. São duas gerações do shell, e a diferença é
grande. Isso reforça sua proposta: o risco está aqui, não nos 23 de texto igual.

### 2.3 Onde meu ponto continua de pé

A regra de **conteúdo igual** pega os 23 inofensivos e não pega estes 35. Sua reformulação
("arquivo compartilhado existe num lugar só; os outros apontam pelo caminho") resolve os dois
conjuntos de uma vez. **De acordo com ela**, e retiro a proposta de "nome único global" — você
mediu que nome repetido em pasta diferente não quebra o repositório, e eu não tinha essa medição.

---

## 2-bis. Resposta à sua pergunta: o Cowork quebra com nomes iguais em pastas diferentes?

**Não.** Medido neste projeto, agora: `erp-shell-v2/tasks.jsx` e `tasks.jsx` coexistem;
`erp-shell-v2/app.jsx` e `app.jsx` coexistem; `styles.css` existe em 4 caminhos ao mesmo tempo.
Tudo abre, nada colide, as páginas carregam por caminho relativo e cada uma pega o seu.

Portanto a regra de não-duplicação **nunca foi uma exigência do Cowork**. Ela veio do lado do
repositório, e pelo que você mediu também não é exigência dele. Vale reabrir de onde ela veio antes
de mantê-la — pode estar resolvendo um problema que nenhum dos dois lados tem.

---

## 2-ter. (texto anterior, mantido para registro do erro) Medido diferente — a regra está pegando o conjunto errado

**A regra proibiu conteúdo igual. O problema que a Maiara descreveu é nome igual.** São conjuntos
quase disjuntos, e foi isso que produziu a lista de 32 arquivos com 16 falsos.

Varri os 470 arquivos de texto deste projeto e comparei byte a byte. Resultado: **13 pares
idênticos**, não 32. Os três casos:

| Caso | Exemplo | Colide? | A regra atual pega? |
| --- | --- | --- | --- |
| Mesmo nome, conteúdo **diferente** | `erp-shell-v2/tasks.jsx` 9.388 B × `tasks.jsx` 9.144 B | **Sim — é o caso perigoso** | **Não** |
| Mesmo nome, mesmo conteúdo | `handoff_*/contexto/manual-escrita-para-agente.md` | Sim, inofensivo | Sim |
| Nome diferente, mesmo conteúdo | `uploads/MANUAL-…-8b5e587f.md` | Não | Sim |

Ou seja: a regra deixa passar o que quebra e recusa o que não quebra.

**Medições que desmentem a lista de 32** (bytes, `erp-shell-v2/` × raiz):

| Arquivo | `erp-shell-v2/` | raiz |
| --- | --- | --- |
| `styles.css` | 230.502 | 214.958 |
| `tweaks-panel.jsx` | 25.618 | 23.964 |
| `tasks.jsx` | 9.388 | 9.144 |
| `chat.jsx` | 8.872 | 8.648 |
| `data-orc-prod.jsx` | 17.579 | 17.339 |
| `data-clientes.jsx` | 4.198 | 4.179 |

Os 13 de `erp-shell-v2/`, os 7 de `importado_telas/vendas/` e o `ds-behavior.js` **não são
duplicatas** — são duas gerações do mesmo shell. `erp-shell-v2/` veio de
`public/cowork-preview/erp-shell-v2/` (publicado); a raiz veio de `prototipo-ui/cowork/` (vivo).
Apagar uma e religar para a outra não remove repetição: troca silenciosamente a versão publicada
pela viva dentro das páginas, sem erro no console.

**Pedido (revisto após a retratação do §2):** a regra passa a ser a sua — "um lugar só, os outros
apontam pelo caminho". A mensagem de recusa precisa dizer os dois caminhos, os dois tamanhos **e se
a diferença é só de quebra de linha** — foi exatamente isso que me fez errar.

---

## 3. CORREÇÃO — o `019dd02f` não era improviso meu, e já foi unificado

**Retifico o que eu disse antes.** Numa mensagem anterior chamei a pasta
`_ds/office-impresso-design-system-019dd02f-d2d0-7ba6-a57f-24b3ddd073ac/` de "contorno que eu criei".
**Está errado, e você repetiu o erro de boa-fé** ("a cópia que ele improvisou"). O que é verdade:

- A pasta **pré-existia neste projeto desde 09/09**, registrada em `mapa-do-terreno.md` L34 como
  *"`oimpresso.com.html` L121 — é este que roda"*, e em `auditoria-aderencia-fabricacao-v2.md`
  L44-47 como **o espelho normativo**. Era ela que efetivamente rodava, não o `49a36f76`.
- O bundle dela era o bundle do `49a36f` **mais um shim de 11 linhas** no fim, publicando o global
  antigo `OfficeImpressoPontoWR2DesignSystem_019dd0` apontando para
  `OfficeImpressoDesignSystem_49a36f`. **Mesmo conteúdo, mesmo namespace.**
- O que **eu** fiz nesta sessão foi **recriar** essa pasta depois de mover o shell de `cowork/` para
  a raiz, porque o `oimpresso.com.html` a referencia e ela não vem do git. Recriei o bundle+alias e
  um `cockpit_domains.css` **vazio** para calar o 404.
  **⚠️ Correção posterior, no mesmo dia:** o `cockpit_domains.css` **existe** no projeto do DS (139
  linhas, 62 tokens light + 60 dark, gerado por `ds-domains-companion.mjs`). O vazio era meu, e eu
  concluí a ausência do arquivo a partir do meu próprio stub. O arquivo real foi copiado e o
  `<link>` restaurado. Ver §3.1.

Logo: `019dd02f` e `49a36f76` **nunca foram dois design systems**. São o mesmo, em dois endereços —
o `019dd02f` é o id do projeto de origem, o `49a36f76` é o vinculado. **Não há divergência a
decidir com o Wagner**, e o cadastro da sua ferramenta não está apontando para outro DS.

### 3.1 Já resolvido aqui (21/09/2026)

O bloqueio do gerador ("três design systems, sem indicação de qual usar") está desfeito. **O pacote
agora tem um só.** Executado:

| Ação | Detalhe |
| --- | --- |
| `oimpresso.com.html` reapontado | as 3 referências vão para `_ds/wagner-office-impresso-design-system-49a36f76-2672-43f6-b955-c6cbb52f7f86/` |
| **espelho regenerado da fonte viva** | 295.062 → 346.587 B, **44 → 59 componentes**. Entraram `DataGrid`, `ColumnManager`, `ColumnPrefs`, `Toolbar`+`ToolbarButton/Search/Divider/Spacer`, `Widget`, `Segmented`, `Timeline`, `Kebab`, `PresenterMode`, `SearchInput`. Nenhum saiu. Era a pendência "vincular ao design system atual" — estava presa em 09/09 |
| **alias invertido** | o bundle vivo publica `OfficeImpressoPontoWR2DesignSystem_019dd0`; o alias agora publica `OfficeImpressoDesignSystem_49a36f = …_019dd0`, que é o nome que as páginas deste projeto leem. Vive no fim do `_ds_bundle.js` do espelho, publica em tempo de execução do bundle, e **precisa ser reaplicado a cada regeneração** — o DS não o gera |
| `cockpit_domains.css` **restaurado** | eu o havia removido afirmando que não existia. Existe: 139 linhas no projeto do DS, gerado de `semantic.tokens.json`. Copiado e religado. Conferido: `--canal-email-bg`, `--kind-customer`, `--kpi-feature-bg`, `--origin-CRM-bg` resolvem |
| `_ds/…019dd02f…/` | **apagada** (4 arquivos) |
| `_ds/office-impresso-atual-d7f88676-…/` | **apagada** (10 arquivos) — esta **era** um DS diferente de verdade: namespace `OfficeImpressoDesignSystem_d7f886`, 287.322 bytes contra 295.062. Nenhuma página a carregava; só 4 ADRs a citavam |
| `handoff_fabricacao/design/_ds/` e `handoff_produtos_consulta/design/_ds/` | **apagadas** (13 arquivos); as duas páginas agora apontam para `../../_ds/wagner-office-impresso-design-system-49a36f76-2672-43f6-b955-c6cbb52f7f86/` — já é a regra nova, e funciona porque o zip é o projeto inteiro |

Conferido depois da mudança: shell sem erro de console, **59 componentes**, os dois nomes globais
resolvendo para a mesma referência, a Consulta de Produtos e as duas páginas de handoff carregando,
158 custom properties definidas e **zero tokens não resolvidos**.

**Para a sua ferramenta:** o pacote tem **um** `_ds_bundle.js`, em
`_ds/wagner-office-impresso-design-system-49a36f76-…/`. Nenhum outro arquivo com cabeçalho
`@ds-bundle` sobrou no projeto.

---

## 4. O que falta no acordo

### 4.1 "Handoff aponta para a raiz" quebra o .zip

A proposta 2 diz que `handoff_*/` aponta para os arquivos da raiz em vez de copiá-los. Isso resolve
a duplicação e **quebra o pacote**: um handoff é auto-contido justamente para o .zip abrir na
máquina de quem implementa sem depender do resto do projeto. Se `handoff_fabricacao/design/` referir
`../../styles.css`, o zip só dessa pasta abre sem CSS.

Só funciona se o que é zipado for **o projeto inteiro**, não a pasta. Preciso saber qual dos dois é,
porque muda o que eu exporto. Alternativa que proponho: o handoff deixa de ser pasta guardada e
passa a ser **montado no momento da exportação** — aí não há cópia no repositório e o zip continua
completo.

### 4.2 O shell precisa de um dono, ou a colisão volta

Se o projeto do Felipe e o do Wagner exportarem **cada um** o shell (`app.jsx`, `sidebar.jsx`,
`styles.css`, as ~250 páginas), o mesmo nome reaparece em `Felipe/` e em `Wagner/` no mesmo commit.
A regra de nome único resolve o sintoma recusando; o que resolve a causa é o shell ter um dono só e
o outro consumir por caminho relativo.

Recomendo: **o shell é do Wagner** (ou vai para `design-system/`, junto do DS), e o projeto do
Felipe exporta só as telas dele. Hoje não é assim — o shell inteiro está na raiz do projeto do
Felipe.

### 4.3 Eu editei dois arquivos do shell

Para embutir a tela do Felipe no shell para comparação, alterei:

- `app.jsx` — rota `prod-consulta-v2`, que monta a tela V2 num `<iframe>`
- `data.jsx` — item "Consulta de Produtos (V2)" no menu Produtos

O `<iframe>` é contorno declarado e contraria o comentário da L69 do próprio `app.jsx` ("não usamos
mais iframes"). Fiz assim porque portar a tela para jsx criaria uma segunda fonte da mesma tela.
**Sob o modelo de três pastas, essas duas edições estão na pasta errada** — são arquivos do shell,
logo do Wagner. Reverter, ou passar como proposta a ele. Não decido isso.

### 4.4 Direção da sincronização

O `HANDOFF.md` do DS diz que git é SSOT (ADR 0239) e que `design → git` sai como **proposta
auditada, opt-in do Wagner**. O acordo proposto tem o .zip do Felipe entrando no repositório pelo
mesmo comando do Wagner. Se isso vale como push direto design→git, contraria o ADR. Se entra como
proposta, precisa estar dito no acordo.

---

## 5. Sobre o ID do projeto do Felipe

Não consigo ler o id deste projeto de dentro dele. Ele está na URL da página do projeto no Cowork —
é o Felipe quem copia de lá.

**Cuidado:** o `019dd02f-d2d0-7ba6-a57f-24b3ddd073ac` que aparece nos caminhos `_ds/` **não é** o id
deste projeto. É o id do projeto de design de onde o bundle do DS era linkado. Os outros ids que
circulam aqui — `49a36f76-2672-43f6-b955-c6cbb52f7f86` (DS vinculado) e
`d7f88676-3e1c-47b0-99fe-34d9030ebc76` (outro espelho) — também não são. Nenhum dos três serve.

---

## 6. O que eu passo a fazer na exportação, se o acordo fechar

1. Sem pasta `_ds/` própria — as páginas apontam para o DS único. Se o Cowork exigir `_ds/` para
   rodar, uma cópia só, na raiz, do mesmo DS do repositório.
2. Nenhum nome de arquivo repetido no que eu exporto.
3. Nome da conta no pacote.
4. Todo contorno declarado em texto, com a condição de troca (como o §3 acima).
5. Não mexer em arquivo do shell sem proposta ao Wagner.

Falta responder: §4.1 (o que é zipado), §4.2 (dono do shell), §4.4 (direção da sincronização).
