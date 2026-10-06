---
sessao: "23"
titulo: Retenções federais na NFS-e (PIS/COFINS/CSLL e IRRF)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 24561f0da83b (2026-10-06)
prefixo: Modules/NFSe/Services/ (cálculo das retenções na DPS) · Tests
nao_toca: resources/js/ (tela é outra thread, depois do alvo)
depende: consolidação NfeBrasil × NFSe ([W], fiscal-faturamento.md) — se a NFS-e for do NfeBrasil, o prefixo muda
decisao: _DECISOES-W-2026-10-06.md (D-SUPORTE · D-MOTOR quando tocar o motor)
implementa: R-NFSE-xx1 · R-NFSE-xx2
us: UC-TRB-20 · US-NFSE-009 ("retenções opcionais")
---
# 23 · Retenções federais na NFS-e (PIS/COFINS/CSLL e IRRF)

Tomador PJ + prestador no regime normal: PIS/COFINS/CSLL 4,65% (Lei 10.833/2003, art. 30), com dispensa até R$ 10,00 (art. 31 §3º). IRRF 1,5% conforme o item. Prestador no Simples: sem retenção de PIS/COFINS/CSLL, só aviso pra confirmar com o contador. INSS 11% (cessão de mão de obra) fica **fora** desta thread.

## Casos de uso que esta thread implementa
**Números provisórios:** confirmar o próximo livre no turno. Cenário correspondente na bateria do protótipo (aba "Bateria de notas") indicado em cada caso.

### R-NFSE-xx1 · Retém 4,65% de tomador PJ no regime normal; dispensa até R$ 10 · `must` `[fiscal]`
- **Destino:** SPEC NFSe
- **Aceite:** Dado serviço de R$ 1.500 pra PJ, prestador normal · Então retenção PIS/COFINS/CSLL = R$ 69,75 (bateria C21). Dado serviço de R$ 150 · Então 4,65% = R$ 6,98 ≤ 10 → dispensado, com aviso. Controle positivo: tomador PF não retém.
- **Teste:** `NfseRetencoesTest` — `R-NFSE-xx1`
- **Contrato:** Lei 10.833/2003 arts. 30–31
- **Regressão que defende:** NFS-e sem retenção que o tomador desconta e o financeiro não espera.

### R-NFSE-xx2 · Prestador no Simples não retém PIS/COFINS/CSLL · `must` `[fiscal]`
- **Destino:** SPEC NFSe
- **Aceite:** Dado prestador optante · Quando emito pra PJ · Então nenhuma linha de retenção federal e aviso "confirmar com o contador" (bateria C22). Controle positivo: mudar para regime normal faz a retenção aparecer.
- **Teste:** `NfseRetencoesTest` — `R-NFSE-xx2`
- **Contrato:** regime do business (`nfe_business_configs.regime`)
- **Regressão que defende:** retenção indevida reduzindo o recebido.

## Prova
Testes verdes · a lei citada literal no docblock de cada regra (lei 4 do módulo) · biz de teste conforme ADR 0358.

Terminou: `_saida-23.md`. Pare.
