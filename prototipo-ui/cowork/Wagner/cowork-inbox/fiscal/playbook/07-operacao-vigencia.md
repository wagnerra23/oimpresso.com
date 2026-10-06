---
sessao: "07"
titulo: Operação + vigência nas regras
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b (2026-10-06)
prefixo: Modules/NfeBrasil/Database/Migrations/<nova> · Models/NfeFiscalRule.php · Models/<NfeOperacaoFiscal nova> · MotorTributarioService.php · Tests/Feature/MotorTributarioServiceTest.php
nao_toca: resources/js/
depende: 06 (mesmo arquivo do motor). 🔴 schema+motor
decisao: _DECISOES-W-2026-10-06.md
implementa: R-NFE-018 · R-NFE-019 · R-NFE-020 · R-NFE-020b · R-NFE-020c · R-NFE-020d
us: US-NFE-010 · ADR ARQ-0004 (schema flexível) · ARQ-0006 (cascata)
---
# 07 · Operação + vigência nas regras

Tabela de operações (nome · finalidade · CFOP com "?" · tipo de destinatário · regra geral) e, em `nfe_fiscal_rules`, `operacao_id` + `valida_de`/`valida_ate`. Cascata: N1 produto → N2 NCM+UF → N3 NCM → **N4 regra geral da operação**, filtrando a versão vigente na data da emissão. Migração append-only: o `tributacao_default` atual vira a regra geral da operação "Venda"; nenhuma coluna removida.

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| R-NFE-018 | A nota usa a versão de regra vigente na data da emissão | `must` `[T0]` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-019 | Editar regra cria versão nova; a antiga continua lendo igual | `must` `[T0]` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-020 | O padrão atual vira a regra geral da operação Venda sem perda | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-020b | Exceção só vale para a operação dela | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-020c | CFOP com "?" vira 5, 6 ou 7 pelo destino | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-020d | Operações e versões de regra não atravessam empresas | `must` `[T0]` | SPEC NfeBrasil |

### R-NFE-018 · A nota usa a versão de regra vigente na data da emissão · `must` `[T0]` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** qualquer emissão depois de uma troca de alíquota
- **Aceite:** Dado a versão A válida até 31/12 e a B a partir de 01/01 · Quando calculo com data 31/12 e com 01/01 · Então uso A e B, respectivamente. Controle positivo: sem vigência cadastrada, o comportamento é o de hoje.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-018 · versão vigente na data`
- **Contrato:** D-OPERACAO · ADR ARQ-0006
- **Regressão que defende:** alíquota nova aplicada retroativamente, ou a velha depois da virada.

### R-NFE-019 · Editar regra cria versão nova; a antiga continua lendo igual · `must` `[T0]` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** contador corrigindo uma alíquota
- **Aceite:** Dado uma regra usada numa nota emitida · Quando edito · Então nasce uma versão nova com `valida_de` = hoje, a antiga ganha `valida_ate` = ontem, e **nenhuma coluna da antiga muda**. Controle positivo: a nota antiga, recalculada para auditoria com a data dela, dá o mesmo valor.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-019 · editar gera versão, append-only`
- **Contrato:** ADR 0093 G8 (append-only) · UC-NFCD-05
- **Regressão que defende:** "corrigir" a regra reescreve a explicação de notas já emitidas.

### R-NFE-020 · O padrão atual vira a regra geral da operação Venda sem perda · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** toda empresa existente na migração
- **Aceite:** Dado `tributacao_default` com cfop/csosn/alíquotas · Quando a migração roda · Então a operação "Venda" tem regra geral com os mesmos valores, e `calcular` devolve o mesmo resultado de antes para o mesmo contexto. Controle positivo: rodar a migração duas vezes não duplica.
- **Teste:** teste de migração — `R-NFE-020 · default vira regra geral da Venda`
- **Contrato:** D-OPERACAO · UC-NFCD-02 (alias `cfop` load-bearing)
- **Regressão que defende:** empresas emitindo com N4 vazio no dia seguinte ao deploy.

### R-NFE-020b · Exceção só vale para a operação dela · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** devolução de um NCM que tem exceção de venda
- **Aceite:** Dado exceção NCM X na operação "Venda" · Quando calculo a operação "Devolução de venda" para o mesmo NCM · Então a exceção de venda **não** é usada (cai na regra geral da devolução). Controle positivo: na "Venda", a exceção é usada.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-020b · exceção restrita à operação`
- **Contrato:** D-OPERACAO
- **Regressão que defende:** regra de venda aplicada em devolução/importação.

### R-NFE-020c · CFOP com "?" vira 5, 6 ou 7 pelo destino · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** venda dentro e fora do estado com a mesma operação
- **Aceite:** Dado operação com CFOP `?102` · Quando destino = UF da empresa / outra UF / exterior · Então CFOP = `5102` / `6102` / `7102`. Controle positivo: CFOP sem "?" (ex.: `3102`) não é alterado.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-020c · CFOP ? por destino`
- **Contrato:** D-OPERACAO · padrão de mercado (Tiny/Olist)
- **Regressão que defende:** CFOP interno numa venda interestadual (rejeição).

### R-NFE-020d · Operações e versões de regra não atravessam empresas · `must` `[T0]`
- **Destino:** SPEC NfeBrasil
- **Persona:** qualquer tenant
- **Aceite:** Dado operação/versão do business A · Quando o business B lista, calcula ou edita · Então não vê, não usa e recebe 404 ao editar. Controle positivo: A vê e usa as próprias.
- **Teste:** `OperacaoFiscalTenantTest` — `R-NFE-020d · operação isolada por business`
- **Contrato:** ADR 0093 · `HasBusinessScope`
- **Regressão que defende:** tabela nova nascendo sem escopo de tenant.

## Prova
Teste: nota com data antes e depois de uma troca de vigência usa versões diferentes · regra sem operação cai na "Venda" · os testes atuais do motor continuam verdes.

Antes de editar: confirmar no turno os símbolos citados (C12). Terminou: `_saida-07.md` com o sha e a saída dos testes. Pare.
