---
sessao: "09"
titulo: Tabela ICMS/FCP por UF curada
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b (2026-10-06)
prefixo: Migrations/<nova> · Models/<NfeIcmsUf nova> · Database/Seeders/<nova> · Tests/Feature/<novo>
nao_toca: Services/ (o fallback do motor é outra thread)
depende: 07 (padrão de vigência)
decisao: _DECISOES-W-2026-10-06.md
implementa: R-NFE-021
us: US-NFE-010 · Lei 4 do módulo
---
# 09 · Tabela ICMS/FCP por UF curada

Tabela UF × vigência: interna, FCP, interestadual. O seed traz **só** as interestaduais com a resolução do Senado citada literal no seeder; a interna fica vazia até o contador preencher (lei 4: sem número sem lei). Sem sync externo.

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| R-NFE-021 | UF sem alíquota interna cadastrada não inventa valor | `must` `[fiscal]` | SPEC NfeBrasil |

### R-NFE-021 · UF sem alíquota interna cadastrada não inventa valor · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** qualquer emissão para UF que o contador ainda não preencheu
- **Aceite:** Dado UF com interestadual semeada e interna vazia · Quando o motor precisa da interna · Então devolve erro explicável ("alíquota interna de RJ não cadastrada"), nunca 0 nem um palpite. Controle positivo: com a interna preenchida, calcula.
- **Teste:** `IcmsUfTest` — `R-NFE-021 · UF sem interna não inventa`
- **Contrato:** Lei 4 do módulo · D-UF
- **Regressão que defende:** DIFAL zero em silêncio.

## Prova
Teste: seed idempotente · UF sem interna não inventa valor · append-only.

Antes de editar: confirmar no turno os símbolos citados (C12). Terminou: `_saida-09.md` com o sha e a saída dos testes. Pare.
