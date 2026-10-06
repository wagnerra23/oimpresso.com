---
sessao: "26"
titulo: Filial em outro estado: a UF de origem vem do emitente
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 24561f0da83b (2026-10-06)
prefixo: Modules/NfeBrasil/Services/NfeService.php (resolverUF por local/filial) · MotorTributarioService.php (chave uf_origem já existe) · Tests
nao_toca: resources/js/ (tela é outra thread, depois do alvo)
depende: 17 · 07. 🔴 sozinho no PR
decisao: _DECISOES-W-2026-10-06.md (D-SUPORTE · D-MOTOR quando tocar o motor)
implementa: R-NFE-031 · R-NFE-032
us: UC-TRB-27 · `business_locations` (UltimatePOS) — confirmar no turno onde mora a UF da filial
---
# 26 · Filial em outro estado: a UF de origem vem do emitente

A `uf_origem` do cálculo deixa de ser a do business e passa a ser a do **local emissor** (filial). As exceções cadastradas para SP não valem pra filial do PR (a chave do motor já inclui `uf_origem`). A alíquota interestadual sai da regra do Senado entre as duas UFs.

## Casos de uso que esta thread implementa
**Números provisórios:** confirmar o próximo livre no turno. Cenário correspondente na bateria do protótipo (aba "Bateria de notas") indicado em cada caso.

### R-NFE-031 · Filial usa a própria UF de origem · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Aceite:** Dado filial no PR vendendo pra SC · Então interestadual 12% e as exceções de SP não são usadas (N4) (bateria C24) · PR → BA → 7% (C25). Controle positivo: a matriz SP vendendo pra BA também dá 7% e usa as próprias exceções.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-031`
- **Contrato:** Resolução do Senado 22/1989 · chave `uf_origem` (ARQ-0006)
- **Regressão que defende:** filial emitindo como se estivesse em SP.

### R-NFE-032 · Série, numeração e certificado são do local emissor · `must` `[T0]` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Aceite:** Dado matriz e filial com séries próprias · Quando emito pela filial · Então série e numeração da filial, sem colidir com a matriz. Controle positivo: duas emissões simultâneas (matriz e filial) não pulam nem repetem número.
- **Teste:** `NfeFilialNumeracaoTest` — `R-NFE-032`
- **Contrato:** R-NFE-003 (numeração sem gap)
- **Regressão que defende:** número repetido entre estabelecimentos.

## Prova
Testes verdes · a lei citada literal no docblock de cada regra (lei 4 do módulo) · biz de teste conforme ADR 0358.

Terminou: `_saida-26.md`. Pare.
