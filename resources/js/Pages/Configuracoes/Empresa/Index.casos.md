---
id: resources-js-pages-configuracoes-empresa-index-casos
casos: Configuração da empresa · /business/settings
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o salvar é substituição total e mexe em margem e pontos; a tela nova não pode mudar valor gravado nem expor segredo.
owner: wagner
last_run: "2026-10-21"
---

# Casos de Uso & Aceite — Configuração da empresa

> Thread `sistema/playbook/08`, F3-1 (Negócio) e F3-2 (Impostos · Produtos · Contatos). Derivados do `BusinessController` e da Blade `business/settings`
> (RUNBOOK-empresa, `empresa-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Configuracoes/EmpresaContratoTest.php`](../../../../../tests/Feature/Configuracoes/EmpresaContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `EmpresaBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-EMP-01 · Com a flag ligada, a tela abre em React sem segredo de e-mail/SMS · `[T0]`
- **Aceite:** Dado `business_settings.access`, a flag `useV2ConfiguracoesEmpresa` ligada e uma senha em `email_settings` ·
  Quando faço `GET /business/settings` como o browser faz · Então renderiza `Configuracoes/Empresa/Index` · E a senha não
  aparece na resposta. (Até a F3-5a o corpo não trazia `email_settings`; desde 2026-10-13 traz, sem os segredos — UC-EMP-12.)
- **Status: 🧪**

## UC-EMP-02 · Flag desligada ou `?classico=1` seguem na Blade
- **Aceite:** Dado a flag ligada · Quando abro `/business/settings?classico=1` · Então vejo a Blade · E com a flag desligada
  `/business/settings` também é a Blade.
- **Status: 🧪**

## UC-EMP-03 · O corpo é do negócio da sessão · `[T0]`
- **Aceite:** Dado o meu negócio e outro · Quando a tela carrega · Então o corpo traz o nome do meu negócio, nunca o do outro.
- **Status: 🧪**

## UC-EMP-04 · Salvar sem mexer não muda nada · `[dado]` `[valor]`
- **Por quê:** o salvar zera checkbox, `common_settings`, `enabled_modules`, atalhos e números que faltam.
- **Aceite:** Dado margem, desconto, pontos, checkboxes, `pos_settings`, módulos, rótulos, atalhos e e-mail/SMS gravados ·
  Quando devolvo o corpo convertido como o browser converte (FormData) · Então nenhuma dessas colunas muda · E trocando só o
  nome, só o nome muda.
- **Status: 🧪**

## UC-EMP-05 · Número do corpo volta igual pelo `num_uf` · `[valor]`
- **Aceite:** Dado os valores 0 · 0,25 · 1 · 1,5 · 25,5 · 99,99 · 1234,5 · 2500 · 25000 · 123456,7891 com precisão de moeda 0 ·
  Quando o corpo os formata · Então `num_uf` devolve o mesmo número · E zero nunca sai como `"0"` (o salvar trocaria por 1).
- **Status: 🧪**

## UC-EMP-06 · Editar Impostos, Produtos e Contatos grava só o que mudou · `[dado]`
- **Por quê:** as três abas mexem em campos soltos e em chaves de `common_settings`; desmarcar não pode apagar as vizinhas.
- **Aceite:** Dado o corpo da tela · Quando troco o nome do imposto 1, o prefixo do SKU, ligo prateleira, desligo marca e
  ponho limite de crédito padrão "2.000,50" e garantia · Então só esses mudam · E o limite fica gravado como foi digitado
  (como na Blade: o cadastro de contato o lê como sugestão) · E e-mail, margem e pontos ficam iguais.
- **Status: 🧪**

## UC-EMP-07 · Chave secreta de pagamento não vai para a tela · `[T0]`
- **Por quê:** `pos_settings` guarda `stripe_secret_key` e `razor_pay_key_secret`, e props do Inertia ficam no HTML.
  O salvar sobrescreve `pos_settings` inteiro, então tirar as chaves do corpo as apagaria.
- **Aceite:** Dado as duas chaves gravadas · Quando a tela carrega · Então nenhuma aparece na resposta (a chave pública
  aparece) · E salvar pela tela mantém as duas · E um envio com a chave vazia, como a Blade faz, ainda limpa.
- **Status: 🧪**

## UC-EMP-08 · Editar Vendas e Pagamento grava só o que mudou · `[dado]` `[valor]`
- **Por quê:** os checkboxes de Vendas e Pagamento vivem em `pos_settings`; na Blade, desmarcar remove a chave.
- **Aceite:** Dado `allow_overselling` ligado, uma chave Stripe secreta e cédulas gravadas · Quando a tela carrega · Então
  ela sabe que a chave secreta existe (`segredos_gravados`) sem receber o valor · E quando ponho desconto padrão "7,5",
  preço sem imposto, desligo vender sem estoque, ligo preço mínimo, arredondamento 0,05 e contagem em dinheiro e cartão ·
  Então o desconto grava 7,5 · E `allow_overselling` some de `pos_settings` · E cédulas e a chave secreta ficam iguais ·
  E as outras colunas não mudam.
- **Status: 🧪**

## UC-EMP-09 · Venda no PDV: balança e atalhos voltam iguais · `[dado]` `[estoque]`
- **Por quê:** a balança decide a quantidade lida do código de barras. A Blade grava o **índice** do seletor (0 = "1"
  dígito) e o `SellPosController` lê `índice + 1`; um seletor que gravasse o rótulo mudaria a quantidade de toda venda
  por balança.
- **Aceite:** Dado balança, atalhos e opções do PDV gravados · Quando devolvo o corpo sem mexer · Então balança, atalhos e
  `pos_settings` ficam iguais · E quando escolho "6" dígitos de SKU · Então grava `5` · E desmarcar uma opção que tem
  padrão (esconder rascunho) volta `0`, como na Blade.
- **Status: 🧪**

## UC-EMP-10 · Compras: a taxa de câmbio volta igual e grava o digitado · `[valor]`
- **Por quê:** a taxa converte o preço da compra em outra moeda, e o salvar a grava **crua** (sem `num_uf`). O campo é
  `type=number`, como na Blade, para chegar com ponto decimal.
- **Aceite:** Dado compra em outra moeda com taxa 5,125 · Quando devolvo o corpo sem mexer · Então taxa, moeda e as
  outras colunas ficam iguais · E quando digito "5.25" · Então grava 5,25 · E quando desligo "comprar em outra moeda" ·
  Então a moeda fica vazia e a taxa volta a 1, como na Blade.
- **Status: 🧪**

## UC-EMP-11 · Painel, Sistema e Prefixos voltam iguais · `[dado]`
- **Por quê:** `ref_no_prefixes` é gravado inteiro pelo objeto que chega; um prefixo editado não pode apagar os outros.
- **Aceite:** Dado tema, alerta de validade, linhas por tabela e dois prefixos gravados · Quando devolvo o corpo sem mexer ·
  Então nada muda · E quando troco o prefixo de compra · Então ele grava e o de devolução de venda continua.
- **Status: 🧪**

## UC-EMP-12 · E-mail e SMS: segredo não vai para a tela e só troca se digitado · `[T0]`
- **Por quê:** `email_settings` e `sms_settings` são gravados inteiros pelo objeto que chega. A senha do e-mail, o segredo
  do Nexmo, o token do Twilio e os valores de cabeçalho/parâmetro do SMS próprio (onde costuma ir o token da API) não podem
  ir para o HTML, e tirá-los do corpo não pode apagá-los.
- **Aceite:** Dado senha de e-mail, cabeçalho `Authorization` com token e segredo Nexmo gravados · Quando a tela carrega ·
  Então nenhum aparece na resposta e `segredos_gravados` diz quais existem · E salvar sem mexer mantém todos · E trocar o
  servidor e digitar outra senha muda só esses dois · E um envio com a senha vazia, como a Blade faz, ainda limpa.
- **Status: 🧪**

## UC-EMP-13 · Pontos de fidelidade e Módulos voltam iguais · `[valor]` `[dado]`
- **Por quê:** quatro campos de pontos passam por `num_uf` e decidem quantos pontos a venda rende
  (`floor(total / valor_por_ponto)`, `TransactionUtil.php:4694`) e quanto vale o resgate; `enabled_modules` é gravado
  pela lista que chega.
- **Aceite:** Dado pontos ligados com os dez campos preenchidos e dois módulos · Quando devolvo o corpo sem mexer · Então
  nada muda · E quando digito "2,5" no valor da venda para 1 ponto, troco o nome e troco um módulo · Então grava 2,5, o
  nome novo e a lista nova · E os outros valores de pontos ficam iguais.
- **Status: 🧪**

## UC-EMP-14 · Rótulos personalizados voltam iguais · `[dado]`
- **Por quê:** `custom_labels` é gravado inteiro e carrega, além dos nomes, o tipo e as opções de cada campo de produto
  (`product_cf_details`, opções separadas por quebra de linha — `product/create.blade.php` usa `explode(PHP_EOL, …)`) e as
  marcações "obrigatório" e "puxar do contato".
- **Aceite:** Dado nomes em vários grupos, um campo de produto do tipo lista com duas opções e duas marcações · Quando
  devolvo o corpo sem mexer · Então o objeto volta igual · E quando renomeio um campo, desmarco "obrigatório" e troco o tipo
  do campo de produto · Então só isso muda, e as opções e a outra marcação ficam.
- **Status: 🧪**

## UC-EMP-15 · Financeiro: ligar a régua de cobrança · `[dado]`
- **Por quê:** a régua D+1/D+5/D+15 (US-FIN-071, `ReguaCobrancaJob`) nasce desligada e só lia
  `common_settings['financeiro_regua_cobranca']`, sem tela para ligar. A chave mora no `common_settings`, que o salvar
  substitui inteiro — a aba tem de devolver as outras chaves como estão.
- **Aceite:** Dado a régua sem configuração · Então ela está desligada com 1, 5 e 15 · Quando ligo na aba Financeiro com
  "2, 7" e salvo · Então `ReguaCobrancaConfig::de()` (o que o job lê) diz ligada com 2 e 7, e as outras chaves de
  `common_settings` ficam · E na tela antiga a aba mostra "2, 7" e desmarcar desliga · E dia 0, acima de 365 ou repetido
  não vira etapa.
- **Aviso antes do vencimento (US-FIN-033, 2026-10-26):** "Dias antes do vencimento" nasce vazio (não avisa antes) ·
  Quando informo "3" e salvo · Então o job lê `dias_antes = [3]` · E a tela antiga mostra e regrava o campo, porque o
  salvar substitui o objeto da régua inteiro (sem o campo na Blade, salvar por ela apagaria o valor).
- **Status: 🧪**

## UC-EMP-16 · Vendas: ligar a pesquisa depois da entrega e escolher a nota baixa · `[dado]`
- **Por quê:** a pesquisa de satisfação 2 dias depois da entrega (US-SELL-071) só roda para a empresa que ligou
  `common_settings.sells_pesquisa_entrega` (lido por `PesquisaEntregaConfig`), e até aqui nenhuma tela gravava a chave.
- **Aceite:** Dado a pesquisa desligada com nota padrão 2 · Quando marco o interruptor na aba Vendas e escolho 3 · Então a
  rotina lê ligada com nota 3, e as outras chaves de `common_settings` ficam · E quando desmarco · Então lê desligada e a
  nota 3 fica · E a Blade da aba Vendas desenha os mesmos dois campos.
- **Teste:** `tests/Feature/Configuracoes/EmpresaContratoTest.php` — `UC-EMP-16`.
- **Status: 🧪**

## UC-EMP-17 · App das lojas: ligar e desligar os ajustes do app · `[dado]` `[T0]`
- **Por quê:** os ajustes `pos_settings.app_*` (P2–P9 do app das lojas) só tinham tela na Blade
  (`settings_app_lojas.blade.php`, #9442). Chave, rótulo, ajuda e padrão moram em `AjustesDaEmpresa::CHAVES`; a aba mostra
  as de `na_tela` e tipo `bool`. Há ajuste com padrão ligado, então desmarcar tem de gravar desligado (a Blade manda
  hidden `0`), e o salvar substitui `pos_settings` inteiro — as outras chaves, inclusive os segredos, ficam.
- **Aceite:** Dado nenhum ajuste do app gravado · Quando a tela carrega · Então `app_lojas` traz as chaves `bool` de
  `na_tela` com o rótulo do serviço e o padrão (biometria ligada, assinatura desligada) · Quando desmarco a biometria e marco
  a assinatura e salvo · Então `AjustesDaEmpresa::paraEmpresa` lê biometria desligada e assinatura ligada · E a chave
  secreta do Stripe e as outras chaves de `pos_settings` ficam · E o `pos_settings` do cliente 99 não muda.
- **Teste:** `tests/Feature/Configuracoes/EmpresaContratoTest.php` — `UC-EMP-17`.
- **Status: 🧪**

## Trilha do tempo
- 2026-10-21 · [CL] UC-EMP-16 (pesquisa depois da entrega na aba Vendas). `last_run` 2026-10-13 → 2026-10-21 por conferência, não por run novo: o `Index.tsx` só ganhou o bloco da pesquisa dentro da aba Vendas; UC-EMP-01..14 seguem batendo com a tela.
- 2026-10-26 · [C] UC-EMP-17 (aba App das lojas, 18 de 18 abas da Blade). UC-EMP-01..16 sem mudança.
