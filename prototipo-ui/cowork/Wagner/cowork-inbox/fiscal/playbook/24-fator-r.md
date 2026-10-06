---
sessao: "24"
titulo: Fator R automático (folha ÷ receita 12 meses)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 24561f0da83b (2026-10-06)
prefixo: Modules/NfeBrasil/Services/<FatorRService novo> · Tests
nao_toca: resources/js/ (tela é outra thread, depois do alvo)
depende: — (lê Ponto/RH e Financeiro; não escreve neles)
decisao: _DECISOES-W-2026-10-06.md (D-SUPORTE · D-MOTOR quando tocar o motor)
implementa: R-NFE-029
us: UC-TRB-22 · LC 123/2006 art. 18
---
# 24 · Fator R automático (folha ÷ receita 12 meses)

Calcula folha (salários + pró-labore) ÷ receita bruta dos últimos 12 meses. ≥ 28% → Anexo III; abaixo → Anexo V. Mostra na aba Serviços e avisa na Saúde fiscal quando cruzar 28%. **Só informa**, não muda regime nem DAS.

## Casos de uso que esta thread implementa
**Números provisórios:** confirmar o próximo livre no turno. Cenário correspondente na bateria do protótipo (aba "Bateria de notas") indicado em cada caso.

### R-NFE-029 · Fator R decide o anexo e avisa ao cruzar 28% · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Aceite:** Dado folha 84.000 e receita 280.000 → 30% → Anexo III (bateria C26) · Dado 50.000/280.000 → 17,9% → Anexo V (C27) · Quando o mês novo derruba de 29% para 27% · Então pendência "Fator R abaixo de 28%" na Saúde fiscal. Controle positivo: receita zero não divide por zero (resultado "sem dado").
- **Teste:** `FatorRTest` — `R-NFE-029`
- **Contrato:** LC 123/2006 art. 18 §5º-J/§5º-M (citar literal)
- **Regressão que defende:** empresa pagando Anexo V sem saber que estava no III, ou o contrário.

## Prova
Testes verdes · a lei citada literal no docblock de cada regra (lei 4 do módulo) · biz de teste conforme ADR 0358.

Terminou: `_saida-24.md`. Pare.
