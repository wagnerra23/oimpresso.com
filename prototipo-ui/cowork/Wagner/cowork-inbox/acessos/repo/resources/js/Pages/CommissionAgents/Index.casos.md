# Comissionados e apuração — casos de uso

> Telas: `resources/js/Pages/CommissionAgents/{Index,Apuracao}.tsx` (tradução do
> `/sales-commission-agents` + apuração nova aprovada por [W] em 2026-08-19).
> F1: `prototipo-ui/cowork/acessos/comissionados-page.jsx` e `comissoes-page.jsx`.
> Testes: `tests/Feature/Users/SalesCommissionAgentTest.php`.

## Rastreabilidade

| UC | Regra | Teste |
|---|---|---|
| UC-CMS-01/02 | C6 | caso 1 |
| UC-CMS-03/04 | C3 | — (contrato de tela) |
| UC-CMS-05 | C1, C2, C4 | caso 2, 3 |
| UC-CMS-06 | C5 | caso 4, 5 |
| UC-CMS-08 | C7, D5 | caso 6, 7 |
| UC-CMI-* | D6 | backend novo |

## UC-CMS-01 · abrir a lista
**Dado** um usuário com `user.view` **ou** `user.create`
**Quando** abre a tela
**Então** vê apenas linhas com `is_cmmsn_agnt = 1` do próprio negócio.

## UC-CMS-02 · sem nenhuma das duas permissões
**Então** 403.

## UC-CMS-03 · nome montado
**Dado** um agente sem sobrenome
**Quando** a lista renderiza
**Então** o nome não tem espaço duplo — a tela normaliza o `CONCAT(surname, first_name, last_name)`
que o legado produz com `COALESCE`.

## UC-CMS-04 · buscar
**Quando** digita parte do nome
**Então** casa o nome completo concatenado, e-mail e usuário vinculado.

## UC-CMS-05 · cadastrar
**Dado** o formulário com prefixo (`surname`), primeiro nome, sobrenome, e-mail, contato, endereço e %
**Quando** salva
**Então** grava `is_cmmsn_agnt = 1` e `allow_login = 0` **sempre** (mesmo se o POST mandar o contrário),
aceita `5,00` em pt-BR, e recusa texto com 422.

## UC-CMS-06 · editar
**Então** o escopo é `business_id` **e** `is_cmmsn_agnt = 1`: id de outro negócio ou de usuário comum
não é alcançado.

## UC-CMS-07 · regra de comissão
**Dado** o bloco "Regra de comissão"
**Quando** escolhe faixa de meta ou margem
**Então** os campos mudam de acordo (dois percentuais + meta, ou percentual sobre margem) — o legado só
tem % fixo, então isto é backend novo (D6).

## UC-CMS-08 · excluir com venda vinculada
**Quando** tenta excluir um agente com venda apontando para ele
**Então** é **bloqueado** com a contagem de vendas e a alternativa de inativar — nunca hard delete
silencioso (hoje o `destroy()` apaga a linha de `users`).

## UC-CMI-01 · apuração por período
**Dado** o período selecionado
**Quando** troca de período
**Então** base, comissão apurada, já pago e a pagar recalculam, e o total do rodapé é igual à soma das linhas.

## UC-CMI-02 · só vendas faturadas
**Quando** desliga o filtro
**Então** vendas a receber entram e a tela avisa que o valor pode cair na baixa.

## UC-CMI-03 · extrato
**Quando** abre o extrato de um agente
**Então** vê a conta aberta — venda, base, % aplicado e comissão por linha — mais a fórmula da regra dele.

## UC-CMI-04 · faixa de meta
**Dado** faturado acima da meta
**Então** a comissão é `meta × %1 + excedente × %2`; com faturado **igual** à meta, o segundo % não entra.

## UC-CMI-05 · fechar período
**Quando** fecha
**Então** a apuração trava, seleção e lançamento ficam indisponíveis (403 no POST, não só na UI), e venda
nova com data dentro do período passa a contar no período seguinte.

## UC-CMI-06 · lançar pagamento
**Quando** lança
**Então** gera **um** título a pagar por agente (favorecido, valor, competência) no Financeiro, sem baixar
caixa e sem alterar as vendas.
