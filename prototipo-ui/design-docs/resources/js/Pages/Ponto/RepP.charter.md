---
id: resources-js-pages-ponto-repp-charter
page: /ponto/rep-p
component: resources/js/Pages/Ponto/RepP.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [dentro, fora, gps-ruim, relogio-errado]
related_prototype: prototipo-ui/cowork/ponto/ponto-mobile.jsx
contrato: prototipo-ui/contrato/ponto-rep-p.contract.json
tier: A
charter_version: 1
---

# Page Charter — /ponto/rep-p (REP-P (celular) e validação)

> **Origem:** — (API: Http/Controllers/Api/MobileMarcacaoController)
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`prototipo-ui/contrato/ponto-rep-p.contract.json`), quando existe.
>
> **Persona:** Técnico Repair no campo (touch de no mínimo 44px); gestor valida depois.

---

## Mission (1 frase)

Bater ponto fora da empresa com prova (selfie, GPS, device, relógio) sem punir quem trabalha na rua: a marcação entra e, se estiver fora da área, fica sinalizada para revisão humana.

---

## Goals — faz

- Bater ponto com os 4 tipos do enum, selfie obrigatória e GPS visível antes de confirmar
- Meu espelho do mês e Justificar (cria intercorrência PENDENTE de verdade)
- Bloqueios anti-cheat com motivo em texto: accuracy acima de 500m, drift acima de 30s, selfie abaixo de 100KB
- Fila do gestor (últimos 7 dias) com NSR, device, lat/lng, precisão, hash truncado, validar/recusar

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO recusa** marcação fora do geofence — sinaliza
- ❌ **NÃO guarda a foto**: só SHA-256 + URI de storage (LGPD)
- ❌ **NÃO gera NSR no aparelho** — é server-authoritative
- ❌ **NÃO promete** fila offline

---

## Anti-hooks (sinais de drift)

- ⚠️ NSR com buraco de sequência vindo do app
- ⚠️ Selfie base64 em log ou em banco
- ⚠️ Alvo de toque abaixo de 44px
- ⚠️ Recusar marcação apagando o registro em vez de marcar divergência

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing
