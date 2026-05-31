---
name: Pipeline de migração da carteira legacy WR → oimpresso (rastreador comercial)
description: Rastreador 1:1 dos clientes legacy Firebird/Delphi a converter em pagantes oimpresso. Scoring + onda + status + próxima ação. Outbound via markdown (feedback-outbound-markdown-over-mcp), NÃO tasks MCP granulares. Atualizar a cada toque comercial.
type: project
---

# 🎯 Pipeline de migração legacy → oimpresso (pagantes)

> **Origem:** [Plano de Crescimento 2026-05-31](../sessions/2026-05-31-plano-crescimento-oimpresso.md) · Alavanca 1.
> **Regra:** outbound/venda mora aqui em markdown ([feedback-outbound-markdown-over-mcp](../reference/feedback-outbound-markdown-over-mcp.md)), não em tasks MCP granulares. Atualize STATUS + PRÓXIMA AÇÃO a cada toque.
> **Meta 90 dias:** 12-18 fechados · **Meta 12 meses:** 30-40 dos 50.

## Como pontuar (0-2 cada eixo · soma 0-10 define a onda)
1. **Uso atual do desktop** (mexe todo dia? Firebird vivo?) — *peso maior*
2. **Saúde financeira** (faturamento 12m, paga em dia)
3. **Dor aguda hoje** (inadimplência, quer cobrar/acessar do celular)
4. **Proximidade/confiança** (relação quente, responde rápido)
5. **Valor de prova social** (referência no nicho/cidade)

→ **Onda A** = top 15 (≥7) · **Onda B** = meio (4-6) · **Onda C** = frio (<4)

## ⚠️ Gate de produto por vertical (importante)
- **Vestuário** ✅ LIVE (Larissa prova o modelo) — pode migrar já
- **OficinaAuto** ✅ Martinho já migrado — converter em pagante AGORA
- **ComunicacaoVisual** 🟡 **V1 não-LIVE** — trava ~30 gráficas da carteira. **→ ComVis V1 é o trabalho de produto de MAIOR ROI: destrava o maior segmento da carteira morna.** (vira task prioritária com justificativa de receita.)

---

## Onda A — quentes/saudáveis (rodar snapshot + migração-demo primeiro)

| Cliente | Vertical | Vendas FB | biz | Score | Status | Próxima ação |
|---|---|---:|---|:---:|---|---|
| **Martinho Caçambas** | OficinaAuto (mec. pesada) | 46.065 | 164 | _?_ | 🟡 migrado parcial (vehicles+SO done; vendas/fin pendente) · demo c/ Larissa feito · **HiSoft concorrendo** | **Terminar migração Fase 4-5 + proposta de fechamento (R$1M+/mês, prioridade máxima)** |
| **Extreme** | Gráfica industrial PCP | 85.575 | a confirmar | _?_ | ⏸️ aguarda ComVis V1 | Rodar snapshot; segurar até ComVis V1 |
| **Gold** | Comunicação visual (m²) | 55.715 | a confirmar | _?_ | ⏸️ trilha dormente · **proposta on-prem pendente (HITL brief)** | Snapshot; on-prem vs Mubisys (ver HITL Wagner) |
| **Zoom** | ComVis (a confirmar) | 52.390 | — | _?_ | candidato saudável | Snapshot quando ComVis V1 |
| **Vargas** | Recapagem caçamba | 3.981 | a confirmar | _?_ | 🔴 **sinais de cancelamento** — abordagem proativa | **Ligar JÁ (risco de churn); snapshot pronto** |
| **Mhundo** | a determinar | 18.327 | — | _?_ | candidato saudável | Sample VERSAO_BANCO + snapshot |

## Onda B — meio + dormentes reativáveis (win-back 3 toques)

| Cliente | Vertical inferida | biz | Score | Status | Próxima ação |
|---|---|---|:---:|---|---|
| Fixar | ComVis? | — | _?_ | candidato saudável (v1421) | Sample + win-back |
| Produart | a determinar | — | _?_ | candidato saudável (pendente sample) | Sample VERSAO_BANCO |
| Display Paraná | ComVis | — | _?_ | dormente | Win-back |
| Max Comunicação | ComVis | — | _?_ | dormente | Win-back |
| MilLetras | ComVis (letras) | — | _?_ | dormente | Win-back |
| GPSinalização | Sinalização viária | — | _?_ | dormente | Win-back |
| RG Comunicação | ComVis | — | _?_ | dormente | Win-back |
| Studium Vinil | ComVis (vinil) | — | _?_ | dormente | Win-back |
| Wow Comunicação | ComVis | — | _?_ | dormente | Win-back |
| Casagrande | Mecânica auto | — | _?_ | dormente | Win-back (OficinaAuto ready) |
| Lebrinha | Mecânica auto | — | _?_ | dormente | Win-back |
| Global Pneus | Pneus/oficina | — | _?_ | dormente | Win-back |
| SCMolas | Molas/autopeças | — | _?_ | dormente | Win-back |

## Onda C — frios / baixo uso (janela final, menor esforço)

Resto do registry HKCU (~30 bancos sem perfil): Art Laser, Assulbrat, Bangalo, Camargo (Sabor Brasil), CiaDosMoveis, CiaSul, CopyLan, CubaInox, CyberStudio, Destak, DMB, ECopias, Estilo, Fluxo, GoldenPrint, GSX, Guia Decor, HexiPrint, Medeiros, Metalurgica SF, Midia&CIA, Midia OFF, MoveisSul, Multimage, NewPrintFoz, Personalise, Safety, TechPress, Vargas Acessorios, etc.
→ **Não analisar especulativo** ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)). Toque único de janela final; quem reagir sobe pra Onda B.

---

## Scripts prontos (copiar — tom dono-pra-dono, 1ª msg nunca pede dinheiro)

### Variação 1 — "O número dele" (MAIS FORTE — pra quem você rodou o snapshot Firebird)
**Toque 1 (dia 0):** *"Oi [Nome], é o Wagner do WR Comercial. Tô passando pessoalmente porque mexi numa coisa nova e lembrei de você. Dei uma olhada no movimento da [Empresa] e vi uns títulos a receber parados ali. Montei um jeito do sistema te avisar no WhatsApp quando cada um vence — e já subi os SEUS dados pra te mostrar. Te mando um vídeo de 2 min ou prefere 15 min numa ligação essa semana?"*
**Toque 2 (dia 3, áudio):** *"[Nome], sem compromisso — eu literalmente já migrei seu histórico dos 26 anos pra te mostrar funcionando. Se não curtir, fica tudo como tá no seu computador. Te ligo hoje ou amanhã?"*
**Toque 3 (dia 7, oferta):** *"[Nome], última: quem migra agora eu faço a mudança inteira de graça (seus dados já estão prontos), você roda os dois em paralelo por 60 dias, e travo seu preço por 2 anos. Te ligo amanhã 10h?"*

### Variação 2 — "Reconexão + prova social do nicho"
**Toque 1:** *"Oi [Nome], é o Wagner (WR Comercial). Faz tempo! Tô avisando os clientes mais antigos primeiro: lancei o oimpresso, a versão nova na nuvem (acessa do celular, IA que ajuda na cobrança e no orçamento). A [gráfica conhecida do nicho] já migrou. Queria te mostrar antes de abrir pra geral. Te ligo quando?"*
**Toque 2 (dia 3):** *"[Nome], o pulo do gato: eu levo TODO seu cadastro e financeiro do sistema antigo, você não digita nada. 15 minutinhos essa semana?"*
**Toque 3 (dia 7):** *"[Nome], vou parar de dar suporte na versão antiga pra quem não migrar até [mês]. Quem migra nesses 90 dias: migração grátis + 2 meses cortesia no anual + preço travado. Bora marcar?"*

### Variação 3 — "Reativação de quem sumiu"
**Toque 1:** *"[Nome], Wagner aqui. Faz tempão que a gente não fala e você tá no sistema antigo ainda. Sem rodeio: construí algo bem melhor e quero te trazer de volta com condição de cliente de casa. Te ligo 10 min essa semana?"*
**Toque 2 (dia 4):** *"[Nome], te mando 1 print do seu próprio movimento já dentro do sistema novo? Vale seus 10 min. Hoje à tarde te ligo?"*
**Toque 3 (dia 8):** *"[Nome], deixa eu facilitar: migração por minha conta, 60 dias em paralelo sem risco, dinheiro de volta se não curtir. Amanhã 9h te ligo — se não puder, me diz o melhor horário."*

**Regras:** WhatsApp primário, email backup. Parar após 3 toques (revisitar na Onda C). Sempre oferecer ligação como próximo passo (warm fecha por voz).

---

## Placar (atualizar semanal)
| Semana | Tocados | Em call | Migração-demo | Fechados | MRR acum. |
|---|---|---|---|---|---|
| — | 0 | 0 | 0 | 0 | R$0 |

**Próxima ação imediata (segunda):** rodar snapshot Firebird (`officeimpresso-financial-snapshot`) nos 5 da Onda A com banco vivo + agendar 1ª call com dados na tela.
