---
date: "2026-07-17"
time: "1015"
slug: produto-tri-campo-pesquisa-mercado
tldr: Pesquisa (17 concorrentes + UX NN/g) + medição em 5 bases reais responde à usabilidade da aba geral. Achado forte — 0 de 5.559 produtos comvis usam a formação avançada (17/07). Vira SDD §4.2 como evidência pra decisão [W] pendente (fronteira aba geral × Formação de Preço). Sem teste.
owners: [W]
prs: [4437]
us: [US-PROD-020, US-PROD-023, US-PROD-024]
---

# Handoff — Produto / tri-campo Custo·Margem·Valor: pesquisa de mercado + uso real

> **Pedido [F]:** "a tela precisa ser fácil… a maioria cria cadastro simples (custo+venda); poucos usam a
> formação avançada (markup composto); essa aba vem oculta. Creio que a confusão é aqui. Não concorde,
> pesquise." **Entregue:** 3 pesquisas + medição em 5 bases reais → SDD §4.2.

## Estado MCP no fechamento

- MCP **desconectou** no meio da sessão (`Oimpresso MCP — Maiara` + chrome) — brief do SessionStart é o
  último snapshot: nenhum cycle ativo; `my-work wagner` (do brief) = US-PROD-023/024/027 em voo no módulo Produto.
- PRs desta sessão longa: **#4321 · #4370 · #4405 MERGED** (aba geral: fórmula + mapa + §1.1) · **#4437 aberto** (este, SDD §4.2).
- Sem colisão: nenhuma outra sessão tocou o SDD em 17/07.

## A hipótese do [F] — testada, não aceita

**Sustenta o QUE, corrige o PORQUÊ (e reforça com número).**

**A favor (forte):** 17 concorrentes (8 BR + 9 globais) — o cadastro básico pede **~2 campos de dinheiro
(custo + preço) com margem derivada**; a composição (custo fixo/variável/comissão/perda) **nunca** fica
inline por default (pricelist/price-rules/ferramenta Formação de Preço/BOM). O "vem oculta" é praticado por
todos. E o número nosso é mais forte que "poucos":

> **Recibo datado** (query `SUM(componentes CALC_PVENDA_* <> 0)`, Firebird, 17/07): **0 de 5.559** produtos
> em 4 clientes de comunicação visual usam a formação avançada. + Martinho (oficina, medido antes): 0.

**Correções (não concordei):**
1. **"O avançado é o markup" — impreciso.** Markup **simples** (derivado `((V/C)−1)×100`) é BÁSICO e fica
   visível. O avançado é o markup **COMPOSTO** (decomposição). Esconder "o markup" esconderia o número que
   todos querem ver.
2. **"assusta o usuário" — sem base.** NN/g fundamenta progressive disclosure em **frequência/learnability**,
   não medo (pesquisado, citação não encontrada). Argumento forte pra defender o desenho = "ninguém usa"
   (0/5.559) + "é decisão de outra natureza" (política multi-eixo), não "assusta".
3. **O simples é ainda mais simples que custo+valor.** Completude custo+valor varia 3,7%–77% por cliente;
   muitos produtos de gráfica têm **só valor, sem custo** (preço por m²) → a aba básica **precisa tolerar
   custo ausente** (pareia US-PROD-027 custo-zero→preço-zero).

## O que virou canon (SDD §4.2)

Distinção margem-simples × markup-composto · tabela de premissa-checada por referência de mercado (⚠️ lápide
2026-07-16 "importar solução sem checar se o problema é nosso" — cada recomendação diz se a premissa deles
vale aqui) · recibo datado do uso · 4 guardas de desenho (básico visível / rotular "Markup %" não "Profit %" /
tolerar custo ausente / máx 1 nível de disclosure, campo obrigatório nunca em collapse fechado).

## Referências de desenho (se sofisticar — NÃO é a prioridade)

- **Dynamics 365 BC** — enum `Item Price Profit Calculation` por item (preço-âncora / margem-âncora / independentes);
  anti-padrão: rótulo "Profit %" sobre fórmula de margem.
- **Linx Microvix** (fonte primária) — flag "mantém markup fixo" × "mantém preço fixo" ao mudar custo; mesma
  fórmula do legado. É o par que o `TEM_MARGEM_FIXA_CONTIBUICAO` do legado já tem (84% em `N`) e o oimpresso não.
- **Bling** — "Formação de Preço" como ferramenta separada e nomeada = a arquitetura mais próxima do que o [F] descreveu.

## Aberto / próxima ação

- **Bloqueio real (decisão [W]):** os 3 campos `[V0]` ficam na aba geral ou pertencem à Formação de Preço
  (`AR-PROD-090..103`, item nº1 do roadmap de charters do PARIDADE §5)? A §4.2 dá o número embaixo; não decide.
- **Sem teste.** Evidência de mercado + uso medido, não contrato. REGRA MESTRE §2/§3 segue pendente pra código de valor.
- **Escopo medido = comunicação visual + oficina.** Vestuário (ROTA LIVRE) não medido — perfil pode diferir.
- Quando avançar pro código: o caminho é o **trio** (ADR 0264, regra F3 — tocar `Create.tsx` obriga `Create.casos.md`);
  o slot pro que não tem teste é o bullet **`[BACKLOG]` sem id** no casos.md (não US, não UC — verificado 2026-07-16).

## Higiene

- Sem BRL, sem nome de cliente de gráfica (anonimizados C1–C4; Martinho já é canon público do módulo). 5 bases
  Firebird copiadas pra scratchpad, consultadas na cópia, **apagadas** (5,4 GB) — nenhuma escrita, só agregados.
- Worktree `produto-sdd` @ origin/main fresco. Os 3 worktrees anteriores da sessão (custo-margem/paridade/mapa)
  já removidos (sem junction — vetor dos incidentes não se aplicava).
