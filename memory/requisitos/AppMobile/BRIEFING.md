---
module: AppMobile
status: em-construcao
status_nota: "Telas próprias no app oimpresso-app (decisão [W] 2026-10-01, 2ª); a base /m dentro do ERP foi revertida."
updated_at: "2026-10-01"
distilled_at: "2026-10-01"
distilled_by: "manual [CL] — reescrito na reversão do /m (#8463 e #8465 revertidos), a partir das decisões [W] de 2026-10-01 e do MAPA-DE-DADOS-v1 (#8462)."
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
- **Encaixe do Ponto (pedido ao Design):**
  `prototipo-ui/cowork/Wagner/cowork-inbox/app-lojas/playbook/01-mobile-com-ponto.md`.

## Regras que não mudam

- Ponto sem câmera/biometria (ADR 0383).
- Valor/estoque em tela mobile seguem a regra mestre (dupla prova + antes→depois + aprovação [W]).
