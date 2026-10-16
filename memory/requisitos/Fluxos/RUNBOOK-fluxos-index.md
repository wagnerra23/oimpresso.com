---
id: requisitos-fluxos-runbook-fluxos-index
title: "Fluxos — Runbook da tela Sistema › Fluxos e regras"
module: Fluxos
tela: Fluxos/Index
owner: W
status: rascunho
last_validated: "2026-10-16"
preconditions:
  - "Catálogo App\\Domain\\Fsm\\Modelos\\CatalogoModelos no branch base (PR #9489)"
  - "Permissões fluxos.ver e fluxos.configurar (migration 2026_10_16_150000)"
steps:
  - "FluxosController@index formata o catálogo (modelos, processos, encaixe porte × atividade)"
  - "Rota GET /fluxos (fluxos.index) no grupo web do ERP; item no sidebar em SISTEMA"
  - "Page Fluxos/Index.tsx com PageHeader e 4 abas (?aba=modelos|processos|empresa|casos)"
related_adrs:
  - 0129-state-machine-canonica-fsm-rbac
  - 0093-multi-tenant-isolation-tier-0
---

# RUNBOOK — Sistema › Fluxos e regras

> **Tipo:** runbook reproduzível (F1 do MWART — ADR 0104). Tela **nova**, sem Blade a migrar.
> **Fonte visual:** `prototipo-ui/cowork/Wagner/fluxos-page.jsx` (rota do protótipo `fluxos`).
> **Decisão de produto:** ADR 0435 "Fluxos por modelo (porte × atividade)" — PR #9488.

A tela mostra os 7 modelos de fluxo do catálogo, ordenados pelo encaixe com o porte e a atividade
escolhidos, e (nos PRs seguintes) os processos, os fluxos em uso na empresa e os casos de uso.

## Entrega em PRs empilhados

| PR | Conteúdo |
|---|---|
| bundle | `resources/css/cowork-fluxos-bundle.css` — CSS do protótipo copiado inteiro (#9493) |
| 1 | permissão, rota, sidebar, página com as 4 abas, aba Modelos só leitura |
| 2 | aba Processos (estados, transições, roteiro) |
| 3 | aba Fluxos da empresa + "Usar modelo" (ação do backend, `fluxos.configurar`) |
| 4 | aba Casos de uso (UC-FLX com fontes e estado) |
| 5 | assistente de configuração (IA do ERP, com queda para a regra de encaixe) |

## Passos

1. O encaixe (0–4), o rótulo (Recomendado/Serve/Parcial) e a frase do card vêm do controller,
   calculados por `CatalogoModelos::encaixe()` / `rotuloEncaixe()`. A tela só ordena e mostra.
2. O perfil porte × atividade é escolha local da tela neste PR (padrão Pequena · Comunicação
   visual, como no protótipo). Gravar o perfil da empresa entra junto com "Usar modelo" (PR 3).
3. Multi-tenant: o catálogo é código, igual para todos os negócios; nada do negócio é lido neste PR.

## Verificação

- `tests/Feature/Domain/Fsm/FluxosTelaContratoTest.php` (lane domain-pest, MySQL).
- Visual contra o protótipo servido estático com `localStorage['oimpresso.route']='fluxos'`.
