---
sessao: "14"
titulo: Saúde fiscal: pendências que viram rejeição
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b (2026-10-06)
prefixo: Modules/NfeBrasil/Services/<SaudeFiscalService novo> · Controllers · Pages/NfeBrasil/Tributacao/Index.tsx · Index.casos.md · Tests/Feature/<novo>
nao_toca: MotorTributarioService.php
depende: 17 · 07 · 12 · 15
decisao: _DECISOES-W-2026-10-06.md
implementa: UC-NFTR-11 · UC-NFTR-13
us: US-NFE-010, critério aberto "[ ] Filtro Suspeitas (ICMS=0 fora Simples, ICMS-ST sem MVA…)" · US-NFE-007 (monitor de rejeições com sugestão de correção) · auditoria 2026-05 bug #3 (`ncm_default` 00000000)
---
# 14 · Saúde fiscal: pendências que viram rejeição

Service read-only que lista: **NCM padrão da empresa inválido** (`00000000` gravado pelo `ConfigDefaultController` / templates sem `ncm_default` — auditoria 2026-05 bug #3) · clientes sem código IBGE do município · itens emitidos com o NCM padrão (`metadata.itens_ncm_padrao`, vem da 17) · produtos ativos sem NCM · serviços sem código municipal onde a prefeitura exige · regras sem cClassTrib · operações sem aceite · dias para o certificado vencer · rejeições recentes de causa cadastral (receita determinística que já existe). Cada item traz a ação que resolve. Card no topo da Index, igual à aba Saúde fiscal do protótipo.

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| UC-NFTR-11 | A saúde fiscal lista o que vai virar rejeição e o item some ao corrigir | `must` `[fiscal]` | `Index.casos.md` |
| UC-NFTR-13 | "Pronto pra emitir" = nenhuma pendência bloqueante | `should` `[fiscal]` | `Index.casos.md` |

### UC-NFTR-11 · A saúde fiscal lista o que vai virar rejeição e o item some ao corrigir · `must` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** Larissa / Eliana abrindo a tela
- **Aceite:** Para cada tipo (NCM padrão `00000000` ou ausente · cidade sem IBGE · item emitido com NCM padrão · regra sem cClassTrib · operação sem aceite · certificado ≤ 30 dias · rejeição cadastral recente): Dado a fixture que provoca · Então aparece com a ação · Quando corrijo · Então some. Controle positivo: empresa sem nenhum problema mostra lista vazia. E nada de outro business aparece.
- **Teste:** `SaudeFiscalTest` — `UC-NFTR-11 · pendência aparece e some ao corrigir`
- **Contrato:** D-SUPORTE · US-NFE-010 "Filtro Suspeitas" · auditoria 2026-05 bug #3
- **Regressão que defende:** o usuário só descobre o problema na rejeição da SEFAZ.

### UC-NFTR-13 · "Pronto pra emitir" = nenhuma pendência bloqueante · `should` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** empresa nova configurando
- **Aceite:** Dado pendências bloqueantes (NCM padrão inválido, certificado vencido) · Então o indicador mostra "não pronto" com a contagem e o link de cada uma · Quando resolvo todas · Então "pronto". Pendência só de aviso não bloqueia. Controle positivo: empresa configurada nasce "pronta".
- **Teste:** `SaudeFiscalTest` — `UC-NFTR-13 · pronto pra emitir`
- **Contrato:** UC-TRB-16 · D-SUPORTE · templates L1 (US-NFE-TPL-001)
- **Regressão que defende:** a primeira nota ser o teste da configuração.

## Prova
Teste por tipo de pendência (fixture que a provoca → aparece; corrige → some) · multi-tenant (outro business não aparece) · zero escrita.

Antes de editar: confirmar no turno os símbolos citados (C12). Terminou: `_saida-14.md` com o sha e a saída dos testes. Pare.
