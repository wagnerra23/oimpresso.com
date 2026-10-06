---
sessao: "25"
titulo: Benefício fiscal na regra (redução de base + cBenef)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 24561f0da83b (2026-10-06)
prefixo: Modules/NfeBrasil/Database/Migrations/<reducao_base, c_benef em nfe_fiscal_rules> · MotorTributarioService.php · NfeService (tag cBenef) · Tests
nao_toca: resources/js/ (tela é outra thread, depois do alvo)
depende: 07 (versões) · 17 (itens reais). 🔴 sozinho no PR
decisao: _DECISOES-W-2026-10-06.md (D-SUPORTE · D-MOTOR quando tocar o motor)
implementa: R-NFE-030
us: UC-TRB-26
---
# 25 · Benefício fiscal na regra (redução de base + cBenef)

A regra ganha `reducao_base` (decimal) e `c_benef` (código da tabela da UF, informado pelo contador). O motor reduz a base do ICMS; o XML leva o cBenef no item. Sem cBenef em UF que exige → pendência na Saúde fiscal, não emissão errada.

## Casos de uso que esta thread implementa
**Números provisórios:** confirmar o próximo livre no turno. Cenário correspondente na bateria do protótipo (aba "Bateria de notas") indicado em cada caso.

### R-NFE-030 · Base reduzida e cBenef no item · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Aceite:** Dado regra com redução de 33,33% e ICMS 18% · Quando vendo R$ 600 · Então base R$ 400,02 e ICMS R$ 72,00 (bateria C23), e o `det` leva o cBenef. Controle positivo: regra sem redução calcula sobre a base cheia.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-030`
- **Contrato:** lei do benefício da UF citada na regra (lei 4)
- **Regressão que defende:** benefício aplicado sem o código exigido (rejeição) ou esquecido (imposto a mais).

## Prova
Testes verdes · a lei citada literal no docblock de cada regra (lei 4 do módulo) · biz de teste conforme ADR 0358.

Terminou: `_saida-25.md`. Pare.
