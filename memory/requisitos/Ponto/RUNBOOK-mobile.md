---
owner: W
last_validated: "2026-09-29"
slug: ponto-runbook-mobile
title: "Ponto — Runbook do REP-P no celular (/ponto/mobile · Mobile/Index)"
type: runbook
module: Ponto
tela: Ponto/Mobile/Index
status: ativo
date: 2026-09-29
related_adrs:
  - 0383-ponto-interno-nao-coleta-biometria
  - 0419-ponto-rep-p-escopo-ratificado-w10
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — REP-P no celular (`Ponto/Mobile/Index`)

> **F1 PLAN da tela nova** (thread 06 do playbook Ponto). Não é migração de Blade: o Blade nunca
> teve REP-P. A lei é a [ADR 0383](../../decisions/0383-ponto-interno-nao-coleta-biometria.md)
> (sem selfie, sem biometria); onde este RUNBOOK e a ADR divergirem, **a ADR manda**.

## 1. O que a tela faz

O colaborador abre `/ponto/mobile` no próprio celular e **bate o ponto**: escolhe o tipo
(entrada, saída almoço, retorno, saída) e envia com a localização do aparelho. Vê as marcações
de hoje com o NSR que o servidor devolveu. Em **Meu espelho** vê o mês corrente (os mesmos
builders do Espelho/Show); em **Justificar** envia a intercorrência que nasce `PENDENTE`.

| Rota | Método | Quem |
|---|---|---|
| `GET /ponto/mobile` | `Api\MobileMarcacaoController@tela` | **qualquer usuário logado** — sem `ponto.access` ([W] 2026-09-29); sem cadastro de ponto a tela fica vazia e as ações dão 403 |
| `POST /ponto/mobile/marcar` | `@registrar` (o MESMO da API) | idem |
| `GET /ponto/mobile/marcacoes/hoje` | `@marcacoesHoje` (o MESMO da API) | idem |
| `POST /ponto/mobile/intercorrencias` | `@criarIntercorrencia` (o MESMO da API) — cria e submete | idem |

As ações são os métodos JSON de `/ponto/api` (Passport) servidos também sob sessão web: o app
não tem `CreateFreshApiToken`, então uma tela Inertia não alcança `auth:api`.

O cabeçalho de abas do Ponto só aparece pra quem tem o módulo (`CheckPontoAccess::permite`) — para o
colaborador sem `ponto.access`, cada aba seria 403.

## 2. Domínio (1a+1b da thread, em `main` pelo #8130)

| Peça | Regra |
|---|---|
| colaborador | SEMPRE o do usuário autenticado (`business_id` + `user_id` + `controla_ponto`); nada do body escolhe |
| `MobileMarcacaoService` | GPS > 500 m **recusa** (422) · relógio > 30 s **recusa** · geofence **sinaliza** (`revisar`) |
| `MarcacaoService` | append-only, NSR + hash — a tela nunca gera NSR |

## 3. O que a tela NÃO faz

- **Não coleta imagem nem biometria** (ADR 0383) — nenhuma câmera.
- **Não oferece "bater mesmo assim"** com GPS fraco (W5): o botão fica desabilitado com
  *"Sinal de GPS fraco — aproxime-se de área aberta"*.
- **Não corrige marcação**: correção é intercorrência (sub-tela Justificar), que o gestor decide.
- **Não mostra a fila do gestor**: é filtro na tela viva de Aprovações (passo 3 da thread).

## 4. Diferenças conscientes contra o protótipo (`ponto-mobile.jsx`)

| Protótipo | Tela | Por quê |
|---|---|---|
| moldura `AndroidDevice` + "Simular condição de campo" | fora | andaime da demonstração no desktop — em produção o aparelho É o celular |
| status "dentro/fora da área" antes de marcar | só a precisão do GPS antes; "fora da área" depois, na resposta | a config de geofence é do servidor — a tela não sabe e não inventa |
| `ValidacaoMobile` ao lado | fora | fila do gestor = filtro em Aprovações (passo 3) |
| "O oficial, assinado, sai no fechamento" | "O oficial sai no fechamento da competência" | D2 da ata: a palavra "assinada" sai (ADR 0413) |

## 5. Como validar

- Pest: `Wave28MobileMarcacaoTest` (API) + `RepPMobileContratoTest` (tela), lane `ponto-pest`.
- Vitest: `tests/js/ponto-subnav-abas.test.tsx` — a aba "REP-P (celular)" é a 10ª, igual ao protótipo.
- Smoke pós-deploy em **biz=1**, num celular: abrir `/ponto/mobile`, permitir o GPS, conferir a
  precisão exibida. **Não** bater ponto real no smoke — marcação é imutável por lei.
