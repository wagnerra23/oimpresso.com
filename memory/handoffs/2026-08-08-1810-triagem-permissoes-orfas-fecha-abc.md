---
date: "2026-08-08"
time: "18:10 BRT"
slug: "triagem-permissoes-orfas-fecha-abc"
tldr: "A D0 da Trilha D achou um medidor de permissões sem invocador; ligá-lo publicou 43 órfãs, e triá-las virou 6 PRs que fecharam as classes A, B e C. O momento decisivo foi [W] desconfiar — eu tinha verificado só código, e permissão pode existir no banco. Verificado em prod: 495 no banco × 357 em código, nenhuma das minhas lá."
prs: [5351, 5352, 5361, 5365, 5369, 5381, 5382]
decided_by: [W]
related_adrs:
  - "0358-doutrina-de-teste-tenant-98-supersede-0101"
  - "0093-multi-tenant-isolation-tier-0"
next_steps:
  - "Decidir edit_purchase_price e report.stock_details — medidas e apresentadas pela sessão da classe D, sob a regra-mestre VALOR/ESTOQUE, sem uma linha tocada"
  - "NÃO declarar as 4 órfãs que exploram a perna else do Gate::before de propósito — declarar seria nocivo (ver handoff 2026-08-08 19:38)"
  - "Antes de triar o grupo teatro (63), consultar o BANCO: 495 permissões lá × 367 em código"
---

# Handoff 2026-08-08 18:10 BRT — triagem das permissões órfãs fecha A, B e C

## Estado no fechamento

MCP disponível (brief #484 carregou). **7 PRs meus mergeados** nesta linha; classes A, B e C da `US-GOV-059` fechadas. Órfãs **43 → 16**.

## Como começou, e por que virou outra coisa

O pedido foi *"podemos fazer o plano de documentação?"*. **O plano já existia** — Trilha D, ondas D0–D8, mergeada no dia anterior. Escrever outro seria duplicar o dono, então executei a **D0**: derivar o eixo `Invocador` no inventário de máquinas ([#5342](https://github.com/wagnerra23/oimpresso.com/pull/5342), no handoff anterior).

Esse eixo revelou o `permission-drift`: **selftest verde em fixture hermética, nenhum invocador**. Ligado no CI, publicou **43 permissões órfãs**. O resto da sessão foi triá-las.

## O momento que definiu a qualidade do trabalho

Depois de 3 PRs mergeados, [W]: *"estou desconfiado disso, pois eu usava essas permissões"*.

Investigado: o `RoleController::__createPermissionIfNotExists` (L350) faz `Permission::create()` para qualquer nome vindo do input da tela de papéis — **permissão pode existir no banco sem estar em código**. Eu tinha verificado **só código**.

Consultado o banco de **produção**: `TOTAL=495`, `ACHADAS=NENHUMA`. A premissa se confirmou e nada foi quebrado — o que fazia tudo "funcionar" para [W] era o `Gate::before` de superadmin. Mas o método estava incompleto, e se o resultado fosse outro eu teria quebrado acesso em 4 PRs já mergeados. **Dado colateral: 495 no banco × 367 em código.**

Daí em diante, toda triagem consultou o banco **antes**.

## O que foi entregue

| PR | Entrega |
|---|---|
| [#5351](https://github.com/wagnerra23/oimpresso.com/pull/5351) | detector parou de ler **comentário** como código — 5 dos 43 eram prosa (43→38) |
| [#5352](https://github.com/wagnerra23/oimpresso.com/pull/5352) | `kb.ai` → `kb.ai.ask` — a UI escondia o botão de IA com nome inexistente enquanto o endpoint liberava |
| [#5361](https://github.com/wagnerra23/oimpresso.com/pull/5361) | `fiscal.inutilizar` é **role**, não permissão — `can()` nunca casava, inutilização de faixa fiscal só-admin |
| [#5365](https://github.com/wagnerra23/oimpresso.com/pull/5365) | `restaurant.view` → `product.view` (classe B) |
| [#5369](https://github.com/wagnerra23/oimpresso.com/pull/5369) | RecurringBilling: `assinatura.update` → `subscriptions.manage` (já existia) e `invoice.cancel` → `invoices.cancel` (declarada) |
| [#5381](https://github.com/wagnerra23/oimpresso.com/pull/5381) | 5 gates da classe C (Crm 3 · Financeiro 1 · Whatsapp 1) |
| [#5382](https://github.com/wagnerra23/oimpresso.com/pull/5382) | Ponto: permissão de importação AFD + **fail-open corrigido** no `authorize()` |

## Decisões de não-fazer, com razão registrada

- **3 `hms.*` mantidas** — aparecem sempre como termo de um `OR` com permissões que existem (`false || X === X`, inócuas). Removê-las custaria editar autorização de **pagamento** em arquivo de fork upstream. Risco desproporcional a −3 num contador.
- **7 do grupo scaffolding** — FormRequest sem controller que o injete. O caso mais claro, `auditoria.revert`, vive num Request cujo endpoint (`revert-bulk`) **nunca foi ligado**: sem rota, não há acesso bloqueado.
- **O Ponto foi separado** do #5375 por decisão [W] (opção b), porque a lane required estava vermelha no `main` e travava qualquer PR que a disparasse.

## O que as sessões-filhas resolveram

Spawnei 4 chips ao fim. Dois fecharam o que eu não fecharia sozinho:

- **Lane do Ponto** ([#5393](https://github.com/wagnerra23/oimpresso.com/pull/5393)) — **não era regressão de isolamento**. Os testes inseriam a linha alheia com `business_id = 99`, que o seed do CI não cria (só 1, 2 e 98) ⇒ **FK 1452 no INSERT**, morte no fixture. Sete guards Tier 0 ficaram 11 dias *"vermelhos por nunca terem chegado a medir"*. A sessão ainda achou que **3 dos 7 ficariam verdes sem exercer isolamento** (LC-13) e deu a cada um a perna que faltava. É **o mesmo erro que eu cometi** num teste do NfeBrasil — e que avisei no chip, o que ajudou.
- **Classe D** ([#5384](https://github.com/wagnerra23/oimpresso.com/pull/5384), handoff [#5428](https://github.com/wagnerra23/oimpresso.com/pull/5428)) — achado que **corrige premissa minha**: o `Gate::before` tem **duas pernas**, e o detector modela uma. A perna `else` faz toda ability não-declarada responder *"sim pra admin, não pro resto"* — e **4 nomes exploram isso de propósito**, então declarar seria **nocivo**.
- `biz=4` saiu do teste do Whatsapp ([#5396](https://github.com/wagnerra23/oimpresso.com/pull/5396)); gate do `ModifierSetsController` resolvido ([#5432](https://github.com/wagnerra23/oimpresso.com/pull/5432)).

## Lições catalogadas

1. **Verificar "não existe" só em código é incompleto quando há caminho de criação em runtime.** Foi a lacuna que [W] pegou. Permissão nasce da tela de papéis; o banco é fonte legítima.
2. **`mergeable=MERGEABLE` não é "pronto pra mergear"** — esse campo só diz *sem conflito*. Quem responde é `mergeStateStatus`. Reportei um PR como pronto lendo o campo errado.
3. **`FAILURE == 0` não é verde** se há check rodando. Passei a contar `SUCCESS` explícito e separar "rodando".
4. **Pathspec `Modules/*/Routes` não casa no `git grep`** — precisa de `:(glob)`. Sem isso eu teria reportado "quase tudo é scaffolding", falso.
5. **Reincidi na lápide do `git stash pop`** (§5 2026-07-27), no mesmo arquivo que ela cita: working tree limpo ⇒ `stash -u` não cria entry ⇒ o `pop` consome o stash de outra sessão. O conflito segurou; revertido pela receita, stash alheio intacto.
6. **Documentar ausência citando o path** cria a referência podre — escrever que `Modules/<X>` "não existe" gerou 2 ghosts e derrubou um gate required.

## Pointers

- Triagem completa das 4 classes: `memory/requisitos/Governance/SPEC.md` §US-GOV-059
- Perna `else` do `Gate::before` e as 4 que não devem ser declaradas: handoff `2026-08-08-1938-permissoes-classe-d-*`
- Detector: `scripts/governance/permission-drift.mjs` (roda no CI via `governance-script-tests.yml`)
