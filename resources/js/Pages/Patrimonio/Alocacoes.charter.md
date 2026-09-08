---
page: /asset/allocation
component: resources/js/Pages/Patrimonio/Alocacoes.tsx
owner: wagner
status: draft
parent_module: AssetManagement
related_us: [US-ASSET-001, US-ASSET-W05]
related_adrs: [0394-endereco-de-ui-do-patrimonio-pages-patrimonio, 0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0180-sidebar-v3-5-grupos-ghosts-header]
related_prototype: prototipo-ui/cowork/patrimonio-page.jsx
related_runbook: memory/requisitos/AssetManagement/RUNBOOK-alocacoes.md
tier: B
charter_version: 1
last_validated: "2026-09-08"
---

# Page Charter — Patrimonio/Alocacoes (DRAFT)

> **Terceira tela Inertia do `Modules/AssetManagement`**, depois de Bens (que fundou o
> `_shared/`). Ela **reusa** o `PatrimonioSubNav` — não cria um segundo.
> Padrão de Tela: **PT-01 Lista** ([PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)).

> **Pasta ≠ módulo, e isso é decisão.** A tela mora em `resources/js/Pages/Patrimonio/` por
> [ADR 0394](../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md),
> enquanto o PHP vive em `Modules/AssetManagement/` e os requisitos em
> `memory/requisitos/AssetManagement/`. **Não existe `Modules/Patrimonio`.**
> O `related_runbook` acima não é enfeite: o hook `block-mwart-violation` deriva o RUNBOOK do
> nome da pasta de `Pages/` e procuraria `requisitos/Patrimonio/`, que não existe nem deve
> existir. O campo é o caminho previsto pelo próprio hook — *"declaração é autoritativa,
> adivinhação não"* (`_saida-06-bens.md §1`).

## Mission

Mostrar **o que está na mão de quem**. Cada linha é uma entrega de bem a uma pessoa: quando
saiu, até quando pode ficar, quanto foi e quanto já voltou. É a tela que transforma "a casa
tem 10 furadeiras" em "3 estão com o João desde março" — e é o rastro de responsabilidade que
o módulo existe pra dar.

## Goals — Features (faz)

- Lista paginada das alocações do business corrente (`asset_transactions` com
  `transaction_type = allocate`), ordenada por data de alocação — **mais recente primeiro**,
  o mesmo default do Blade.
- Busca **server-side** pelos campos que identificam a linha: código da alocação, nome do
  bem, modelo e nome de quem recebeu.
- Recorte por **situação**, feito no servidor: **Ativas** (ainda há unidade na mão da pessoa)
  · **Devolvidas** (voltou tudo) · **Todas**.
- Por linha: código, bem + modelo, quem recebeu, quem entregou, categoria, data de alocação,
  prazo, quantidade, quantidade devolvida, motivo e a situação.
- Ações por linha, cada uma pra rota que já existe: editar · devolver · excluir — desenhadas
  conforme o que a alocação permite (o que já voltou por inteiro não se devolve de novo).
- Sub-navegação do módulo **derivada** de `shell.menu`, nunca declarada aqui.
- Estados: cheia · filtrada-vazia · vazia · carregando (esqueleto do defer) · sem-permissão
  (403 do gate de assinatura).

## Non-Goals — Features (NÃO faz)

> Proposta [CC] a partir da medição desta onda — **[W] aprova antes de `status: live`.**
> Cada item vira Pest GUARD quando a onda correspondente entrar. Os três primeiros são
> **adiamento com motivo** (§5 do RUNBOOK), não recusa permanente.

- ❌ NÃO aloca, edita nem devolve dentro da tela. Os três são **escrita de quantidade** —
  REGRA MESTRE Tier 0, que exige prova por dois caminhos independentes + antes→depois
  apresentado ao [W]. Os botões apontam pras rotas Blade reais, que continuam funcionando.
- ❌ NÃO soma "N unidades alocadas" no rodapé (protótipo `:462`). É **agregação de
  quantidade** — a mesma REGRA MESTRE que fez a irmã Bens recusar o total de valor. O número
  **por linha** entra.
- ❌ NÃO põe contagem nas pílulas das sub-abas. Contar sobre o **conjunto** pede agregações
  extras; contar só a página corrente faria a pílula dizer "4" olhando 25 de N linhas — a
  mentira que a Bens já catalogou.
- ❌ NÃO mostra avatar nem "papel" de quem recebeu (protótipo `:437`): `users` não tem papel
  na alocação. O protótipo desenha um campo que o modelo não tem.
- ❌ NÃO implementa a trava de saldo. Ela não é desta tela — é a **thread 02**, no
  `AssetAllocationService`. Ver a dívida declarada abaixo.
- ❌ NÃO cruza tenants — `AssetTransaction` não tem global scope, o filtro por `business_id`
  é manual ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0).
- ❌ NÃO exporta, não imprime, não configura colunas nem densidade.
- ❌ NÃO renderiza aba que não navega. Devoluções é aba **própria** (`/asset/revocation`,
  ghost `revocation`); o protótipo a trata como estado dentro desta tela, e **a rota manda**
  (`_saida-06-bens.md §2`).

## Anti-hooks (NÃO faz automaticamente)

> Mesma ressalva: proposta [CC], [W] aprova antes de `live`.

- ❌ NÃO escreve nada. A tela é de leitura pura — nenhum caminho aqui grava, apaga ou dispara
  job. As ações são links para rotas que já existiam.
- ❌ NÃO recalcula, arredonda nem converte quantidade. Exibe o que o backend mandou.
- ❌ NÃO deriva "prazo vencido" no cliente a partir de `allocated_upto`. A comparação é do
  servidor, com o relógio dele — derivar no browser faria o veredito depender do fuso da
  máquina de quem olha.
- ❌ NÃO esconde a coluna "Devolvido" por causa do resíduo Tier 0 abaixo: o Blade já a
  mostrava, e escondê-la seria regressão que não conserta o dado.
- ❌ NÃO oferece "devolver" em alocação já devolvida por inteiro — botão que parece agir e
  não age é afordância falsa.

## Dívida herdada — declarada, não escondida

⚠️ **"Devolvido" NÃO é número auditado.** O `leftJoin` de `asset_transactions as PT` por
`parent_id` (`AssetAllocationController::index()` `:70`), que alimenta `revoked_quantity`,
**não filtra `PT.business_id`**. Mesmo padrão do gêmeo catalogado em `_saida-01.md §9(a)` e
confirmado pela Bens (`_saida-06-bens.md §5`), com thread dona.

Esta onda **preserva a expressão byte-a-byte** e não a conserta, por duas leis que caem
juntas: mexer em quantidade é REGRA MESTRE Tier 0 (prova dupla + antes→depois + [W]) e
1 PR = 1 intent.

⚠️ **Não há trava de saldo no backend.** `AssetAllocationService::criar()` grava sem consultar
`quantidadeDisponivel()` — dá pra alocar 10 de um bem que tem 3 (thread 02). Esta tela **não
finge que a trava existe**: ela não oferece o caminho de criar, e não desenha nenhum aviso de
saldo que sugira uma proteção inexistente. Validação só no cliente seria segurança de teatro.

⚠️ **Assimetria de permissão.** A thread 03 pôs `asset.view` no `index()` de **Bens**. Este
controller não tem guarda `asset.*` em método nenhum — só a assinatura do módulo. Registrado,
não consertado: mudaria quem enxerga a tela, é decisão [W], e 1 PR = 1 intent.

## UX Targets

- Cabe em 1280px (monitor do piloto) sem scroll horizontal **na página**: a tabela rola dentro
  do próprio wrapper, em vez de espremer coluna.
- `tabular-nums` em todo número e data.
- Ação por linha nasce à **esquerda** (como o protótipo `:422` e como a Bens): a tabela é mais
  larga que a janela e o botão primário não pode nascer fora do viewport.
- Nenhum atalho de teclado é anunciado nesta onda — declarar atalho que a tela não implementa
  é afordância falsa.

## Pendências antes de `status: live`

1. [W] aprovar os Non-Goals e Anti-hooks acima (hoje são proposta [CC]).
2. Screenshot aprovado por [W] (gate visual F1.5).
3. `Alocacoes-visual-comparison.md` — comparação **medida** contra o protótipo
   (`design-diff --probe` nos dois lados), nunca no olho.
4. Decidir o resíduo Tier 0 de "Devolvido" (thread do gêmeo) e a trava de saldo (thread 02).
5. **US no `SPEC.md`** — mesma pendência que a Bens registrou (`_saida-06-bens.md §8`): a
   `US-ASSET-W05` (migração Blade→Inertia) segue marcada como backlog feature-wish, embora a
   ADR 0394 e o `SCOPE.md` já a tenham liberado e estas telas sejam a entrega dela.

## Refs

- RUNBOOK: [`memory/requisitos/AssetManagement/RUNBOOK-alocacoes.md`](../../../../memory/requisitos/AssetManagement/RUNBOOK-alocacoes.md)
- Casos: [`./Alocacoes.casos.md`](./Alocacoes.casos.md)
- Irmã que fundou o `_shared`: [`./Bens.charter.md`](./Bens.charter.md)
- Fonte visual: `prototipo-ui/cowork/patrimonio-page.jsx` (aba `alocacoes`, `:409`) — **alvo**,
  não decisão de produto
- Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/06-ui-bloqueada.md`
- [PT-01 Lista](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)
