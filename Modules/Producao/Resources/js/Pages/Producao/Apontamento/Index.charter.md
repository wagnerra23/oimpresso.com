---
id: modules-producao-pages-producao-apontamento-index-charter
page: /producao/apontamento
component: Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/producao-cv-campo.jsx
owner: wagner
status: draft
last_validated: "2026-10-15"
parent_module: Producao
related_adrs: [93, 358, 431]
tier: B
charter_version: 1
---

# Page Charter — /producao/apontamento (DRAFT · celular do operador)

> **Status:** draft. Nasce na thread 04 + emenda E04 do playbook de Produção (PCP).
> Casos: [`Index.casos.md`](Index.casos.md) · regra: `Modules/Producao/Services/ApontamentoOperadorService.php` ·
> alvo medido: `governance/design/targets/producao--apontamento--index-390.alvo.json`.
> Forma: classes `pcv-*` do bundle `resources/css/cowork-producao-bundle.css` (cópia inteira do `producao-cv.css`, #9463).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

O operador aponta o próprio trabalho pelo celular: vê as etapas dele de hoje, lê o QR da OS,
inicia, pausa e conclui; o tempo vira custo real e a OS anda para o próximo responsável.

## Goals — Features (faz, nesta fase)

- "Rodando agora": o apontamento de pessoa e as máquinas que ele iniciou, com cronômetro e a linha do ponto.
- "Minhas etapas de hoje", na ordem programada.
- Ler QR pela câmera (BarcodeDetector) ou digitar o código; QR de etapa alheia só informa onde está e com quem.
- Pausar exige motivo do cadastro do posto; concluir passa a OS adiante.
- Concluir etapa de máquina leva junto, opcional, o m² impresso e a tinta C/M/Y/K em ml (US-COMVIS-004 · UC-PCIMP-01..03).
- Registrar refugo na etapa rodando: motivo de refugo do posto, peças e se vai para retrabalho (US-PCP-027 · UC-FLX-16, thread 08, 2026-10-21).
- Sem internet: guarda o toque com a hora do aparelho e envia depois, sem duplicar.
- Toque ≥ 44px em todo controle.

## Non-Goals (nesta fase)

- ❌ Moldura de celular e o texto explicativo do protótipo: são andaime da demonstração.
- ❌ Baixa de material (protótipo "Mídia usada"): é da thread 08. O "Registrar refugo" da mesma thread entrou em 2026-10-21 (Goals).
- ❌ Modo terminal fixo na máquina e sobra de material: forma nova do Cowork, ainda fora do espelho (threads 12 e 16).
- ❌ Escrever no Ponto: ele só é lido (D-PONTO).
