---
id: resources-js-pages-vestuario-remarcacao-index-casos
casos: Remarcação por coleção · /vestuario/remarcacao
irmaos: Index.charter.md (lei) · Index.tsx (código) · RUNBOOK-remarcacao.md (MWART F1) · memory/requisitos/Vestuario/_telas/remarcacao.casos.md (serviço)
tecnica: Caso de uso = narrativa de quem remarca + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela muda preço em massa; ela só pode existir para quem ligou e tem permissão, e as etiquetas e a reversão não podem vazar entre empresas.
owner: wagner
related_us: [US-VEST-032]
last_run: "2026-10-27"
last_run_ci: "0 UC executado na lane — nasce neste PR"
---

# Casos de Uso & Aceite — Remarcação por coleção (`/vestuario/remarcacao`)

> Deriva da US-VEST-032 (decisão [W] 2026-10-27), nunca do `Index.tsx`. A conta, a prévia e a
> reversão estão em [`_telas/remarcacao.casos.md`](../../../../../memory/requisitos/Vestuario/_telas/remarcacao.casos.md)
> (UC-VREM-01..04); aqui ficam os casos da tela e dos endpoints.
> Testes no tenant 98 × 99. Nunca biz=4 (ROTA LIVRE).

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-VREM-05 | desligada por padrão: sem o setting a tela e os endpoints dão 404 | must | US-VEST-032 | `RemarcacaoTelaContratoTest` |
| UC-VREM-06 | ligada, a tela abre com categorias e marcas só da empresa; sem `product.update`, 403 | must `[T0]` | US-VEST-032 | `RemarcacaoTelaContratoTest` |
| UC-VREM-07 | prévia → gravar → etiquetas do lote; etiquetas de lote alheio dão 404 | must `[T0]` | US-VEST-032 | `RemarcacaoTelaContratoTest` |

## UC-VREM-05 · Desligada por padrão
- **Persona:** empresa que ainda não ligou a remarcação.
- **Aceite:** Dado uma empresa sem `remarcacao.enabled` · Quando abre `/vestuario/remarcacao` ou
  chama a prévia · Então recebe 404 e nada é calculado.
- **Teste:** `Modules/Vestuario/Tests/Feature/RemarcacaoTelaContratoTest.php`
- **Regressão que defende:** tela ou endpoint de remarcação acessível com o setting desligado.
- **Status: ⬜** (não verificado — nasce neste PR; roda na lane `verticais-pest`)

## UC-VREM-06 · Ligada, só com permissão, só dados da empresa `[T0]`
- **Persona:** quem cuida dos preços da loja e tem permissão de editar produto.
- **Aceite:** Dado o setting ligado · Quando quem tem `product.update` abre a tela · Então recebe a
  página com categorias e marcas da própria empresa (nenhuma de outra). Sem a permissão, 403.
- **Teste:** `Modules/Vestuario/Tests/Feature/RemarcacaoTelaContratoTest.php`
- **Regressão que defende:** tela aberta sem permissão de editar produto, ou listando categoria/marca de outra empresa.
- **Status: ⬜** (não verificado — nasce neste PR; roda na lane `verticais-pest`)

## UC-VREM-07 · Do filtro às etiquetas `[T0]`
- **Persona:** quem remarca e precisa reimprimir as etiquetas das peças remarcadas.
- **Aceite:** Dado o setting ligado · Quando pede a prévia, grava com a assinatura dela e pede as
  etiquetas do lote · Então recebe os itens com o preço novo no formato de `lote/pdf`. Pedir as
  etiquetas (ou reverter) um lote de outra empresa dá 404/422 e não muda preço.
- **Teste:** `Modules/Vestuario/Tests/Feature/RemarcacaoTelaContratoTest.php`
- **Regressão que defende:** etiqueta ou reversão de lote de outra empresa, ou etiqueta saindo com o preço antigo.
- **Status: ⬜** (não verificado — nasce neste PR; roda na lane `verticais-pest`)
