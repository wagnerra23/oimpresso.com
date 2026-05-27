---
slug: 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
number: 194
title: "Correção domínio OficinaAuto Martinho — mecânica pesada caminhão basculante (não locação caçamba container)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-26
module: OficinaAuto
quarter: 2026-Q2
tags: [oficina-auto, correcao-dominio, martinho-cacambas, vocabulario, cnae, errata]
supersedes: []
supersedes_partially: []
amends: [0137, 0171]
superseded_by: []
related: [0105, 0121, 0137, 0143, 0171, 0192]
pii: false
review_triggers:
  - "Cliente real de locação caçamba container m³/diária aparecer — reabrir sub-vertical 3 com piloto ancorado"
  - "Schema service_orders.daily_rate / expected_return_date / delivery_address (migration 2026_05_12_220002) sem uso ativo M6+ — avaliar drop ou manter nullable se sub-vertical 3 ganhar cliente"
  - "BRIEFING/charter/ROADMAP/RUNBOOK OficinaAuto não atualizados em PR separado até 2026-06-15 — drift entre ADR e docs operacionais"
  - "CAPTERRA-FICHA OficinaAuto não recalibrada vs concorrentes corretos (Auto Manager / Mecânico Tecnomotor / Plumelp / Sysmecânica) até 2026-06-30"
---

# ADR 0194 — Correção domínio OficinaAuto Martinho · mecânica pesada (não locação)

## Status

`aceito` 2026-05-26 — Wagner aceitou no PR #1593 (mesma sessão da identificação do erro).

## Contexto

[ADR 0137](0137-modules-oficinaauto-qualificada.md) (2026-05-11) qualificou `Modules/OficinaAuto` ancorado em 2 de 4 clientes OfficeImpresso saudáveis: **Vargas** (recapagem pneu) + **Martinho** (descrito como "oficina de caçambas avulsas estacionárias, locação pra obra, CNAE 4581-4/00").

Em **2026-05-26** Wagner re-leu o BRIEFING/ADR e identificou erro de leitura sobre Martinho:

> *"eu entendi o Módulo Oficina Auto Mecânica parece que é aluguel de Caçamba kkk não é caçaba e o tamanho da caçamba. kkk é Mecânica de caminhão caçamba de grande porte, 1 milhão ou mais de faturamento mes. é quase concessionária"*

E em refinamento posterior:

> *"ta mais perto de concessionaria loja de peça, entra o caminhão para trocar e concertar não vejo caminhões destruídos lá"*

A leitura original confundiu **"caçamba container estacionária pra entulho"** (volume m³, diária, sem placa) com **"caminhão caçamba/basculante"** (caminhão pesado com carroceria que tomba, tem placa ANTT, valor unitário alto).

### Evidência da correção

| Sinal | Aponta pra |
|---|---|
| **96% PLACA Firebird** em `EQUIPAMENTO_VEICULO` (91 veículos importados) | Caminhão com placa ANTT — caçamba container estacionária **NÃO TEM PLACA** (incompatibilidade dura com leitura original) |
| **Faturamento R$ 1M+/mês** (Wagner) / R$ 6.28M/12m (Firebird snapshot 2026-05-11) | Compatível com mecânica pesada autorizada (peça unitária R$ 30-80k) — locação caçamba container ticket R$ 200-500/dia não gera esse volume |
| **Wagner 2026-05-26**: "não vejo caminhão destruído lá" | Perfil oficina autorizada de manutenção programada, NÃO lataria/reparação de batida |
| **WebSearch [Martinho Caçambas Capivari de Baixo SC](https://listatudo.com.br/santa-catarina/florianopolis-e-regiao/tubarao/construcao-civil-e-meio-ambiente/construcao-civil/maquinas-e-equipamentos/cacambas/martinho-da-cacamba/)** | Negócio confirmado: venda de **peças hidráulicas pra caminhões basculantes, Polli-guindastes, plataformas e munck** |
| **WebSearch [Tork Tomadas de Força Capivari](https://lp.tork.ind.br/)** | Fábrica industrial de PTO (Power Take-Off) + kit hidráulico na mesma cidade — vetor de cadeia comercial: Tork (fábrica) → Martinho (revenda+instala) → frota basculante terceiro |
| **2 entidades distintas** | WebSearch identificou (a) Capivari de Baixo (loja peça hidráulica) + (b) Tubarão (transportadora resíduo). **Errata 2026-05-26 pós-smoke prod biz=164 Location BL0001:** cliente real cadastrado em **Tubarão SC Humaitá de Cima** (não Capivari como inicialmente apontado). Inconclusivo qual entidade WebSearch corresponde — possível terceira não-identificada OU registro legacy importado pela entidade-mãe Tubarão. |

## Decisão

1. **Reclassificar Martinho biz=164** de:
   - ❌ CNAE 4581-4/00 (locação de veículos) sub-vertical "Locação caçamba estacionária pra obra"
   - ✅ **CNAE 4520-0/01** (manutenção/reparação mecânica veículos automotores) sub-vertical "Mecânica pesada / autorizada caminhão basculante"

2. **Sub-vertical 3 (Locação caçamba container CNAE 4581)** perde Martinho como piloto — fica **hipótese sem cliente real ancorado** (caso cliente real de locação caçamba container aparecer no futuro, reabrir sub-vertical com piloto novo).

3. **Criar sub-vertical 4** (mecânica pesada/autorizada caminhão basculante CNAE 4520-0/01) em `memory/reference/dominios-verticais-oimpresso.md` ancorado em Martinho biz=164 **Tubarão/SC · Humaitá de Cima** (errata 2026-05-26 — primeira versão deste ADR dizia Capivari de Baixo).

4. **Tork Tomadas de Força** (Capivari de Baixo, CNPJ 24.758.624/0001-30) entra em `memory/research/clientes-prospect/tork-tomadas-forca/` como **prospect indústria PTO** — vetor B2B via Martinho.

5. **Preservar** schema `service_orders.daily_rate` + `expected_return_date` + `delivery_address` + accessor `valor_receber`/`is_overdue` (migration `2026_05_12_220002`) **nullable sem drop** — review_trigger M6+ caso sub-vertical 3 ganhe cliente real.

6. **Recalcular `final_total`** em [ADR 0192 auto-faturar OS→Venda](0192-auto-faturar-os-venda-jobsheet-observer.md):
   - **Locação (sub-vertical 3 hipotético):** `daily_rate × dias_locacao` (preserva accessor `valor_receber`)
   - **Mecânica (sub-vertical 4 Martinho):** **peça × quantidade + hora-trabalho × horas** (NÃO daily_rate) — implementar quando Modules/Compras catálogo peça hidráulica chegar (V1)

## Consequências

### Positivas

- ✅ **Sinal qualificado [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) corretamente classificado** — Martinho era "baixo-médio" porque sub-vertical 4581 tem mercado pequeno; corrigido pra 4520 vira sinal **alto** (mecânica pesada autorizada caminhão = mercado de milhares de empresas no BR)
- ✅ **Concorrentes corretos identificados**: Auto Manager / Mecânico (Tecnomotor) / Plumelp / Sysmecânica / softwares concessionária Volvo-Scania-MB — não Lokoz/locadoras caçamba (benchmark errado em [ADR 0137 §"Posicionamento comercial"](0137-modules-oficinaauto-qualificada.md))
- ✅ **Vetor de prospecção novo (Tork)** desbloqueado — indústria PTO em Capivari + relação B2B com Martinho permite porta de entrada Modules/Industria/PCP (perfil em [clientes-prospect/tork-tomadas-forca/](../research/clientes-prospect/tork-tomadas-forca/01-perfil.md))
- ✅ **Vocabulário canônico saneado** — `m³ + diária` removido como característica Martinho (mantido só como hipótese sub-vertical 3 caso cliente real surgir)
- ✅ **Cadeia comercial mapeada** — Tork (fábrica) → Martinho (revenda + oficina) → frota terceiro = ecosistema explícito pra estratégia comercial regional Capivari/Tubarão

### Negativas / riscos

- ⚠️ **Drift entre ADR 0194 e BRIEFING/charter/ROADMAP/RUNBOOK** — esses docs ainda referenciam "locação caçamba avulsa" / `daily_rate` / `valor_receber`. Mitigação: PR separado pós-aceite atualiza esses operacionais em batch (review_trigger 2026-06-15).
- ⚠️ **CAPTERRA-FICHA OficinaAuto recalibrada** — score Capterra 63 foi calculado contra concorrentes locação caçamba (errado). Recalcular contra Auto Manager / Mecânico (Tecnomotor) / Plumelp / Sysmecânica em PR separado (review_trigger 2026-06-30).
- ⚠️ **Schema órfão** (`daily_rate`/`expected_return_date`/`delivery_address` + accessor `valor_receber`/`is_overdue`) — preservado nullable mas sem cliente real ativo. Risco: dev futuro pode achar que é feature ativa. Mitigação: comentário em migration + ADR como link.
- ⚠️ **ADR 0137 e ADR 0171 ficam "aceitos mas com erro factual"** — amends formal aqui (não supersedes — partes desses ADRs continuam corretas: schema multi-placa, FSM canônica, ativação faseada).
- ⚠️ **Vargas (sub-vertical 2 recapagem)** não é afetado por essa correção — segue qualificado como antes.

### Neutras

- Importação Firebird 91 veículos preservada — campo `vehicles.legacy_id` continua válido independente da reclassificação
- `Modules/OficinaAuto` continua módulo único (não split em mecânica vs locação) — coerente com [ADR 0137 §"Risco sub-vertical recapagem vs locação caçamba"](0137-modules-oficinaauto-qualificada.md) que já previa fluxos diferentes sob mesmo módulo
- FSM canon [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) continua aplicável — fluxo OS Simples 3 estados serve mecânica programada igualmente

## Alternativas consideradas

1. **A — Supersede ADR 0137 inteiro** ❌ — overkill, partes do 0137 continuam corretas (schema multi-placa, FSM 3/5 estados, importer Firebird, qualificação Vargas). Amends é cirúrgico.

2. **B — Reclassificação silenciosa (atualizar BRIEFING sem ADR)** ❌ — viola [ADR 0094 §"Charter > Spec"](0094-constituicao-v2-7-camadas-8-principios.md) e [ADR 0061 §"Conhecimento canônico"](0061-conhecimento-canonico-git-mcp-zero-automem.md). Erro de domínio em ADR mãe exige ADR errata formal.

3. **C — Splitting do módulo** (Modules/OficinaPesada novo + Modules/LocacaoCacamba) ❌ — não há sinal qualificado pra módulo novo (Martinho cabe em OficinaAuto sub-vertical 4; locação caçamba container é hipótese sem cliente). Premature optimization viola [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md).

4. **D — Amends [0137, 0171] + sub-vertical 4 novo + Tork prospect** ✅ **escolhida**. Mínimo necessário pra corrigir factualmente sem reverter trabalho válido (V0 LIVE, FSM canon, importer Firebird, ativação piloto faseada).

## Critério de validação

- [x] Perfil cliente `memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md` reescrito com domínio correto (Tubarão/SC Humaitá de Cima — errata 2026-05-26 corrigiu Capivari/SC inicialmente registrado, peça hidráulica + oficina autorizada, sub-vertical 4)
- [x] `memory/reference/dominios-verticais-oimpresso.md` adicionou sub-vertical 4 + marcou sub-vertical 3 como hipótese
- [x] Perfil prospect `memory/research/clientes-prospect/tork-tomadas-forca/01-perfil.md` criado
- [ ] BRIEFING OficinaAuto reescrito (Missão + capacidades + diferenciais) em PR separado pós-aceite (review_trigger 2026-06-15)
- [ ] CAPTERRA-FICHA OficinaAuto recalibrada vs concorrentes corretos em PR separado (review_trigger 2026-06-30)
- [ ] charter-1pager `demo-martinho-2026-05-13/charter-1pager.md` revisado
- [ ] RUNBOOK-migracao-cliente-legacy.md revisado (vocabulário peça hidráulica)
- [ ] ROADMAP OficinaAuto atualizado (mantém Fases 0-3 mas vocabulário sub-vertical 4)
- [ ] `final_total` em [ADR 0192 auto-faturar](0192-auto-faturar-os-venda-jobsheet-observer.md) recalculado pra OS mecânica (peça × qty + hora × horas) em PR separado quando Modules/Compras catálogo peça hidráulica chegar V1

## Refs

- [ADR 0137](0137-modules-oficinaauto-qualificada.md) — qualificação OficinaAuto (amendado por este ADR)
- [ADR 0171](0171-oficinaauto-ativacao-piloto-martinho-faseada.md) — ativação piloto faseada (amendado por este ADR)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
- [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) — modular especializado por vertical
- [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM canon LIVE prod
- [ADR 0192](0192-auto-faturar-os-venda-jobsheet-observer.md) — auto-faturar OS→Venda (impacto `final_total`)
- Perfil cliente atualizado: [01-perfil.md Martinho](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- Perfil prospect novo: [01-perfil.md Tork PTO](../research/clientes-prospect/tork-tomadas-forca/01-perfil.md)
- Dicionário domínios: [dominios-verticais-oimpresso.md §"Sub-vertical 4"](../reference/dominios-verticais-oimpresso.md)
- WebSearch fontes 2026-05-26:
  - [Martinho Caçambas (Listatudo) — Capivari de Baixo SC, peça hidráulica basculante](https://listatudo.com.br/santa-catarina/florianopolis-e-regiao/tubarao/construcao-civil-e-meio-ambiente/construcao-civil/maquinas-e-equipamentos/cacambas/martinho-da-cacamba/)
  - [Tork Tomadas de Força — Capivari de Baixo SC, fábrica PTO](https://lp.tork.ind.br/)
  - [Tork Tomadas de Forca CNPJ 24758624000130](https://cnpj.biz/24758624000130)
  - [Jocimar dos Santos Martinho CNPJ 27302634000155 — Tubarão SC (entidade pai, NÃO cliente)](https://cnpj.biz/27302634000155)
