---
id: requisitos-vestuario-telas-remarcacao-casos
casos: Remarcação por coleção · serviço Remarcacao (a tela /vestuario/remarcacao consome)
irmaos: ../SPEC.md (US-VEST-032)
tecnica: Caso de uso = narrativa de quem remarca + critério de aceite verificável (Dado/Quando/Então)
por_que: a remarcação muda o preço de muitas peças de uma vez; a conta, a confirmação, a reversão e o isolamento por empresa não podem mudar sem querer.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "0 UC executado na lane — nasce neste PR"
related_us: [US-VEST-032]
---

# Casos de Uso & Aceite — Remarcação por coleção

> **2ª casa do contrato** (`memory/requisitos/<Mod>/_telas/`): nesta etapa o fluxo é o serviço; a
> tela vem num PR seguinte, que trará o caso de tela. Deriva da US-VEST-032 (decisão [W]
> 2026-10-27), nunca do código.
>
> ⚠️ Afeta a ROTA LIVRE (biz=4), que usa o Vestuário em produção. Os testes rodam no tenant 98; nada
> é testado na biz=4. Os valores esperados são contas feitas à mão (2º caminho da regra mestre de valor).

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-VREM-01 | a prévia mostra antes → depois por variação e não grava | must | US-VEST-032 | `RemarcacaoContratoTest` |
| UC-VREM-02 | gravar exige a prévia em dia e guarda o antes; sem imposto e margem derivados | must | US-VEST-032 | `RemarcacaoContratoTest` |
| UC-VREM-03 | reverter devolve o antes e não pisa em preço mexido depois | must | US-VEST-032 | `RemarcacaoContratoTest` |
| UC-VREM-04 | outra empresa não entra nem reverte; sem filtro não remarca | must `[T0]` | US-VEST-032 | `RemarcacaoContratoTest` |

## UC-VREM-01 · A prévia mostra antes → depois e não grava
- **Persona:** quem remarca a loja na troca de estação.
- **Aceite:** Dado um filtro (categoria, subcategoria, marca ou trecho do nome) · Quando pede a prévia
  com um percentual (ex.: −30%) ou um preço fixo · Então cada variação aparece com o preço de etiqueta
  de antes e o novo, arredondado a 2 casas; nenhum preço muda e nenhum lote é criado. Variação cujo
  preço não mudaria, ou ficaria zero, fica fora.
- **Teste:** `Modules/Vestuario/Tests/Feature/RemarcacaoContratoTest.php`
- **Regressão que defende:** gravar preço na prévia, ou mostrar preço que não será o gravado.
- **Status: ⬜** (não verificado — nasce neste PR; roda na lane `verticais-pest`)

## UC-VREM-02 · Gravar exige a prévia em dia e guarda o antes
- **Persona:** quem confirma a remarcação depois de olhar a prévia.
- **Aceite:** Dado uma prévia · Quando confirma · Então, se algum preço mudou desde a prévia, nada é
  gravado e pede-se nova prévia; senão, cada variação recebe o preço novo, o preço sem imposto e a
  margem são recalculados pela taxa do produto, e o lote guarda o antes de cada uma.
- **Teste:** `Modules/Vestuario/Tests/Feature/RemarcacaoContratoTest.php`
- **Regressão que defende:** gravar sobre prévia velha (preço mexido por outra pessoa) ou perder o preço de antes.
- **Status: ⬜** (não verificado — nasce neste PR; roda na lane `verticais-pest`)

## UC-VREM-03 · Reverter devolve o antes sem pisar em preço mexido depois
- **Persona:** quem desfaz a liquidação no fim da campanha.
- **Aceite:** Dado um lote aplicado · Quando reverte · Então a variação que ainda tem o preço da
  remarcação volta ao de antes (etiqueta, sem imposto e margem); a que foi mexida depois fica como
  está e é contada como conflito; reverter de novo é recusado.
- **Teste:** `Modules/Vestuario/Tests/Feature/RemarcacaoContratoTest.php`
- **Regressão que defende:** reversão que apaga um preço ajustado depois da remarcação.
- **Status: ⬜** (não verificado — nasce neste PR; roda na lane `verticais-pest`)

## UC-VREM-04 · Outra empresa não entra; sem filtro não remarca `[T0]`
- **Aceite:** Dado produtos de outra empresa com o mesmo nome · Quando remarca · Então eles não entram
  na prévia nem mudam de preço, e o lote não pode ser revertido por outra empresa. Sem nenhum filtro a
  prévia é recusada, para não remarcar a loja inteira por engano.
- **Teste:** `Modules/Vestuario/Tests/Feature/RemarcacaoContratoTest.php`
- **Regressão que defende:** remarcar produto de outra empresa, ou a loja inteira sem filtro.
- **Status: ⬜** (não verificado — nasce neste PR; roda na lane `verticais-pest`)
