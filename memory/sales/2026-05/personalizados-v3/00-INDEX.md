# Cold emails personalizados v3 — INDEX (pós-ADR 0121)

> Lote 2026-05-10. Reescrita após [ADR 0121](../../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — oimpresso é ERP modular especializado por vertical (núcleo + Modules/ComunicacaoVisual em construção).
> Cada arquivo tem versão A + B (test) + notas de envio.
> Restrições: zero PII pessoal; números só com `[validar — placeholder]`; sinais públicos do site.

## Mudança principal vs v1

| Antes (v1) | Agora (v3) |
|---|---|
| "ERP vertical comunicação visual" | "ERP modular brasileiro com Modules/ComunicacaoVisual em construção" |
| "ROTA LIVRE: cliente piloto SP, gráfica" | "Cliente piloto vestuário SC, 99% volume há 2+ anos — prova estabilidade do núcleo" |
| "Vem ser cliente do oimpresso" | "Vem ser piloto Modules/ComunicacaoVisual: 50% off 12m + acesso direto Wagner + co-criação" |
| Diferencial = setor com.visual exclusivo | Diferencial = núcleo testado (Jana, NFe-de-boleto, multi-tenant) + profundidade vertical co-criada |

## Os 3 pivots em todo email

1. **Core stable** — núcleo oimpresso roda há 2+ anos com cliente em prod (vestuário SC, 99% volume). Fundamentos provados: multi-tenant, NFe automática a partir de boleto pago, Jana IA com memória persistente.
2. **Vertical depth** — Modules/ComunicacaoVisual em construção (cálculo m², PCP gráfico, OS multi-etapa por máquina). Vamos co-criar com 1-3 pilotos. Você vira referência setorial.
3. **Pioneer offer** — primeiros 3 clientes Modules/ComunicacaoVisual: **50% off 12m** + **acesso direto Wagner** (não suporte tier 1) + **voto na priorização** das próximas features do módulo.

## Tabela de emails

| # | Arquivo | Empresa | Cidade | Ângulo v3 | Status |
|---|---------|---------|--------|-----------|--------|
| 01 | [01-sandice.md](01-sandice.md) | Sandice | SP — Moema | Backend pro portal B2B + piloto módulo | nao-enviado |
| 02 | [02-midiaprint.md](02-midiaprint.md) | MidiaPrint | SP — Vila Guilhermina | Multi-etapa + co-criação OS | nao-enviado |
| 03 | [03-lc-comunicacao-visual.md](03-lc-comunicacao-visual.md) | LC Comunicação Visual | SP — Guaianazes | NFe-de-boleto (núcleo provado) + piloto fachada | nao-enviado |
| 04 | [04-sp-sign.md](04-sp-sign.md) | SP Sign | SP — Vila Romana | Maquinário diverso → co-criar PCP por máquina | nao-enviado |
| 05 | [05-sam-comunicacao-visual.md](05-sam-comunicacao-visual.md) | SAM Comunicação Visual | SP — Z. Norte | Jana (núcleo) + co-criar 3D/ACM | nao-enviado |
| 06 | [06-gmg-impressao-digital.md](06-gmg-impressao-digital.md) | GMG Impressão Digital | São Caetano (ABC) | NFe automática (núcleo) + piloto vertical | nao-enviado |
| 07 | [07-hefeito.md](07-hefeito.md) | Hefeito | Campinas | Jana (núcleo provado) + relatório multinacional | nao-enviado |
| 08 | [08-acm-visual.md](08-acm-visual.md) | ACM Visual | Campinas | Governança + Jana (núcleo) + co-criar enterprise | nao-enviado |
| 09 | [09-new-signs-campinas.md](09-new-signs-campinas.md) | New Signs Campinas | Campinas | Multi-departamento → o piloto perfeito | nao-enviado |
| 10 | [10-kiart.md](10-kiart.md) | Kiart | Santos | Pioneer histórico + virar pioneer Modules/ComunicacaoVisual | nao-enviado |

## Top 3 maior probabilidade de aceitar virar piloto

1. **New Signs Campinas (#09)** — site confessa abertamente departamentos próprios (marcenaria + serralheria + digital + instalação). É o ICP perfeito pra co-criação Modules/ComunicacaoVisual: dor declarada + porte 16-30 (suporta change) + 24 anos (não é startup ansiosa, vai querer feature certa). Match nº1 absoluto.
2. **SP Sign (#04)** — 30 anos, Roland + laser + router + gravação inox. Tem maquinário **diverso** o suficiente pra forçar PCP por máquina sério no Modules/ComunicacaoVisual. Email gmail.com indica TI simples = chance maior de aceitar SaaS moderno + 50% off.
3. **Sandice (#01)** — já tem portal B2B (web2print.inf.br). Cliente educado em "tech faz diferença". Aceitar virar piloto = case de venda forte, e Sandice tem porte pra absorver onboarding co-criativo.

## Distribuição de ângulos v3

| Ângulo principal | Quantos | Quais |
|---|---|---|
| Multi-etapa / multi-departamento (co-criação OS) | 4 | 02, 04, 05, 09 |
| NFe automática (núcleo provado) → piloto vertical | 3 | 03, 06, 10 |
| Jana IA (núcleo provado) → priorizar relatório vertical | 2 | 07, 08 |
| Backend pro portal existente + piloto | 1 | 01 |

## Regras de envio (idênticas v1)

- **Cadência:** máx 3 emails/dia, espaçar 4h entre eles. Não rajada.
- **Hora preferencial:** Ter/Qui 09h-11h ou 14h-16h.
- **Domínio remetente:** Wagner@oimpresso.com (não enviar de gmail/yahoo).
- **Tracking:** rastrear open + reply (sem pixel invasivo).
- **Follow-up:** se não responder em 5 dias úteis, follow-up curto. 12 dias = `frio` por 90 dias.
- **Resposta qualificada:** Wagner grava vídeo de 2min em até 24h.

## O que NÃO fazer (atualizado v3)

- ❌ Não citar "ROTA LIVRE é gráfica de SP" — corrigido: ROTA LIVRE é vestuário em SC. Usar "cliente piloto em vestuário, 99% volume há 2+ anos" pra provar estabilidade do núcleo.
- ❌ Não vender Modules/ComunicacaoVisual como pronto — está em construção. Vender co-criação, pioneirismo, 50% off.
- ❌ Não anexar PDF no primeiro toque.
- ❌ Não inventar número/cliente — placeholder `[validar]` em qualquer métrica não confirmada.
- ❌ Não mandar pra mais de 1 contato da mesma empresa no mesmo dia.

## Próximo passo se prospect responder

- **Pediu vídeo/print:** Wagner grava em até 24h (Loom 2min, mostrando núcleo + Modules/Vestuario rodando como prova de fundamentos).
- **Pediu reunião:** Calendly 30min, framing **"discovery piloto Modules/ComunicacaoVisual"** — não é demo de produto pronto. É co-criação.
- **Topou virar piloto:** ADR 0105 (sinal qualificado) — Wagner formaliza acordo: 50% off 12m, acesso direto Wagner, voto em prio, em troca **logo + case + reportar dor real**.
- **Negou educadamente:** referral path (gráfica conhecida que sente dor X).

---
**Lote v3 criado em:** 2026-05-10 (sessão pós-ADR 0121).
**Reescrita feita por:** Claude Code (instrução Wagner) sobre v1 (sessão amazing-williamson-0c8854 de 2026-05-09).
