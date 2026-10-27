---
id: requisitos-vestuario-telas-curva-de-compra-casos
casos: Sugestão de compra por tamanho pela curva de venda · GET /vestuario/curva-compra (a tela da compra consome)
irmaos: ../SPEC.md (US-VEST-031)
tecnica: Caso de uso = narrativa de quem compra + critério de aceite verificável (Dado/Quando/Então)
por_que: a sugestão decide quanto comprar de cada tamanho; a conta e o isolamento por empresa não podem mudar sem querer.
owner: wagner
last_run: "2026-10-26"
last_run_ci: "0 UC executado na lane — nasce neste PR; CT 100 (cópia isolada do branch) no corpo do PR"
related_us: [US-VEST-031]
---

# Casos de Uso & Aceite — Sugestão de compra pela curva de venda

> **2ª casa do contrato** (`memory/requisitos/<Mod>/_telas/`): nesta etapa o fluxo é o endpoint e o
> serviço; o botão na tela da compra vem num PR seguinte, que trará o caso de tela.
> Deriva da US-VEST-031 (decisão [W] 2026-10-26), nunca do código.
>
> ⚠️ Afeta a ROTA LIVRE (biz=4), que usa o Vestuário em produção. Os testes rodam no tenant 98; nada
> é testado na biz=4.

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-VCUR-01 | o total se divide pela proporção vendida do produto em 90 dias, somando o total | must | US-VEST-031 | `CurvaDeCompraContratoTest` |
| UC-VCUR-02 | produto novo usa a curva da categoria, pelo nome da variação | must | US-VEST-031 | `CurvaDeCompraContratoTest` |
| UC-VCUR-03 | sem venda não sugere; outra empresa não entra; sugerir não grava nada | must `[T0]` | US-VEST-031 | `CurvaDeCompraContratoTest` |

## UC-VCUR-01 · O total se divide pela proporção vendida do produto em 90 dias
- **Persona:** quem compra para a loja, decidindo a grade da próxima reposição.
- **Aceite:** Dado um produto com grade e vendas finalizadas nos últimos 90 dias · Quando pede a
  sugestão para N peças · Então cada variação recebe N × a proporção vendida dela, arredondando pelo
  maior resto, e a soma bate com N. Devolução desconta; venda fora dos 90 dias e rascunho não contam.
- **Teste:** `Modules/Vestuario/Tests/Feature/CurvaDeCompraContratoTest.php`
- **Status: 🧪**

## UC-VCUR-02 · Produto novo usa a curva da categoria, pelo nome da variação
- **Persona:** quem compra uma coleção nova, que ainda não vendeu.
- **Aceite:** Dado um produto sem venda no período · Quando pede a sugestão · Então vale a proporção
  vendida na categoria, casando as variações pelo nome; tamanho que a categoria não vendeu fica com 0.
- **Teste:** `Modules/Vestuario/Tests/Feature/CurvaDeCompraContratoTest.php`
- **Status: 🧪**

## UC-VCUR-03 · Sem venda não sugere; outra empresa não entra; sugerir não grava nada
- **Persona:** dono da loja, que não pode ver venda de outra empresa nem ter o estoque mexido.
- **Aceite:** Dado um produto sem venda nele nem na categoria · Então a resposta diz o motivo e não
  traz linhas. Venda e produto de outra empresa não entram na conta (produto alheio = não encontrado).
  Pedir a sugestão não cria venda, compra nem movimento de estoque.
- **Teste:** `Modules/Vestuario/Tests/Feature/CurvaDeCompraContratoTest.php`
- **Status: 🧪**
