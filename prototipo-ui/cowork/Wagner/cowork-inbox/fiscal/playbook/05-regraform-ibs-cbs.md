---
sessao: "05"
titulo: Campos IBS/CBS no RegraForm
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b (2026-10-06)
prefixo: resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx · RegraForm.casos.md
nao_toca: Modules/NfeBrasil/Services/ · Http/Requests/
depende: 04 (validação) · 12 (alvo medido)
decisao: _DECISOES-W-2026-10-06.md
implementa: UC-NFRF-07
us: US-FISCAL-021 · RegraForm.charter §Pendências (charter ainda `draft`)
---
# 05 · Campos IBS/CBS no RegraForm

Seção "Reforma tributária" no form: cClassTrib (6 dígitos), CST IBS, CST CBS (3 dígitos), alíquotas IBS/CBS em decimal via `FieldDecimal`, que já existe. Alvo: o drawer de regra do protótipo (`fiscal-tributacao.jsx` · `TrRegraDrawer`).

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| UC-NFRF-07 | Os campos de IBS/CBS aparecem e salvam pela tela | `should` `[fiscal]` | `RegraForm.casos.md` |

### UC-NFRF-07 · Os campos de IBS/CBS aparecem e salvam pela tela · `should` `[fiscal]`
- **Destino:** `RegraForm.casos.md`
- **Persona:** contador no navegador
- **Aceite:** Dado o formulário de regra · Quando preenche a seção "Reforma tributária" e salva · Então reabrir a edição mostra os mesmos valores, com alíquota em % na tela e decimal no banco. Controle positivo: trocar CSOSN↔CST não apaga os campos de IBS/CBS.
- **Teste:** e2e `e2e/nfe-tributacao-regra.spec.ts` — `UC-NFRF-07 · seção reforma salva e reabre`
- **Contrato:** UC-NFRF-05 · `Index.charter.md` §UX (alíquota 2 casas, vírgula PT-BR)
- **Regressão que defende:** o backend aceita e a tela nunca manda.

## Prova
UC novo em `RegraForm.casos.md` com e2e ou feature test que salva e relê os 5 campos.

Antes de editar: confirmar no turno os símbolos citados (C12). Terminou: `_saida-05.md` com o sha e a saída dos testes. Pare.
