---
title: "RUNBOOK — Teste de carga com k6 (staging CT 100)"
owner: W
status: rascunho
last_validated: "2026-11-15"
---

# RUNBOOK — Teste de carga com k6 (staging CT 100)

> **O que é:** como rodar os cenários de carga em `tests/load/*.js` contra o **staging** (CT 100),
> ler o resultado e parar antes de causar estrago. Nasceu do pedido [W] de 2026-11-15: medir
> capacidade antes da Uniafibro ir para produção, com associadas abrindo o portal ao mesmo tempo.
>
> **Estado:** scripts prontos e validados por `k6 inspect` (compilam, a trava de produção funciona).
> **Ainda não rodaram contra o staging** — aguardam janela combinada com [W].

## 0. Regras que não se negociam

1. **Só staging.** `tests/load/lib/common.js` recusa `oimpresso.com` e `uniafibro.com.br` antes de
   qualquer requisição. Não existe flag para desligar. Carga em produção é DoS auto-infligido.
2. **Só com janela combinada.** O CT 100 também roda Vaultwarden e o MCP (`mcp.oimpresso.com`).
   Janela acordada com [W]: noite depois das 23h (BRT) ou fim de semana. Avise o time antes.
3. **Um cenário por vez**, começando sempre em `CARGA=calibrar` (~10% da carga).
4. **Nunca biz=4 (ROTA LIVRE).** Dados de teste vêm do tenant fictício **98** (ADR 0358) ou do biz=1.
5. **Ponto grava para sempre.** `ponto_marcacoes` é append-only por lei (Portaria 671/2021) — o
   que o cenário de Ponto gravar não sai mais, nem no staging. Só colaboradores de teste do 98.
6. **Jana gasta token de verdade.** Orçamento autorizado por [W] em 2026-11-15 para **50 chamadas
   por execução** (10 conversas × 5 mensagens). Mais que isso, combine antes.

## 1. Cenários

| Arquivo | Prioridade | Carga | Meta |
|---|---|---|---|
| `portal-associada.js` | 🔴 | rajada de 100 acessos em 1 min; rampa 0→500 em 5 min, 5 min no pico | P95 < 800 ms · falha < 1% |
| `site-uniafibro.js` | 🔴 | 50 visitantes/min por 5 min; pico de 300/min por 5 min | P95 < 1,2 s · zero 5xx |
| `login-financeiro.js` | 🟡 | 20 usuários simultâneos, login + Visão Unificada | P95 < 600 ms · zero 500 |
| `jana-chat.js` | 🟡 | 10 conversas × 5 mensagens | P95 < 3 s por mensagem |
| `ponto-marcacao.js` | 🟢 | 50 colaboradores batendo ENTRADA juntos | P95 < 400 ms · 100% gravadas |

**Desvios em relação ao pedido original — leia antes de interpretar o número:**

- **Portal da Associada não existe ainda** (US-ASSOC-005, onda 6 da ADR 0451). O script mede o
  **portal do paciente do Fisio** (`/fisio/p/{token}` + `POST pin`), que usa o mesmo padrão de link
  assinado + PIN. Quando a onda 6 mergear, troque `PORTAL_PREFIXO` (e o campo do PIN se mudar).
- **Site Uniafibro tem só a home hoje.** `/direitos`, as páginas e o PDF chegam com os PRs #10908+.
  O script já aceita `PAGINAS` e `PDF`; até lá, roda só a home. O staging responde pelo site
  porque o script manda `Host: uniafibro.com.br` (o `Route::domain` casa pelo Host) — **conferir
  numa requisição única antes da primeira janela** que o Caddy do staging repassa esse Host.
- **Ponto:** a rota é `POST /ponto/api/marcar` (Bearer Passport, a mesma do app). Resposta 201 com
  `sucesso: true` e `marcacao.nsr`.

## 2. Throttle por IP — o que ele faz com o número

As rotas públicas (portal do Fisio, site Uniafibro) têm `throttle:60,1`: **60 requisições por
minuto por IP**. De uma máquina só, toda carga acima disso recebe **429**. Os scripts:

- **não** contam 429 como falha (é a proteção funcionando);
- contam à parte em `throttle_429` — se esse número for alto, o teste mediu o throttle, não a
  capacidade.

Para medir capacidade de verdade há duas saídas, ambas decisão [W]: rodar distribuído (vários
IPs) ou liberar o IP do runner no throttle **do staging**. Fica registrado como achado: associadas
atrás do mesmo IP (rede de uma empresa, NAT da operadora) dividem os mesmos 60/min.

## 3. Pré-voo (antes de cada janela)

1. [W] confirmou a janela e o time foi avisado.
2. Staging responde: `curl -s -o /dev/null -w "%{http_code}\n" https://staging.oimpresso.com/login` → `200`.
3. CT 100 sem sinal de carga prévia: `https://mcp.oimpresso.com` responde. Se não responder, **não
   rode** — e lembre que o RUNBOOK-acesso-ct100 aponta o cabo de rede como 1ª hipótese de CT 100 mudo.
4. **Tailscale do CT 100 válido** (`tailscale ping ct100-mcp`). Em 2026-11-15 a chave estava
   **vencida** — sem ela o teste mede só o lado do cliente (latência e erro), sem CPU, memória,
   MySQL ou consulta lenta. Renovar antes da janela, se quiser os gargalos do servidor.
5. Dados de teste prontos (§4).

## 4. Dados de teste (variáveis de ambiente)

| Variável | Cenário | Conteúdo |
|---|---|---|
| `BASE_URL` | todos | padrão `https://staging.oimpresso.com` |
| `CARGA` | todos | `calibrar` (~10%) ou `cheia` |
| `PORTAL_TOKENS`, `PORTAL_PIN` | portal | tokens de portal de pacientes de teste do 98, separados por vírgula; o PIN deles |
| `PAGINAS`, `PDF` | site | caminhos separados por vírgula; vazio = só a home |
| `LOGIN_USUARIOS` | financeiro, jana | `usuario:senha,usuario:senha` do seed de teste (Jana exige `jana.chat`) |
| `PONTO_TOKENS` | ponto | 1 token Passport por colaborador de teste (50 para o cenário cheio) |

No GitHub Actions viram os secrets `LOAD_PORTAL_TOKENS`, `LOAD_PORTAL_PIN`,
`LOAD_LOGIN_USUARIOS`, `LOAD_PONTO_TOKENS` e as variables `LOAD_SITE_PAGINAS`, `LOAD_SITE_PDF`.
**Nenhum valor desses entra em git.**

## 5. Como rodar

**Pela máquina local** (k6 ≥ 2.4; no Windows sem admin, o zip portátil da release oficial basta):

```bash
k6 run -e CARGA=calibrar -e PORTAL_TOKENS=... -e PORTAL_PIN=... tests/load/portal-associada.js
```

Passe as variáveis com `-e` — `k6 inspect` não lê o ambiente do shell.

**Pelo GitHub Actions:** Actions → *Load test (k6, manual)* → escolher cenário e carga. O resumo
fica no artefato `k6-<cenário>-<carga>`. O workflow só tem `workflow_dispatch` e `concurrency`
de um por vez.

**Validar sem gerar tráfego:** `k6 inspect --execution-requirements -e ... tests/load/<arquivo>.js`.

## 6. Abortar

Interrompa (Ctrl+C no local, *Cancel* no Actions) se:

- aparecer qualquer 5xx em sequência no calibrar;
- `mcp.oimpresso.com` ou o Vaultwarden pararem de responder durante a execução;
- `throttle_429` dominar o tráfego (o resto do teste não mede mais nada).

## 7. Ler o resultado

- `http_req_duration{expected_response:true}` — latência só das respostas boas (P50/P90/P95/P99
  com `--summary-trend-stats="avg,min,med,p(90),p(95),p(99),max"`).
- `http_req_failed` — falhas reais (429 excluído nos cenários públicos).
- `throttle_429`, `erros_5xx`, `marcacoes_gravadas`/`marcacoes_recusadas`, `jana_mensagem_ms`.
- **Tokens da Jana:** com Tailscale, somar `tokens_in` e `tokens_out` de `jana_mensagens` das
  conversas com título `load-test VU %` criadas na janela.

## 8. Limite do que isto prova

O staging roda no **CT 100** (FrankenPHP). A produção do `oimpresso.com` e do `uniafibro.com.br`
roda no **Hostinger** (hospedagem compartilhada, LiteSpeed) — outra máquina (ADR 0062). O teste
mostra gargalo de **código** (consulta, cache, N+1) e o comportamento do throttle; **não** prova
quantas associadas a infra de produção aguenta.
