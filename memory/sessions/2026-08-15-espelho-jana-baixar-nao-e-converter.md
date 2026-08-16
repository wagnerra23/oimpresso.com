# Baixar não é converter — o espelho da Jana, 7 semanas atrás

**2026-08-15** · handoff pareado: [`2026-08-15-2035-jana-espelho-defasado-ciclo-e-9-prs`](../handoffs/2026-08-15-2035-jana-espelho-defasado-ciclo-e-9-prs.md)

Session log conta o TRABALHO; o handoff conta o ESTADO. Este registra as decisões e o que foi medido para chegar nelas.

---

## O pedido, e o que ele virou

*"pode testar a aplicação do protótipo do Jana? primeiro abra a tela para eu ver a ancora que esta sendo usada. eu quero aprovar"*

A âncora é `prototipo-ui/cowork/jana-merge.jsx` — resolvida por `node prototipo-ui/ancora.mjs Jana/Index`, declarada no `related_prototype` do charter v6. **Aprovada por [W].**

Aí ele olhou o próprio Cowork e apontou dois elementos que meu render não tinha: o fundo do card "a receber" e o **botão "plano Pro"**. Perseguir isso consumiu a sessão e produziu 9 PRs.

## Decisões, e por que cada uma

### 1. Não apagar/mover nada do espelho — [W] pediu, medição barrou

*"apague todos os errados"* / *"ou mova"*.

Medido antes de tocar: `chat-jana.jsx` é carregado pelo shell (`oimpresso.com.html:82`) e consumido por `app.jsx` **e** `jana-merge.jsx` — define `JanaHeader` e `JcIcon`. Apagar ou mover **quebra a âncora que ele acabara de aprovar**. E o diagnóstico não era "arquivo errado sobrando": era arquivo **certo em versão velha**.

Levantei a objeção, ele redirecionou, ninguém apagou nada.

### 2. Não construir a camada de conversão — morreu na medição

Eu ia propor uma camada que reaplicasse consertos locais após cada `--export-from`, para o P-3 do #5738 não evaporar. **Não construí**, e a razão é que a medição derrubou a premissa:

| token | vivo (`var(--*-soft)`) | espelho (`color-mix` #5738) |
|---|---:|---:|
| `neg` | **4.32** | 4.26 |
| `pos` | 5.09 | 5.08 |
| `warn` | 5.61 | 5.68 |

Deltas ≤ 0,07, medidos no **elemento renderizado**, com controle preto/branco = 21. O "conserto" que eu ia proteger é ruído — e o `neg` reprova AA (4,5) nos dois. Havia **1 caso** para justificar a máquina, e ele não se sustentou.

Complemento: two-strikes (ADR 0344) — 1ª ocorrência conserta, não codifica.

### 3. Aceitar que a rotina de frescor é manual — e declarar

O `--compare` exige snapshot do `get_file` (`rc=2` sem ele) e essa auth é interativa. **Zero workflows usam `DesignSync`** — os 2 hits em `.github/` são comentários explicando que não dá. Dar cron seria fabricar um executor que não autentica.

Decisão: aceitar + registrar em `AUTOMATIONS.md` (#5814). O que estava errado não era o desenho — era a ausência ser **implícita**.

### 4. Não somar 4 asserts ao selftest — já existiam

[W] pediu teste persistente com casos de uso do ciclo de download. Fui criar e encontrei os quatro cobertos: linha 168 (`repoHash = contentHash(bytes)`), 341 (*"conteúdo passa INTACTO"*), 389 (hash do snapshot), 53-56 (SYNC/STALE). Somar seria ruído sobre contrato já escrito melhor — inclusive com **anti-tautologia** (linha 271) que eu não tinha pensado em cobrir.

**A garantia existia. Eu é que não a usei** — reimplementei o `contentHash` à mão e obtive hash errado.

### 5. Baixar os 97 — não, e o número diz por quê

```
174 arquivos · 4,72 MB · média 28 KB
só leitura: ~1,4M tokens     escrever dobra → ~2,8M
maior: styles.css, 209 KB (~60k tokens sozinho)
```

Cada arquivo passa pelo contexto duas vezes. O limite não é budget — é contexto. As **10 âncoras** (598 KB) cabem numa sessão dedicada; os 164 `dep` não.

## O que a medição corrigiu em mim

**A distinção do [W] que destravou tudo:** *"baixar deve baixar, a conversão é mais um passo depois de baixar tudo"*. Eu tratava download e transcrição como a mesma coisa, e por isso travava; ele separou os passos e o caminho abriu.

**Teste de identidade** como prova de fidelidade: escrever o conteúdo do vivo e diffar contra o espelho. Se a escrita corrompesse, apareceria ruído aleatório. Não apareceu — 34 linhas no `.css`, 68 no `.jsx`, todas semanticamente coerentes.

E o `.jsx` derrubou minha própria medição: eu havia dito *"13 linhas, só o `JanaHeader`"* olhando o bloco que **eu escolhi** olhar. O delta real trazia `BriefDiario` (+`onChip`/`onAudio`), `AcaoRow` (+`onCta`) e o `send()` inteiro (5 ramos regex). Remendar deixaria **55 linhas de fora, em silêncio** — que é exatamente o argumento do [W] virando número.

## Um falso alarme que vale registrar

O `compras-page.jsx` do vivo chegou com `m\\u00f3dulo` e eu quase tratei como corrupção do transporte. Era o **JSON escapando a barra**: o espelho tem o mesmo `ó` literal (3 ocorrências). Se eu tivesse "consertado" ao escrever, **eu** é que corromperia — a transcrição errando por parecer que conserta.

## Pendências reais

- **BRL scan** vai reclamar em todo download futuro do protótipo (os mocks têm valores). O #5816 mergeou com ele vermelho por decisão [W]; propagar ou tratar caso a caso segue aberto.
- **97 arquivos** sem veredito — método provado, volume é que não cabe.
- **`.jc-updated-b` não existe em CSS nenhum** — o botão que desceu no #5816 chega sem estilo. Gap do Cowork vivo.
