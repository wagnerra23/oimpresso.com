# ADR ARQ-0004 (NfeBrasil) · Schema flexível pra Reforma Tributária CBS/IBS

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0002, R-NFE-011

## Contexto

A Reforma Tributária (PEC 45/2019, EC 132/2023) cria 2 novos impostos federais que substituem PIS/COFINS/ICMS/ISS:

- **CBS** (Contribuição sobre Bens e Serviços) — federal
- **IBS** (Imposto sobre Bens e Serviços) — estadual + municipal

Implementação **gradual 2026-2033**:
- 2026: alíquotas teste (0,9% CBS + 0,1% IBS) — não cobra; só obriga declarar
- 2027-2028: PIS/COFINS extintos; CBS já obrigatório
- 2029-2032: alíquotas IBS sobem; ICMS/ISS reduzem
- 2033: regime atual extinto

Tenant que emite hoje precisa estar **pronto pra 2026 (sequer cobra mas declara) e 2027 (cobra)**. Mas legislação ainda muda — não dá pra implementar tudo agora.

## Decisão

**Schema flexível com campos CBS/IBS NULL hoje, preenchidos quando regulamentação consolidar.**

- Tabela `nfe_fiscal_rules` tem colunas `cbs_aliquota`, `cbs_cst`, `ibs_aliquota`, `ibs_cst` — todas NULL
- Tabela `nfe_emissoes` tem `valor_cbs`, `valor_ibs` — NULL
- `MotorTributarioService::calcular()` skip cálculo CBS/IBS se rule.cbs_* é NULL
- `NfeBuilderService::build()` skip blocos `<CBS>` / `<IBS>` no XML se valor é NULL
- Activation por feature flag `nfe.cbs_ibs_enabled` por business (rollout gradual)
- Datasets cBS/cIBS (CSTs) populados no seeder zerados; atualizar quando Receita publicar

## Consequências

**Positivas:**
- Schema não muda quando legislação consolidar (zero migration risk em 2027)
- Tenant pode ativar pra teste antecipado em 2026 (sandbox SEFAZ-AN)
- Forward compat: outros impostos novos seguem mesmo padrão (campos nullable + flag)
- Auditoria: emissão antiga (NULL) e nova (preenchida) coexistem no mesmo schema

**Negativas:**
- Ligeiramente mais colunas na tabela `nfe_fiscal_rules` (~8 nullables extras)
- Lógica do motor tributário tem branches (com vs sem CBS/IBS) — mais código
- Risco de esquecer de preencher quando legislação obriga — mitigar com alerta automático ("CBS obrigatório a partir de YYYY-MM, mas seu business não tem regras")

## Estrutura prevista

```yaml
business_config:
  reforma_tributaria_modo: legacy | hybrid_2026 | full_2027
fiscal_rules:
  - ncm: 22021000  # bebida
    uf_origem: SP
    csosn: 102     # tradicional ainda preenchido
    aliquota_icms: 18.00
    cbs_cst: null  # vazio até regulamentação
    cbs_aliquota: null
    ibs_cst: null
    ibs_aliquota: null
```

Quando 2026 chega:
1. Receita publica CSTs CBS/IBS oficiais
2. Seeder atualizado distribui pra todos os businesses (opt-in via flag)
3. Tenant ativa flag → próxima emissão calcula novos impostos

## Pattern obrigatório

```php
class MotorTributarioService {
    public function calcular(Item $item, Business $b): TributoCalculado {
        $rule = $this->ruleFor($item, $b);
        $tributo = new TributoCalculado();

        // Tradicional sempre
        $tributo->icms = $this->calcIcms($rule, $item);
        $tributo->pis = $this->calcPis($rule, $item);
        $tributo->cofins = $this->calcCofins($rule, $item);

        // CBS/IBS condicional
        if ($b->reforma_tributaria_modo !== 'legacy' && $rule->cbs_aliquota !== null) {
            $tributo->cbs = $this->calcCbs($rule, $item);
        }
        if ($b->reforma_tributaria_modo === 'full_2027' && $rule->ibs_aliquota !== null) {
            $tributo->ibs = $this->calcIbs($rule, $item);
        }

        return $tributo;
    }
}
```

## Tests obrigatórios (R-NFE-011)

- `MotorTributarioCbsIbsNullTest` — rule sem CBS/IBS → emissão sem blocos
- `MotorTributarioCbsAtivoTest` — flag + rule preenchida → bloco no XML
- `BusinessFlagDefaultTest` — todo business novo nasce `legacy` (não força adoção)

## Alternativas consideradas

- **Aguardar 2026 e migrar** — rejeitado: tenant que assina hoje não quer susto na virada
- **Implementar tudo já** — rejeitado: regulamentação ainda muda; risco de retrabalho
- **Tabela separada `cbs_ibs_rules`** — rejeitado: complica join + sincronia
- **JSON column flexível** (`metadata.cbs.cst`) — rejeitado: query/filtro vira penoso; preferimos colunas nomeadas

## Referências

- PEC 45/2019, EC 132/2023
- Lei Complementar 214/2025
- `_Ideias/NfeBrasil/evidencias/conversa-claude-2026-04-mobile.md`
- R-NFE-011 (SPEC)
- Site CONFAZ — atualização periódica
