---
id: requisitos-fiscal-telas-cert-health-check-casos
casos: Avisar que o certificado A1 está vencendo (fluxo SEM tela React) · comando artisan agendado
irmaos: ../SPEC.md (US-FISCAL-022) · ../../../../resources/js/Pages/Fiscal/Config.casos.md (a tela que MOSTRA a validade)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-01"
last_run_ci: "0 UC executado nesta sessão — CT 100 inacessível (tailscale 502). Veredito pendente da lane."
related_us: [US-FISCAL-022]
---

# Casos de Uso & Aceite — Health-check do certificado A1

> **2ª casa do contrato** (`memory/requisitos/<Mod>/_telas/`): este fluxo **não tem tela React** —
> é comando artisan agendado. O `casos-coverage-guard` varre só `Pages/**`, então um fluxo sem tela
> não teria onde ancorar contrato; a porta `requisitos-status.mjs` lê esta casa também.
>
> **Por que nasceu em 2026-09-01:** a `US-FISCAL-022` estava `status: todo` no SPEC enquanto o
> comando, o agendamento e **seis** testes já existiam. Corrigido o status, o painel derivado passou
> a cobrar — corretamente — o contrato de uma US entregue. Este arquivo é esse contrato. **Nenhuma
> asserção de teste mudou:** os `it()` só passaram a citar o UC que já defendiam.
>
> **Sem CU no SDD, de propósito:** o §6 do [SDD](../SDD-cockpit-fiscal-v1.0.md) cobre a superfície de
> **tela** do cockpit; o `CU-FISC-06` é a leitura da validade em `/fiscal/config` (contrato de
> `Config.casos.md`), não o alarme assíncrono. Inventar um CU aqui para "fechar bonito" seria
> anti-padrão inventado com cara de canon. A âncora é a US.
>
> ⚖️ **Força do veredito:** lane `Pest Fiscal` — **ADVISORY** (não está em
> `governance/required-checks-baseline.json`): reprova fica visível e **não bloqueia merge**.
> Os casos exigem `nfe_certificados` + `mcp_alertas_eventos` com schema MySQL canon; em SQLite pulam.

## Rastreabilidade

| UC | O que defende | Peso | Âncora (US) | Teste |
|---|---|---|---|---|
| UC-FCERT-01 | o comando existe e é invocável | must | US-FISCAL-022 | `CertHealthCheckCommandTest` |
| UC-FCERT-02 | cert vencendo em ≤30d vira alerta do business dono | must `[T0]` | US-FISCAL-022 | `CertHealthCheckCommandTest` |
| UC-FCERT-03 | cert longe do vencimento não vira ruído | must | US-FISCAL-022 | `CertHealthCheckCommandTest` |
| UC-FCERT-04 | a severidade acompanha a urgência | should | US-FISCAL-022 | `CertHealthCheckCommandTest` |
| UC-FCERT-05 | rodar todo dia não empilha alerta repetido | must | US-FISCAL-022 | `CertHealthCheckCommandTest` |
| UC-FCERT-06 | o ensaio não escreve nada | must | US-FISCAL-022 | `CertHealthCheckCommandTest` |

---

## UC-FCERT-01 · O comando existe e é invocável
- **Persona:** [W] / operador conferindo o agendamento.
- **Aceite:** Dado o app carregado · Quando lista os comandos disponíveis · Então
  `fiscal:cert-health-check` aparece.
- **Por que assim:** o teste pergunta ao **runtime** (`Artisan::call('list')`), não ao disco —
  `class_exists` provaria só que o arquivo existe, nunca que o registro pegou.
- **Status: 🧪**

## UC-FCERT-02 · Cert vencendo em ≤30 dias vira alerta do business dono `[T0]`
- **Persona:** contadora que não pode ser pega por certificado vencido no dia da emissão.
- **Aceite:** Dado um certificado A1 que vence em 15 dias · Quando o health-check roda · Então
  nasce um evento `cert_a1_vencimento` **escopado ao business do certificado**, com severidade
  `medium` (faixa 8–30d) e status `aberto`.
- **Regressão que defende:** alerta de um tenant aparecer para outro
  ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Status: 🧪**

## UC-FCERT-03 · Cert longe do vencimento não vira ruído
- **Aceite:** Dado um certificado válido por 200 dias · Quando o health-check roda · Então **nenhum**
  alerta é criado.
- **Por que importa:** alarme que dispara sempre é alarme que se aprende a ignorar — o limiar de 30
  dias é o que separa aviso útil de ruído diário.
- **Status: 🧪**

## UC-FCERT-04 · A severidade acompanha a urgência
- **Aceite:** Dado um certificado **já vencido** · Quando o health-check roda · Então a severidade do
  evento é `critical` — não a mesma de um que vence em 30 dias.
- **Status: 🧪**

## UC-FCERT-05 · Rodar todo dia não empilha alerta repetido
- **Aceite:** Dado o mesmo certificado vencendo · Quando o health-check roda **duas vezes** · Então
  continua existindo **um** evento, deduplicado por
  `chave_idempotencia = cert_a1_vencimento:{business}:{uuid}`.
- **Por que importa:** o comando é agendado diariamente (06:30 BRT); sem dedup, 30 dias de janela
  viram 30 alertas do mesmo certificado.
- **Status: 🧪**

## UC-FCERT-06 · O ensaio não escreve nada
- **Aceite:** Dado qualquer certificado na janela · Quando roda com `--dry-run` · Então o relatório
  sai e **nada** é persistido.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando um teste de COMPORTAMENTO os cobrir)

- **[BACKLOG · ⬜ sem teste] O agendamento de fato dispara em produção** — hoje o que se prova é a
  presença do `$schedule->command('fiscal:cert-health-check')->dailyAt('06:30')` em
  `app/Console/Kernel.php:236`. Que o cron do Hostinger o executou é pergunta de **runtime**, e a
  resposta honesta vem da consequência (evento com timestamp fresco em `mcp_alertas_eventos`), não
  da leitura do Kernel — [proibicoes §5](../../../proibicoes.md) 2026-07-17.
- **[BACKLOG · ⬜ dívida herdada] Tenant do teste** — os casos ancoram em `CERT_HC_BIZ = 1` citando a
  ADR 0101. A [ADR 0358](../../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
  supersede a 0101 e move o tenant fictício para **98**. Trocar mexe em asserção: é intent próprio.

## Como rodar
```
tailscale ssh root@ct100-mcp "docker exec oimpresso-staging \
  php artisan test Modules/Fiscal/Tests/Feature/CertHealthCheckCommandTest.php"
```
⚠️ Leia **assertions**, não `0 failed`: sem `nfe_certificados`/`mcp_alertas_eventos` MySQL os casos
**pulam**, e skip sai com exit 0 ([proibicoes §5](../../../proibicoes.md) 2026-07-24 · LC-13).

## Trilha do tempo
- 2026-09-01 · [CC] criado ao corrigir o `status` da `US-FISCAL-022` (`todo` → `done`): o comando, o
  agendamento e os 6 testes já existiam desde a entrega; faltava o contrato. Os `it()` passaram a
  citar o UC que já defendiam — nenhuma asserção mudou.
