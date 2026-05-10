# Estratégia expansão vertical oficinas auto — proposta — 2026-05-09

**Status**: proposed (Wagner valida)
**Pergunta central**: oimpresso deve expandir pra oficinas auto?
**Autor**: Claude (VP product/strategy roleplay, sessão 2026-05-09)
**Refs**: ADR 0105 (cliente sinal qualificado), ADR 0094 §2 (Tiered cost), ADR 0106 (10x IA-pair), `roadmap-tecnico-12m-2026-2027.md` (R25), `01-mercado-oficinas-auto-br.md` (R30)
**Inputs ausentes** (R31, R32, R33, R34): a análise se ancora na pesquisa de mercado R30 (única entregue) + roadmap R25 + ADRs canon. Conclusão é robusta sem eles porque a decisão NÃO depende de pricing fino dos concorrentes — depende de sinal qualificado, e este não existe.

---

## Sumário executivo

A pergunta "oimpresso deve expandir pra oficinas auto?" tem uma resposta desconfortável mas inevitável quando a governança formal do projeto é aplicada com rigor: **não agora — Cenário 4 (STAY-FOCUSED)**. O mercado é genuinamente atrativo (10-30x maior que comunicação visual, dores universais idênticas, `Modules/Repair` reaproveitável em ~70%), mas a regra constitucional do oimpresso (ADR 0105) determina que backlog **só recebe item se cliente paga + reporta** ou métrica detecta drift. Hoje, 2026-05-09, **zero oficinas auto fizeram outreach inbound, zero pagaram piloto, zero mecânicos foram entrevistados**. Toda a tese de oportunidade está em desk research — exatamente o tipo de input que ADR 0105 categoriza como "ADR de feature wish, não US ativa".

O cenário de Pivot (1) é descartado de plano: jogar fora 2 anos de validação ROTA LIVRE pra entrar num red ocean de 10+ concorrentes verticais com 10-15 anos de tração, sem ter sequer entrevistado um dono de oficina, é o protótipo do erro de founder que vê grama mais verde do outro lado. Multi-vertical (2) divide um time de 5 pessoas com Wagner já documentado como bottleneck (WIP máx 2) entre 2 GTMs, 2 ICPs, 2 marketing playbooks — sem aumentar headcount nem ARR. Spin-off (3) é tecnicamente coerente mas prematuro: brand nova, setup organizacional duplo, custo cognitivo de Wagner gerenciar 2 produtos enquanto ROTA LIVRE ainda concentra 99% do volume e o 2º cliente com.visual ainda não fechou.

A decisão honesta é manter foco: **resolver concentração ROTA LIVRE primeiro** (compromisso de roadmap M2-M3 = 2º + 3º + 4º cliente com.visual em 90 dias), **fechar smoke SEFAZ pendente** (M1), **ativar mwart-gate enforce** (M1) — e tratar oficinas auto como **ADR feature-wish** com gatilhos pré-definidos pra revisitar. Não é "nunca". É "agora não, e quando — exige sinal externo qualificado, não convicção interna".

A análise final — abordada na seção "Founder shiny object syndrome?" — é que a pergunta surgir agora, com ROTA LIVRE ainda sendo 99% do faturamento e o 2º cliente com.visual não fechado, é um sinal clássico de tentação por escala antes de validação. O mercado auto sendo "10-30x maior" é exatamente o tipo de razão que founders dão pra diluir foco antes de ter PMF reproduzível.

---

## Tabela ROI 4 cenários

> **Convenção:** "h IA-pair" usa fator 10x ADR 0106 em codáveis. Wallclock cliente (canary, smoke, vendas, discovery, onboarding) NÃO se beneficia de IA-pair. h marketing são wallclock real (Wagner/Eliana humano).

| Métrica | Pivot (C1) | Multi (C2) | Spin-off (C3) | Stay (C4) |
|---|---|---|---|---|
| **Investimento h dev (12m)** | ~80h IA-pair (Repair→Auto: NFS-e, tabela tempária, veículos, garantia, catálogo peças) + 40h migração ROTA LIVRE deprec | ~80h IA-pair (mesmas peças auto) + 60h IA-pair manter com.visual em paralelo | ~80h IA-pair auto + 60h IA-pair com.visual + ~30h IA-pair separação multi-tenant brand | **~0h auto** + roadmap atual M1-M6 |
| **Investimento h marketing/vendas (wallclock)** | ~600h wallclock (reset GTM auto: SEO, copywriting, relacionamentos, ICPs novos, CAC pré-Capterra) | ~700h wallclock (2 GTMs paralelos — Wagner+Eliana 40% comercial cada, dividido 50/50) | ~400h wallclock (auto separado, com.visual segue) | ~400h wallclock (M1-M6 já planejado, sem auto) |
| **Investimento R$ caixa** | R$ [redacted Tier 0]-60k (brand reset, landing nova, anúncios setoriais, presença Encontros Reparadores) | R$ [redacted Tier 0]-80k (2 brands ativas, marketing dividido) | R$ [redacted Tier 0]-100k (brand nova "oimpresso Mecânica", setup CNPJ separado opcional, landing dedicada) | R$ [redacted Tier 0]-5k (manutenção atual) |
| **Receita esperada 12m** | R$ [redacted Tier 0]-15k MRR (ramp-up zero — primeiros 6m são discovery+entrevista; 6m últimos = 2-3 pilotos pagos R$ [redacted Tier 0]-399) | R$ [redacted Tier 0]-50k ARR (com.visual pulled-back: 3 clientes em vez de 4-5; auto 1-2 pilotos) | R$ [redacted Tier 0]-20k MRR auto + R$ [redacted Tier 0]-50k ARR com.visual (com.visual desacelera ~25% por atenção dividida Wagner) | **R$ [redacted Tier 0]k+ ARR** (compromisso roadmap 5 clientes com.visual em 12m) |
| **Receita esperada 24m** | R$ [redacted Tier 0]-150k ARR (10-25 clientes auto pagando R$ [redacted Tier 0]-400/m — assumindo CAC sob controle, o que não está validado) | R$ [redacted Tier 0]-180k ARR (4 com.visual + 6-10 auto) | R$ [redacted Tier 0]-200k ARR (5-7 com.visual + 6-10 auto separado) | R$ [redacted Tier 0]k+ ARR (7 com.visual stretch + Mubisys-migration unlock 8-10 clientes) |
| **Risco (1-5)** | **5** (massivo — abandona base validada) | **4** (alto — divide time pequeno) | **3** (mediano — preserva foco mas duplica overhead) | **1-2** (baixo — execução do plano já validado) |
| **Alinhamento ADR 0105** | ❌ Viola — pivota sem sinal qualificado | ❌ Viola — adiciona vertical sem sinal | ❌ Viola — cria produto novo sem sinal | ✅ Cumpre — backlog só com sinal pago/reportado |
| **Capacidade time** (5 pessoas, Wagner WIP 2) | Insuficiente — exige hire BDR + dev senior auto vertical | Insuficiente — 2 GTMs simultâneos com 5 pessoas | Marginal — exige Eliana ou Felipe dedicar ≥30% só ao spin-off | **Suficiente** — roadmap calibrado em premissas reais |
| **Risco ROTA LIVRE churn** | Alto (cliente piloto sente abandono) | Mediano (atenção dividida) | Baixo (foco preservado em com.visual) | Mínimo |

---

## Análise por cenário

### Cenário 1 — PIVOTAR pra oficinas auto

**Tese:** mercado é 10-30x maior, dor é a mesma, Repair reaproveita 70%. Com.visual revelou-se nicho pequeno (~5-15k estabelecimentos formais BR vs 133-150k em auto).

**Por que não:**
- **ADR 0105 violado de forma frontal.** Zero oficinas auto fizeram outreach. Zero pagaram piloto. Zero foram entrevistadas. A tese inteira é desk research. ADR 0105 categoriza isso explicitamente como "ADR de feature wish, não US ativa".
- **Joga fora 2 anos de validação ROTA LIVRE.** Larissa (biz=4) é proof real. NFC-e com.visual está quase pronta (US-NFE-002 fechada server-side). Pivotar agora = jogar fora o único cliente provando que o produto funciona em produção.
- **Red ocean.** R30 lista 10+ concorrentes verticais auto (Mecânico, ManagerOS, Lokoz, Auto Manager, OficinaMaster, Carros2, Sti3, GP Office, Mais Oficina, Olho Vivo) com 10-15 anos de mercado. CAC contra incumbent estabelecido é 3-5x maior que entrar em mercado fragmentado.
- **Faltam peças críticas auto-específicas.** NFS-e (oimpresso só tem NFC-e), tabela tempária (preço hora-homem por procedimento), cadastro de veículo (placa+chassi+km), catálogo de peças com referência montadora. R30 estima 3-6 semanas IA-pair pra paridade básica — mas é só MVP, paridade competitiva real é meses.
- **Time de 5 não absorve.** Roadmap R25 já está calibrado no limite (Wagner WIP 2, Felipe 90% técnico, Eliana 30% dev). Pivot exige hire BDR + dev senior auto + reset marketing/SEO/copy.

**Veredito:** descartado. Violação direta de ADR 0105 + risco massivo. Sem 5+ pilotos pagos auto, este cenário não existe.

---

### Cenário 2 — MULTI-VERTICAL (com.visual + auto simultâneo)

**Tese:** aproveitar Repair genérico, ampliar TAM, evitar concentração ROTA LIVRE.

**Por que não:**
- **Time de 5 não suporta 2 GTMs.** Wagner já documentado como bottleneck (WIP máx 2 em `regras-time.md`). Felipe é único líder técnico de fato. Eliana 30% dev / 50% financeiro / 20% comercial. Adicionar 2º vertical exige split de Wagner+Eliana entre 2 ICPs com expectativas diferentes (gráfica espera fluxo OS+arte+instalação; auto espera fluxo OS+peças+veículo+garantia).
- **Marketing dividido = CAC dobrado.** 2 landings, 2 SEO playbooks, 2 grupos LinkedIn target, 2 case studies. Com R$ [redacted Tier 0]-5k marketing budget atual, dividir = ambos morrem.
- **Suporte 2x.** Eliana onboarding + Maiara suporte cobrem hoje 1 cliente ativo. Multi-vertical = 2 vocabulários técnicos diferentes (mecânico vs gráfico), 2 sets de feature requests divergentes.
- **ADR 0105 violado pela mesma razão de C1.** Adicionar vertical sem sinal qualificado entrante = backlog inflado por tese.
- **Risco de "ficar mediano em 2"**, em vez de excelente em 1. Pra com.visual, oimpresso ainda não fechou o 2º cliente — antes de virar campeão de nicho, diluir é prematuro.

**Veredito:** descartado. Time pequeno + Wagner bottleneck + ADR 0105 violado.

---

### Cenário 3 — SPIN-OFF ("oimpresso Mecânica" como produto separado)

**Tese:** foco em com.visual preservado, vertical novo nasce com brand separada (CNPJ separado opcional, landing dedicada, marketing independente).

**Por que não AGORA (mas talvez em 12-18 meses):**
- **Prematuro.** Spin-off só faz sentido depois que o produto matriz tem PMF reproduzível (≥3-5 clientes com.visual com churn baixo). Hoje: 1 cliente paga (ROTA LIVRE 99% volume), 2º com.visual ainda não fechou. Spin-off antes disso é construir 2º andar antes do 1º estar de pé.
- **Custo organizacional duplo.** Brand nova exige landing, copy, SEO, presença setorial separada (Sebrae auto, Sindirepa, Encontros Reparadores). R$ [redacted Tier 0]-100k caixa real + 400h wallclock. Sem retorno previsível.
- **Wagner não escala pra 2 produtos.** Mesma razão de C2 — Wagner WIP 2, único decisor estratégico, já bottleneck.
- **ADR 0105 também é violado.** Spin-off sem sinal qualificado = ADR feature-wish com brand separada. Apenas decoração organizacional.

**Quando este cenário voltaria a ser viável:**
- ≥5 clientes com.visual ativos pagando (compromisso roadmap M6 mai/2027)
- ≥3 oficinas auto fizeram outreach inbound em 90 dias OU 1 oficina aceitou piloto pago R$ [redacted Tier 0]-399/m por 6 meses (ADR 0105 critério rígido seção 8.4 R30)
- ROTA LIVRE concentração caiu pra <70% volume (diversificação real)
- Hire de BDR ou growth lead (time ≥6 pessoas)

**Veredito:** correto em estrutura, prematuro em timing. Re-avaliar em **mai/2027** (fim M6 do roadmap).

---

### Cenário 4 — STAY-FOCUSED (não expandir; ADR feature-wish)

**Tese:** ADR 0105 aplicado rigorosamente. Time pequeno + ROTA LIVRE concentração + 2º com.visual não fechado = consolidar antes de expandir.

**Por que sim:**
- **ADR 0105 cumprido.** Backlog só recebe item com sinal qualificado. Auto vai pra "ADR feature-wish" (assim como App mobile, DAM nativo, IoT, BI próprio listados em `roadmap-tecnico-12m-2026-2027.md` §Backlog ADR-feature-wish).
- **Roadmap R25 já é compromisso real.** M1-M6 entregam: smoke SEFAZ (M1), API docs Swagger (M2), 2º-4º clientes com.visual (M2-M3), ABICOMV endorsement (M4-M6), DAM decisão (M4), 5-7 clientes em 12m. Atrasos de 1-2 cycles em qualquer milestone afetam tudo — não há folga pra explorar vertical novo.
- **Capacidade compatível.** Time 5 pessoas + IA-pair 10x cobre M1-M6 com 20% buffer pra ROTA LIVRE incidentes. Adicionar auto fura buffer.
- **Concentração ROTA LIVRE precisa resolver primeiro.** 99% volume em 1 cliente é risco existencial. M3 entrega 4 clientes com.visual — concentração cai pra ~25-40% só com isso. Antes de diluir entre verticais, diluir DENTRO do vertical já reduz risco.
- **Mercado auto não vai a lugar nenhum.** R30 conclusão §8.4 sugere revisitar ~Q4 2026 / Q1 2027. Confirmação de que "agora não" não significa "nunca".

**Custo:**
- **Oportunidade perdida** se mercado auto entrar em janela ouro (improvável — é red ocean, não greenfield).
- **Se 2-3 oficinas auto fizerem outreach espontâneo** e oimpresso não tiver capacity, perde-se o sinal qualificado por inação. **Mitigação:** gatilho de revisão imediata (ver seção Critério-de-mudança).

**Veredito:** **escolhido**. Aplicação direta de ADR 0105. Risco baixo, retorno conhecido, capacidade compatível.

---

## Recomendação

**Cenário 4 — STAY-FOCUSED.** Não expandir pra oficinas auto agora. Tratar como ADR feature-wish.

**Justificativa quantitativa:**
1. **ADR 0105 zero violações.** Cenários 1, 2, 3 todos violam o princípio de sinal qualificado. C4 é o único cenário que cumpre a constituição do projeto.
2. **ROI 12m de C4 (R$ [redacted Tier 0]k+ ARR) > ROI 12m de C1 (R$ [redacted Tier 0]-15k MRR ramp-up zero) e > C2 (R$ [redacted Tier 0]-50k pulled-back) e > C3 (R$ [redacted Tier 0]-20k auto + 30-50k com.visual desacelerado).** Em 12 meses, ficar focado entrega 2-5x mais ARR que qualquer alternativa.
3. **Capacidade do time (R25) só comporta C4.** Qualquer outro cenário exige hire (BDR + dev senior) ou aceitar que roadmap M1-M6 derrapa em ≥3 cycles.

**Ação concreta imediata:**
- Criar ADR feature-wish `proposals/0XXX-vertical-oficinas-auto.md` com status `lifecycle: ideation` documentando: (a) tese de mercado, (b) gatilhos pra revisitar, (c) referência a R30, (d) ICP refinado em §6 R30. Não vira US, não vira backlog ativo.
- Manter R30 (pesquisa de viabilidade) como artefato de referência. Não jogar fora.
- Atualizar roadmap R25 §Backlog ADR-feature-wish adicionando linha "Vertical oficinas auto — sem sinal qualificado".

---

## Critério-de-mudança (gatilhos pra revisitar)

Esta decisão **vira candidata a Cenário 3 (Spin-off)** se QUALQUER dos seguintes acontecer:

| Gatilho | Cenário ativado | Prazo |
|---|---|---|
| **3+ oficinas auto fizerem outreach inbound em 90 dias** (LinkedIn, indicação, contato direto via site oimpresso.com) | C3 (Spin-off) — abrir RFC e re-avaliar | Em até 2 weeks após detecção |
| **1 dono de oficina auto ICP refinado aceitar piloto pago R$ [redacted Tier 0]-399/m por 6 meses** (critério rígido R30 §8.4) | C3 (Spin-off MVP) — criar SPEC `Modules/Repair` extensão Auto + ADR de pivô | 4 cycles (8 semanas) |
| **5º cliente com.visual fechar com sucesso** (M5+M6 cycle 41-44) E concentração ROTA LIVRE <50% volume | C3 viabilidade aumenta — re-avaliar formalmente | Cycle 46 retro anual (mai/2027) |
| **ROTA LIVRE fizer churn** | **Revisar TUDO** — incluindo se com.visual é o vertical certo. Crise existencial, não momento de explorar auto | Imediato |
| **2º + 3º com.visual NÃO fecharem em 90d** | Sinal de PMF fraco em com.visual — antes de explorar auto, diagnosticar PMF gap | M3 cycle 32 (set/2026) |
| **Endorsement ABICOMV travar / não responder em 6 meses** | Re-avaliar GTM com.visual em geral; auto NÃO entra automaticamente, mas vira input pra reflexão | M4 fim cycle 36 (nov/2026) |

**Revisão programada:** **mai/2027 (cycle 46 retro anual)** — re-rodar esta análise com dados reais de 12m de roadmap executado. Se nenhum gatilho disparou e Cenário 4 entregou KPIs (5+ clientes, ARR R$ [redacted Tier 0]k+), confirmar foco. Se underperform, reabrir tese com novos inputs.

---

## Risco da recomendação

**Principal risco:** "perder janela" se mercado auto entrar em fase de aceleração (ex: regulamentação nova obrigando software vertical, consolidação dos concorrentes deixa 1-2 incumbents com pricing power, ou montadora abre API pública pra DMS independentes).

**Mitigação:**
1. **Pesquisa R30 já feita** — não precisa re-fazer, só atualizar anualmente. Custo de manter consciência do mercado é baixo (~4h/ano de monitoramento).
2. **Gatilhos explícitos** acima detectam aceleração via outreach inbound (sinal de mercado nos procurando) ou via cliente pagante (sinal mais forte).
3. **Repair genérico continua sendo desenvolvido** pro com.visual — quando hora vier, base técnica está pronta (~70% reaproveitamento estimado em R30 §7).
4. **ABICOMV endorsement (M4-M6) é amortecedor** — se com.visual virar setor com força institucional, valor relativo de pivotar pra auto cai. Se não virar, sinal pra repensar GTM (não necessariamente pra auto).

**Risco secundário:** Wagner aceitar esta recomendação mas re-questionar em 60 dias por ansiedade de crescimento. **Mitigação:** registrar como ADR canônica, exigir nova ADR `supersedes:` pra mudar — fricção institucional protege foco.

---

## Análise: founder shiny object syndrome?

**Resposta honesta: provavelmente sim, parcialmente.** Confronto direto:

**Sinais de shiny object syndrome no pedido:**

1. **Timing.** A pergunta surge agora, em 2026-05-09, com:
   - ROTA LIVRE ainda 99% do volume
   - 2º cliente com.visual ainda não fechado
   - Smoke SEFAZ pendente (US-NFE-002 server-side fechada mas não validada em homologação)
   - mwart-gate ainda warning-only (não enforce)
   - Concentração de cliente é risco P0 explícito do roadmap

   Founders saudáveis exploram mercados adjacentes **depois** que o vertical primary atinge PMF reproduzível (≥3-5 clientes com baixa friction). Antes disso, é fuga.

2. **Justificativa "10-30x maior" é o padrão clássico.** R30 §1.4 mostra 133-150k oficinas formais vs 5-15k gráficas. Esse tipo de comparação é o gatilho mais comum de shiny object: "vou pra setor maior crescer mais rápido". Mas tamanho de mercado **não é o gargalo do oimpresso hoje** — o gargalo é capacidade comercial de fechar 2º cliente com.visual. Trocar de mercado não resolve gap comercial.

3. **Repair "reaproveita 70%" é tentação técnica.** R30 §8.2 razão #2 diz "Modules/Repair já existe — gap é só tabela tempária + veículos + NFS-e". Esse é o erro técnico clássico: confundir custo técnico (baixo) com custo de mercado (alto — red ocean, CAC contra 10+ incumbents).

4. **Ausência de sinal externo.** Wagner não foi procurado por dono de oficina. Não tem 1 mecânico amigo pedindo. Não saiu de uma reunião com cliente que disse "meu primo tem oficina, ele precisa disso". Toda a tese é interna — exatamente o tipo de input que ADR 0105 categoriza como "hipótese sem sinal".

**Sinais de pesquisa legítima (não shiny object):**

1. **Wagner pediu pesquisa antes de decisão** — em vez de pivotar primeiro e racionalizar depois. Isso é maturidade.
2. **Pediu confronto honesto** explicitamente — "esta análise pode recomendar NÃO expandir". Founder em pleno shiny object não pede que o sparring partner conteste.
3. **Aplicação rigorosa de ADR 0105** já está embutida no critério de decisão proposto — Wagner sabe a regra e está testando se ela vale.
4. **Roadmap R25 já calibrado em premissas reais** — Wagner não está em modo "vou abandonar tudo amanhã"; está em modo "estou certo de que esta é a melhor aposta?".

**Veredito sobre shiny object syndrome:**

**Diagnóstico:** Wagner está testando a tese contra governança própria, o que é saudável — mas a tese em si carrega marcas clássicas de shiny object (mercado maior, custo técnico baixo, sem sinal externo). **A função desta proposta é aplicar a governança que Wagner mesmo escreveu (ADR 0105) e devolver "agora não, e o por quê — quando, exige sinal".**

**Recomendação meta:** se em 30-60 dias Wagner re-questionar esta análise sem novos dados (sem outreach inbound, sem oficina pagante, sem disparo de gatilho), isso será sinal **mais forte** de shiny object — momento de reler ADR 0105 e este documento juntos antes de revisitar. Auto-disciplina por design vence ansiedade por circunstância.

**Nota afirmativa final:** querer crescer mais rápido não é defeito. Querer crescer mais rápido **abandonando o cliente que provou** que produto funciona, **sem sinal externo qualificado**, e **sem capacidade do time** é o defeito. Cenário 4 não é "fique pequeno". É "termine de provar que funciona em 5 clientes com.visual antes de se distrair".

---

**Última atualização:** 2026-05-09 (sessão Claude — VP product/strategy roleplay).
