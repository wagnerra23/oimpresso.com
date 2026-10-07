---
slug: 0429-log-de-falhas-glitchtip-self-host-ct100
number: 429
title: "Log de falhas do app e do ERP no GlitchTip self-host no CT 100"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-07"
module: infra
tags: [infra, observabilidade, apm, app-mobile, lgpd, ct100]
supersedes: []
related:
  - 0062-separacao-runtime-hostinger-ct100
  - 0132-langfuse-self-host-ct100
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0429 — Log de falhas do app e do ERP no GlitchTip self-host no CT 100

## Contexto

A versão 7 do app das lojas (`wagnerra23/oimpresso-app`) travava logo depois do login no
Android 12. A causa só apareceu depois de horas de investigação, porque o app não manda
relatório de falha para lugar nenhum. A sessão do app planejou ligar o Sentry, mas ainda não
havia conta, e o [W] precisava decidir onde esses logs ficam.

O [W], no chat em 2026-10-07, textual: *"eu não entendo onde deve ficar os logs, pode escolher
eu gosto de segurança e manter domínio sobre as informações"*. E depois: *"faça o serviço
completo"*.

O tema já tinha dono: a US-INFRA-003 (APM full-stack) do SPEC de Infra listava *"avaliar
GlitchTip OSS vs Sentry SaaS"* e *"subir GlitchTip no CT 100"* desde antes desta decisão. O
ERP já carrega o SDK `sentry/sentry-laravel` (`config/sentry.php`), sem DSN apontado.

## Decisão

1. **Os logs de falha ficam no GlitchTip, instalado no CT 100**, em
   `https://apm.oimpresso.com`. Não usamos Sentry SaaS nem outro serviço de terceiro.
2. **Os SDKs são os do Sentry.** O GlitchTip fala o mesmo protocolo: o app usa
   `@sentry/capacitor` e o ERP usa o `sentry/sentry-laravel` que já está no `composer.json`.
   Trocar de servidor no futuro é trocar o DSN.
3. **Acesso fechado.** Cadastro aberto e criação de organização ficam desligados; só entra
   quem o admin criar.
4. **Retenção de 90 dias.** Relatório de falha não é arquivo histórico.
5. **Sem dado pessoal no relatório:** `sendDefaultPii: false`, sem corpo de requisição, sem
   replay de tela. A política de privacidade das lojas declara a coleta de diagnóstico antes
   do app mandar o primeiro relatório.
6. Configuração versionada em [`docker/glitchtip/`](../../docker/glitchtip/README.md), no
   mesmo padrão do Langfuse ([ADR 0132](0132-langfuse-self-host-ct100.md)).

## Por que GlitchTip e não as alternativas

| Opção | Por que não |
|---|---|
| Sentry SaaS | Os relatórios saem do nosso controle e ficam em servidor de terceiro nos EUA. Contraria o pedido do [W]. |
| Sentry self-host | Exige ~16 GB de RAM e dezenas de containers (Kafka, ClickHouse, Snuba). Desproporcional ao volume do app. |
| Firebase Crashlytics | O app saiu do Firebase pela ADR 0428 (proposta, PR #8962). |
| Endpoint próprio no ERP | Reinventa agrupamento de erros, stack trace e alertas; e roda no Hostinger, que não deve receber essa carga. |

O GlitchTip 6 roda em 3 containers: aplicação (web + worker num só, `SERVER_ROLE=all_in_one`),
Postgres e Valkey. Medido na instalação: cerca de 230 MB de RAM no total.

## Consequências

- Mais um serviço no CT 100 para manter (atualização por PR, versão fixada `6.2.6`).
- **Limite conhecido:** o GlitchTip tem suporte fraco a falhas em código nativo C/C++ (sem
  simbolização de minidump). O app é Capacitor (JavaScript + Java/Kotlin), então erros de JS e
  exceções Java chegam inteiros; só uma falha em biblioteca nativa chegaria pobre.
- Sem SMTP configurado, não há convite por e-mail nem "esqueci a senha". Usuários são criados
  pelo admin no terminal até configurarmos o `EMAIL_URL`.
- A ligação do ERP (`SENTRY_LARAVEL_DSN` no Hostinger) é passo separado da US-INFRA-003, com
  projeto próprio no GlitchTip.

## Estado na decisão (2026-10-07)

- `apm.oimpresso.com` com DNS (A → 177.74.67.30, pela API da Hostinger) e certificado válido.
- `/_health/` responde 200. Evento de teste aceito (200) e registrado como problema no projeto
  `oimpresso-app`; chave errada recusada (401).
- Organização `oimpresso`, projeto `oimpresso-app`.
