---
owner: W
last_validated: "2026-10-01"
slug: ponto-runbook-publico
title: "Ponto — Runbook das páginas públicas do app de ponto (privacidade e exclusão de dados)"
type: runbook
module: Ponto
tela: Ponto/Publico/Privacidade
status: ativo
date: 2026-10-01
related_adrs:
  - 0383-ponto-interno-nao-coleta-biometria
  - 0419-ponto-rep-p-escopo-ratificado-w10
---

# RUNBOOK — páginas públicas do app de ponto (`Ponto/Publico/*`)

> F1 PLAN de tela nova (não migra Blade: o repo não tinha página de privacidade — medido em
> 2026-10-01, `git grep -i privacidade` em `routes/`, `resources/views/` e rotas de módulo só
> achou a frase solta do rodapé de `Site/Login` e `Site/Register`, sem link).

## 1. O que é

O app de ponto (REP-P, `/ponto/mobile`, embrulhado em Capacitor) vai para o Google Play e a App
Store. As duas lojas exigem **URL pública e estável** com a política de privacidade, e o Google Play
exige também uma página para **pedir exclusão de conta e dados**.

| Rota | Nome | Tela |
|---|---|---|
| `GET /privacidade/ponto` | `ponto.publico.privacidade` | `Ponto/Publico/Privacidade` |

Pilha: `web` + `throttle:60,1`, **sem** `auth`. `PublicoController` só renderiza — não lê banco,
sessão nem tenant.

## 2. De onde vem cada afirmação do texto

| Afirmação | Fonte |
|---|---|
| horário definido pelo servidor | `MobileMarcacaoService::registrar` (`momento => now()`) |
| GPS só no momento da marcação | `lat`/`lng` do payload da marcação; sem rastreio em segundo plano |
| identificador do aparelho | `dispositivo_id = mobile:<device_uuid>` |
| sem biometria nem imagem | [ADR 0383](../../decisions/0383-ponto-interno-nao-coleta-biometria.md) |
| marcação não se apaga | Portaria MTP 671/2021 + trigger append-only de `ponto_marcacoes` |
| controlador × operador | [lgpd-mapa-tratamento](../../reference/lgpd-mapa-tratamento.md) Op-01 |

Se o REP-P passar a coletar outro dado, **o texto muda no mesmo PR** — política que omite coleta é
pior que ausência.

## 3. Pendências antes de submeter às lojas (não são do código)

- [ ] **Revisão jurídica da Eliana [E]** — o texto é rascunho técnico; prazos de retenção e base legal são dela.
- [ ] **Alias `lgpd@oimpresso.com.br` existir e ser lido** — o mapa LGPD o marca "a configurar". A página publica esse e-mail.
- [ ] **Razão social e CNPJ do operador** — o mapa LGPD diz "CNPJ a registrar"; a página cita só "oimpresso" até [W] informar.

## 4. Como validar

- Pest: `PrivacidadePublicaContratoTest`, lane `ponto-pest`.
- Smoke pós-deploy: `curl -sv https://oimpresso.com/privacidade/ponto 2>&1 | grep '^< HTTP'` → `200`, sem redirect para `/login`.
