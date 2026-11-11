---
id: modules-fisio-pages-fisio-pesquisa-index-charter
page: /fisio/pesquisa
component: Modules/Fisio/Resources/js/Pages/Fisio/Pesquisa/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/prototipos/fisio/Pesquisa.dc.html
owner: wagner
status: draft
last_validated: "2026-11-11"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/pesquisa
  rota_nome: fisio.pesquisa.index
  permission: fisio.pesquisa
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/pesquisa (DRAFT · área do estudo)

> Nasce na etapa 07 P-04 (pesquisa clínica em fibromialgia, `memory/requisitos/Fisio/etapas/07-pesquisa-fibromialgia.md` §4.2). Casos: [`Index.casos.md`](Index.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Dar ao pesquisador do estudo um lugar para cadastrar o estudo, incluir e acompanhar os participantes e ver os questionários que vencem em cada momento de avaliação.

## Goals — Features (faz)

- Criar estudo com parecer do CEP, versão do protocolo, versão do TCLE e momentos de avaliação — UC-FES-01.
- Incluir participante com o aceite do TCLE na versão do estudo e código que não vem do nome nem do CPF — UC-FES-02.
- Registrar a saída (perda de seguimento, conclusão, outro) com motivo, sem apagar — UC-FES-03.
- Ver as pendências por participante e momento — UC-FES-04.

## Non-Goals (não faz)

- Decidir conduta ou classificar o participante. O software organiza o estudo (ADR 0437 §5).
- Juntar dado de clínicas diferentes (ADR 0437 §7).

## Automation Anti-hooks

- Quem não tem `fisio.editar` vê só o código do participante, nunca o nome — UC-FES-07.
