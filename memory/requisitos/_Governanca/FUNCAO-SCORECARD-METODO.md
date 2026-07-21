---
status: ativo
last_reviewed: "2026-07-21"
next_review: "2026-10-21"
related_adrs: [0093, 0256, 0264, 0155, 0230]
---

# FUNCAO-SCORECARD — método de PARECER por função (ancorado em rubrica)

> **O que é:** o `screen-grade` ([SCREEN-GRADE-METODO](../_DesignSystem/SCREEN-GRADE-METODO.md)) aplicado **por função** — mas de PARECER, não de nota. Emite, por função de um arquivo, um veredito `concordo | discordo | n/a` **por critério**, cada um ancorado numa rubrica externa + citação extrativa do código.
> **Linhagem:** module-grade (ADR 0155) → screen-grade (por tela) → **[novo] por função**. Score-as-code estilo Backstage Soundcheck / Cortex (a opinião é COMPILADA em regra, nunca prosa livre).
> **Origem:** pedido [W] 2026-07-21 — *"derive do código e escreva se concorda ou não com a função... o lado positivo e negativo do que fazer e não fazer."* Pesquisa que ancora o desenho: [session 2026-07-21 arte-catálogo (PR #4611)](https://github.com/wagnerra23/oimpresso.com/pull/4611). **Status: PILOTO** — só vale depois de passar o bite-test (§5).

## §0 Princípios duros (leia ANTES de qualquer veredito)

1. **A opinião livre da IA é CARIMBO.** Uma IA que lê uma função e diz "concordo com ela" tende a concordar com quase tudo que lê — sicofância é propriedade estrutural do RLHF (Anthropic 2024; leniência; auto-preferência). Portanto: **um veredito só existe julgado contra uma RUBRICA EXTERNA declarada ANTES de ler a função** (este doc), nunca contra o gosto do juiz. É o §5 de [proibicoes.md](../../proibicoes.md) 2026-06-05 ("teste tautológico derivado do código") aplicado à OPINIÃO.
2. **PLUGAR, NÃO FUNDIR** (igual SCREEN-GRADE §3-bis): **NÃO existe nota agregada.** Os 8 vereditos ficam LADO A LADO + contagem (`totals`). Fundir 8 critérios ortogonais numa nota recria a superfície de sicofância (uma nota alta "perdoa" um critério vermelho) e convida pressão de catraca antes do juiz estar provado.
3. **Veredito é PARECER, não ACHADO.** Um `discordo` **NÃO autoriza** um fix, nem cria task. Ele é candidato a US via aprovação humana. Qualquer correção segue o protocolo §5 2026-07-15: **varredura contada de TODOS os consumidores + âncora de contrato citada + teste vermelho** — e, se tocar valor/estoque, a REGRA MESTRE (dupla confirmação + impacto antes→depois). Ver §6.
4. **`n/a` honesto é esperado, não fraqueza.** Um critério que não se aplica à função (ex.: C2 numa função de string pura) = `n/a`. **Esticar** um critério pra "achar" algo é o modo de falha — tão ruim quanto carimbar.

## §1 Os 8 critérios (a rubrica — geral, não retro-derivada de nenhuma função)

Cada critério é **best-practice/canon**, derivável só do canon (não da função julgada — senão a rubrica vaza o veredito, "rubric artifact"). `como-medir` é binário.

| id | critério | âncora canon | como-medir (binário) | quando `n/a` |
|---|---|---|---|---|
| **C1** | **Escopo multi-tenant** — toda query em tabela tenant-owned filtra `business_id` (direto ou via relação escopada); nenhum `Model::find($idDoRequest)` cru em dado de negócio | [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) | há query a Model de negócio SEM `business_id`/global scope? | função não toca DB |
| **C2** | **Defensibilidade de VALOR/ESTOQUE** — função que calcula/muta valor ou quantidade tem prova (golden/property/2 caminhos) e não faz parsing locale-ambíguo (`num_uf` sobre float de request) | [proibicoes REGRA MESTRE](../../proibicoes.md) + incidente num_uf 2026-06-05 | mexe em valor/estoque E não há prova OU há parse ambíguo? | função não toca valor/estoque |
| **C3** | **Contrato de dado-ausente** — retorno pra 0-row/entrada vazia é explícito e tipado (não empty-string/`false`/`null` silencioso que o consumidor precisa adivinhar) | proibicoes §5 2026-07-15 + US-PROD-027 | há caminho 0-row cujo retorno é ambíguo? | função sempre retorna dado presente |
| **C4** | **Atomicidade** — mutação multi-escrita (estoque, linhas de pedido) roda em `DB::transaction` OU declara que o caller envolve | proibicoes (estoque) + Laravel | há N escritas que deveriam ser atômicas fora de transaction? | função não escreve, ou 1 escrita só |
| **C5** | **N+1 / query-em-loop** — nenhuma query por iteração quando há forma set-based | `AUDITORIA-PERFORMANCE-2026-07` + Eloquent | há query dentro de `foreach`/`map` evitável? | função sem loop sobre coleção |
| **C6** | **Higiene de SQL cru** — `whereRaw`/`DB::raw` só com bindings, nunca interpolação de variável | Laravel security + espírito ADR 0093 | há `Raw` com `"...$var..."` interpolado? | função sem SQL cru |
| **C7** | **Verdade de tipos/falha** — docblock bate com o retorno real; sem `mixed` return (`false`\|array\|string); falha é observável (Log/exception, não silêncio) | família §5 2026-07-15 (achado/contrato) | docblock mente OU retorno é polimórfico ambíguo? | trivial (getter tipado) |
| **C8** | **Cobertura contada** — Nº de testes em lane CI que citam a função + Nº de consumidores, ambos declarados como NÚMERO (`git grep` sem `head_limit`) | [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) + SDD/CU | a função tem 0 teste OU o nº de consumidores não foi contado? | — (sempre aplica) |

## §2 Regra de evidência (o que torna o parecer não-carimbo)

- Todo `concordo`/`discordo` **exige uma citação extrativa** — um trecho literal do arquivo julgado + o intervalo de linhas. **Veredito sem citação = INVÁLIDO** (o consumidor do YAML rejeita).
- A citação prova que o juiz LEU aquele ponto — é a defesa contra "concordo genérico".
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
totals: { concordo: 0, discordo: 0, na: 0 }
functions:
  - function: getVariationGroupPrice
    line: 1050
    vereditos:
      C1: { status: discordo, evidencia: { quote: "VariationGroupPrice::where('variation_id', $variation_id)", linhas: "1052-1055" }, ancora: "0093", nota: "sem filtro business_id" }
      C2: { status: n/a, motivo: "leitura de preço, não muta" }
      # C3..C8
pendencias: []                   # discordos → candidatos a US (aprovação humana, NÃO fix automático)
```

## §4 Protocolo do juiz (ordem é load-bearing)

1. **Lê ESTA rubrica primeiro** — nunca julga de cabeça.
2. Lê a função-alvo + o contexto ao redor no arquivo.
3. **Varreduras contadas** (§5 2026-07-15): `git grep -n "<fn>" -- '*.php'` **sem `head_limit`**; declara "N consumidores" e "N testes" como NÚMERO pra C8.
4. Por critério: `concordo | discordo | n/a` + citação extrativa obrigatória + linhas. Sem citação ⇒ inválido.
5. Vocabulário: **"parecer"**. Proibido propor edit de código ou criar task (§0.3 / §6).

## §5 Bite-test (o gate de confiança — análogo ao SCREEN-GRADE §7)

O método **não vale** até o juiz provar que DISCRIMINA. Antes de shippar qualquer YAML, rodar em `app/Utils/ProductUtil.php`:

- **T2 · Validade discriminante:** com o gabarito FORA da sessão-juiz, o juiz deve achar **≥2 das 3 famílias de defeito plantadas** com a linha certa (`getVariationGroupPrice` C1/C3/C7 · `getProductDiscount` C6 · `calculateInvoiceTotal` C2) **E zero `discordo` infundado** numa função-controle limpa (over-flag é falha simétrica ao carimbo).
- **T1 · Test-retest:** 3 sessões frescas (só rubrica+código) → concordância por-critério **≥90%** (≤3 flips em ~32 vereditos). Critério que flipa `concordo↔discordo` ⇒ `como-medir` subjetivo ⇒ endurecer pra binário + 1 re-run.
- **Contaminação conhecida:** `proibicoes.md` é contexto always-on, então o juiz pode "saber" que há defeitos neste ecossistema. Por isso T2 mede **linha + mecanismo exatos** (não "existe um problema"), e a função-controle limpa é a metade NÃO-contaminada.
- **Se FALHAR:** o piloto **FALHA e a gente diz isso** — registra no session log + a proposta vira `parked/rejected` (cultura lápide). YAML não shippa.

### Ledger do bite-test
_(preenchido após a corrida — mirror de `screen-grades-pilot.md`)_

## §6 O que um `discordo` NÃO autoriza

- ❌ NÃO edita código. ❌ NÃO cria task automática. ❌ NÃO é "achado" (é parecer).
- ✅ Vira **candidato a US** via aprovação humana. A correção, se aprovada, segue: varredura contada de TODOS os consumidores + âncora de contrato + teste vermelho (§5 2026-07-15); + se valor/estoque, REGRA MESTRE (dupla confirmação + impacto antes→depois).
