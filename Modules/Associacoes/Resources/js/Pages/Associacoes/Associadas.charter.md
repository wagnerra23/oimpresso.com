---
id: modules-associacoes-pages-associacoes-associadas-charter
page: /associacoes/associadas
component: Modules/Associacoes/Resources/js/Pages/Associacoes/Associadas.tsx
owner: wagner
status: draft
last_validated: "2026-11-13"
parent_module: Associacoes
related_prototype: "n/a (herda PT-01 Lista; não há canvas Cowork da área de membros — gerada ancorada no Design System)"
related_adrs: [93, 358, 451]
alcance:
  rota: /associacoes/associadas
  rota_nome: associacoes.associadas.index
  permission: associacoes.access
  menu_hook: Modules/Associacoes/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: associacoes_module
tier: B
charter_version: 1
---

# Page Charter — /associacoes/associadas (DRAFT · associadas e consentimentos)

> Nasce na US-ASSOC-003 (ADR 0451, onda 4), carimbada do PT-01 Lista por `criar-tela.mjs`.
> Casos: [`Associadas.casos.md`](Associadas.casos.md). Non-Goals e Anti-hooks além dos abaixo
> são de [W] — não se inferem aqui.

## Mission

Saber quem faz parte de cada associação da rede e cadastrar uma nova associada com o aceite dela, sem guardar mais dado pessoal do que o necessário.

## Goals — Features (faz)

- Lista de associadas com associação, cidade, situação e data de entrada; filtro por associação e por situação — UC-ASSOCI-03.
- Celular sempre mascarado na lista e na busca de contato — UC-ASSOCI-04.
- Nome visível para quem representa a associação; para a diretoria da rede, só se a associada aceitou aparecer para a rede (ADR 0451 D5) — UC-ASSOCI-04.
- Cadastro a partir de um contato da empresa (busca no servidor) ou de um contato novo (nome, celular, cidade), sem duplicar celular na empresa — UC-ASSOCI-01.
- Aceite por finalidade no cadastro: fazer parte da associação (obrigatório), WhatsApp e aparecer para a rede (opcionais). A versão vem da configuração do módulo — UC-ASSOCI-01, UC-ASSOCI-05.
- Desligar com data, sem apagar — UC-ASSOCI-06.
- Lista só da empresa da sessão; o representante só vê e só cadastra na associação dele — UC-ASSOCI-02, UC-ASSOCI-03.
- Ver exige `associacoes.access`; cadastrar e desligar exigem `associacoes.editar` — UC-ASSOCI-07.

## Non-Goals — Features (NÃO faz)

- ❌ Pedir CPF, diagnóstico, laudo ou CID (ADR 0451 D5).
- ❌ Guardar nome ou celular na associada: isso mora no contato do núcleo.
- ❌ Apagar associada ou consentimento: desliga e revoga.
- ❌ Ligar a associada ao paciente do Fisio (outra empresa — ADR 0451, fora da decisão).

## UX Targets

- Cabe em 1280px sem scroll horizontal.
- Textos dos aceites em linguagem simples, lidos para a associada no cadastro.

## Refs

- ADR 0451 (D3, D4, D5) · PT-01 Lista · UI-0013 · molde: `Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.tsx`
