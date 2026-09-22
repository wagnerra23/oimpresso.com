# Pedido ao projeto do design system

Para colar no chat do projeto **WAGNER Office Impresso — Design System**
(`49a36f76-2672-43f6-b955-c6cbb52f7f86`).

---

Estou montando o diretório compartilhado entre o Felipe, o Wagner e o design system, e preciso
fechar o que vem de vocês. Antes de pedir, o combinado, porque ele muda o formato da entrega.

## O modelo acordado

Três pastas no mesmo repositório, cada uma com um dono:

- `prototipo-ui/cowork/Wagner` — as telas do Wagner
- `prototipo-ui/cowork/Felipe` — as telas do Felipe
- `prototipo-ui/design-system` — o DS, **um só**, usado pelos dois

**A regra que vale** (substitui a antiga "conteúdo igual é proibido", que pegava os casos
inofensivos e deixava passar os perigosos):

> Arquivo compartilhado existe **num lugar só**; os outros apontam para ele pelo caminho.
> As pastas de entrega (`handoff_*`) são **geradas na exportação**, não guardadas.

Dois fatos de execução, medidos do lado do Claude Code: **o .zip é sempre o projeto inteiro** (então
apontar para a raiz funciona), e **nada entra no repositório sem PR aprovado pelo Wagner**.

E o método de conferência mudou: comparação **em bytes, ignorando quebra de linha**. Comparar bytes
crus dá falso positivo — os arquivos do `erp-shell-v2` estão em CRLF e os da raiz em LF, e isso
sozinho produziu uma lista de 32 "duplicatas" com 16 falsas.

## O que eu já trouxe daqui (para não pedir duas vezes)

Copiei em 21/09/2026: `components/` (49 pastas, 148 arquivos `.jsx` + `.d.ts`), `_ds_bundle.js`,
`colors_and_type.css`, `styles.css`, `cockpit_domains.css`, `_ds_manifest.json`,
`_adherence.oxlintrc.json`, `assets/` (marca + 7 fontes IBM Plex), `templates/` (os 7),
`ui_kits/` (app + site), `Norte/`, `support.js`, e os documentos `README.md`, `HANDOFF.md`,
`NOTAS_INTERNAS.md`, `SKILL.md`, `github.md`, `HANDOFF-2026-08-31-tabbar-pageheader.md`,
`PENDENCIAS-WAGNER-2026-09-09.md`, `PENDENCIAS-PR-7096.md`,
`CODE_NOTES-2026-09-17-import-recusado-r4.md`.

## O que eu preciso

**1. O `CLAUDE.md` do projeto do DS.** A cópia foi recusada (caminho reservado). Cole o conteúdo, ou
diga que ele não deve ir para o pacote.

**2. Confirmação de que o espelho que eu tenho é o corrente.** Regenerei em 21/09 e medi:

| arquivo | bytes |
| --- | --- |
| `_ds_bundle.js` | 346.587 (+ 7 linhas de alias, ver item 3) |
| `colors_and_type.css` | 19.852 |
| `styles.css` | 1.359 |
| `cockpit_domains.css` | 139 linhas, 62 tokens light + 60 dark |
| `_ds_manifest.json` | 48.472 |
| componentes no bundle | **59** |

Se algum desses números não bate com o que está aí agora, me diga qual e eu regenero.

**3. O nome global — resolvido em 21/09/2026, sem pedir nada ao DS.**

O bundle gerado publica `window.OfficeImpressoPontoWR2DesignSystem_019dd0`. O espelho anterior
(09/09) publicava `window.OfficeImpressoDesignSystem_49a36f`, e era esse o nome que as páginas do
protótipo liam. O contorno era um alias de 7 linhas no fim do espelho do bundle — que **toda
regeneração apagava em silêncio**.

**O contorno foi removido e as páginas migraram para o nome publicado** (6 arquivos:
`Consulta de Produtos.dc.html` e os cinco `manufacturing-*.jsx`). O espelho agora é cópia byte a
byte do DS vivo, sem acréscimo nenhum — não há mais o que perder numa regeneração. **Nada é pedido
ao DS neste item.**

Teste de aceite: no console, `typeof window.OfficeImpressoDesignSystem_49a36f === 'undefined'` e
`Object.keys(window.OfficeImpressoPontoWR2DesignSystem_019dd0).length === 59`.

**4. O que ainda não trouxe, por não saber se deve entrar no pacote compartilhado:**
`arquivo/`, `preview/`, `memory/`, `public/`, `prototipo-ui/`. Digam quais entram.

**5. Duas decisões que estão travando o combinado:**

- **Quem é dono do shell** (`oimpresso.com.html`, `app.jsx`, `data.jsx`, `sidebar.jsx`, as ~250
  páginas). Hoje ele está inteiro na raiz do projeto do Felipe, e existe em duas gerações com
  conteúdos diferentes — `app.jsx` tem 39.697 bytes na versão publicada e 74.179 na viva. Se o
  Felipe e o Wagner exportarem cada um a sua, a colisão volta no mesmo commit.
  **Minha recomendação:** o shell vai para `design-system/`, não para `Wagner/` — o Felipe consome o
  shell do mesmo jeito que consome o DS, e o Wagner já decide os dois. Em `Wagner/` ele parece tela,
  e tela é o que cada um edita na própria pasta.
- **A emenda ao ADR 0397:** trocar "bytes iguais" por "um lugar só". É regra do Wagner; só ele muda.

**6. Como resolver os 35 conflitos que já existem.** São arquivos com o mesmo nome e conteúdo
diferente — o caso que nenhuma regra de conteúdo pega. O combinado diz como **detectar**, não como
**resolver**: quando `app.jsx` tem 39.697 numa pasta e 74.179 na outra, qual sobrevive? Sem uma
regra ("vence a viva", "vence a publicada", "caso a caso"), a primeira importação para em 35
decisões manuais.

## Duas correções minhas, para não propagarem

Errei duas vezes hoje sobre este DS, e as duas viraram afirmação em documento antes de eu remedir:

1. **Disse que `cockpit_domains.css` não existia.** Existe — 139 linhas, geradas de
   `semantic.tokens.json` por `ds-domains-companion.mjs`. Eu tinha em mãos um stub vazio criado por
   mim e concluí a ausência a partir dele. Restaurado.
2. **Disse que a pasta `_ds/…019dd02f…` era improviso meu.** Ela pré-existia desde 09/09 e era a que
   efetivamente rodava. O bundle dela era o do `49a36f` mais o shim de alias — mesmo conteúdo, mesmo
   namespace. **`019dd02f` e `49a36f76` nunca foram dois design systems**, e não há divergência a
   decidir aí.
