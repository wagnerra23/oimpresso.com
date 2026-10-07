---
sessao: "07"
titulo: Operação + vigência nas regras (R-NFE-018..020d)
autor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main 03f5d9d822 (já com o #8874 da thread 06 e o #8889 da thread 04)
prefixo_tocado: 2 migrations novas · Models/NfeOperacaoFiscal.php (novo) · Models/NfeFiscalRule.php · MotorTributarioService.php · TributacaoController (update/index) · MotorTributarioServiceTest · OperacaoFiscalTenantTest (novo) · 3 testes que liam a regra pelo id antigo · SPEC · SCOPE · SUPERFICIE (regenerada) · nfebrasil-pest.yml · este arquivo
---
# _saida-07 · Operação + vigência nas regras

**Resposta curta:** existe a tabela `nfe_operacoes_fiscais`, as regras têm `operacao_id`,
`valida_de`/`valida_ate` e `versao_origem_id`, e o motor escolhe a versão vigente na data da
emissão, restringe a exceção à operação dela e resolve CFOP com "?" pelo destino. Editar uma regra
gera versão nova. **Com os dados de hoje, nenhum valor calculado muda** (prova no §3).

## 1 · Confirmação dos símbolos (C12, relidos no turno)

- `nfe_fiscal_rules` não tem índice único (o `cascade_idx` é comum), então versões cabem na tabela.
- `regra_id` **não é gravado** na nota (só `nivel_tributacao`, `NfeService.php:534`): versão nova
  não quebra referência de nota.
- O override por produto (Nível 1) aponta um **id** de regra: por isso `versao_origem_id` — o
  motor segue a cadeia até a versão vigente.
- O listener `SyncFiscalRuleToTaxRate` (ARQ-0005) cria um `tax_rate` por regra sem vínculo. Versão
  nova sem cuidado duplicaria a alíquota no cadastro de impostos do UltimatePOS.
- Os motores falsos dos testes sobrescrevem só `calcular` (4 parâmetros); os parâmetros novos
  entraram em `calcularComDestino`.

## 2 · Decisões de técnica

| decisão | por quê |
|---|---|
| "Venda" nasce com `regra_geral` NULL = usa o `tributacao_default` | copiar o JSON deixaria a tela ConfigDefault editando uma cópia que o motor não lê |
| regra sem `operacao_id` = da operação padrão | nenhuma linha existente precisa de UPDATE (append-only) |
| operação não-padrão sem regra geral recusa o cálculo | cair no padrão de venda é exatamente o que o R-NFE-020b proíbe |
| a versão nova herda o vínculo de `tax_rate` | o cadastro de impostos não ganha uma alíquota por edição |
| versão encerrada some da lista e dá 404 ao editar | editar uma encerrada abriria uma 2ª versão paralela à vigente |
| tudo só liga se a migração rodou (`hasColumn`, cache por conexão) | os testes SQLite montam o schema à mão sem as colunas novas |

## 3 · Prova (CT 100, worktree isolado `/tmp/wt-t07`, sha `6d1bd4712`)

**Dupla prova de valor.** O staging não tem regra nem configuração fiscal, então montei um corpus
sintético no tenant 98 (36 regras N2/N3 com CST e CSOSN, MVA, FCP, IPI, IBS/CBS, 3 UFs + padrão
N4 + 36 overrides N1) e calculei 86 casos três vezes:

| comparação | resultado |
|---|---|
| motor do `main` × motor da 07 **sem** a migração | 86 iguais · 0 diferentes |
| motor do `main` × motor da 07 **com** as 2 migrações | 86 iguais · 0 diferentes |

**Testes (com as migrações aplicadas):** `MotorTributarioServiceTest` 25 passed · 115 assertions ·
`OperacaoFiscalTenantTest` 3 passed · `RegraTributariaIbsCbsValidacaoTest` 3 passed ·
`TributacaoControllerTest` 7 passed · `TributacaoIndexContratoTest` 6 passed ·
`ImportRegrasCsvServiceTest` 12 passed · `NfeEmissaoPorItemTest` 9 passed.
`TributacaoGatesContratoTest`: 3 failed, os mesmos do `main` (`Admin#1` do staging).

**Mutações:** motor do `main` → R-NFE-018, 019, 020b e 020c caem (4 failed). Versionar sem mover
o vínculo → o caso do `tax_rate` cai (*"2 is identical to 1"*).

**Limpeza do staging:** as 2 migrações foram revertidas (`migrate:rollback --path`), a tabela
`migrations` voltou sem as entradas da 07, o seed sintético foi apagado. A rodada de mutação deixou
2 regras de teste no tenant 98 (o caso caiu antes da limpeza do fim); apaguei-as e passei a
limpeza para o `afterEach`.

## 4 · Diferença do pedido

- **Granularidade de dia.** A vigência é por data: editar uma regra hoje faz uma nota emitida mais
  cedo hoje, se recalculada, usar a versão nova.
- **A emissão passa a data de hoje** (parâmetro default). O **SPED** (`Modules/Fiscal`,
  `SpedIcmsIpiGeneratorService:300`) recalcula notas antigas pelo `calcular` sem passar a data
  delas: depois desta thread ele pode usar a versão nova numa nota velha. Fora do prefixo; fica
  para uma thread do SPED.
- **Exterior = `EX`** no `uf_destino`. A emissão ainda não manda `EX`.
- **3 testes existentes** liam a regra pelo id antigo depois do `PUT` (UC-NFRF-01, UC-NFTR-04,
  UC-NFRF-07). Pela precedência (teste > casos > charter > SPEC) e pela D-OPERACAO, o perdedor é o
  assert: agora leem a versão vigente da cadeia.
- **Sem tela.** Cadastro de operações e campo de vigência no form são das threads de UI.
