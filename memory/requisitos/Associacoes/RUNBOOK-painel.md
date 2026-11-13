---
title: "RUNBOOK — Associacoes/Painel (painel da rede, US-ASSOC-004)"
module: Associacoes
tela: Associacoes/Painel
owner: W
status: ativo
last_validated: "2026-11-13"
preconditions:
  - "Pacote associacoes_module na empresa + permissão associacoes.access no papel"
  - "Migrations do Modules/Associacoes aplicadas (onda 2)"
steps:
  - "Abrir /associacoes pelo menu Associações → Painel"
  - "Conferir os 4 KPIs e as duas listas (associadas por associação, pendências)"
  - "Conferir o alcance: diretoria vê a rede, representante vê só a associação dele"
related_adrs:
  - 0451-associacoes-rede-uniafibro-area-de-membros
  - 0093-multi-tenant-isolation-tier-0
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
---

# RUNBOOK — Associacoes/Painel

Tela nova (não é migração de Blade): nasce do PT-04 Dashboard por `criar-tela.mjs`. Não há
protótipo Cowork da rede; a forma vem do arquétipo PT-04 e do Design System
(`related_prototype: n/a` no charter).

## O que a tela mostra

| Bloco | Prop (todas `Inertia::defer`) | Fonte |
|---|---|---|
| Associações por situação, cidades, associadas ativas, nº de pendências | `kpis` | `PainelDaRedeController::kpis` |
| Associadas ativas por associação (nome, cidade, total) | `associadasPorAssociacao` | `PainelDaRedeController::associadasPorAssociacao` |
| Pendências de documentação | `pendencias` | `Services/PendenciasDaRede::calcular` |

Só números e nomes de associação. Nenhum dado pessoal de associada sai do controller (ADR 0451 D5).

## Regras de pendência (`PendenciasDaRede`)

Por associação que não está desligada; documento removido (`removido_em`) não conta:

- **Sem estatuto** — nenhum `estatuto` na lista.
- **Sem ata da diretoria** — nenhuma `ata_diretoria` na lista.
- **Certidão vencida** — tem `certidao` e nenhuma com validade vazia ou ≥ hoje.
- **Diretoria mudou** — `diretoria_mudou_em` preenchido e nenhuma ata da diretoria enviada
  nesse dia ou depois. No prazo até `config('associacoes.prazo_ata_diretoria_dias')` (30) dias;
  depois, **atrasada** (D7: avisa, não bloqueia).

A onda 3 escreve a mesma regra para a tela de associações; unificar num dono só depois do merge.

## Alcance (D4)

`AssociacoesAcesso`: com `associacoes.rede` (ou superadmin), a rede inteira; sem ela, só as
associações em `assoc_representantes` do usuário. Toda consulta do painel passa por
`restringir()` (contagens) ou `associacoesVisiveis()` (pendências), além do `business_id`.

## Testes

`Modules/Associacoes/Tests/Feature/AssociacoesPainelContratoTest.php` (lane `verticais-pest`,
MySQL): números não vazam entre empresas (98 × 99) nem entre associações para o representante;
pendência da diretoria fica atrasada depois do prazo.

## Smoke manual

Empresa com o pacote, usuário com `associacoes.access` e sem `associacoes.rede`, vinculado a uma
associação: o painel mostra só aquela associação. Com `associacoes.rede`: mostra todas.
