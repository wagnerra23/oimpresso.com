# Onda 5 — Ponto (REP-P) no app

Mesmo fluxo do REP-P web, nativo: GPS de verdade, relógio, tipos, comprovante, jornada do dia, Meu espelho (parcial) e Justificar. Espelho visual: telas 36–38.

## Conferido no main (01/10)
API em `Modules/Ponto/Http/routes.php` bloco 2 — prefixo `/ponto/api`, `auth:api` (Passport):
`POST /marcar` · `GET /marcacoes/hoje` · `GET /saldo` · `GET /escala/hoje` · `GET /dashboard/kpis` · `GET|POST /intercorrencias` · `POST|DELETE /push/dispositivo`.
Respostas e erros (`sem_colaborador` 403, `validacao_falhou` 422) tipados em `ponto-api.ts` a partir do `MobileMarcacaoController`.

| Arquivo | Ação |
| --- | --- |
| `mobile/lib/ponto-api.ts` | **Criar.** 7 rotas da API, tipos das respostas, `fmtMinutos`. |
| `mobile/hooks/use-ponto.ts` | **Criar.** `useGps` (expo-location), `useDeviceUuid`, `usePontoToken`. |
| `mobile/app/ponto/index.tsx` | **Criar.** Bater ponto · Meu espelho (banco de horas + escala de hoje + minhas justificativas) · Justificar. |
| `_layout.tsx.patch` | **Aplicar.** Registra a rota. |
| Dependência | `npx expo install expo-location` + permissão de localização "em uso" no app.json. |

## Regras mantidas (ADR 0383, 0419)
Sem câmera/biometria · GPS > 500 m trava o botão · NSR só do servidor · fora da fila offline de propósito (relógio > 30 s é recusado).

## ⚠ Decisões do Wagner antes do merge
0. **Já existe um app de ponto separado** (Capacitor, "app das lojas" — ADR 0423, rotas `/ponto/api/push/dispositivo`, App Links em `/.well-known/`). Este pacote coloca o ponto **dentro** do app Expo. Escolher: (a) manter os dois; (b) só o app de ponto e o Expo abre ele por link; (c) unificar no Expo. Não decidi isso.
1. **Token Passport no app Expo.** Ele autentica no servidor tRPC próprio. Proxy pelo tRPC ou login emitindo token Passport. `usePontoToken()` é placeholder.
2. **Dia a dia do mês.** Falta `GET /ponto/api/espelho?mes=` com `buildTotaisEspelho` + `buildLinhasEspelho` (já existem no EspelhoController).
3. **Motivos da justificativa.** `IntercorrenciaController::tiposDisponiveis()` não tem rota na API; estão fixos no app. Expor em `GET /ponto/api/intercorrencias/tipos`.
