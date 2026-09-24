# Retratação — o pedido das 3 props está retirado

**Para:** Wagner e Luiz
**Assunto:** retirada da proposta anterior deste arquivo, com a medição que a derrubou
**Data:** 09/09/2026
**O que eu preciso de vocês:** nada de produto. Um push do DS para o espelho, se e quando fizer sentido.

Este arquivo pedia aprovação de 3 props no `DataTable` (`stickyHeader`, `minWidth`, `rowLabel`) e
classificava a terceira como **defeito do DS**. **Está retirado.** Vocês estavam certos em não
classificar como defeito.

---

## 1 · O que eu media, e o que eu deixei de medir

Eu medi o **espelho compilado** (`_ds/wagner-office-impresso-design-system-49a36f76-…/_ds_bundle.js`,
9.290 linhas) e concluí de lá. Não abri o repo — com a pasta `oimpresso.com` anexada na sessão.
A regra do próprio DS diz o contrário: **git é SSOT, `claude.ai/design` é NÃO-fonte** (ADR 0239,
ADR 0315 Eixo A, ADR 0299). Conclusão tirada do espelho sobre o que "o DS não tem" não vale.

## 2 · O que o DS e o repo já têm — medido em 09/09/2026

**Correção mais importante desta página, achada depois da primeira retratação:** o teclado na linha
**já está no espelho do DS que o protótipo consome** — `_ds_bundle.js` **L3041-3052**: `tabIndex`,
`role="button"`, `aria-label`, Enter e Espaço. Não havia bloqueio nenhum, nem prop a pedir, nem
espera. A onda 1 estava travada por um bloqueio que eu inventei. O que faltava era o protótipo
**usar o componente** em vez de desenhar `div` à mão (`manufacturing-page.jsx` L251).

| O que eu chamei de falta | Onde já existe |
|---|---|
| Linha de tabela sem teclado (B-01) | **espelho do DS L3041-3052** · **e** repo `shared/DataTable.tsx` **L370-381**. Os dois fazem a mesma coisa. O docblock do repo **L161-162** já declara a razão: *"linha clicável que só responde ao mouse é armadilha de teclado, e o DS não tem por que produzir uma"* |
| Largura mínima da tabela | repo: prop **`minTableWidth`** (L173, aplicada em L290). **No espelho, vem do CSS do módulo** — que é como o próprio repo faz: o L173 cita a tela declarando `min-width` no CSS dela. **Não é prop faltando; é CSS de tela** |
| Cabeçalho fixo (`stickyHeader`) | **nenhum dos dois tem, e nenhum precisa** — o `thead` do repo (L304) também não é sticky. É CSS de tela, via `tableWrapperClassName` (L177-186) no repo |
| Nome acessível da linha — o `cli` cravado que eu chamei de defeito (B-02) | **a afirmação estava errada duas vezes.** (1) Com `role="button"` e sem `aria-label`, o nome acessível é **computado do conteúdo** da linha: fica **verboso, não ausente**. (2) O repo resolve outro problema — nome da **tabela**, via **`caption` obrigatório** (L126-142), com medição axe-core 4.12.1 de 2026-09-04 provando que **o axe não tem regra que exija nome acessível em tabela**, e por isso o teste mede o **nome computado** (`getByRole('table', { name })`) |
| Teclado de seta nas abas (C-01) | repo `shared/PageHeaderTabs.tsx` **L146-220** — `role="tablist"`, `role="tab"`, `tabIndex` roving, `onKeyDown` com ←/→ |

**Três consequências desconfortáveis, ditas em voz alta:** eu ia pedir aprovação de três coisas que já
estavam implementadas; o item que classifiquei como defeito já estava resolvido com medição mais
rigorosa que a da minha auditoria; e **o único item de acessibilidade que era real não dependia de
ninguém** — bastava usar o componente do DS.

## 3 · O que a situação é, então

**`ds-mirror-drift`.** O espelho compilado que o protótipo consome está atrás do repo. O mecanismo
para isso já existe e já está nomeado no DS: push **git→design** (`scripts/design-sync/ds-push.mjs`,
gate VALOR:0, sentinela `ds-mirror-drift`). A direção design→git é opt-in de vocês, com triagem.

**Portanto:** nenhuma proposta ao DS, nenhuma prop nova, nenhuma decisão de produto. O que resolve é
um push do espelho — e, até ele acontecer, o protótipo carrega uma limitação de espelho **declarada
como tal**, não uma divergência de design.

Os dois itens que eu **não** verifiquei e que por isso não afirmo mais nada sobre:

- **Live region / `Toast` (C-02).** O repo tem `role="status"` em `Components/ui/field-state.tsx`
  L27 e L37, e a camada de toast é sonner (que traz região de anúncio própria) — **não confirmei o
  install**, então não classifico. Retiro a afirmação de que "o sistema não anuncia nada": ela vale
  para o espelho, não para o produto.
- **Teclado de seta nas abas no espelho.** O repo tem (L146-220). Não remedi o `TabBar` do espelho
  depois de descobrir o padrão do `DataTable` — dado que errei exatamente aqui, trato como **não
  medido**, não como falta.

## 4 · O que sobra da auditoria, e é pouco

**Sobra inteiro** (não tem relação com o DS — é dos nossos arquivos):

1. **O pacote de handoff está velho.** `handoff_fabricacao/design/`: 5 dos 6 `.jsx` divergem da raiz
   (medido byte a byte) e o `Fabricacao - Guia de Producao.html` **não carrega `_ds_bundle.js` em
   nenhuma tag**. Quem implementar lendo o pacote reconstrói a versão anterior às 23 correções da
   onda A. *Aceite:* `diff` dos 6 pares volta vazio e `grep -c "_ds_bundle"` no guia volta ≥ 1.
2. **Rede de proteção.** `manufacturing-page.jsx` L48 desestrutura 12 componentes na primeira linha
   do render; se o bundle não carregar, `ds()` devolve `{}` (L15) e o módulo cai em *"Element type is
   invalid"*. *Aceite:* com bundle ausente, a tela informa a falha e o resto do app fica de pé.
3. **CSS da tela fora da rampa/canon:** 20 seletores fora de `--fs-*`, 3 raios fora do canon,
   6 seletores + 4 usos inline de `--accent` como texto. *Aceite:* `grep` dos valores volta 0 no que
   for trocado, e o que ficar está declarado no handoff com motivo.

**Muda de natureza, e fica trivial:** as 4 tabelas do protótipo continuam sem teclado — mas a
correção **não depende de ninguém e não espera nada**. É trocar a `div` por `DataTable` do espelho
(que já traz teclado, L2996-3005) e dar a largura mínima pelo CSS do módulo, como o repo faz.
Não há dilema 1A/1B/1C: eu inventei os três caminhos em cima de um bloqueio inexistente.
*Aceite:* Tab chega em toda linha, Enter e Espaço abrem o drawer, e a rolagem horizontal continua
nas 4 larguras atuais (960 / 1100 / 900 / 940 px).

**Precisa ser remedido antes de ir a qualquer lugar:** as listas **B e C** da
`auditoria-aderencia-fabricacao-v2.md` foram levantadas contra o espelho. Todo item nelas —
`Input` sem `ref`/`step`, `Breadcrumb` sem `onSelect`, `Button` sem `dashed`/`link`, `EmptyState`
compacto, ausência de `Slider`/`Card`/`Separator`/`KeyValue`/rodapé de tabela/combobox — tem a mesma
chance de já existir no repo. **Nada disso vai para `pauta-design-system.md` antes de eu remedir
contra `oimpresso.com/resources/js`.** Não tratem nenhum item daquelas listas como falta do DS até lá.

## 4-bis · Confronto de citações — resolvido em 09/09/2026

Contestei quatro citações da usuária. **Ela estava certa nas quatro, e a prova que eu apresentei
contra ela era um artefato de ferramenta.**

**O que aconteceu:** eu media o espelho local `_ds/`, que estava **defasado**, e o confirmava com
`grep` em caminho cross-project — que **retorna vazio mesmo quando o termo está no arquivo**. Os
dois erros se somaram: o espelho velho não tinha `caption`, e o `grep` "provou" que a fonte viva
também não tinha. Li um vazio de ferramenta como medição.

| Citação | Eu afirmei | Medido na fonte viva | Veredito |
|---|---|---|---|
| declaração do `DataTable` | L2894 | **L2926** (`function DataTable({`), com `caption` na L2930 | ela |
| teclado da linha | L2996-3005 | **L3041-3052** (L3041 abre o `<tr>`, L3043-3045 = `onClick`/`tabIndex`/`role:'button'`) | ela |
| `caption` no DS | "não existe em lugar nenhum" | **existe**: `components/DataTable/DataTable.d.ts` **L43** `caption: string;` não-opcional, com o docblock explicando por que é obrigatório; `DataTable.jsx` **L57-60** renderiza o `<caption>` oculto; 8 ocorrências no bundle | ela |
| `cli` cravado | L2989, depois "corrigido" para L2993 | **L3040** | nenhum dos dois |

**As 32 linhas de diferença entre 2894 e 2926** são o bloco `caption` + `scope="col"` + `aria-sort`
que entrou no sync de **2026-09-09T11:18Z**, registrado no `github.md` do DS como *"DataTable — nome
acessível (caption) + scope=col"*. Meu bundle era anterior a ele.

**Portanto não há drift DS↔repo no `caption`** — os dois têm, e o DS desde hoje. O drift era o meu
`_ds/` local. Corrigido nesta sessão: regenerei os dois espelhos a partir da fonte viva (9.301 →
**9.355 linhas**), preservando o shim de alias do `019dd02f` (o arquivo que `oimpresso.com.html`
carrega em L121 — atualizar só o espelho `wagner-…` não muda nada em runtime, e o sintoma é
silencioso). Reconferido depois da regeneração: L2926 `function DataTable({`, L2930 `caption`,
L3041-3045 o teclado da linha, 8 `caption`, 2 `scope: "col"`, 2 `aria-sort`.

**Item 3 — o único em que eu estava certo:** `manufacturing-page.jsx` e `oimpresso.com.html` são
deste projeto, não do DS. O arquivo está na raiz (401 linhas), carregado por `oimpresso.com.html`
**L195**, CSS em **L68**. A onda 1 é trabalho daqui — **e, com o `caption` já no DS, não depende de
prop nenhuma.**

## 5 · As regras que eu quebrei, para constar

`CLAUDE.md` já registra duas retratações minhas por parar de ler cedo, e a régua diz *"'O componente
não tem' nunca é conclusão"* e *"o DS decide por componente E por template"*. Esta sessão pagou duas
pernas novas:

1. **O espelho não decide nada.** Antes de escrever que o DS não tem algo: abrir o repo. Sem repo na
   sessão, a frase certa é "não medi", nunca "não existe".
2. **"Falta prop" também nunca é conclusão.** Eu li o `DataTable` do espelho, vi que ele não tinha
   `stickyHeader`/`minWidth` e classifiquei a onda como bloqueada — sem perguntar de onde o repo
   tira esses dois valores. Tira do **CSS da tela**. Bloqueio inventado vale menos que número
   inventado: ele para trabalho que estava liberado.
