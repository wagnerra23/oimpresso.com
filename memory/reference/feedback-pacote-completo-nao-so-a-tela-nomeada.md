---
id: reference-feedback-pacote-completo
tipo: feedback
origem: "Maiara [M], 2026-09-02, no fechamento do PR #6546 (tela de Fabricação)"
aplica_a: "todo pedido de 'coloca em produção' que venha acompanhado de handoff, protótipo ou ZIP"
---

# Pedido de "colocar em produção" = PACOTE COMPLETO, não só a tela nomeada

> **Palavras da Maiara (2026-09-02):** *"quando eu (Maiara) te enviar algo e pedir para colocar
> em produção, tem que colocar o pacote completo, incluindo demais telas/abas associadas"*.

## O que aconteceu (o custo que gerou a regra)

No [PR #6546](https://github.com/wagnerra23/oimpresso.com/pull/6546) ela mandou o handoff
**"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"** e pediu: *"coloque a tela de Fabricação em produção no
endereço https://oimpresso.com/manufacturing/recipe"*.

O handoff cobre uma **família de 7 telas** — o §15.2 dele lista cada uma com a rota proposta.
Entreguei **uma**: a consulta de receitas. Li o pedido ao pé da letra ("uma tela, um endereço") e
tratei o resto como escopo que eu inventaria se fizesse.

Resultado: as abas **Insumos · Ordens de produção · Relatório · Configurações** ficaram apontando
pras telas Blade antigas, e ela precisou perguntar *"por que não estão em produção também?"*.
O que eu chamei de disciplina de escopo, ela leu como entrega pela metade — e ela estava certa:
quem pede uma tela de um módulo espera o módulo utilizável, não um pedaço.

## Como aplicar

Ao receber handoff/protótipo/ZIP com "coloca em produção":

1. **Inventarie a família ANTES de escrever a primeira linha.** O documento normativo costuma ter
   a seção de diff contra o repo (no de Fabricação é o **§15.2**) listando cada tela e a rota.
   **Esse é o escopo** — não a tela que ela nomeou no endereço.
2. **Apresente as ondas com o custo, da menor pra maior.** A ordem é decisão dela; a estimativa é
   minha. Textual: *"começaremos com as que gastam menos"*.
3. **O que o próprio handoff PROÍBE entregar fica de fora com a razão citada** — e vira onda
   própria, nunca silêncio. Ex.: a aba Insumos, §18.3: *"sem backend, a aba não sai"*.
4. **Uma onda = um PR.** O "pacote completo" é o **compromisso de escopo**, não licença pra um PR
   gigante — `commit-discipline` (1 PR = 1 intent, ≤300 linhas) continua valendo.

## O que esta regra NÃO afrouxa

**Não confunde com o corte que PROTEGE.** Existe um corte de escopo legítimo e ele continua
valendo: não oferecer caminho que toque tela viva da Larissa (ROTA LIVRE, `biz=4`, 99% do volume)
— ali o certo é descartar sozinho a rota arriscada e apresentar só a segura. A diferença é de
quem o corte protege: aquele protege **cliente em produção**; este aqui protegia **só a minha
estimativa de esforço**, e não devia existir.

Também não afrouxa a **REGRA MESTRE de valor/estoque** nem os Non-Goals que o handoff declara —
"pacote completo" é entregar tudo que o documento **autoriza**, e o que ele proíbe continua
proibido (ver o §18 do handoff de Fabricação: markup do preço em massa não foi decidido).
