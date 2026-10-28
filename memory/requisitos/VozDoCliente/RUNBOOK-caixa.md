---
slug: vozdocliente-runbook-caixa
title: "Voz do Cliente — Runbook da Caixa"
type: runbook
module: VozDoCliente
tela: VozDoCliente/Caixa
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0105-cliente-como-sinal-guiar-sem-mandar'
---

# RUNBOOK — Voz do Cliente · Caixa (`/voz-do-cliente`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `modulos-faltantes/playbook/03` (executa também `telas-soltas/playbook/05`)
> **Fonte de design:** `prototipo-ui/cowork/Wagner/voz-do-cliente-page.jsx` (`VozDoClientePage`), versão de 2026-10-07. Sem alvo medido ainda.
> **Fronteira desta onda:** a caixa de **leitura** (lista, contagens, filtro por situação). A **triagem** (escrita) é a onda seguinte — ver §10.

## Estado final esperado (desta onda)

`GET /voz-do-cliente` (`SinalController::index`) responde Inertia `VozDoCliente/Caixa` com:

> **Cutover F5 em 2026-10-27.** Até essa data a rota era dual: Blade `vozdocliente::caixa` com a flag
> `useV2VozDoClienteCaixa` desligada ou com `?legacy=1`, Page com ela ligada. A flag valeu `true` para todas as
> empresas de 2026-10-13 a 2026-10-27; no F5 a Blade, a flag e o `?legacy=1` saíram.

- `contagem` — total, pendentes, triados, fechados e gravidade alta, do negócio da sessão (eager, barato);
- `filtro` — situação pedida em `?situacao=` (`pending|triaged|closed`, ou ausente = todos);
- `sinais` — paginado, adiado (`Inertia::defer`), pendente primeiro e mais novo primeiro, como a Blade.

`?legacy=1` não tem mais efeito (UC-VOZ-02).

## 1. Objetivo

Trocar a tabela AdminLTE por uma tela React no padrão PT-01 (lista read-mostly), sem mudar quem enxerga, o que
enxerga, nem a ordem.

## 2. Pré-condições

- `vozdocliente.triar` (ou `superadmin`) — decisão `PERM-VOZ`; a mesma checagem que a Blade já fazia.
- Módulo `vozdocliente_module` na assinatura, para a entry aparecer na sidebar (`DataController`).

## 3. Passo-a-passo

1. A checagem de permissão roda **antes** de qualquer consulta (quem não pode ver não lê nada).
2. O `business_id` vem da sessão; a consulta tem `where` explícito **e** o global scope de `Sinal`.
3. A Page mostra cabeçalho, 4 KPIs, os 4 chips de situação e a tabela; a troca de chip recarrega só `sinais` e
   `filtro`.

## 4. Tokens CSS

Só tokens do DS (Badge `warning|info|neutral|danger`, trilha `urgent`/`archived` do `DataTable`).

## 5. Estados visuais

Cheia · filtrada-vazia ("Nada nesta situação.") · vazia ("Nenhum relato ainda.", sem KPI nem filtro) ·
carregando (skeleton do `Deferred`). Sem permissão = 403 do servidor.

## 6. Responsividade

KPIs em 2 colunas abaixo de `lg`; a tabela rola na horizontal e a coluna "O que disse" não encolhe abaixo de 320px.

## 7. Atalhos

Nenhum.

## 8. Component contract

Seções `data-contract`: `cabecalho`, `kpis`, `filtros`, `lista` — as do contrato proposto
`cowork-inbox/modulos-faltantes/voz-do-cliente.contract.json`. A seção `triagem` entra com a onda da triagem.

## 9. Testes

`tests/Feature/VozDoCliente/CaixaContratoTest.php`, lane `acessos-pest.yml` (MySQL). O teste do módulo
(`SinalCrossTenantTest`) continua na lista sqlite.

## 10. Fora desta onda

- **Triagem** (Virar US / Fechar sem ação): escreve `status`, `triado_para_us`, `triado_por`, `triado_em`.
  O "motivo do fechamento" do protótipo **não tem coluna** em `voz_sinais` — precisa de migration, e por
  isso a triagem é PR próprio.
- ~~Ligar a flag em produção e remover `caixa.blade.php`: cutover (F5).~~ Feito em 2026-10-27.
- Contrato de forma em `governance/design/contracts/voz-do-cliente.contract.json`: depois do alvo medido.
