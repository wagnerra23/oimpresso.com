---
id: modules-associacoes-pages-associacoes-index-charter
page: /associacoes/lista
component: Modules/Associacoes/Resources/js/Pages/Associacoes/Index.tsx
related_prototype: "n/a (módulo novo sem protótipo Cowork; herda PT-01 Lista e o drawer do DS, gerado ancorado no Design System)"
owner: wagner
status: draft
last_validated: "2026-11-13"
parent_module: Associacoes
related_adrs: [93, 358, 451]
alcance:
  rota: /associacoes/lista
  rota_nome: associacoes.lista
  permission: associacoes.access
  menu_hook: Modules/Associacoes/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: associacoes_module
tier: B
charter_version: 1
---

# Page Charter — /associacoes/lista (DRAFT · associações da rede e documentos)

> Nasce na US-ASSOC-002 (ADR 0451, onda 3). Casos: [`Index.casos.md`](Index.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

> `/associacoes` (raiz) é o painel da rede (onda 5); esta lista mora em `/associacoes/lista`.

## Mission

Manter o cadastro das associações-membro da rede e os documentos delas (estatuto, ata da diretoria, certidão), e mostrar quem está com a ata da diretoria pendente ou atrasada.

## Goals — Features (faz)

- Lista das associações no alcance do usuário (nome, sigla, cidade, região, situação, representante, pendência de diretoria, documentos) — PT-01
- Ficha em drawer: cadastrar e editar nome, sigla, cidade, região, CNPJ e situação
- Diretoria da rede (`associacoes.rede`) cria associação e escolhe os usuários representantes
- Registrar mudança de diretoria; a ata nova enviada fecha a pendência (ADR 0451 D7)
- Documentos com arquivo no `Modules/Arquivos`, validade e "tirar da lista" com motivo
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

- ❌ Apagar documento — sai da lista com motivo (ADR 0451 D3)
- ❌ Bloquear a associação com ata atrasada — o sistema avisa, não bloqueia (ADR 0451 D7)
- ❌ Associadas e consentimentos (onda 4) e painel da rede (onda 5)

## UX Targets

- Cabe em 1280px sem scroll horizontal
- A lista vem deferida (`Inertia::defer`); a ficha carrega só quando aberta (`Inertia::optional`)

## Refs

- Padrão de Tela: PT-01 Lista (DataTable + PageHeader + filtros)
- Constituição UI v2: UI-0013
- ADR 0451 (área de membros da rede Uniafibro) · ADR 0093 (multi-tenant)
