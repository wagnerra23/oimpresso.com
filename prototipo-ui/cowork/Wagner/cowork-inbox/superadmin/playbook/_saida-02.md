---
sessao: "_saida-02"
thread: "02 · Negócios: create/show → drawer da lista (Negocios/Index)"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main 81a585242e
---
# _saida-02 (PR-1: show → drawer · PR-2: create → drawer)

## Decisão [W] (2026-10-05, no chat)
A página `/superadmin/business/{id}` mostrava mais que o drawer. Pergunta feita: o que entra no
drawer antes de a show virar redirecionamento. Resposta: **tudo entra no drawer**.

## Entregue
- `Modules/Superadmin/Http/Controllers/BusinessController.php`
  - `show()` redireciona para `/superadmin/business?negocio=<id>` (o drawer da lista). Não lê
    mais o negócio.
  - `detalheDoNegocio()` ganhou o que só a show tinha:
    - `cadastro`: moeda, imposto 1 e 2 (rótulo + número), fuso, quem cadastrou, logo;
    - `locais`: nome, código, referência, cidade, UF, CEP, país;
    - `usuarios`: a mesma lista do `usersList()` da show (sem o superadmin logado, sem agente de
      comissão), com papel, e `pode_agir` = `can('user.update')`, o mesmo `@can` da show;
    - no histórico de assinaturas: fim do teste, pago via, transação, data e autor do lançamento.
      O limite de 12 saiu (a show listava todas).
- `Negocios/_components/SecoesDoDetalhe.tsx` (novo): seções "Dados do cadastro", "Locais" e
  "Usuários". Por usuário: **Definir senha** (diálogo, mínimo 8 caracteres, `POST
  /superadmin/update-password` — o mesmo endpoint e a mesma validação da show) e **Entrar como**
  (`/sign-in-as-user/{id}?save_current=true`, o mesmo link da show). `Linha` e `Secao` saíram do
  `Index.tsx` para cá.
- `Negocios/Index.tsx`: as três seções no fim do drawer; histórico com as linhas novas; `esc` no
  diálogo fecha só o diálogo (o Radix marca o evento como tratado).
- Apagados: `Resources/views/business/show.blade.php` e `update_password_modal.blade.php` (só a
  show o incluía).
- Casos: UC-SANEG-09 (show redireciona) e UC-SANEG-10 (drawer carrega o que a show mostrava),
  com teste em `SuperadminNegociosContratoTest.php`. UC-SANEG-10 tem pré-condição anti-vácuo: o
  usuário comum precisa estar na lista para as ausências (superadmin, agente) provarem algo.
- `memory/requisitos/Superadmin/SUPERFICIE.md` regerado.

## Ordem das seções (forma)
O protótipo (`NegocioDrawer`) tem 4 seções: Assinatura, Uso, Dono e contato, Histórico. Elas
ficaram como estavam e no topo. As 3 novas não estão no protótipo; entraram **depois**, por
decisão [W] de preservar a capacidade da show. O contrato `superadmin-negocios` (fora do prefixo)
não foi tocado: ele exige as 4 seções do protótipo, que continuam lá na mesma ordem.

## Prova do json, medida no branch
`BusinessController.php` não contém `view('superadmin::business.show')` — ✅

## Corte
A show **era servida** (rota `GET /superadmin/business/{id}`). Por isso este PR é cutover e fica
**sem auto-merge**, para o [W] mergear.

## PR-2: create → drawer "Novo negócio"

**Decisão [W] (2026-10-05, no chat):** layout do protótipo, regras de hoje. O `NegocioForm` do
protótipo manda convite por e-mail sem senha, dias de teste e CNPJ; o `store()` exige usuário,
senha e moeda e grava a assinatura com pacote + "pago via". O drawer segue as 3 seções do
protótipo com os campos que o `store()` aceita.

- `create()` redireciona para `/superadmin/business?novo=1`. A Blade `business.create` saiu
  (o `business.partials.register_form` é do núcleo e fica).
- `index()` ganha `novo` e `formNovo` (deferido; só consulta com `?novo=1`): moedas (BRL de
  padrão), fusos (`config('app.timezone')` de padrão), pacotes ativos por `sort_order` e gateways
  configurados — as mesmas listas da Blade.
- `Negocios/_components/NovoNegocio.tsx` (novo): Identificação (nome, moeda, fuso, telefone,
  cidade, UF, CEP, país) · Dono da conta (nome, sobrenome, e-mail, usuário, senha + confirmação) ·
  Assinatura inicial (pacote opcional; com pacote, "pago via" e transação). Envia por `useForm`
  ao mesmo `POST /superadmin/business`; erros do servidor aparecem no campo.
- `Negocios/_components/Gaveta.tsx` (novo): a casca dos dois drawers saiu do `Index.tsx`.
- `Index.tsx`: botão "Novo negócio" no cabeçalho, tecla `n` (a do protótipo), `esc` fecha.
- `StoreBusinessRequest`: `unique` em `username` e `email` (a Blade checava no navegador por
  `/business/register/check-*`) e `paid_via` `required_with:package_id` (regra que a Blade tinha
  no jQuery Validate). Fora do prefixo da thread, mas é a mesma regra de antes, agora no servidor.
- Casos UC-SANEG-11 (create redireciona), 12 (opções só com o drawer aberto) e 13 (duplicado e
  pacote sem "pago via" voltam como erro do campo, sem criar negócio), com teste.

### Do protótipo e não do backend (gap registrado, não construído)
Convite por e-mail com link de senha, dias de teste, data de início da assinatura, CNPJ, celular
do dono e o liga/desliga "Negócio ativo".

### Da Blade e não do drawer
Logo, site, telefone alternativo, ponto de referência, impostos, mês de início do exercício e
método de custeio. São opcionais no `StoreBusinessRequest`; sem eles o banco usa o padrão. Logo e
impostos podem ser preenchidos depois nas configurações do negócio.

### Observado e não mexido
`business_locations.zip_code` é `char(7)`: um CEP com 8 dígitos não cabe. Já era assim na Blade.

## Pendente
- `usersList()` e a rota `GET /superadmin/users/{business_id}` ficam sem consumidor (só a show
  usava). Não removidos: a rota está fora do prefixo.
- Quem tem `superadmin` sem `user.update` não vê os botões, como na show.

## NÃO MEDI
Pest, PHPStan e typecheck (CI/CT 100; o worktree não tem `node_modules`). Render do drawer em
produção: depois do merge.
