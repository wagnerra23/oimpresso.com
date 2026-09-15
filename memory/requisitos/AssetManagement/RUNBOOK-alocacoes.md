---
id: requisitos-asset-management-runbook-alocacoes
title: "RUNBOOK — Patrimônio · Alocações (`/asset/allocation`)"
module: AssetManagement
tela: Patrimonio/Alocacoes
owner: W
status: rascunho
last_validated: "2026-09-08"
preconditions:
  - "Usuário autenticado; a tela exige hoje só a assinatura do módulo — `asset.*` NÃO guarda este controller (ver §9, assimetria declarada)"
  - "`business_id` na sessão — `AssetTransaction` NÃO tem global scope; o isolamento é filtro manual (ADR 0093, Tier 0)"
  - "Módulo `assetmanagement_module` habilitado no pacote do business (Camada 1 — superadmin/packages)"
  - "Middleware `AdminSidebarMenu` na rota — é ele que dispara `DataController::modifyAdminMenu()`, dono dos ghosts que a sub-navegação lê"
preconditions_short: business_id na sessão, módulo habilitado, AdminSidebarMenu na rota
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0180-sidebar-v3-5-grupos-ghosts-header, 0394-endereco-de-ui-do-patrimonio-pages-patrimonio]
---

# RUNBOOK — Patrimônio · Alocações (`/asset/allocation`)

> **F1 PLAN do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).**
> Escrito ANTES do `.tsx`, como o hook `block-mwart-violation` exige — ele não tem override
> (medido 2026-08-08: zero `process.env`, única saída é `process.exit(2)`).
>
> **Terceira tela Inertia do módulo**, depois de Bens (que fundou o `_shared/`). Ela **reusa**
> o `PatrimonioSubNav` — não cria um segundo.

## 1. Objetivo

Migrar a **listagem de alocações** (`asset_transactions` com `transaction_type = allocate`)
de Blade + DataTables (yajra, server-side via ramo `ajax`) para Inertia/React, no endereço da
[ADR 0394](../../decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md):
`resources/js/Pages/Patrimonio/Alocacoes.tsx`.

**A URL não muda.** `Route::resource` já cria `allocation.index` → `GET /asset/allocation`
(`Routes/web.php:14`). Criar rota nova seria um segundo dono da mesma tela — o mesmo veredito
que a Bens registrou (`_saida-06-bens.md §3`). **O arquivo de rotas não é tocado por esta
onda**, o que também a tira do caminho das threads irmãs, que o compartilham.

## 2. Persona principal

Quem responde pelo patrimônio: precisa saber **o que está na mão de quem**, desde quando, até
quando, e o que já voltou. É a tela que transforma "a casa tem 10 furadeiras" em "3 estão com
o João desde março".

## 3. Pré-requisitos

Ver `preconditions` no frontmatter. O que mais morde na prática:

| Pré-requisito | Se faltar |
|---|---|
| `business_id` na sessão | a query filtra por nulo e a lista vem vazia — não é erro visível |
| `assetmanagement_module` no pacote | 403 no `index()` (gate de assinatura, `:59`) |
| `AdminSidebarMenu` na rota | `shell.menu` não é montado e a sub-navegação **degrada pra nada** (o `_shared` devolve nulo de propósito) |

## 4. Fluxo principal (golden path)

1. Usuário abre `/asset/allocation` pelo ghost **Alocações** do menu do módulo.
2. `AssetAllocationController::index()` valida assinatura e monta `Inertia::render` do
   componente `Patrimonio/Alocacoes`.
3. A prop cara (`alocacoes`) é **deferida** — a tela pinta cabeçalho, abas e filtros primeiro.
4. Partial reload traz a página de 25, ordenada por **data de alocação, mais recente
   primeiro** (mesmo default do Blade: ordenação descendente na 8ª coluna).
5. Por linha: código, bem, quem recebeu, quando, até quando, quantidade, quanto voltou e a
   situação (em uso · devolvida em parte · devolvida · prazo vencido).
6. Ações de linha levam pras rotas Blade que já existem (editar · devolver · excluir).

## 5. Onda desta entrega, e o que fica pra depois

**Esta onda entrega a LISTAGEM migrada — leitura pura.** Nada aqui grava.

O que **fica pra depois**, com o motivo (não é preguiça, é lei do projeto):

| Adiado | Motivo |
|---|---|
| **Alocar / editar / devolver dentro da tela** | são caminhos de **escrita de quantidade** → REGRA MESTRE Tier 0 (prova por dois caminhos + antes→depois pro [W]). Os botões apontam pras rotas Blade reais, que continuam funcionando |
| **Rodapé "N unidades alocadas"** | é **soma de quantidade** — mesma REGRA MESTRE que fez a Bens recusar o total de valor. O número **por linha** entra |
| **Contagem nas pílulas das sub-abas** | contar "ativas/devolvidas" sobre o **conjunto** pede agregações extras; contar só a página corrente faria a pílula dizer "4" olhando 25 de N linhas — a mentira que a Bens já catalogou |
| **Avatar e papel de quem recebeu** (protótipo `:437`) | `users` não tem "papel na alocação"; o protótipo inventa um campo que o modelo não tem |
| **Trava de saldo** | **não é desta tela** — é a thread 02, no `AssetAllocationService`. Ver §11 |

## 6. Estados (loading / empty / error / success)

| Estado | O que a tela mostra |
|---|---|
| **carregando** | esqueleto de tabela enquanto o defer não resolve |
| **vazio absoluto** | "Nenhuma alocação registrada" + ação pra alocar (se o menu oferecer) |
| **vazio filtrado** | "Nenhuma alocação para esses filtros" — vazio de recorte é outro vazio, e oferecer "crie a primeira" a quem só filtrou demais é ruído |
| **sem sub-navegação** | `shell.menu` ausente, o SubNav some e a tela continua utilizável |
| **erro** | 403 do gate de assinatura; a listagem não tem caminho de erro próprio |

## 7. Atalhos de teclado

**Nenhum é anunciado nesta onda.** Declarar atalho que a tela não implementa é afordância
falsa — mesma postura da Bens.

## 8. Dependências de API/backend

| O quê | Onde |
|---|---|
| listagem | `AssetAllocationController::index()` — dois ramos: o do DataTables legado (**preservado**) e o Inertia |
| criação/edição/remoção | `AssetAllocationService` (`criar`, `atualizar`, `remover`) — **não tocados** |
| devolução | `RevokeAllocatedAssetController` — aba própria (`/asset/revocation`), ghost `revocation` |
| sub-navegação | `DataController::modifyAdminMenu()` → `shell.menu` → `_shared/PatrimonioSubNav` |

## 9. Multi-tenant + LGPD

**Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).**
`AssetTransaction` **não tem global scope** — o isolamento é o filtro explícito por
`business_id` na tabela base (`:72`), preservado.

⚠️ **RESÍDUO Tier 0 herdado — declarado, NÃO consertado nesta onda.** O `leftJoin` de
`asset_transactions as PT` por `parent_id` (`:70`), que alimenta `revoked_quantity`, **não
filtra `PT.business_id`**. É o mesmo padrão do gêmeo já catalogado em `_saida-01.md §9(a)` e
confirmado pela Bens (`_saida-06-bens.md §5`), com **thread dona**. Corrigir aqui esbarraria
em duas leis que caem juntas: mexer em quantidade é REGRA MESTRE (prova dupla + antes→depois
+ [W]) e 1 PR = 1 intent. A expressão é preservada byte-a-byte; o antes→depois medido está em
`_saida-06-alocacoes.md`.

⚠️ **Assimetria de permissão, declarada.** A thread 03 pôs `asset.view` no `index()` **de
Bens**. Este controller **não tem** guarda `asset.*` em método nenhum — só a assinatura do
módulo. **Não é conserto desta onda** (mudaria quem enxerga a tela, é decisão [W] e 1 PR = 1
intent), mas fica registrado: quem for fechar tem o precedente pronto no
`AssetController::index()`.

**LGPD:** a tela mostra **nome de pessoa** (quem recebeu, quem alocou). É dado pessoal de
colaborador, já exposto pelo Blade — sem PII nova. Nada de CPF ou documento entra no payload.

## 10. Smoke check pós-deploy

```bash
curl -sv https://oimpresso.com/asset/allocation 2>&1 | grep '^< HTTP'
```

Sem sessão, o esperado é o redirecionamento para o login. Com sessão: a tela responde 200, o
componente Inertia é `Patrimonio/Alocacoes`, e a aba **Alocações** aparece marcada na
sub-navegação.

## 11. O que NÃO fazer

- ❌ **NÃO criar rota nova** — `allocation.index` já existe (§1).
- ❌ **NÃO mexer no `AssetAllocationService`** — é prefixo da thread 02 (trava de saldo).
- ❌ **NÃO escrever validação no `StoreAssetAllocationRequest`**: ele é **órfão**. O `store()`
  (`:186`) recebe `Illuminate\Http\Request` **cru** e o controller tem **0** chamadas de
  validação — o `rules()` dele nunca executa. Regra escrita ali passa no CI e é **inerte em
  produção** (`_saida-04.md §5`).
- ❌ **NÃO deixar o React postar quantidade com exatamente 3 casas decimais.** `Util::num_uf`
  lê `2.500` como **2500** (um ponto seguido de exatamente 3 dígitos é separador de milhar
  pt-BR). É o vetor do incidente de 2026-06-05. Esta onda **não posta quantidade**; a regra
  fica aqui para a onda que postar.
- ❌ **NÃO remover o ramo do DataTables** — os modais de `create`/`edit` Blade recarregam a
  tabela por ele.
- ❌ **NÃO derivar "prazo vencido" no cliente** a partir de `allocated_upto`: a comparação é
  feita no servidor, com o relógio dele.

## 12. Diagnóstico / Troubleshoot

| Sintoma | Causa provável |
|---|---|
| lista vazia com dado no banco | `business_id` ausente na sessão, ou filtro de situação recortando tudo |
| abas somem | `shell.menu` ausente — rota sem `AdminSidebarMenu` |
| `Inertia page component file [Patrimonio/Alocacoes] does not exist` | o `.tsx` não está na árvore do ambiente que roda o teste |
| 409 no partial reload | versão do Inertia lida fora do ciclo da request — leia a versão do próprio render |
| "devolvido" maior que o esperado | resíduo Tier 0 do §9 (join `PT` sem tenant) |

## 13. Refs

- Charter: [`resources/js/Pages/Patrimonio/Alocacoes.charter.md`](../../../resources/js/Pages/Patrimonio/Alocacoes.charter.md)
- Casos: [`resources/js/Pages/Patrimonio/Alocacoes.casos.md`](../../../resources/js/Pages/Patrimonio/Alocacoes.casos.md)
- Irmã que fundou o `_shared`: [`RUNBOOK-bens.md`](RUNBOOK-bens.md)
- Fonte visual: `prototipo-ui/cowork/Wagner/patrimonio-page.jsx` (aba `alocacoes`, `:409`) — **alvo**, não decisão de produto
- Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/06-ui-bloqueada.md`
- [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md)
