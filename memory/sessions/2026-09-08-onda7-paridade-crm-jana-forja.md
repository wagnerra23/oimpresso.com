---
date: "2026-09-08"
hour: "09:24 BRT"
topic: "Onda 7 lote Crm+Jana+Forja — casamento inventário↔tela, e as seis sondas erradas de uma sessão cujo tema era medir"
authors: ["C"]
outcomes:
  - "PR #6974 (merged): parityLinked 18 → 27; 9 vínculos declarados, 8 órfãos mantidos por veredito"
  - "5 telas da Forja tinham inventário e a fila por NOME DE PASTA não as via — 5 dos 9 pontos vieram daí"
  - "PR #6979 (merged): linha da Onda 7 no PLANO-MESTRE, sem congelar contagem viva"
  - "PR #6984: ledger LC-08 → 146 — seis sondas erradas, todas pegas por uma SEGUNDA sonda discordando"
  - "Medição de runtime (passo 5 do 7a) NÃO rodou: sem lado vivo + Jana fora do universo do harness"
---

# O lote que subiu o contador, e as seis vezes que quase subiu o número errado

## Contexto — o pedido

Onda 7 do programa-ondas (paridade protótipo↔produção), escopo **Crm + Jana + Forja**, com
sessões irmãs em Sells, Financeiro, Fiscal, Compras/Estoque e Repair/OficinaAuto. O método é o
[7a](../requisitos/_Governanca/programa-ondas/onda-7-paridade-prototipo/7a-inventario-e-metodo.md);
o resultado do lote virou o
[7b](../requisitos/_Governanca/programa-ondas/onda-7-paridade-prototipo/7b-lote-crm-jana-forja.md).

## O que foi feito

**9 vínculos `related_visual_comparison` declarados**, cada tela lida **no conteúdo** do
inventário (`inertia_target` / `target_charter` / `charter:`), nunca por semelhança de nome:
3 na Jana (`Index`, `Memoria`, `Chat`), 1 em `governance/Dashboard`, 5 em `team-mcp/` da Forja.

`parityLinked` **18 → 27** no PR, com quebrados em **0**. Nenhum `.tsx` tocado.

**8 órfãos ficaram órfãos por veredito**, que é o passo 4 do método — vínculo forçado é proibido:

| órfão | por quê |
|---|---|
| 4 em `Crm/_legado-fullpage/` | o README **datado** da pasta (2026-06-01) declara histórico pré-drawer; as telas já apontam pro inventário vigente, e vincular o histórico por cima trocaria um ponteiro certo por um vencido |
| 2 em Forja | as telas Triage/Inbox **foram revogadas** (Onda 11, ADR 0367 D1/D6) — `git ls-files` devolve vazio |
| `Jana/Chat-header-tabs` | escopo header-only cross-tela; o charter-alvo é o mesmo do `Chat-visual-comparison` e a chave é 1:1 |
| `Crm/cliente-drawer-760` | charter-alvo já vinculado |

## Os quatro achados

**E — 5 telas da Forja invisíveis à fila.** Os inventários de `memory/requisitos/TeamMcp/`
declaram telas que vivem **dentro de `Modules/Forja/Resources/js/Pages/team-mcp/`**. A fila do
lote foi montada por nome de pasta (*"Forja: 2"*) e nasceu cega a elas. Lendo o conteúdo
apareceram 5 telas vivas, com charter, sem vínculo — **5 dos 9 pontos do lote**.

**B — inventário arquivado no módulo errado.** `governance-dashboard-extension-visual-comparison.md`
mora em `memory/requisitos/Jana/` e declara `module: Governance`. Vínculo feito no charter certo;
o arquivo segue na pasta da Jana (mover é realocação, com dono e adversário próprios).

**C — `Cliente/Index` aponta pro inventário superado.** O charter declara o de 2026-05-15, que
descreve drawer **480px** com gate pendente. **Medido no código, não herdado do doc:**
`Cliente/Index.tsx:1953` renderiza `w-[760px] sm:max-w-[760px]`. A tela viva é o 760. Não mexido —
[W] proibiu tocar nos charters de Cliente já vinculados, e trocar não moveria o contador.

**D — a Jana está fora do universo do `design-diff-lote`.** O harness seleciona de
`application-report.json` por `lifecycleState === 'anchored'`: **93 screens, 0 citando Jana**, e as
**54 fontes são todas `*-page.jsx`** enquanto a âncora da Jana é `jana-merge.jsx`.

## O que NÃO foi medido, e por quê

**A medição de runtime (passo 5 do 7a) não rodou.** Dois motivos independentes, datados:
`curl --max-time 6 http://127.0.0.1:8000/` devolve **HTTP 000**, `php` não está no PATH e não há
`.env` no worktree — as 11 entradas do `.claude/launch.json` servem **só o lado protótipo**;
e mesmo com app de pé, o harness não seleciona a Jana (achado D). Medir um lado só responderia
outra pergunta, então **nenhum veredito de paridade foi emitido** e o passo 7 não gerou itens.

O que **foi** verificado: âncora resolvida pela porta per-tela das 3 telas da Jana →
`prototipo-ui/cowork/jana-merge.jsx`, frescor `2026-09-07T20:57:48.072Z`. Não é o `chat-jana.jsx`
proibido pela lápide §5 2026-08-10/08-11.

## A parte que mais ensina: seis sondas erradas numa sessão sobre medir

Nenhuma chegou a artefato publicado. O padrão é o achado: **em 6 de 6, quem pegou foi uma segunda
sonda discordando — nunca releitura.** Reler a própria sonda não pega, porque o erro estava na
**pergunta**, não na leitura. Detalhe por caso no recibo do [LC-08](../LICOES_CODE.md).

1. `git ls-files 'Crm/**/*visual-comparison*'` → 4 no Crm e **0** em Jana/Forja: o `**` do
   pathspec cegou pasta. Pego pela varredura completa, que achou 20.
2. `Object.values(r).find(isArray)` pegou `transportChanges[127]` em vez de `screens[93]` — eu ia
   publicar *"0 telas da Jana no report"*. Pego porque um `grep -c` devolveu 3 e contradisse.
3. `node <script> | tail` e li o `rc=0` do `tail` como rc do script, que era 1.
4. `git diff --cached origin/main` mostrou 2 PHP como `M` e quase reportei *"trabalho alheio no
   meu worktree"* — era o `origin/main` que avançara 2 commits. A sonda certa é `git status`.
5. `gh pr checks --watch` saiu **exit 0** com as últimas linhas dizendo `pending`.
6. `git grep -ql <path> -- "*.charter.md"` casou com **changelog em prosa** e disse "vinculado"
   pra um órfão. A pergunta era pela **chave**, não pela menção.

**Não abri lápide §5 nova**: os seis instanciam limite já registrado, e §5 2026-07-09 proíbe
duplicar régua consolidada. Só o contador andou.

## Os dois PRs de fechamento

**#6979 — linha da Onda 7 no `PLANO-MESTRE`.** Correção de rota no meio: a 1ª redação trazia
`64/84`; ao re-medir antes de commitar já era **66**, com outras sessões mergeando. Congelar
contagem viva em doc canon é a lápide §5 2026-07-17. A linha ficou como **fato datado em passado**
e aponta pra porta viva em vez de repetir o número. Também diz o que o contador **não** prova:
o eixo que andou é vínculo **declarado**, não paridade **medida**.

**#6984 — ledger LC-08 145 → 146.** Contagem conferida com grep antes (145 = 145 reais, sem
herdar erro) e depois (146 = 146).

## Dívida declarada, não paga

Tocar charter legado acorda gate diff-aware (§5 2026-07-12): o `charter related_us join` fica com
**5 achados**. É **advisory**, e os 5 inventários de `TeamMcp/` não têm campo `stories:` — não há
fonte pra derivar a US. Inventar id seria anti-padrão que parece canon. Decisão [W].
