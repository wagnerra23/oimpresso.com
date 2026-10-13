---
id: resources-js-pages-configuracoes-empresa-index-casos
casos: Configuração da empresa · /business/settings
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o salvar é substituição total e mexe em margem e pontos; a tela nova não pode mudar valor gravado nem expor segredo.
owner: wagner
last_run: "2026-10-13"
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
  Quando faço `GET /business/settings` como o browser faz · Então renderiza `Configuracoes/Empresa/Index` · E o corpo não
  tem `email_settings` nem `sms_settings` · E a senha não aparece na resposta.
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
