---
page: /compras · window.ComprasPage
component: compras-page.jsx (+ compras-page.css)
repo_alvo: Inertia Purchases/Index (controller a confirmar no git)
status: APROVADO por [W] (chat 2026-06-02 — "pode fazer sim, nada estranho"). Backfill que TRAVA o estado vivo (já migrado ao DS roxo, 9.4). Mirror no git pendente (L-13).
owner: wagner
last_validated: 2026-06-02
validated_against: compras-page.jsx @ cowork-2026-06-02
persona: Eliana [E] (financeiro/entrada de nota) · Wagner (volume/fornecedores)
identidade: roxo 295 — usa o **accent canônico do DS** (ADR 0235), SEM escopo próprio. Já migrada: 0 hex cru, classes `--cmp-*` → tokens DS (STATUS 2026-05-30).
nota_atual: 9.4 (migrado DS roxo 295)
irmao: Compras.casos.md (6 UCs grounded no código)
tier: A
charter_version: 1
---

# Page Charter — /compras

> **Status:** memória-por-tela (L-14), backfill que trava o conceito da tela **já migrada ao DS** (0 hex, 9.4). [W] confirma aprovações/reprovações explícitas.
> **Padrão visual:** Cockpit V2 (ADR 0110) · list + detail com drawer FSM.
> Personas: Eliana (entra nota, concilia, paga) · Wagner (volume, fornecedores).

---

## Mission

A entrada de compras da gráfica: receber a **NF-e do fornecedor** (por XML ou manual), acompanhar a compra pelo seu ciclo de vida, conferir o que chegou e pagar. A pergunta que responde primeiro é **"o que tenho a pagar e o que está chegando?"** — com a nota fiscal de entrada grudada e a ação certa em cada etapa.

---

## Goals — Features (PRECISA TER)

**Vivo hoje (aprovado / manter):**
- **FSM de compra:** rascunho → pedido → trânsito → recebido → conferido → pago — o trilho (`fsm-track`/`fsm-step`) marca onde está (`now`/`done`) — _UC-K04_
- **Filtros por status:** Todas / A pagar / Rascunhos / Em trânsito, com contagem (`filter`) — _UC-K01_
- **4 KPIs:** A pagar · Em trânsito · Volume do mês · Fornecedores ativos (`kpi.aberto/transito/mes/fornec`) — _UC-K02_
- **Entrada de nota:** "Importar XML" (NF-e do fornecedor) ou "Nova compra" manual (atalhos I/N) — _UC-K03_
- **Ação certa por etapa** (rodapé do drawer): "Enviar pedido" (rascunho) · "Marcar recebida" (trânsito) · "Conferir itens" (recebido) · "Pagar agora" (conferido+devendo) — _UC-K05_
- **Busca** por NF-e, fornecedor, ref ou chave SEFAZ; `/` foca a busca — _UC-K06_

---

## Non-Goals — Features (NÃO faz)

- ❌ Detalhe em modal full-screen (canon = drawer lateral / Sheet)
- ❌ Emissão fiscal de saída (isso é Vendas/NFe; aqui é **entrada** de nota do fornecedor)
- ❌ Contas a pagar recorrentes / financeiro completo (vínculo com Financeiro, não duplicar o módulo)
- ❌ Inglês em UI cliente-facing

---

## UX Targets

- Cabe em 1280px sem scroll horizontal (Eliana escritório / Larissa balcão)
- A etapa do FSM é legível num relance; o botão de ação **muda com a etapa** (nunca todos visíveis)
- Importar XML é caminho de 1 clique (entrada de nota é o fluxo quente)
- escala warm semântica (a pagar/atraso), zero cor crua — **manter os 0 hex da migração**
- 0 erros JS console

---

## UX Anti-patterns (REPROVADO — não repetir)

- ❌ **Reintroduzir hex cru / `--cmp-*` bespoke** — a tela já foi migrada pra tokens DS (0 hex). Regressão pra cor crua = desfaz o trabalho (L-02/L-23). Cor só via token do DS.
- ❌ **Mostrar todos os botões de ação a toda hora** — a ação é condicional ao `stage` (UC-K05); mostrar tudo quebra o "ação certa da etapa".
- ❌ Modal full-screen pra detalhe (usar drawer FSM)
- ❌ `rounded-xl+` · `font-bold` em h1
- ❌ Confundir entrada (compras) com saída (vendas) no vocabulário fiscal

---

## Identidade & DS

- **Roxo canônico do DS** (ADR 0235) — Compras **não** tem accent escopado próprio; usa o `var(--accent)` canon direto. Foi a 1ª tela a provar a migração completa (navy/cream → tokens DS, 0 hex).
- Migração pro `ds-v5`: barata (já está em tokens). Confirmar que nenhum `--cmp-*` residual sobrou; gate visual F1.5 antes/depois.

---

## Refs

- Compras.casos.md — 6 UCs grounded em `compras-page.jsx`
- ADR 0110 — Cockpit V2 · ADR 0235 — roxo canon · ADR 0200 — DS é piso

## Trilha do tempo
- 2026-06-02 · [CC] criou o charter (backfill) travando o estado vivo da tela migrada 9.4, grounded nos 6 UCs. Passo 2 do `_PROPOSTA-0245` (trio antes de migrar pro v5). Aguarda confirmação de [W] + mirror no git.
