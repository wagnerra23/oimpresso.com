---
id: modules-fisio-pages-fisio-public-portal-charter
page: /fisio/p/{token}
component: Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.tsx
related_prototype: "n/a (sem fonte em prototipo-ui/) — referência visual: canvas de design Prescreve Pro (claude.ai), telas PacienteAcesso, PacienteHoje, PacienteCheckin, PacienteCrise e PacienteNota ; a implementação usa os tokens e componentes do repo"
owner: wagner
status: draft
last_validated: "2026-10-19"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/p/{token}
  rota_nome: fisio.portal.show
  permission: n/a (pública, sem login — link + PIN gerados pela clínica)
  pacote: fisio_module
tier: B
charter_version: 2
---

# Page Charter — /fisio/p/{token} (DRAFT · portal do paciente)

> Nasce na US-FISIO-007a e ganha as telas do dia na US-FISIO-007b (ADR 0437 §4 a §7; HANDOFF-F1 T-07; etapa 02 T-07a a T-07c). Casos: [`Portal.casos.md`](Portal.casos.md).
> Uma página Inertia; as telas são componentes em `_components/` (`Entrada.tsx`, `Hoje.tsx`, `Checkin.tsx`) escolhidos pela `etapa` e pelo `modo` que o servidor decide.
> Precedente: link público de aprovação da OficinaAuto (token HMAC + PIN + lockout). Non-Goals e Anti-hooks além dos abaixo são de [W].

## Mission

Deixar o paciente abrir, pelo link que a clínica gerou e com o PIN que a fisioterapeuta passou, o próprio programa de exercícios, depois de aceitar o acompanhamento à distância.

## Goals — Features (faz)

- Link inválido, expirado, revogado ou substituído: o mesmo estado vazio, sem dizer qual condição quebrou — UC-FPP-01, UC-FPP-02.
- PIN de 4 dígitos com 5 tentativas e trava de 30 min — UC-FPP-03, UC-FPP-04.
- Consentimento de teleatendimento antes de qualquer dado; WhatsApp e câmera são opcionais e independentes; revogação pelo portal — UC-FPP-05, UC-FPP-06.
- Paciente bloqueado ou de outra clínica não abre — UC-FPP-07.
- Lembrar este aparelho por 30 dias; o PIN volta em aparelho novo, depois do prazo ou de revogação — UC-FPP-22, UC-FPP-23.
- Hoje: exercícios do programa ativo com dose, vezes por dia, passos e cuidados da biblioteca, e o jogo (nível, XP para o próximo, sequência, pausa gentil) — UC-FPP-17, UC-FPP-18, UC-FPP-25.
- "Como foi hoje?" em 3 toques (fiz tudo/parte/nada → dor 0–10 → "notou algo diferente?"), esforço e recado recolhidos; reenvio no mesmo dia corrige — UC-FPP-08 a UC-FPP-16.
- Dia de pausa (dor ≥ limiar ou sinal de alerta): só a instrução escrita pelo profissional, sem jogo — UC-FPP-19, UC-FPP-20.
- "Hoje estou em crise" quando o profissional escreveu a versão de crise — UC-FPP-24.
- App instalável: manifesto por link (sem o nome da clínica), tela do dia aberta sem rede só em aparelho lembrado, convite "Adicionar à tela inicial" a partir do 3º dia cumprido — UC-FPP-26 a UC-FPP-28.
- Missões de hoje, desafio da semana opcional e medalhas (T-08c), só fora da pausa — UC-FPP-33, UC-FPP-34.
- Fila offline do check-in: sem rede, o check-in fica no aparelho lembrado e vai quando a internet volta, uma vez só (chave por envio); de outro dia é recusado — UC-FPP-30 a UC-FPP-32.

## Non-Goals — Features (NÃO faz)

- ❌ Ranking, moeda e loja (ADR 0437 §6). Missões e medalhas entraram com a T-08c (2026-10-19), só pelo prescrito.
- ❌ Câmera, nota de execução e vídeo do exercício (F2).
- ❌ Áudio do paciente (fora da F1 — o handoff proíbe guardar áudio).
- ❌ Mostrar queixa, marcadores de condição, aviso de contraindicação ou série de dor (ADR 0437 §5 — SaMD).
- ❌ Calcular conduta para o paciente (ajustar dose, sugerir troca de exercício) — dispositivo médico (Anvisa/SaMD).

## Anti-hooks

- ⛔ Nenhum dado clínico antes do PIN e do consentimento — garantido no servidor, nunca só na tela.
- ⛔ O business nunca vem de parâmetro: vem assinado no token.
- ⛔ Token, PIN e segredo do aparelho em claro nunca vão para banco, log ou activity_log.
- ⛔ O portal nunca recebe série histórica de dor; só o relato do dia corrente.

## Refs

- ADR 0437 · `memory/requisitos/Fisio/HANDOFF-F1.md` (T-07) · `etapas/02-f1-constancia.md` (T-07a, T-07b) · `resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md`