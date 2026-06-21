---
paths:
  - "Modules/**/Http/Middleware/Verify*.php"
  - "app/Http/Middleware/TrustProxies.php"
---

# Mudar auth de fronteira de integração (webhook/callback externo)

> Origem: incidente 2026-06-16 (#2726). Um hardening de segurança removeu a ÚNICA
> auth em uso (IP-whitelist) assumindo um substituto (HMAC) que **nunca esteve
> ativo** → recebimento de WhatsApp morto 3 dias. A análise da vulnerabilidade
> estava certa; o erro foi "confirmar" o substituto lendo um **comentário de
> config** (aspiracional/inerte), não observando o emissor real.

**ANTES de remover, trocar ou endurecer o mecanismo de auth de um webhook/callback
externo, responda no corpo do PR (checklist bloqueante — pareia com `## Infra Contract`):**

1. **Qual auth autentica HOJE em produção?** Por **evidência observada** (log de uma
   entrega real, `docker logs` do emissor, header capturado) — NUNCA por doc/comentário.
   Comentários mentem: `WUZAPI_GLOBAL_HMAC_KEY` estava setada mas o binário a ignora.
2. **O substituto já foi observado funcionando com o PRODUTOR REAL?** Um contract-test
   com o payload+headers que o emissor manda de verdade (não credencial auto-forjada)
   passa VERDE? Ver `WhatsmeowWebhookAuthTest` (caso "contrato REAL do daemon").
3. **Ordem de deploy + paridade de segredos:** app e emissor têm o mesmo segredo? Quem
   deploya primeiro? `.env` de prod tem a chave? (config cacheado + opcache → rebuildar.)
4. **Qual monitor detecta `inbound==0` pós-mudança e em quanto tempo?** Sentinela de
   fluxo `whatsapp_inbound_flow` (`jana:health-check`, horária) cobre este canal?

> Não derrube a viga antes de confirmar que a nova está segurando — com o **cliente
> real**, não com a doc. Os 5 webhooks vivem em `Modules/Whatsapp/Http/Middleware/Verify*.php`.
> `TrustProxies='*'` torna `$request->ip()` spoofável: auth por IP em PHP é proibida
> (use segredo na URL/HMAC verificado, ou allowlist no edge Traefik/firewall). Ver
> ADR 0093 (Tier 0) · `memory/proibicoes.md`.
