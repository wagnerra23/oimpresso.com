---
page: /ponto/mobile
component: resources/js/Pages/Ponto/Mobile/Index.tsx
owner: wagner
status: draft
parent_module: Ponto
related_prototype: prototipo-ui/cowork/Wagner/ponto-mobile.jsx
runbook: memory/requisitos/Ponto/RUNBOOK-mobile.md
alcance:
  rota: /ponto/mobile
  rota_nome: ponto.mobile        # name() da rota — é o que o guard procura
  permission: ponto.access      # grupo web do Ponto (CheckPontoAccess)
  menu_hook: Modules/Ponto/Http/Controllers/DataController.php::modifyAdminMenu  # ghost `mobile` = aba "REP-P (celular)"
  pacote: ponto_module              # superadmin_package
tier: B
charter_version: 1
---

# Page Charter — Ponto/Mobile/Index (REP-P no celular)

> Nascida do Padrão de Tela **PT-02 Form/Drawer** via `criar-tela.mjs` (UI-0013) — a batida é um
> formulário de um campo (o tipo) enviado com a localização do aparelho. Thread 06 do playbook do
> Ponto; escopo ratificado por [W] em 2026-09-29 (W10, ADR 0419). Casos em
> [`Index.casos.md`](Index.casos.md); plano em [RUNBOOK-mobile](../../../../../memory/requisitos/Ponto/RUNBOOK-mobile.md).

## Mission

O colaborador que trabalha fora da empresa registra a própria marcação pelo celular, com a
mesma imutabilidade do relógio do balcão (Portaria MTP 671/2021 reconhece o REP-P).

## Goals — Features (faz)

- Bater ponto: escolhe o tipo (Entrada · Saída almoço · Retorno almoço · Saída) e envia com a
  localização real do aparelho; o NSR e o hash vêm do servidor.
- Mostra as marcações de hoje com NSR, e "fora da área" quando o servidor sinalizou geofence.
- Meu espelho: totais e dia a dia do mês corrente, com os builders do Espelho/Show.
- Justificar: envia intercorrência que nasce `PENDENTE` na fila de Aprovações.
- Aba "REP-P (celular)" no header de módulo, 10ª, igual ao protótipo (`ponto-page.jsx` ABAS).
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ **Selfie, foto ou qualquer biometria** — [W] 2026-08-27, ADR 0383 (LGPD Art. 5º II + Art. 11).
- ❌ **"Bater mesmo assim" com GPS fraco** — [W] W5: sinal ruim é recusa, o botão fica desabilitado.
- ❌ Gerar ou exibir NSR calculado no cliente — só o que o servidor devolveu.
- ❌ Fila do gestor nesta tela — é filtro na tela viva de Aprovações (thread 06, passo 3).

## UX Targets

- Alvos de toque ≥ 44 px (persona Técnico Repair, celular).
- Cabe em 1280px sem scroll horizontal no desktop do RH.

## Refs

- Padrão de Tela: PT-02 Form/Drawer (useForm + `<form>`)
- ADR 0383 (sem biometria) · ADR 0419 (W10) · Constituição UI v2: UI-0013
