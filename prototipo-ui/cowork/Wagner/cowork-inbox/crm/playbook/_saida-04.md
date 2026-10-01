---
sessao: "04"
titulo: "Painel do Crm (CrmDashboardController) → Inertia — saída da thread"
autor: "[CL]"
data: 2026-10-01
base: origin/main 0754c7213
thread: 05-painel.md §04
veredito: "Entregue o painel do CRM (`/crm/dashboard`) em Inertia, PT-04, com contrato + charter + casos + teste no mesmo PR (lei IT2). O portal do contato (`DashboardController`) NÃO foi tocado — ver Pendente 1."
---

# _saida-04 · Painel do Crm → Inertia

## Estado ao abrir

O placar mostrou `pendente`: sem `_saida`, prova ainda não satisfeita e `depende de 01 (não
feita)`. A 01 foi entregue só com o recibo (#8345), porque o check required "Contratos de tela"
reprova contrato sem a Page; pela opção 1 do `_saida-01`, cada contrato entra no PR da sua Page
(a 03 fez o mesmo com `crm-acompanhamentos`, #8352). Esse era o único motivo.

## O que entrou

| arquivo | o quê |
|---|---|
| `Modules/Crm/Http/Controllers/CrmDashboardController.php` | `index()` renderiza `Crm/Painel/Index`; `?classico=1` mantém a Blade. KPIs/agregados em `Inertia::defer` (`pessoal`, `negocio`) |
| `Modules/Crm/Resources/js/Pages/Crm/Painel/Index.tsx` | KPIs + 8 seções na ordem do contrato |
| `governance/design/contracts/crm-painel.contract.json` | copiado do `_saida-01`; só o `alvo` mudou para o caminho real |
| `Index.charter.md` · `Index.casos.md` | 6 UCs (UC-CRMPAI-01..06) |
| `Modules/Crm/Tests/Feature/CrmPainelContratoTest.php` | 1 teste por UC, tenant 98 (99 como "outro negócio") |
| `.github/workflows/verticais-pest.yml` | a lane MySQL roda o teste e dispara com o controller |
| `memory/requisitos/{Crm,Cliente}/SUPERFICIE.md` · `Crm/_STATUS-GENERATED.md` | regenerados pelos donos |

## Multi-tenant Tier 0

- Os três agregados do usuário que **não** filtravam o negócio passaram a filtrar (vale também
  para a Blade): `myFollowUps` (`follow_ups.business_id`), `todaysFollowUp` (`business_id`),
  `myConversion` (`contacts.business_id`). Guarda: UC-CRMPAI-02.
- O bloco do negócio filtra `business_id` em cada consulta, inclusive nas que a Blade buscava em
  outros controllers (por usuário, conversões, chamadas de todos). Guarda: UC-CRMPAI-03.
- O bloco do negócio só é calculado e enviado para `Admin#<biz>` (o mesmo gate da Blade).
  Guarda: UC-CRMPAI-04.

## Decisões técnicas (do Code)

- Seções da Blade que vinham por ajax de outros controllers (`ReportController`,
  `CallLogController`) viraram agregados no próprio painel, para não tocar fora do prefixo.
- A ficha diz PT-05; o painel é dashboard, então segui o PT-04 (o PT-05 do repo é Kanban).
- "Registro de chamadas — todos os usuários" usa as colunas do protótipo (Hoje · No mês · Todas);
  a Blade mostrava Hoje · Ontem · Todas.
- Conversão por fonte arredondada a 1 casa (a Blade imprimia o float cru).

## Provas do json conferidas

- `Modules/Crm/Http/Controllers/CrmDashboardController.php` contém `Inertia::render(` — ✅ neste PR.
- `contrato-de-tela.mjs --contract crm-painel.contract.json` → 8 seções, âncora + copy + ordem,
  **limpo**. `--map --check` e `--anti-tautologia` limpos.
- `casos-coverage-guard`: sem violação nova. `validate.mjs` no charter: conforme.
- `module-surface --all --check`: sem drift após regenerar.
- Pest: **NÃO rodado local** (regra do repo). A prova é a lane `verticais-pest` do PR.

## Pendente (não feito, e por quê)

1. **`DashboardController` (portal do contato, `/contact/contact-dashboard`)**: está no prefixo,
   mas é a tela **cliente-facing** da D3 ("entra, o Cowork fatia"). Não há contrato nem âncora de
   protótipo para ela (`CrmPortalPage` não foi derivado na 01). Fazer agora seria inventar. Precisa
   de thread própria do Cowork.
2. **Filtros de período e categoria** em "Acompanhamentos por usuário" e o **detalhe das
   conversões** por usuário: seguem na Blade (`?classico=1`).
3. **Gate de acesso à rota**: `/crm/dashboard` não tem checagem de permissão nem de pacote antes
   ou depois deste PR (só as seções são gateadas). Não acrescentei gate sem fonte. Decisão [W].
4. **Aviso prévio/canary F5 (ADR 0104):** sem flag; a saída é `?classico=1`. Decisão [W].
5. **SPEC US-CRM-062** ("MWART CrmDashboard"): âncora sugerida
   `**Implementado em:** _parcial_ · Modules/Crm/Resources/js/Pages/Crm/Painel/Index.tsx` — não
   editei o SPEC (fora do prefixo).
6. **Índice:** a prova da 01 passa a ser cumprida thread a thread (opção 1 do `_saida-01`). Com
   este PR, 2 de 3 contratos estão no repo; falta `crm-leads` (thread 02). Editar o
   `00-INDICE.md` é do Cowork.

## Placar

Esperado após o merge: `04` com prova verde e `_saida`, mas `pendente` enquanto a 01 não virar
`feito` no placar (a 01 depende dos 3 contratos; falta o da thread 02).

## PR

O PR da branch `claude/crm-thread-04`.
