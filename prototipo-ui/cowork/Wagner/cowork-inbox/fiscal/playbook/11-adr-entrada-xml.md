---
sessao: "11"
titulo: Proposta de ADR: entrada por XML de compra
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b (2026-10-06)
prefixo: memory/decisions/proposals/2026-10-06-entrada-xml-compra.md
nao_toca: código (só papel)
depende: — (D-ENTRADA)
decisao: _DECISOES-W-2026-10-06.md
implementa: — (papel/medição, sem UC de comportamento)
us: US-NFE-049: já prevê `nfe_dfe_itens` (… `produto_id_match` nullable, `ncm` …). O de-para da entrada **estende** esse modelo, não cria outro.
---
# 11 · Proposta de ADR: entrada por XML de compra

Escopo da ADR: fonte do XML (DF-e já manifestado · upload), de-para item do fornecedor → produto (por business, lembrado), conversão de CFOP 5→1 / 6→2 / 5405→1403, aviso de NCM divergente sem troca automática, CST 060 → produto sai com CSOSN 500, custo × crédito por regime, e onde mora (NfeBrasil × Compras). **Importação (UC-TRB-12) entra nesta mesma ADR:** nota de entrada CFOP 3102, valor aduaneiro = (FOB + frete + seguro) × câmbio **da data de registro da DI/DUIMP, gravado na nota**; II, IPI, PIS/COFINS-importação e ICMS "por dentro"; variação cambial depois vai ao financeiro e nunca recalcula o imposto. Alvo de fluxo: abas Entradas e Importação do protótipo.

## Prova
Arquivo da proposta com status `proposto`, referenciando UC-TRB-18.

Antes de editar: confirmar no turno os símbolos citados (C12). Terminou: `_saida-11.md` com o sha e a saída dos testes. Pare.
