---
sessao: "16c"
titulo: Saída da thread 16 (3ª passada) — /asset/revocation redireciona para Alocações
dono: "[CL]"
medido_em: 2026-09-30
base_medida: 057d3c748 (origin/main)
pr: "https://github.com/wagnerra23/oimpresso.com/pull/8286"
complementa: "_saida-16.md (parou no dado) e _saida-16b.md (decisão [W] pela opção b)"
---

# 16c · A decisão (b) executada

## Pré-requisitos da `_saida-16b`, conferidos no `main`
A `_saida-16b` punha a 16 depois de duas coisas. As duas estão no ar:

| pré-requisito | onde está |
|---|---|
| drawer de detalhe do bem, aba Alocações, com devoluções 1 : N | `_shared/DetalheBemDrawer.tsx` (código, quantidade, data, autor e motivo de cada devolução) |
| thread 18 — drawer Devolver com **Excluir devolução** | #8262, `_saida-18.md` |

Excluir era a única capacidade que só a lista Blade `/asset/revocation` tinha. Com ela no drawer,
redirecionar deixou de apagar caminho de UI.

## O que mudou
- `RevokeAllocatedAssetController::index()` redireciona para `AssetAllocationController::index`
  (`/asset/allocation`). O gate de assinatura continua antes do redirecionamento.
- O ramo `ajax()` com o DataTables saiu. Ele tinha um defeito além de ser Blade: o cliente Inertia
  manda `X-Requested-With` em toda visita, então quem clicava em **Devoluções** no menu recebia o
  JSON cru do DataTables. O único consumidor dele era a própria Blade.
- `asset_revocation.index` tem **0** chamadores em PHP (`git grep`). O `.blade.php` fica no disco,
  como a thread permitia; retirá-lo é onda de limpeza.
- `Alocacoes.tsx`: sai o botão **Devoluções** do cabeçalho, que apontava para a lista. Ele não
  existe no protótipo e não está declarado no contrato de tela (`patrimonio-alocacoes.contract.json`
  o lista como copy não declarada de propósito).
- Charter de Alocações: o Non-Goal que dizia "Devoluções é aba própria" foi emendado com data.
- `SmokeRoutesTest`: 2 casos novos. O de redirecionamento testa **com** e **sem** `X-Inertia`; o
  controle prova que, sem a assinatura do módulo, a rota segue dando 403.

## Prova de que o teste morde
O teste subiu **sozinho** antes do conserto, com o controller antigo, na lane
`PHP / Pest (AssetManagement · MySQL)`:

| run | commit | resultado | leitura |
|---|---|---|---|
| 36750156755 | `94e3ee7fe` | 2 failed, ambos **409** | **não vale**: `X-Inertia` num GET sem versão, o middleware respondeu antes do controller |
| 36750763699 | `04ca384ea` | `1 failed, 136 passed (638 assertions)` | **vale**: o redirecionamento recebeu **200** (JSON do DataTables); o controle passou com **403** |

A lane irmã `Pest AssetManagement` (SQLite) **pulou** os dois casos (`77 skipped`): o verde dela
não mede este teste.

## O que ficou de fora, com motivo
- **Ghost `revocation` do menu** (`DataController::modifyAdminMenu`, "Devoluções") segue
  registrado e hoje leva a Alocações. Tirá-lo muda o sub-nav das 6 telas e o
  `MenuGhostsContratoTest`; o `DataController` não está no prefixo desta thread.
- **As provas do índice não batem com a decisão (b).** A 1ª prova da thread exige que o controller
  contenha `Inertia::render('Patrimonio/Alocacoes'`, escrita quando o plano era renderizar a
  Page na mesma rota. Com o redirecionamento, a prova segue reprovando. Não escrevi a string no
  controller só para satisfazê-la. O índice é do Cowork: a prova precisa virar algo como
  `contem "redirect()->action([AssetAllocationController::class, 'index'])"`.
- `revocation.edit`/`update` seguem como a `_saida-18` deixou.

## O que o índice precisa refletir (do Cowork, não editado aqui)
- thread 16: trocar a prova 1 pela do redirecionamento e tirar do §B/§4-ter "a rota NÃO funde" e
  "prop opcional em `Alocacoes.tsx`";
- threads 17 e 18: a dependência da 16 fica satisfeita quando a prova for trocada.
