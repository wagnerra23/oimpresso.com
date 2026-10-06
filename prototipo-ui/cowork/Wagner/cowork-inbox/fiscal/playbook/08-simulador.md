---
sessao: "08"
titulo: Simulador read-only em /nfe-brasil/tributacao
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b (2026-10-06)
prefixo: Modules/NfeBrasil/Http/Controllers/TributacaoController.php (método simular) · Routes · Pages/NfeBrasil/Tributacao/Index.tsx · Index.charter.md · Index.casos.md
nao_toca: Services/ (só chama, não muda)
depende: 07 · 12 (alvo)
decisao: _DECISOES-W-2026-10-06.md
implementa: UC-NFTR-08 · UC-NFTR-08b
us: US-NFE-010, critério aberto "[ ] Preview de cálculo com produto exemplo". A SPEC pede o que o Non-Goal do Index.charter proíbe; o ConfigDefault.charter tem o mesmo Non-Goal ("Calcular tributação de venda exemplo"). D-SIM resolve só para a Index.
---
# 08 · Simulador read-only em /nfe-brasil/tributacao

Endpoint read-only que monta um `ProdutoFiscalContext` e chama `calcular`, devolvendo `nivel_usado`, `regra_id` e os valores. UI: card "Simular nota" (produto · qtd · UF · destinatário · operação) com o rastro N1–N4 e o aviso literal *"Prévia: usa o mesmo motor da emissão… não é garantia"*. O charter troca o Non-Goal pela exceção D-SIM.

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| UC-NFTR-08 | O simulador devolve o mesmo cálculo da emissão e não grava nada | `must` `[fiscal]` | `Index.casos.md` (próximo UC-NFTR livre — conferir) |
| UC-NFTR-08b | Simular produto de outra empresa é 404 | `must` `[T0]` | `Index.casos.md` |

### UC-NFTR-08 · O simulador devolve o mesmo cálculo da emissão e não grava nada · `must` `[fiscal]`
- **Destino:** `Index.casos.md` (próximo UC-NFTR livre — conferir)
- **Persona:** Eliana conferindo um imposto antes de vender
- **Aceite:** Dado um produto, UF e destinatário · Quando simulo · Então o resultado (CFOP, nível N1–N4, `regra_id`, valores) é **idêntico** ao `TributoCalculado` que a emissão monta para o mesmo contexto, e a contagem de linhas de todas as tabelas fiscais não muda. Controle positivo: mudar a UF muda o nível usado.
- **Teste:** `TributacaoSimuladorTest` — `UC-NFTR-08 · simulador = emissão e zero escrita`
- **Contrato:** D-SIM · US-NFE-010 "Preview de cálculo"
- **Regressão que defende:** um simulador com cálculo próprio que diverge da nota.

### UC-NFTR-08b · Simular produto de outra empresa é 404 · `must` `[T0]`
- **Destino:** `Index.casos.md`
- **Persona:** qualquer tenant
- **Aceite:** Dado `product_id` de outro business · Quando simulo · Então 404 e nenhum dado do produto volta. Controle positivo: produto próprio simula. Sem `nfe.tributacao.manage` (ou permissão de consulta definida no turno) → 403.
- **Teste:** `TributacaoSimuladorTest` — `UC-NFTR-08b · simulador isolado por tenant`
- **Contrato:** ADR 0093
- **Regressão que defende:** endpoint read-only vazando cadastro alheio.

## Bateria de 27 notas (dataset dos testes)
O protótipo tem a aba **"Bateria de notas"** (`TR_CENARIOS` em `fiscal-tributacao.jsx`): 20 cenários com o esperado **calculado à parte**, rodando o mesmo cálculo do simulador. Hoje passam 27 de 27 (C21–C27: retenções, benefício, filial e Fator R). A mesma bateria vira o **dataset** de `TributacaoSimuladorTest` e de `NfeEmissaoPorItemTest` (17): cada cenário roda no simulador **e** na montagem da emissão, e os dois têm de dar o mesmo resultado (UC-NFTR-08).
Cobre: NFC-e balcão · NF-e interestadual (N2) · regra geral (N4) · produção própria · produto sem NCM (bloqueio) · ST já retida no Simples · ST no regime normal · DIFAL + FCP · exceção do produto (N1) · CBS/IBS ano-teste · devolução parcial e devolução acima do vendido · NFS-e com ISS retido · exportação · remessa · DIFAL no Simples (aviso, não cálculo) · importação com câmbio da DI · consumidor final sem ST · interestadual 7%.
**Os valores são de exemplo** (alíquotas do protótipo). No teste real, os números esperados saem das regras semeadas na fixture, e o `biz` de teste segue a ADR 0358 (nunca biz=4).

## Prova
Teste: o simulador e a emissão devolvem o mesmo `TributoCalculado` para o mesmo contexto · zero escrita no banco · UC no `Index.casos.md`.

Antes de editar: confirmar no turno os símbolos citados (C12). Terminou: `_saida-08.md` com o sha e a saída dos testes. Pare.
