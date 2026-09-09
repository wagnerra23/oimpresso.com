---
modulo: ancora
titulo: ancora.mjs — os vínculos de tela que a máquina não resolve (3 threads no mesmo arquivo)
dono: "[CL]"
base: 752041ac450d
---
# ancora · playbook

## Objeto
`prototipo-ui/ancora.mjs` (**49.089 B**, lido neste turno em `752041ac450d`) é o dono da resolução de âncora; `design-coverage`, `ancora-guard`, `integrity-check` e o hook `post-merge-ui-smoke-required` derivam dele. Auditei os **vínculos** (charter → arquivo de design) e achei **3 defeitos** e **1 limite estrutural**. Nada aqui é sobre o princípio da ferramenta — o princípio está certo (âncora computada do charter, `n/a` classificado como declaração, frescor num eixo separado do conteúdo, selftest com controle negativo). O que está errado é o vínculo chegar ao leitor.

**Não rodei `node`** (sem execução no Cowork): tudo abaixo é leitura de código + conferência dos valores declarados contra os arquivos que existem no `main`. Nenhum veredito de `--selftest`/`--list` é afirmado.

## Lei desta pasta
> **ADITIVO OU NADA, E EM SÉRIE.** As 3 threads escrevem no **mesmo arquivo** (`prototipo-ui/ancora.mjs`) — logo **não são paralelas**: 01 → 02 → 03, cada uma **remedindo o sha antes de escrever**. Nenhuma muda a API pública (`frontmatter`, `repoTsx`, `mockupJsx`, `ehAncoraIlegitima`, `desasparValor`, `ehDeclaracaoNa`, `caminhoDaAncora`, `tokenDeArquivo`, `simbolosCitados`, `defeitosDaAncora`, `entradasDoLedger`, `ultimaRodada`, `frescorDoEspelho`, `resolveAncora`) — o hook `post-merge-ui-smoke-required.mjs:316-322` importa `caminhoDaAncora`/`ehDeclaracaoNa` e **degrada em silêncio** se o import quebrar.

## O que medi (denominador)
- **189** charters em `resources/js/Pages/**/*.charter.md`.
- **~146** declarações de `related_prototype`/`bundle_source`/`visual_source` lidas.
- **Todo** caminho citado que li **existe** no git — inclusive os formatos sujos que o `caminhoDaAncora` cobre (`hrm-extras.jsx (Metas) · herda PT-01`, `vendas-create-page.jsx (ancora medida em 2026-09-07 …)`, `Financeiro - Prova Viva (primitivos).html` com espaços e parênteses, que só passa porque o valor cru é testado **antes** do regex).
- **Não medido ⇒ não verificado:** `kb`, `Modules`, `NfeBrasil`, `Nfse`, `Purchase`, `RecurringBilling`, `Site`, `StockAdjustment`, `StockTransfer`, `Suporte`, `Tarefas`, `TransactionPayment`, `User`, `Vestuario`, `Whatsapp`, `Essentials/{Holidays,Reminders,Knowledge/Index}`, `Manufacturing/{Report,Settings}`. A varredura A→Manufacturing saiu **parcial** (328 de 400 arquivos, budget de 10 s) — baixa contagem ali não é prova de ausência.

## Os 3 defeitos
**D1 · a perna do bundle só existe com `--staging`, e não cai no `LUGAR_FIXO`.** `resolveAncora` guarda todo o bloco do `-page.jsx` em `if (stagingDir)`. Sem a flag, o charter cujo único vínculo é `bundle_source`/`visual_source` imprime *"⚠️ charter sem related_prototype nem -page.jsx — registre o protótipo"* (ou "sem âncora", quando tem `n/a` + bundle) — **enquanto o arquivo está no git, no lugar fixo**. O `--list` foi consertado pra ler esses campos em 2026-08-28; o comando de 1 tela **não foi**: as duas portas do mesmo arquivo discordam. **14 telas medidas** (tabela na thread 01).

**D2 · resolução por query é frouxa e ambígua em silêncio.** `norm()` apaga `/index`, e o loop aceita `comp.includes(q)`/`relc.includes(q)`; o **último** match fraco vence, na ordem de `walk`, sem aviso. `ancora.mjs Ponto/Index` → `q="ponto"` → casa **21** charters → devolve **um arbitrário** com `✓`. Um veredito que parece medido e é sorteio é exatamente o que faz sessão nenhuma confiar no resultado.

**D3 · `--list` carimba `hasSource` sem provar arquivo.** Usa `related_prototype || bundle/visual || mockupJsx(fm.component)` e **não** chama `caminhoDaAncora`/`existsSync`. Valor podre conta como coberto no `design-coverage`. E o 3º fallback (`component`) é **tautológico** — âncora = a própria tela, que é o que o charter `Repair/Settings` recusa em prosa ("ancorar aqui seria ancorar a tela nela mesma").

## O limite estrutural (não é bug — é o que impede decidir contrato)
Muitos-para-um, medido: **20** charters do Ponto → **2** arquivos (`ponto-page.jsx`, `ponto-telas.jsx`) · `Financeiro/{Conciliacao,Dre,Fluxo,Impostos}` → **1** (`financeiro-telas-extras.jsx`) · **5** do Patrimônio → **1** (`patrimonio-page.jsx`) · **7** do Fiscal → **2**. A skill `comparar-design-prod` já declara: *"responde QUAL ARQUIVO é a âncora, nunca QUAL VIEW dentro dele"*. Logo **o contrato de tela não é decidível pela âncora como ela é hoje**.

Um único charter do corpus medido resolve isso, e resolve bem:
`Sells/Caixa/Index` → `prototipo-ui/cowork/vendas-extras.jsx · função VendasCaixaPage (linhas 123-354)`.

É a mesma ancoragem por **símbolo** das threads 13–15 do Ponto. Virar norma do campo é **decisão de [W]** (`D-SIMBOLO` abaixo), não PR do Code.

## Threads
| # | thread | prefixo | veredito |
|---|---|---|---|
| **01** | perna do bundle resolve no `LUGAR_FIXO` sem `--staging` (D1) | `prototipo-ui/ancora.mjs` | **CABE** |
| **02** | query ambígua deixa de sortear: candidatos + exit 2 (D2) | `prototipo-ui/ancora.mjs` | **CABE** · depende 01 (mesmo arquivo) |
| **03** | `--list` prova o arquivo e mede o fallback `component` (D3) | `prototipo-ui/ancora.mjs` | **CABE** · depende 02 (mesmo arquivo) |
| — | formato `arquivo :: símbolo :: faixa` no `related_prototype` | — | **BLOQUEADA** por `D-SIMBOLO` ([W]) |

## O que este playbook NÃO resolve
- **Não afirma que os 43 charters não medidos estão certos.** Estão **não medidos**.
- **Não conserta charter nenhum** — as 3 threads mexem só na máquina. Reancorar tela é onda por módulo, depois de `D-SIMBOLO`.
- **C6 segue sem dono** (rota no `app.jsx` sem componente) — declarado, não coberto.
- **Frescor** não entra: o eixo já existe e funciona; `--list` continua sem falar de frescor por desenho.

## Frescor — a árvore andou durante a auditoria
Li em `752041ac450d`; a última busca do turno já respondeu de `7742b9621c32`. Sha diferente na abertura ⇒ **remedir antes de escrever** e dizer isso no `_saida`.

## Fonte da máquina (o `placar-indice.mjs` lê o bloco abaixo)
```json
{
 "modulo": "ancora",
 "sha": "752041ac450d",
 "gerado": "2026-09-09",
 "variaveis": {
  "ALVO": "prototipo-ui/ancora.mjs",
  "FIXO": "prototipo-ui/cowork"
 },
 "decisoes": [
  {
   "id": "D-SIMBOLO",
   "pergunta": "related_prototype passa a exigir granularidade de símbolo (arquivo :: símbolo :: faixa), como Sells/Caixa já declara? Sem isso, 20 charters do Ponto e 4 do Financeiro apontam pro mesmo arquivo e o contrato de tela não é decidível pela âncora.",
   "respondida": false,
   "define": "FORMATO_ANCORA"
  },
  {
   "id": "D-COMPONENT",
   "pergunta": "O fallback mockupJsx(fm.component) do --list pode morrer? Ele é tautológico (âncora = a própria tela), mas nao medi quantas linhas hoje saem com via='component' — se for >0, alguma tela perde 'fonte' no design-coverage e a remoção precisa de [W].",
   "respondida": false,
   "define": "MATA_FALLBACK_COMPONENT"
  }
 ],
 "threads": [
  {
   "id": "01",
   "titulo": "perna do bundle resolve no LUGAR_FIXO sem --staging",
   "dono": "CL",
   "arquivo": "01-bundle-sem-staging.md",
   "prefixo": ["prototipo-ui/ancora.mjs"],
   "nao_toca": ["resources/js/Pages/**", ".claude/hooks/**", "scripts/governance/**", "prototipo-ui/cowork/**"],
   "depende_threads": [],
   "depende_decisoes": [],
   "provas": [
    { "tipo": "contem", "path": "prototipo-ui/ancora.mjs", "padrao": "LUGAR_FIXO", "nota": "a constante já existe; a thread passa a usá-la na perna do bundle" },
    { "tipo": "contem", "path": "prototipo-ui/ancora.mjs", "padrao": "BITE bundle sem staging" },
    { "tipo": "contem", "path": ".claude/hooks/post-merge-ui-smoke-required.mjs", "padrao": "caminhoDaAncora", "guarda": true },
    { "tipo": "arquivo", "path": "prototipo-ui/cowork/repair-page.jsx", "guarda": true }
   ]
  },
  {
   "id": "02",
   "titulo": "query ambigua deixa de sortear charter",
   "dono": "CL",
   "arquivo": "02-query-ambigua.md",
   "prefixo": ["prototipo-ui/ancora.mjs"],
   "nao_toca": ["resources/js/Pages/**", ".claude/hooks/**", "scripts/**"],
   "depende_threads": ["01"],
   "depende_decisoes": [],
   "nota_provas": "mesmo arquivo da 01 — remedir o sha antes de escrever",
   "provas": [
    { "tipo": "contem", "path": "prototipo-ui/ancora.mjs", "padrao": "candidatos" },
    { "tipo": "contem", "path": "prototipo-ui/ancora.mjs", "padrao": "BITE ambiguidade" },
    { "tipo": "contem", "path": "prototipo-ui/ancora.mjs", "padrao": "CONTROLE ambiguidade" }
   ]
  },
  {
   "id": "03",
   "titulo": "--list prova o arquivo e mede o fallback component",
   "dono": "CL",
   "arquivo": "03-list-prova-arquivo.md",
   "prefixo": ["prototipo-ui/ancora.mjs"],
   "nao_toca": ["scripts/governance/**", "resources/js/Pages/**"],
   "depende_threads": ["02"],
   "depende_decisoes": [],
   "nota_provas": "a remoção do fallback component fica atrás de D-COMPONENT; a thread entrega o campo medido e o número",
   "provas": [
    { "tipo": "contem", "path": "prototipo-ui/ancora.mjs", "padrao": "existe" },
    { "tipo": "contem", "path": "prototipo-ui/ancora.mjs", "padrao": "BITE list: fonte que nao abre" },
    { "tipo": "contem", "path": "prototipo-ui/ancora.mjs", "padrao": "hasSource", "guarda": true, "nota": "consumidor design-coverage lê este campo — não pode desaparecer" }
   ]
  }
 ]
}
```
