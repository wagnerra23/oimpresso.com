---
id: requisitos-compras-telas-cotacao-casos
casos: Avaliação do fornecedor na cotação de compra · serviço CotacaoDeCompra::avaliacaoDosFornecedores (a tela da cotação consome)
irmaos: ../SPEC.md (US-COM-031, US-COM-033)
tecnica: Caso de uso = narrativa de quem compra + critério de aceite verificável (Dado/Quando/Então)
por_que: o comprador escolhe o fornecedor pelo preço; a avaliação põe ao lado o que as entregas já mostraram.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "0 UC executado na lane — nasce neste PR"
related_us: [US-COM-033]
---

# Casos de Uso & Aceite — Avaliação do fornecedor na cotação

> **2ª casa do contrato** (`memory/requisitos/<Mod>/_telas/`): a cotação ainda não tem tela; nesta
> etapa o fluxo é o serviço. A tela da cotação trará o caso de tela.
> Deriva da US-COM-033 (decisão coordenação 2026-10-26), nunca do código.

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-COT-01 | por fornecedor: entregas no prazo, completude e reprovação, calculadas das entregas | must | US-COM-033 | `AvaliacaoDoFornecedorTest` |
| UC-COT-02 | outra empresa não enxerga as entregas nem a cotação desta | must `[T0]` | US-COM-033 · ADR 0093 | `AvaliacaoDoFornecedorTest` |

## UC-COT-01 · Prazo, completude e reprovação por fornecedor

- **Dado** um fornecedor com duas entregas de um pedido previsto para hoje−10 (uma em hoje−12, outra
  em hoje−8), um segundo pedido vencido que recebeu 5 de 10, e uma inspeção com 1 reprovada de 4,
- **Quando** o comprador abre a cotação em que ele foi convidado,
- **Então** vê: entregas 2 · no prazo 50,0% · completas 75,0% · reprovação 25,0%. Fornecedor sem
  dado mostra os três eixos vazios, nunca 0 nem 100. Não há nota única: os três números ficam lado
  a lado, porque qualquer peso entre eles seria inventado.
- **Teste:** [`AvaliacaoDoFornecedorTest`](../../../../Modules/Compras/Tests/Feature/AvaliacaoDoFornecedorTest.php).
- **Status: 🧪 sem veredito**

## UC-COT-02 · Tier 0

- **Dado** o mesmo fornecedor com entregas na empresa A,
- **Quando** a empresa B pede a avaliação dele, ou a avaliação da cotação da empresa A,
- **Então** a avaliação sai vazia, e a cotação alheia é recusada.
- **Teste:** [`AvaliacaoDoFornecedorTest`](../../../../Modules/Compras/Tests/Feature/AvaliacaoDoFornecedorTest.php).
- **Status: 🧪 sem veredito**
