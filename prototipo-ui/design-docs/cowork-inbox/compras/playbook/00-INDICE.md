---
sessao: "00"
titulo: SINCRONIZAR Compras — índice do playbook (fonte da máquina embutida em §7)
autor: "[CC]"
criado: 2026-09-08
base: wagnerra23/oimpresso.com@main (árvore 9101f86af501 · lida 2026-09-08 10:31 UTC)
destino_no_main: prototipo-ui/design-docs/cowork-inbox/compras/playbook/
regra: este índice é PEDIDO (threads a executar), não inventário. Ninguém escreve estado — ele é derivado (§2-bis). Nunca em prototipo-ui/cowork/ (guard R1).
---

# SINCRONIZAR Compras — playbook

> **Absorve e CORRIGE:** `COLAR-NO-CODE-compras-ondas.md` (2026-09-04). Aquele doc pedia **8 arquivos destravados**; a leitura de hoje mostra que **6 já existem**. Onde divergem, **manda a árvore de hoje**.
> **Anti-scatter:** este playbook substitui o doc de 04/09 como pedido do módulo. Não abrir doc novo pro Compras.

## 0 · O que mudou desde 04/09 (medido hoje, não recordado)

| o doc de 04/09 pedia | árvore 9101f86af501 diz | veredito |
|---|---|---|
| criar `Purchase/Index.casos.md` | **existe** — 28.167 B | ❌ pedido morto |
| criar `Purchase/Create.casos.md` | **existe** — 22.643 B | ❌ pedido morto |
| criar `Purchase/Edit.casos.md` | **existe** — 19.415 B | ❌ pedido morto |
| criar `Purchase/Show.casos.md` | **existe** — 24.739 B | ❌ pedido morto |
| criar `contrato/compras-cockpit.contract.json` | **existe** — 3.946 B | ❌ pedido morto |
| criar `contrato/purchase-create.contract.json` | **existe** — 4.272 B | ❌ pedido morto |
| "não afirmo que `Purchase/Create.tsx` importa a grade" | **importa** — `Create.tsx:26` `import GradeMatrixInput, { type GradeCell } from '@/Pages/Purchase/_components/GradeMatrixInput'`; uso em `:459` | ✅ confirmado |
| "E2E/VRT: não verifiquei" | `e2e/` tem **17 arquivos e ZERO de compras/purchase** (specs existem pra essentials, jana, oficina, produto, sells, arquivos, manufacturing) | ✅ é a única frente viva |
| "coluna Margem: não verifiquei se o `ComprasService` entrega" | **busca `margem|margin|lucro` em `Modules/Compras/` = 0 ocorrências** | 🔴 **número sem fonte no meu build** |

**Resultado: o trio e os contratos estão fechados. Sobra 1 frente de código (rede), 1 defeito meu (Margem) e 3 gates de [W].** Sem esta releitura, o Code teria aberto 6 PRs pra criar arquivos que já estão no `main` — exatamente a classe de erro que o doc de 04/09 cometeu ao confiar num retrato de 4 dias.

## 1 · LEVANTAR — 4 denominadores

**D1 rota** `Modules/Compras/Routes/web.php`: só `GET /compras` (`compras.index`) e `GET /compras/{id}/detalhe` (`compras.show`) — create/store/edit/update/destroy + importar-dfe são Waves 3 e 6 (comentário no topo do arquivo, lido em 04/09).
**D2 nav legado**: sidebar v3 declara **ghost `/compras/create`** — rota que o canon do `Purchase/Create` proíbe (Non-Goal C1). Conflito vivo, item 1 do RESÍDUO.
**D3 protótipo** `compras-page.jsx`: `NAV.ds-tabbar.jm-tabs` com **3 abas** — Painel · Pedidos (7) · Fornecedores (4).
**D4 runtime** (`Inertia::render`): o cockpit `/compras` já é React (`Compras/Index.tsx`, 28.813 B, trio completo); o CRUD `/purchases` também (4 telas × `.tsx` + charter + **casos**). **Nenhuma rota do módulo espera Page nova.**

| tela | Page | charter | casos | contrato | e2e | estado | thread |
|---|---|---|---|---|---|---|---|
| `/compras` cockpit | ✅ 28.813 B | ✅ 12.740 B | ✅ 26.468 B | ✅ 3.946 B | **✕** | 🔵 à frente | 01 |
| `/purchases` Index | ✅ 14.658 B | ✅ | ✅ 28.167 B | — | **✕** | 🔵 à frente | 01 |
| `/purchases/create` | ✅ 28.232 B (grade plugada) | ✅ | ✅ 22.643 B | ✅ 4.272 B | **✕** | 🔵 · gate [W] | 01 · 05 |
| `/purchases/{id}/edit` | ✅ 20.249 B | ✅ | ✅ 19.415 B | — | ✕ | 🔵 | — |
| `/purchases/{id}` | ✅ 17.601 B | ✅ | ✅ 24.739 B | — | ✕ | 🔵 | — |
| **Fornecedores** (aba do protótipo) | **sem receptor** | — | — | — | — | ⛔ [W] | 03 |

## 2 · Threads — ordem · dono · prefixo (Lei 1) · dependência

| # | thread | dono | prefixo que escreve | depende de | vaga |
|---|---|---|---|---|---|
| 01 | Rede: 2 specs E2E do módulo (a única frente de código) | [CL] | `e2e/compras-cockpit.spec.ts` · `e2e/purchase-create.spec.ts` · lane no workflow | — | 1 |
| 02 | Build daqui: coluna **Margem** sem fonte no drawer do protótipo | [CC] | `compras-page.jsx` · `oimpresso.com.html` (bump) | — | 1 |
| 03 | **Fornecedores** — aba sem receptor | [W] → [CL] | fora deste playbook até [W] responder | D-FORN | ⛔ |
| 04 | **Ghost `/compras/create`** — conflito de canon | [W] → [CL] | `DataController` (se remover) · pacote de 6 (se criar) | D-GHOST | ⛔ |
| 05 | Smoke/canary da grade tam×cor (US-COM-005) | [W2] | nenhum arquivo — é aprovação de screenshot | D-GRADE | ⛔ |

**Vaga 1:** 01 ∥ 02 (prefixos disjuntos: um só em `e2e/`, outro só no build do Cowork). **Vagas seguintes:** nenhuma até [W] responder o RESÍDUO. Não há thread 06+ inventada pra "parecer trabalho".

## 2-bis · ESTADO — derivado, nunca escrito

> `node scripts/qa/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/compras/playbook/00-INDICE.md --root . --proximo`
> `_saida-NN.md` presente **e** provas verdes = `feito`; sem `_saida` = não feito mesmo com PR mergeado; `bloqueada` é fila de [W], não do Code.

Render esperado contra 9101f86af501: `Compras: entregue 0 de 5 · próximo 2 · pendente 0 · bloqueada 3` — **PRÓXIMO: 01 · 02.**

### Fluxo (6 passos, iguais para toda thread)
```
1 ABRIR    sessão limpa · gh pr list --state open × arquivos a tocar · ler NN-*.md + âncora no main (sha no _saida)
2 MEDIR    read-only; T1 duas leituras iguais quando houver tela
3 GERAR    só se a thread cria tela (nenhuma aqui cria)
4 APLICAR  1–3 arquivos do prefixo · reusar átomos/serviços listados · PARAR SE vale mais que terminar
5 PROVAR   provas do NN verdes · placar no corpo do PR
6 FECHAR   _saida-NN.md (feito · não feito e por quê · pedido literal · descobertas · prefixo tocado) → parar
```

## 3 · Abertura de thread (colar como 1ª mensagem — sessão limpa)
```
Sessão fresca. ANTES de abrir: gh pr list --state open e cruze com os arquivos do seu prefixo.
Leia nesta ordem, do main, nunca de cópia local:
1. prototipo-ui/design-docs/cowork-inbox/ponte/03-REGRAS-DE-PARALELISMO.md   ← Leis 1–4
2. prototipo-ui/design-docs/cowork-inbox/compras/playbook/00-INDICE.md       ← §1 estados · §2 seu prefixo · §7 fonte
3. prototipo-ui/design-docs/cowork-inbox/compras/playbook/NN-<sua-thread>.md ← escopo · alvo · dado · prova
4. memory/requisitos/Compras/SCOPE.md                                        ← "cockpit de LEITURA; CRUD é /purchases"
5. resources/js/Pages/Purchase/Create.charter.md                             ← Non-Goal C1 (não nasce Pages/Compras/Create.tsx)
6. prototipo-ui/PRE-FLIGHT-TELA.md · memory/proibicoes.md · memory/LICOES_CC.md
7. os arquivos da âncora listados na sua thread
AVISO: o doc prototipo-ui/COLAR-NO-CODE-compras-ondas.md (04/09) está VENCIDO nos blocos 1-bis e 6 —
os 4 casos.md e os 2 contratos que ele manda criar JÁ EXISTEM. Este índice §0 é a versão medida.
Você escreve SOMENTE no seu prefixo e no seu _saida-NN.md. Não edita este índice, github.md nem memory/**.
Terminou: escreva _saida-NN.md e pare.
```

## 4 · VERIFICAR
Thread `feito` = `_saida-NN.md` com os 5 itens **e** provas verdes lendo o `main`. **T7** (`design-diff --compare --check` nos dois renders, prod deployada) não é visível daqui — o placar afirma "arquivos verdes", nunca "paridade". Parciais que as threads **reusam** (não recriar): os 10 Pest de `Modules/Compras/Tests/Feature/**` · os 3 componentes do cockpit · `GradeMatrixInput` + `GradeProductCombobox`.

## 5 · Revisão 3× por passo
| passo | R1 · fonte | R2 · falsificação | R3 · frescor |
|---|---|---|---|
| LEVANTAR | 4 denominadores, nunca por pasta (Compras reusa `transactions`, não tem tabela) | contei `casos.md` **na árvore**, não no doc de 04/09 — e 4 de 4 existiam | **o doc de 04/09 estava 4 dias velho e 6 pedidos dele morreram**; releitura antes de emitir é a regra que salvou este ciclo |
| PUXAR | produção à frente em todas as telas com receptor | conferi a grade no `.tsx`, não no charter: `Create.tsx:26` importa | charter v2 dizia "implementado"; o import prova |
| REACT | nenhuma tela nova nesta onda | Fornecedores não tem receptor → não inventar Page | ghost × Non-Goal segue em conflito |
| PLAYBOOK | 5 threads, 2 executáveis | descartei 6 threads que seriam no-op | 1 thread = 1 PR = 1 prefixo |
| VERIFICAR | prova = caminho no repo | `e2e/` tem 17 specs e nenhum do módulo — a lacuna é real | Margem: 0 hit em `Modules/Compras` ⇒ defeito meu, não pedido |

## 6 · RESÍDUO Compras — fila de decisão [W]
1. **Ghost `/compras/create`: remove ou cria?** SCOPE Wave 3 diz TODO; o charter do `Purchase/Create` diz Non-Goal. Remover = **1 arquivo**; criar = **6** e contraria o charter vigente. (D-GHOST → thread 04)
2. **Fornecedores é tela?** Fornecedor é `contacts type=supplier`; não existe `Pages/Fornecedor*`. (a) view do cadastro de contatos, (b) tela própria (5 arquivos), (c) Non-Goal escrito. (D-FORN → thread 03)
3. **Smoke/canary da grade tam×cor** (US-COM-005, biz=4 Larissa): aprova por screenshot? Nenhum arquivo destrava — é [W2]. (D-GRADE → thread 05)
4. **Alvo de toque em 1280 denso:** 24×24 WCAG ou exceção declarada? (mesma pendência de CRM, Repair, HRM, Ponto e Patrimônio — uma resposta serve pro ERP todo)
5. **Grade do DS** (`th` sem `scope`, `TH` ordenável sem semântica): **5º módulo** com o achado. Aqui o `SortTh` do Compras é a referência certa — vale portar pro DS num pedido próprio.

## 7 · Fonte da máquina (playbook.json embutido — primeiro bloco json deste arquivo; schema em `_schema/playbook.schema.json`)
```json
{
  "modulo": "Compras",
  "modulo_codigo": "Compras",
  "sha": "9101f86af501",
  "gerado": "2026-09-08",
  "absorve": ["prototipo-ui/COLAR-NO-CODE-compras-ondas.md"],
  "variaveis": { "LANE": null },
  "decisoes": [
    { "id": "D-GHOST", "pergunta": "Ghost /compras/create do sidebar v3: remover (1 arquivo) ou criar a rota (6 arquivos, contra o Non-Goal C1 do Purchase/Create)?", "respondida": false, "destrava": ["04"] },
    { "id": "D-FORN", "pergunta": "Fornecedores (contacts type=supplier) e tela propria, view do cadastro de contatos, ou Non-Goal escrito no charter do cockpit?", "respondida": false, "destrava": ["03"] },
    { "id": "D-GRADE", "pergunta": "Smoke/canary da grade tam x cor (US-COM-005, biz=4): [W] aprova por screenshot?", "respondida": false, "destrava": ["05"] },
    { "id": "D-LANE", "pergunta": "Os specs de Compras entram em lane existente ou nasce workflow do modulo?", "respondida": false, "define": "LANE" }
  ],
  "threads": [
    { "id": "01", "titulo": "Rede: 2 specs E2E do modulo", "dono": "CL", "vaga": 1, "arquivo": "01-rede-e2e.md",
      "prefixo": ["e2e/compras-cockpit.spec.ts", "e2e/purchase-create.spec.ts"],
      "nao_toca": ["resources/js/Pages/Compras/", "resources/js/Pages/Purchase/", "Modules/Compras/", "prototipo-ui/contrato/compras-cockpit.contract.json", "prototipo-ui/contrato/purchase-create.contract.json"],
      "provas": [
        { "tipo": "arquivo", "path": "e2e/compras-cockpit.spec.ts" },
        { "tipo": "arquivo", "path": "e2e/purchase-create.spec.ts" },
        { "tipo": "arquivo", "path": "prototipo-ui/contrato/compras-cockpit.contract.json", "guarda": true, "nota": "contrato JA existe (3.946 B) — a thread nao o recria nem o edita" },
        { "tipo": "arquivo", "path": "prototipo-ui/contrato/purchase-create.contract.json", "guarda": true, "nota": "contrato JA existe (4.272 B)" },
        { "tipo": "contem", "path": "resources/js/Pages/Purchase/Create.tsx", "padrao": "GradeMatrixInput", "guarda": true, "nota": "a grade nao pode ser desplugada por um PR de rede" }
      ] },
    { "id": "02", "titulo": "Build daqui: coluna Margem sem fonte no drawer do prototipo", "dono": "CC", "vaga": 1, "arquivo": "02-margem-sem-fonte.md",
      "prefixo": ["prototipo-ui/cowork/compras-page.jsx", "prototipo-ui/cowork/oimpresso.com.html"],
      "nao_toca": ["resources/js/Pages/Compras/", "Modules/Compras/", "prototipo-ui/cowork/compras-grade-matrix.jsx"],
      "provas": [],
      "nota_provas": "build do Cowork: prova = _saida-02.md com a decisao (remover a coluna OU declarar a fonte real lida no ComprasService/Drawer.tsx) + render medido. Nao vira PR no main." },
    { "id": "03", "titulo": "Fornecedores — aba sem receptor", "dono": "W", "arquivo": "03-fornecedores-bloqueada.md",
      "prefixo": [], "nao_toca": ["resources/js/Pages/Compras/Index.tsx"],
      "bloqueio": "D-FORN: fornecedor e contacts type=supplier e nao existe Pages/Fornecedor*. Sem rota declarada por [W], overlay sem receptor nao vira Page.",
      "depende_decisoes": ["D-FORN"], "provas": [] },
    { "id": "04", "titulo": "Ghost /compras/create — conflito de canon", "dono": "W", "arquivo": "04-ghost-create-bloqueada.md",
      "prefixo": [], "nao_toca": ["resources/js/Pages/Purchase/Create.tsx", "resources/js/Pages/Purchase/Create.charter.md"],
      "bloqueio": "D-GHOST: SCOPE Wave 3 (TODO /compras/create) x Non-Goal C1 do charter do Purchase/Create. Ghost apontando pra rota que o canon proibe = link morto ou tela proibida; nenhum PR antes da resposta.",
      "depende_decisoes": ["D-GHOST"], "provas": [] },
    { "id": "05", "titulo": "Smoke/canary da grade tam x cor (US-COM-005)", "dono": "W", "arquivo": "05-grade-smoke-bloqueada.md",
      "prefixo": [], "nao_toca": ["resources/js/Pages/Purchase/_components/GradeMatrixInput.tsx"],
      "bloqueio": "D-GRADE: status_note do charter v2 diz 'aguarda smoke/canary Wagner'. Gate humano [W2] — nenhum arquivo destrava.",
      "depende_decisoes": ["D-GRADE"], "provas": [] }
  ]
}
```
