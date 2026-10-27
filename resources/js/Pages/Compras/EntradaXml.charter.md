---
page: /compras/entrada-xml
component: resources/js/Pages/Compras/EntradaXml.tsx
owner: wagner
status: draft
parent_module: Compras
related_prototype: prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx
alcance:
  rota: /compras/entrada-xml
  rota_nome: compras.entrada-xml        # name() da rota — é o que o guard procura
  permission: compras.import_xml     # declarada em DataController::user_permissions
  menu_hook: Modules/Compras/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: compras_module              # superadmin_package
tier: B
charter_version: 1
---

# Page Charter — Compras/EntradaXml (DRAFT · carimbado do PT-01 · desenho TrEntradas)

> Nascida do Padrão de Tela **PT-01 Lista** via `criar-tela.mjs` (UI-0013 — herança
> de padrão, NÃO bespoke). Golden do arquétipo: [PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
> Preencha os TODO antes de subir de `draft` → `live` (exige screenshot aprovado por Wagner).

## Mission

Conferir a NF-e do fornecedor (vinda da SEFAZ ou enviada em arquivo) e transformá-la em compra
recebida sem digitar, com o vínculo item do fornecedor → produto lembrado para a próxima nota
(US-COM-003 · ADR `proposals/2026-10-06-entrada-xml-compra.md`, fase 4).

## Goals — Features (faz)

- Recebe o XML da nota em arquivo e lista as notas que ainda não viraram compra
- Mostra os itens no desenho da aba Entradas do protótipo: CFOP de entrada proposto, CST com ST retida, ICMS destacado
- Vincula cada item a um produto da empresa, com sugestão por EAN que só vale se a pessoa clicar
- Gera a compra recebida no local escolhido, pelo mesmo caminho do `/purchases`
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

- ❌ Vincular produto sozinho (ADR D3: sugestão sim, vínculo silencioso não)
- ❌ Trocar NCM ou CSOSN do produto (ADR D4: só sugestão confirmada)

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE)
- TODO: metas de p95 se aplicável

## Refs

- Padrão de Tela: PT-01 Lista (DataTable + PageHeader + filtros)
- Constituição UI v2: UI-0013
