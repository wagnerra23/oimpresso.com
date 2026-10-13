---
sessao: "17b"
titulo: Saída da thread 17 (adendo) — smoke em produção da edição do bem
dono: "[CL]"
medido_em: 2026-10-13
base_medida: "origin/main 94c005aec5 no início da sessão; o SHA deployado não foi conferido"
complementa: "_saida-17.md (2026-09-30), que declarou o smoke de edição NÃO feito porque a empresa do smoke não tinha bem"
---

# 17b · Smoke em produção: editar o bem grava e volta ao estado de antes

Adendo datado. O `_saida-17.md` fica intacto.

## Onde
`oimpresso.com`, WR2 Sistemas (biz=1, tenant de smoke), usuário com `criar`/`editar`/`excluir`
no Patrimônio. O bem usado foi o **id=2**, "TESTE — medição drawer (apagar)", que já existia
na empresa (compra em 30/09/2026, quantidade 2, valor unitário R$ 1,00). Ele não foi criado
nesta passada.

## Resultado

| Cenário | Resultado |
|---|---|
| Clique em **Editar** na linha do bem | URL vira `/asset/assets/2/edit`, abre o drawer "Editar bem" com os campos preenchidos e o foco no **Nome** |
| `/asset/assets/2/edit` carregado direto | 200, componente `Patrimonio/Bens`, prop `edicao` presente |
| `/asset/assets/999999999/edit` | **404** |
| **Salvar alterações** com só a Descrição alterada | toast "Sucesso", o drawer fecha e a URL volta pra `/asset/assets` |
| Releitura do servidor (`edicao.form`) | `descricao` gravada; `nome`, `quantidade` (2) e `valorUnitario` (1) iguais; `garantias` = `[]` (nenhuma criada) |
| Reabrir o drawer | o campo Descrição mostra o valor salvo |

Só a Descrição foi tocada, para não mexer em valor nem estoque (REGRA MESTRE).

## Reversão
A Descrição foi esvaziada pelo mesmo drawer e salva de novo. Na releitura do servidor, o
`edicao.form` ficou igual ao de antes da passada: `descricao` vazia, `nome`, `quantidade`,
`valorUnitario`, `compraEm`, `categoriaId`, `localId` e `tipoCompra` inalterados,
`garantias` = `[]`.

## O que não foi medido
- O SHA em produção no momento do smoke (o deploy não foi conferido por ancestralidade).
- Usuário sem `editar` (a guarda fica com os testes do módulo).
- Editar com garantia preenchida: o bem não tem garantia, e o reenvio das garantias antigas
  que a `_saida-17` descreve não foi exercitado.
