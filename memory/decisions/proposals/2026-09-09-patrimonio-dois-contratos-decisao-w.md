---
proposal_id: patrimonio-dois-contratos
status: open
created: 2026-09-09
proposed_by: claude-code
decided_by: wagner
title: "Patrimônio — dois contratos para a mesma tela: um é gate, o outro é fonte de design"
module: AssetManagement
type: conflito-de-artefato
related_prs: [7133, 7139, 7040, 7143]
decisoes_resolvidas: [1]
decisoes_abertas: [2, 3, 4, 5]
---

# Dois contratos, uma tela — o que convive, o que cede, e o que sangra agora

> **Pedido que originou:** o contrato do Cowork desceu no [#7133](https://github.com/wagnerra23/oimpresso.com/pull/7133) e agora há dois contratos descrevendo o Patrimônio, que não concordam.
> **Resposta curta:** eles **convivem**. O do Cowork chegou num lugar da árvore que o gate varria, e por isso reprovou o CI entre 18:40 e 19:21 de 2026-09-09 — **isso já está consertado** (ver §1-bis). O que resta é reconciliação de conteúdo, e é decisão sua.

---

## 1-bis. O desbloqueio JÁ FOI FEITO — por outra sessão, no [#7143](https://github.com/wagnerra23/oimpresso.com/pull/7143)

> Registro de 2026-09-09, depois de [W] autorizar a opção 1. Ao medir a base fresca antes de
> escrever a primeira linha, o vermelho **não existia mais**: `--map --check` e
> `--contract` no arquivo do Cowork saíam **exit 0**.

O commit `d4f7652743` (#7143, commit date 19:21) fez exatamente a opção 1, e **mais amplo** do que eu proporia:

```js
const PASTAS_DOC_DESIGN = ['prototipo-ui/design-docs/', 'prototipo-ui/cowork/Wagner/'];
```

— a pasta inteira do espelho, não só `cowork/contrato/`. E trouxe o bite-test junto, com controle negativo, que é a parte que impede o filtro de virar carimbo:

```
[OK] --contract prototipo-ui/cowork/Wagner/contrato/x.contract.json → exit 0 (pulado)
[OK] --contract governance/design/contracts/x.contract.json        → exit 1 (REPROVA)
```

Rodei `node scripts/contrato-de-tela.test.mjs`: **todos os controles passam**. Nenhum PR meu foi
aberto — seria duplicata ([LC-19](../../LICOES_CODE.md)). **A decisão 1 do §5 está resolvida; as
decisões 2 a 5 seguem abertas**, e são o valor que sobra desta proposta.

⚠️ O que segue abaixo é o **retrato da medição das 18:40-19:11**, preservado como o diagnóstico
que motivou a decisão. Ele descreve o que era verdade naquela janela, não o estado de agora.

---

## 1. O que sangrava na janela 18:40-19:21 (isto não é opinião — é run de CI)

O job `Preflight + contratos ativos` do workflow `Contrato de Tela` **falhou** no push do #7133:

```
== prototipo-ui/cowork/Wagner/contrato/patrimonio.contract.json ==
X contrato sem `alvo` (array) ou `secoes` (array)
##[error]Process completed with exit code 1.
```

— run [34390410727](https://github.com/wagnerra23/oimpresso.com/actions/runs/34390410727), step `Contratos de tela ativos`.

**Por que atinge todo mundo, e não só o Patrimônio.** O job descobre contratos com `git ls-files '*.contract.json'` — repo inteiro — e o gatilho `detect` casa qualquer `.contract.json` **e** qualquer `resources/js/Pages/**.tsx`. Ou seja: **todo PR que toque qualquer tela** roda esse step e o encontra vermelho. O filtro de exclusão existente (`ehDocDesign`) cobre só `prototipo-ui/design-docs/` — e o arquivo desceu em `prototipo-ui/cowork/Wagner/contrato/`, fora dele.

⚠️ **As duas runs verdes que vieram depois não desmentem isto.** Medi os steps: elas estão `skipped` (skip-as-pass, porque eram `docs`/`chore`). Verde por não ter rodado, não por passar.

O job é **advisory** — medido em 2026-09-09 contra a união `classic_protection ∪ rulesets` de [`required-checks-baseline.json`](../../../governance/required-checks-baseline.json) (46 contexts; o único que casa "tela" é `Nota de tela não desce vs origin/main`, que é outro gate). Advisory não bloqueia merge; **continua sendo vermelho permanente**, e a doutrina do projeto sobre isso já é [W]:

> *"Contrato vermelho permanente treina o time a ignorar gate — custo maior que a ausência do contrato."*
> — [proposal `ponto-contratos-retidos`](2026-08-21-ponto-contratos-retidos-decisao-w.md) §7, decisão [W] 2026-08-21 (opção B)

**Isto já aconteceu uma vez.** Em 2026-08-24, 3 arquivos `.contract.json` do Cowork desceram e reprovaram pelo mesmo motivo. O comentário do próprio gate registra o veredito: *"são de OUTRO schema, legítimos como proposta e inválidos como contrato do repo"* — e a solução foi excluir a pasta da varredura, não converter os arquivos.

---

## 2. O que cada arquivo é — medido, não suposto

| | **A) repo** `contrato/patrimonio-index.contract.json` | **B) Cowork** `cowork/contrato/patrimonio.contract.json` |
|---|---|---|
| Nasceu | 2026-09-08 (#7040), do `criar-tela.mjs` | 2026-09-09 (#7133), pousado por máquina |
| Granularidade | 1 tela (`Patrimonio/Index`) | 1 módulo (9 seções, 5 telas) |
| Roda no gate | ✅ **exit 0, 4 seções OK** | ❌ **exit 1** (em `--contract` e em `--map --check`) |
| `alvo` | ✅ presente | ❌ ausente — é o campo que reprova |
| `fonte` | ✅ `patrimonio-page.jsx` | ❌ ausente (`--map` diz *"fonte aponta arquivo inexistente"*) |
| `$schema` | (implícito, `contract.schema.json`) | ❌ aponta `contrato-tela.schema.json` — **arquivo que não existe** |
| Copy | 4 seções, **todas vazias** por `_pendente_w` | copy literal, colunas, ações de linha, regras R1-R10, a11y, permissões |

**A incompatibilidade de schema não é um detalhe de forma.** O `contract.schema.json` declara `additionalProperties: false` **dentro de cada seção** — só `id`, `copy`, `estados` são aceitos. O contrato do Cowork usa 18 chaves extras por seção (`seletor`, `obrigatoria`, `elementos`, `colunas`, `acoesLinha`, `lote`, …) e, em `header`, `copy` é **objeto** onde o schema exige array de string. Converter B para o formato A **descarta a maior parte do que faz B valioso**.

Isso é o argumento mais forte a favor de **conviverem**: eles não são duas versões da mesma coisa. A é uma **catraca** (âncora + copy + ordem, verificável sem render); B é uma **especificação de design** (seletor, regra, permissão, a11y). O schema de A é estreito de propósito.

---

## 3. As tensões, com o custo de cada uma medido

### (a) ENDEREÇO — o do Cowork aponta para uma pasta que não existe

`resources/js/Pages/AssetManagement/` **não existe**. As telas estão em `Pages/Patrimonio/**` por [ADR 0394](../0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md) — `status: aceito`, `decided_by: [W]`, 2026-09-08. O charter real é `Pages/Patrimonio/Index.charter.md`.

**Custo de corrigir: 2 campos** (`charter`, e `modulo` se quiser). **Quem cede: o Cowork** — não há tensão real aqui, há uma decisão [W] posterior ao protótipo que ele ainda não conhece.

### (b) GRANULARIDADE — e aqui o repo **já tem resposta praticada**

Medi os 30 contratos ativos sobre 18 fontes: **5 protótipos já servem múltiplos contratos** — `fiscal-page.jsx` para 4 telas, `superadmin-page.jsx` para 4, `fiscal-subpages.jsx` para 4, `jana-telas-novas.jsx` para 3, `ponto-page.jsx` para 2.

O padrão consolidado é **1 protótipo de módulo, N contratos de tela**. Não é questão em aberto: é o desenho vigente, com 5 precedentes. O Cowork trouxe o formato inverso (1 arquivo, N telas), que o gate não sabe ler.

**Custo de seguir o padrão: 4 contratos novos** (Bens, Alocações, Manutenções, Configurações) derivados de B. **Quem cede: o Cowork**, na forma — sem perder conteúdo, porque B continua existindo como fonte.

### (c) COPY — 75% já bate; o gate morde nos 25%

Gerei os 5 contratos-candidatos derivados de B e rodei o **gate real** neles (não uma reimplementação). Resultado: **52 de 69 strings já presentes (75%)**, **17 ausentes**. E as 17 se dividem em três naturezas que não devem ser tratadas igual:

| Natureza | Exemplos | O que significa |
|---|---|---|
| **Ausência real** (funcionalidade não existe) | `Todos`, `Alocáveis`, `Editar bem`, `Excluir bem`, `Exportar seleção`, `Prestador`; drawer `Resumo` / `Depreciação` / `Histórico` | virar gate = vermelho até construir |
| **Vocabulário divergente** | Cowork `Ação` contra tela `Ações`; Cowork `Prefixo do código do **ativo**` contra tela `…do **bem**` | escolher um rótulo é decisão de copy, logo [W] |
| **Divergência que vale olhar** | Cowork `revogação` contra tela `devolução` | **o backend usa "Revogar"** — 8 chaves em `Modules/AssetManagement/Resources/lang/pt/lang.php`. A tela React divergiu do backend **e** do protótipo ao mesmo tempo. Não há dicionário de domínio para Patrimônio (`memory/dominio/` tem 6, nenhum de asset), então nenhum gate arbitra isso hoje |

⚠️ **Limite honesto da medição:** o mapa seção-do-Cowork para tela-do-repo é **meu**, declarado, não derivado — B não diz a que tela cada seção pertence. Prova de que ele erra em pelo menos um ponto: `Devolvido` foi contado como ausente em `manutencoes`, mas existe — em `Alocacoes.tsx`. O número real de ausências é **menor ou igual a 17**.

### (d) ABAS — o charter está certo e o protótipo está adiantado

B lista 7 abas (com Garantias e Auditoria). O charter as declara Non-Goal, e a razão é boa: a subnav **deriva** do `shell.menu`, então elas simplesmente não aparecem — *"renderizar aba que não navega é afordância falsa"*, decisão fundada na tela de Bens (`D-GARANTIAS` / `D-AUDITORIA`).

**Não proponho mexer nisso.** É o protótipo estando à frente do backend, não um defeito. A seção `auditoria` de B é a única sem tela correspondente.

### (e) VOCABULÁRIO DE ID — a tensão que não estava na lista

Os dois usam nomes diferentes para as mesmas seções: A, na `Index.tsx`, ancora `cabecalho · subnav · resumo · kpis · analises · acoes · meus-bens`; B declara `header · abas · painel · bens · drawer · auditoria · config`. **Zero interseção.**

E: só a `Index.tsx` tem âncoras `data-contract`. **Bens, Alocacoes, Manutencoes e Configuracoes têm zero** — qualquer contrato para elas exige instrumentar as telas primeiro.

---

## 4. As opções, com custo

| | O que é | Custo | Efeito no CI |
|---|---|---|---|
| **1. Convivem, com B fora da varredura** ✅ **FEITA** no [#7143](https://github.com/wagnerra23/oimpresso.com/pull/7143) | B vira fonte de design declarada (como `design-docs/`); A segue sendo o gate. Estendeu `ehDocDesign` para cobrir `prototipo-ui/cowork/Wagner/` — o mesmo remédio de 2026-08-24 | 1 linha mais bite-test | 🟢 fechou às 19:21 |
| **2. Convivem, e B ganha os 4 contratos irmãos** | Opção 1 **e** derivar `Bens` / `Alocacoes` / `Manutencoes` / `Configuracoes.contract.json` de B, seguindo o padrão de 5 precedentes | 1 linha, 4 contratos, e **instrumentar 4 telas com `data-contract`** | 🟢 fecha, e a cobertura sobe de 1 para 5 telas |
| **3. Viram um só (B convertido ao schema de A)** | Reescrever B em N contratos no formato estreito | descarta seletor, regras R1-R10, a11y, permissões — **perde o que B tem de melhor** | 🟢 fecha, ❌ perde conteúdo |
| **4. Ampliar o schema de A para aceitar B** | Trocar `additionalProperties: false` por permissivo e ensinar o gate a ler `seletor` / `colunas` | gate novo sobre 30 contratos vivos; FP não medido | ⚠️ risco alto, sem sinal que justifique |
| **5. Reter B (tirar do repo)** | O que [W] decidiu em 2026-08-21 para o Ponto | perde o handoff que acabou de descer sem transcrição | 🟢 fecha |

**Recomendei 1 agora, 2 depois; [W] aprovou, e a 1 já estava feita pelo #7143** (§1-bis). A opção 1 era o precedente literal de 2026-08-24 aplicado à pasta nova, para uma classe de arquivo que o próprio gate já classificou como *"legítimo como proposta, inválido como contrato do repo"*. **A opção 2 segue aberta** — é o trabalho de verdade e cabe em PR próprio, por tela.

**O que NÃO recomendo:** apontar o gate para B (ele não tem `alvo`, e dar-lhe um `alvo` de 5 telas quebra a granularidade que 5 precedentes estabeleceram) e ligar a copy como gate hoje (17 vermelhos, dos quais parte é decisão de copy sua, não código faltando).

---

## 5. As decisões [W] — nomeadas

1. ~~**O desbloqueio do CI: opção 1, ou reter B?**~~ ✅ **RESOLVIDA** — [W] escolheu a opção 1, e ela já estava mergeada no [#7143](https://github.com/wagnerra23/oimpresso.com/pull/7143) quando fui executar (§1-bis). Nenhum PR foi aberto.
2. **`revogação` ou `devolução`?** O backend diz *Revogar* (8 chaves de lang), o protótipo diz *revogação*, a tela React diz *devolução*. Um dos três está errado, e não é decisão de engenharia. Se você escolher, eu alinho os outros dois no mesmo PR.
3. **`Prefixo do código do ativo` ou `do bem`?** Mesma natureza, menor consequência.
4. **A copy vira gate agora?** Se sim, aceite que ela nasce com até 17 vermelhos — e a maior parte é funcionalidade a construir (`Exportar seleção`, drawer de `Depreciação`), não texto a corrigir. Minha leitura: fica **declarada e inerte** até as telas 08-12 existirem, e sobe junto com cada uma.
5. **Ampliar o `contract.schema.json` (opção 4)?** Só se você quiser que o gate passe a verificar seletor, coluna e ação. Hoje não há sinal pedindo isso, e mexer no schema toca 30 contratos vivos.

---

## 6. O que eu faço assim que houver resposta

- ~~**(1)** estendo o filtro mais selftest provando que morde~~ — **já feito pelo #7143**, com o bite-test e o controle negativo (§1-bis). Nada a fazer.
- **(2)** derivo os 4 contratos irmãos e instrumento as telas com `data-contract`, **um PR por tela**, cada um descendo contrato e âncoras juntos — que é o que mantém o gate verde;
- **(2 ou 3)** corrijo `charter` e `modulo` em B para `Pages/Patrimonio/**` ([ADR 0394](../0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md)) — **exceto** se a rota canônica de espelho reescrever o arquivo, caso em que a correção nasce no Cowork e desce.

**Não toquei em nada.** Não movi charter, não apontei o gate para B, não adicionei Garantias nem Auditoria, não rodei codemod. A medição foi feita num worktree descartável de `origin/main` (já removido), porque este checkout está 448 commits atrás.

---

### Como reproduzir cada número

```bash
node scripts/contrato-de-tela.mjs --contract governance/design/contracts/patrimonio-index.contract.json
node scripts/contrato-de-tela.mjs --contract prototipo-ui/cowork/Wagner/contrato/patrimonio.contract.json
node scripts/contrato-de-tela.mjs --map --check
gh run view 34390410727 --log-failed
```

Refs: [#7133](https://github.com/wagnerra23/oimpresso.com/pull/7133) (trouxe B) · [#7040](https://github.com/wagnerra23/oimpresso.com/pull/7040) (trouxe A) · [#7139](https://github.com/wagnerra23/oimpresso.com/pull/7139) (baseline) · [ADR 0394](../0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md) · [proposal ponto-contratos-retidos](2026-08-21-ponto-contratos-retidos-decisao-w.md) · ADR 0286 (Contrato de Tela) · ADR 0261 (always-run)
