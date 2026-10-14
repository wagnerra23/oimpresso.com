---
sessao: "decisoes-w"
titulo: "Crm — D3 cobre o portal que o contato vê (/contact/*); 01 fecha pelos contratos de 02·03·04"
executor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main 6511ed7c34
---
# _DECISOES-W-2026-10-14 — Crm (pedido ao Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` e das fichas `01` e `05` DO COWORK. O Code não
> edita o espelho (§5 2026-09-24). Vale quando o Cowork aplicar e o próximo retorno descer.
> Este arquivo não é recibo: o placar lê só `_saida-<id>.md`.

## O que o placar mostra hoje

`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/crm/playbook/00-INDICE.md`
em `origin/main` 6511ed7c34:

```
Crm: entregue 17 de 19 · próximo 0 · em curso 0 · pendente 0 · bloqueada 2
  01 [bloqueada] Trio + contratos das 3 telas do lote 1 (do protótipo, antes do código)
  05 [bloqueada] Lote 2 + portal do contato (D3) — o Cowork levanta e fatia
```

## A decisão de [W]

A D3 foi respondida em 2026-10-01 dentro do "Aprove todos" (`_DECISOES-W-2026-10-01.md`). A pergunta
dela descrevia o portal como `ContactLoginController` (logins e comissões). Isso é o lado do admin, e a
thread 16 já entregou. A área que o próprio contato vê não estava na pergunta.

Perguntado em 2026-10-14 se a D3 cobre também as telas de `/contact/*`, [W] respondeu:
**"Sim, entram no lote 2."**

## O que está medido em `/contact/*`

Grupo de rotas `Modules/Crm/Routes/web.php:6` (middleware `ContactSidebarMenu`, `CheckContactLogin`,
prefixo `contact`). As 6 telas seguem em Blade, com zero `Inertia::render` nos controllers:

| tela | rota | controller → view |
|---|---|---|
| Painel do contato | `contact/contact-dashboard` | `DashboardController:49` → `crm::dashboard.index` |
| Perfil (+ senha) | `contact/contact-profile` | `ManageProfileController:51` → `crm::profile.edit` |
| Compras | `contact/contact-purchases` | `PurchaseController:166` → `crm::purchase.index` |
| Vendas | `contact/contact-sells` | `SellController:211` → `crm::sell.index` |
| Extrato | `contact/contact-ledger` | `LedgerController:46` → `crm::ledger.index` (o `getLedger:75` devolve `contact.ledger` do core) |
| Agendamentos | `contact/bookings` | `ContactBookingController:89` → `crm::booking.index` (+ stubs `crm::create/show/edit`) |

O protótipo já tem `CrmPortalPage` (citado no §1 deste índice). Ele é a fonte dessas telas.

Fora do D3, e só para o Cowork conferir ao levantar, seguem em Blade no lado do admin:
`crm/b2b-marketplace` (`CrmMarketplaceController:51`) e a gestão de modelos de proposta
(`ProposalTemplateController`, 7 views). A thread 14 migrou a lista `/crm/proposals`; o
`Index.tsx` dela ainda leva o usuário para a Blade do modelo. Entram no lote só se o Cowork achar
que têm tela no protótipo.

## O que mudar no índice

**1. Decisão D3** — trocar a resposta:

```json
{
  "id": "D3",
  "respondida": true,
  "resposta": "sim. [W] 2026-10-01 aprovou; em 2026-10-14 confirmou que cobre também as 6 telas que o contato vê em /contact/* (painel, perfil, compras, vendas, extrato, agendamentos). Logins e comissões (lado admin) = thread 16.",
  "fonte": "_DECISOES-W-2026-10-14.md"
}
```

**2. Thread 01** — ela não está mais esperando nada. Os 3 contratos que ela pedia existem no `main`,
cada um entregue no PR da sua Page (o `_saida-01.md` explica por que não podiam vir antes):

| contrato | entrou em |
|---|---|
| `governance/design/contracts/crm-leads.contract.json` | #8370 (thread 02) |
| `governance/design/contracts/crm-acompanhamentos.contract.json` | #8352 (thread 03) |
| `governance/design/contracts/crm-painel.contract.json` | #8369 (thread 04) |

Pedido: tirar o `bloqueio`, pôr as 3 provas e depender de 02·03·04. Com o `_saida-01.md` já no
espelho, o placar passa a contar a 01 como `feito` (18 de 19):

```json
{
  "id": "01",
  "depende_threads": ["A1", "02", "03", "04"],
  "provas": [
    { "tipo": "json_com_chaves", "path": "${CONTRATOS}/crm-leads.contract.json", "chaves": ["alvo", "secoes"] },
    { "tipo": "json_com_chaves", "path": "${CONTRATOS}/crm-acompanhamentos.contract.json", "chaves": ["alvo", "secoes"] },
    { "tipo": "json_com_chaves", "path": "${CONTRATOS}/crm-painel.contract.json", "chaves": ["alvo", "secoes"] }
  ],
  "nota_provas": "absorvida: cada contrato entrou no PR da sua Page (02 #8370 · 03 #8352 · 04 #8369). Derivação no _saida-01."
}
```

(e remover a chave `bloqueio`). A variável `CONTRATOS` já existe no índice.

Testado numa cópia do índice fora do repo, com esta mudança aplicada:
`Crm: entregue 18 de 19 · bloqueada 1` e `01 [feito]`. Controle negativo: trocando a chave
`alvo` do contrato do Painel por uma que não existe, a 01 volta a `em curso` com
`crm-painel.contract.json (faltam chaves naoexiste)`. As provas medem os arquivos. A ficha
`01-trio-lote1.md` deve dizer o mesmo em uma linha.

Se o Cowork preferir não reescrever a 01, ela fica `bloqueada` e o módulo fecha igual
(`placar-indice.mjs:321` conta `bloqueada` como fechada). A diferença é só o 17 virar 18.

**3. Thread 05 e lote 3** — levantar as 6 telas de `/contact/*` a partir do `CrmPortalPage`,
no mesmo formato de A2 + 11..17:

- uma thread de ALVO (`A3`) com os 6 `${ALVOS}/crm--portal-<tela>--index.*`;
- uma thread por tela (ou por par, se o Cowork achar melhor), depois da A3;
- na 05, trocar o `bloqueio` para `"fatiada: lote 2 = A2 + 11..17 (2026-10-07); portal do contato = A3 + <ids> (2026-10-14)"`.

O que o Code precisa saber ao pegar essas threads, para o Cowork escrever nas fichas:

- é área de **contato**, não de usuário: outro guard (`CheckContactLogin`) e outro menu
  (`ContactSidebarMenu`). O shell do admin (AppShellV2) pode não servir; a ficha precisa dizer qual
  casca a tela usa;
- o escopo é por **contato logado**, além do `business_id`. Uma tela que mostre compra, venda ou
  extrato de outro contato do mesmo negócio é vazamento;
- `Extrato` mostra valores. Se a migração mexer em cálculo de saldo, vale a regra de valor
  (dupla prova e aprovação de [W]). Se só trocar a tela, não.

## Pendente

Nada espera [W]. Fica com o Cowork: aplicar os 3 itens acima e levantar o lote 3.
