---
module: AppMobile
status: em-construcao
status_nota: "Telas próprias no app oimpresso-app (decisão [W] 2026-10-01, 2ª); a base /m dentro do ERP foi revertida."
updated_at: "2026-10-15"
distilled_at: "2026-10-15"
distilled_by: "manual [CL] — 2026-10-15: relidos a ADR 0432 e os 7 contratos ⬜ das propostas P1–P9 (api/tela-02, 03-anexos, 04, 13-lucratividade, 22-avancar-etapa, 22-entrega, 41); entraram a regra da biometria e o ponteiro para os contratos. Base anterior: reversão do /m de 2026-10-01 (#8463, #8465) e MAPA-DE-DADOS-v1 (#8462). Não há Modules/AppMobile, então o destilador automático não cobre esta porta."
owner: W
---

# BRIEFING — App das lojas

## O que é

O app do oimpresso nas lojas (Google Play e App Store, Capacitor `com.oimpresso.app`, repo
`wagnerra23/oimpresso-app`) mostra as telas do **protótipo Mobile** (handoff design-v3, e o
design-v4 em `mobile/ref/design-v4/`).

## Decisões de 2026-10-01 (em ordem)

1. [W] recusou o app que abria o site do ERP emulado: *"não gostei, foi pego o site e emulado.
   eu quero o Mobile mesmo"*.
2. Escolha inicial: "Telas no ERP (`/m`)". A base foi construída e foi ao ar (#8463 tokens,
   #8465 shell).
3. [W], vendo `/m` em produção no emulador: *"não gostei dele dentro do sistema"* → **volta pro app
   com telas próprias; os dois PRs revertidos**. Vigente.

## Desenho vigente

- Telas escritas **no próprio app** (React + Vite empacotado no Capacitor, sem `server.url`).
- Dados do ERP por **API com token Passport**, pelo HTTP nativo do Capacitor. Hoje a API existe
  só para o Ponto (`/ponto/api`); as demais telas precisam de endpoint novo no ERP, um por tela,
  com isolamento `business_id` (ADR 0093) e teste cross-tenant no tenant 98 (ADR 0358).

## Onde ler

- **De onde vem cada dado de cada tela no ERP:** [MAPA-DE-DADOS-v1.md](MAPA-DE-DADOS-v1.md).
  Ele foi escrito para o desenho `/m`, mas o inventário de fontes (Services, controllers,
  permissões) vale igual para desenhar a API de cada tela.
- **Fonte de design mais nova:** `mobile/ref/design-v4/` (#8467) — telas 00–39 (36–39 = Ponto),
  proposta de tokens mobile (IBM Plex, toque ≥ 44 px) e `handoff/onda-5/README.md` (Ponto nativo
  via `/ponto/api` com Passport — o caminho do app; bloqueios: token Passport, espelho mensal,
  tipos de justificativa). Onde v4 e v3 divergirem, vale a v4.
- **Propostas P1–P9 (handoff `propostas-app`, 2026-10-05):** contratos ⬜ em `api/tela-02-apontamento.md`,
  `api/tela-03-anexos.md`, `api/tela-04-orcamento-rapido.md`, `api/tela-13-lucratividade.md`,
  `api/tela-22-avancar-etapa.md`, `api/tela-22-entrega.md` e `api/tela-41-atendimento.md`. Cada um vira ✅
  no PR de API do playbook.
- **Encaixe do Ponto (pedido ao Design):**
  `prototipo-ui/cowork/Wagner/cowork-inbox/app-lojas/playbook/01-mobile-com-ponto.md`.

## Regras que não mudam

- Ponto sem câmera/biometria (ADR 0383).
- App das lojas: biometria **só no login**, conferida pelo aparelho, nada biométrico vai ao ERP; câmera
  permitida para anexos de OS e QR ([ADR 0432](../../decisions/0432-app-das-lojas-biometria-so-no-login.md)).
  A regra do ponto acima vale só para o ponto, não para o app inteiro.
- Valor/estoque em tela mobile seguem a regra mestre (dupla prova + antes→depois + aprovação [W]).
