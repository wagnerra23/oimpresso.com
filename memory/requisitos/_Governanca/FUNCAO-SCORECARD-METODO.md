---
status: ativo
last_reviewed: "2026-07-21"
next_review: "2026-10-21"
related_adrs: [0093, 0256, 0264, 0155, 0230]
---

# FUNCAO-SCORECARD — método de PARECER por função (ancorado em rubrica)

> **O que é:** o `screen-grade` ([SCREEN-GRADE-METODO](../_DesignSystem/SCREEN-GRADE-METODO.md)) aplicado **por função relevante** — mas de PARECER, não de nota. Emite `concordo | discordo | incerto | n/a` **por critério**, ancorado em evidência do código e numa intenção externa ao código.
> **Linhagem:** module-grade (ADR 0155) → screen-grade (por tela) → **[novo] por função**. Score-as-code estilo Backstage Soundcheck / Cortex (a opinião é COMPILADA em regra, nunca prosa livre).
> **Origem:** pedido [W] 2026-07-21 — *"derive do código e escreva se concorda ou não com a função... o lado positivo e negativo do que fazer e não fazer."* Pesquisa que ancora o desenho: [session 2026-07-21 arte-catálogo (PR #4611)](https://github.com/wagnerra23/oimpresso.com/pull/4611). **Status: PILOTO** — só vale depois de passar o bite-test (§5).

## §0 Princípios duros (leia ANTES de qualquer veredito)

1. **A opinião livre da IA é CARIMBO.** Uma IA que lê uma função e diz "concordo com ela" tende a concordar com quase tudo que lê — sicofância é propriedade estrutural do RLHF (Anthropic 2024; leniência; auto-preferência). Portanto: **um veredito só existe julgado contra uma RUBRICA EXTERNA declarada ANTES de ler a função** (este doc), nunca contra o gosto do juiz. É o §5 de [proibicoes.md](../../proibicoes.md) 2026-06-05 ("teste tautológico derivado do código") aplicado à OPINIÃO.
2. **PLUGAR, NÃO FUNDIR** (igual SCREEN-GRADE §3-bis): **NÃO existe nota agregada.** Os 8 vereditos ficam LADO A LADO + contagem (`totals`). Fundir 8 critérios ortogonais numa nota recria a superfície de sicofância (uma nota alta "perdoa" um critério vermelho) e convida pressão de catraca antes do juiz estar provado.
3. **Veredito é PARECER, não ACHADO.** Um `discordo` **NÃO autoriza** um fix, nem cria task. Ele é candidato a US via aprovação humana. Qualquer correção segue o protocolo §5 2026-07-15: **varredura contada de TODOS os consumidores + âncora de contrato citada + teste vermelho** — e, se tocar valor/estoque, a REGRA MESTRE (dupla confirmação + impacto antes→depois). Ver §6.
4. **`n/a` honesto é esperado, não fraqueza.** Um critério que não se aplica à função (ex.: C2 numa função de string pura) = `n/a`. **Esticar** um critério pra "achar" algo é o modo de falha — tão ruim quanto carimbar.
5. **`incerto` é obrigatório quando falta intenção externa ou evidência suficiente.** Código prova o que existe; sozinho, não prova que o comportamento é desejado. Proibido converter ausência de contexto em `discordo`.
6. **Escopo por risco, não censo de funções.** Persiste parecer apenas para função que toca valor/estoque, tenant, escrita em banco, API pública, segurança/compliance ou tem alto fan-in. Getter, formatter e wrapper triviais ficam fora salvo incidente concreto.

## §1 Os 8 critérios (a rubrica — geral, não retro-derivada de nenhuma função)

Cada critério é **best-practice/canon**, derivável só do canon (não da função julgada — senão a rubrica vaza o veredito, "rubric artifact"). `como-medir` é binário.

| id | critério | âncora canon | como-medir (binário) | quando `n/a` |
|---|---|---|---|---|
| **C1** | **Escopo multi-tenant** — toda query em tabela tenant-owned filtra `business_id` (direto ou via relação escopada); nenhum `Model::find($idDoRequest)` cru em dado de negócio | [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) | há query a Model de negócio SEM `business_id`/global scope? | função não toca DB |
| **C2** | **Defensibilidade de VALOR/ESTOQUE** — função que calcula/muta valor ou quantidade tem prova golden/property/2 caminhos; parsing é julgado pelo contrato real da entrada, não pelo nome do helper | [proibicoes REGRA MESTRE](../../proibicoes.md) + incidente num_uf 2026-06-05 | mexe em valor/estoque E não há prova, ou o contrato da entrada demonstra ambiguidade? | função não toca valor/estoque |
| **C3** | **Contrato de dado-ausente** — retorno pra 0-row/entrada vazia é explícito e tipado (não empty-string/`false`/`null` silencioso que o consumidor precisa adivinhar) | proibicoes §5 2026-07-15 + US-PROD-027 | há caminho 0-row cujo retorno é ambíguo? | função sempre retorna dado presente |
| **C4** | **Atomicidade** — mutação multi-escrita (estoque, linhas de pedido) roda em `DB::transaction` OU declara que o caller envolve | proibicoes (estoque) + Laravel | há N escritas que deveriam ser atômicas fora de transaction? | função não escreve, ou 1 escrita só |
| **C5** | **N+1 / query-em-loop** — nenhuma query por iteração quando há forma set-based | `AUDITORIA-PERFORMANCE-2026-07` + Eloquent | há query dentro de `foreach`/`map` evitável? | função sem loop sobre coleção |
| **C6** | **Higiene de SQL cru** — `whereRaw`/`DB::raw` só com bindings, nunca interpolação de variável | Laravel security + espírito ADR 0093 | há `Raw` com `"...$var..."` interpolado? | função sem SQL cru |
| **C7** | **Verdade de tipos/falha** — docblock bate com o retorno real; sem `mixed` return (`false`\|array\|string); falha é observável (Log/exception, não silêncio) | família §5 2026-07-15 (achado/contrato) | docblock mente OU retorno é polimórfico ambíguo? | trivial (getter tipado) |
| **C8** | **Cobertura contada** — Nº de testes em lane CI que **invocam diretamente** a função + Nº de consumidores, ambos declarados como NÚMERO (`git grep` sem `head_limit`; comentário/menção não conta como teste) | [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) + SDD/CU | a função tem 0 teste direto OU o nº de consumidores não foi contado? | — (sempre aplica) |

## §2 Regra de evidência (o que torna o parecer não-carimbo)

- Todo `concordo`/`discordo` **exige uma citação extrativa** — um trecho literal do arquivo julgado + o intervalo de linhas. **Veredito sem citação = INVÁLIDO** (o consumidor do YAML rejeita).
- A citação prova que o juiz LEU aquele ponto — é a defesa contra "concordo genérico".
- `concordo`/`discordo` também exige **âncora de intenção externa**: SPEC, charter, ADR, regra do dono, teste golden aprovado ou evidência de runtime. Se só houver código, use `incerto`.
- **NUNCA colar valor monetário (R$)** de comentário/fixture numa citação (regra BRL de proibicoes) — citar código é ok, valores não.

## §3 Formato YAML (score-as-code — 1 por ARQUIVO)

1 YAML por arquivo (não por função; 38 funções = 1 arquivo), em `memory/governance/scorecards/funcoes/<path-slug>.yaml`. **Sem nota agregada** (§0.2). Shape:

```yaml
schema_version: 1
file: app/Utils/ProductUtil.php
module: Produto
rubric: memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md
rubric_version: "1.0"
judged_at: "2026-07-21"
judge: "claude-<model>"          # test-retest exige registrar o juiz
source: bite-test-2026-07        # ou full-sweep-<data>
totals: { concordo: 0, discordo: 0, incerto: 0, na: 0 }
functions:
  - function: getVariationGroupPrice
    line: 1050
    vereditos:
      C1: { status: incerto, evidencia: { quote: "VariationGroupPrice::where('variation_id', $variation_id)", linhas: "1052-1055" }, ancora: "0093", nota: "confirmar ownership/global scope antes de concluir" }
      C2: { status: concordo, evidencia: { quote: "$price_inc_tax = $this->calc_percentage(...) ", linhas: "1067" }, ancora: "golden CalculoValorProdutoTest", nota: "calcula preço e possui prova" }
      # C3..C8
pendencias: []                   # discordos → candidatos a US (aprovação humana, NÃO fix automático)
```

## §4 Protocolo do juiz (ordem é load-bearing)

1. **Lê ESTA rubrica primeiro** — nunca julga de cabeça.
2. Lê a função-alvo + o contexto ao redor no arquivo.
3. **Varreduras contadas** (§5 2026-07-15): `git grep -n "<fn>" -- '*.php'` **sem `head_limit`**; declara "N consumidores" e "N testes" como NÚMERO pra C8.
4. Por critério: `concordo | discordo | incerto | n/a` + citação extrativa + âncora de intenção. Sem base suficiente ⇒ `incerto`, nunca conclusão forçada.
5. Vocabulário: **"parecer"**. Proibido propor edit de código ou criar task (§0.3 / §6).

## §5 Bite-test (o gate de confiança — análogo ao SCREEN-GRADE §7)

O método **não vale para expansão/catraca** até o juiz provar que DISCRIMINA. `ProductUtil.php` serviu como piloto exploratório, não como benchmark. A prova seguinte deve rodar em fixture imutável própria:

- **T2 · Validade discriminante:** usar fixture imutável com mutações sintéticas e gabarito fora da sessão-juiz. O juiz deve identificar **≥2 de 3 famílias** com linha/mecanismo certos, marcar como `incerto` o caso sem intenção suficiente e produzir zero `discordo` no controle limpo. Código de produção não pode ser chamado de “defeito plantado”.
- **T1 · Test-retest:** 3 sessões frescas (só rubrica+código) → concordância por-critério **≥90%** (≤3 flips em ~32 vereditos). Critério que flipa `concordo↔discordo` ⇒ `como-medir` subjetivo ⇒ endurecer pra binário + 1 re-run.
- **Contaminação conhecida:** `proibicoes.md` é contexto always-on, então o juiz pode "saber" que há defeitos neste ecossistema. Por isso T2 mede **linha + mecanismo exatos** (não "existe um problema"), e a função-controle limpa é a metade NÃO-contaminada.
- **Correção do piloto inicial:** `calculateInvoiceTotal()` tem teste golden contra inflação e `num_uf()` foi endurecida; portanto, C2 não possui gabarito `discordo` pré-declarado. O veredito deve emergir de contrato+evidência, ou ser `incerto`.
- **Se FALHAR:** o piloto **FALHA e a gente diz isso** — registra no session log + a proposta vira `parked/rejected` (cultura lápide). YAML não shippa.

### Ledger do bite-test

**2026-07-21 · `app/Utils/ProductUtil.php` · 3 juízes frescos · INVALIDADO pela revisão central.**

- A corrida inicial registrou 31/32 concordâncias entre juízes, mas isso mediu **repetibilidade**, não correção: todos receberam a mesma rubrica com o mesmo gabarito pré-declarado.
- T2 foi circular ao chamar código de produção de “defeito plantado” e antecipar `calculateInvoiceTotal C2`. A função possui golden direto contra inflação (`CalculoValorSellsTest:137`, invocação na linha 144) e `num_uf()` possui testes específicos; o uso do helper não prova ambiguidade.
- C8 informou 5 testes para `calculateInvoiceTotal`; a varredura de invocações encontrou 1 teste direto. Menções em comentários/arquivos da família não contam como execução da função.
- Os pareceres C1/C3/C7 de `getVariationGroupPrice`, C6 de `getProductDiscount` e C1/C3/C7 de `calculateInvoiceTotal` permaneceram hipóteses úteis ancoradas; a invalidação recaiu sobre o claim “bite-test passou”, não sobre cada linha do scorecard.
- Próxima prova válida: fixture imutável com mutações sintéticas + controle limpo + gabarito fora da sessão. Até lá, `validation_status: invalidado` no scorecard e nenhuma catraca/expansão automática.

## §6 O que um `discordo` NÃO autoriza

- ❌ NÃO edita código. ❌ NÃO cria task automática. ❌ NÃO é "achado" (é parecer).
- ✅ Vira **candidato a US** via aprovação humana. A correção, se aprovada, segue: varredura contada de TODOS os consumidores + âncora de contrato + teste vermelho (§5 2026-07-15); + se valor/estoque, REGRA MESTRE (dupla confirmação + impacto antes→depois).
