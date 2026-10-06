---
sessao: "20"
titulo: Devolução de venda copia base e alíquotas da nota de origem
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 11d035d368ea (2026-10-06)
prefixo: Modules/NfeBrasil/Services/<DevolucaoService novo ou método no NfeService — decidir no turno> · Tests/Feature/<NfeDevolucaoTest novo>
nao_toca: MotorTributarioService.php (devolução NÃO recalcula pela regra de hoje)
depende: 17 (itens reais) · 07 (operação "Devolução de venda"). 🔴 sozinho no PR
decisao: _DECISOES-W-2026-10-06.md (D-OPERACAO)
implementa: R-NFE-026 · R-NFE-027
us: UC-TRB-13 (protótipo, aba Devoluções) — sem US no SPEC hoje: criar a US no mesmo PR
---
# 20 · Devolução de venda

Finalidade 4, nota referenciada (refNFe), CFOP espelho (5102→1202 · 6102→2202 · 5405→1411…), quantidade parcial proporcional. **A base e as alíquotas vêm da nota de origem, nunca da regra vigente.**

## Casos de uso que esta thread implementa
| UC | Título | Prioridade | Destino |
|---|---|---|---|
| R-NFE-026 | Devolução copia base e alíquotas da nota de origem | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-027 | Devolução parcial é proporcional e não passa da quantidade vendida | `must` `[fiscal]` | SPEC NfeBrasil |

### R-NFE-026 · Devolução copia base e alíquotas da nota de origem · `must` `[fiscal]`
- **Aceite:** Dado nota autorizada com ICMS 18% e, depois, a regra mudada para 12% · Quando emito a devolução · Então o `det` usa 18% e a base da origem, `finNFe=4`, `refNFe` = chave da origem e CFOP espelho. Controle positivo: venda nova depois da mudança usa 12%.
- **Teste:** `NfeDevolucaoTest` — `R-NFE-026 · devolução copia a origem`
- **Contrato:** D-OPERACAO · R-NFE-019 (versão antiga intacta)
- **Regressão que defende:** devolução recalculada pela alíquota de hoje (crédito/débito errado).

### R-NFE-027 · Devolução parcial é proporcional e não passa da quantidade vendida · `must` `[fiscal]`
- **Aceite:** Dado venda de 10 unidades · Quando devolvo 4 · Então valores e impostos = 4/10 da linha · Quando tento devolver mais 7 · Então recusado (saldo 6). Controle positivo: devolver as 6 restantes é aceito.
- **Teste:** `NfeDevolucaoTest` — `R-NFE-027 · parcial proporcional com saldo`
- **Contrato:** UC-TRB-13
- **Regressão que defende:** devolução maior que a venda.

## Prova
Os 2 testes verdes · `NfeServiceIdempotenciaRetryTest` segue verde · US nova no SPEC citando R-NFE-026/027.

Terminou: `_saida-20.md`. Pare.
