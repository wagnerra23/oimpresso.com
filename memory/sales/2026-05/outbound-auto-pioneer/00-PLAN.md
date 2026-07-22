# Outbound OficinaAuto Pioneer — Top 30 prospects

**Origem:** índice [00-INDEX-UFS.md](../../../research/2026-05-prospeccao-auto/00-INDEX-UFS.md) — 288 oficinas em 10 UFs, Top 30 destilado.
**Vertical:** Modules/OficinaAuto · status `feature-wish` ([ADR 0125](../../../decisions/0125-modules-autopecas-feature-wish.md) — corrigir nesta sessão pra ADR específico de OficinaAuto se ainda não existir).
**Status governança:** ⚠️ outbound **exploratório / pioneer-hunting** — não há cliente piloto pagante. Objetivo: gerar **sinal qualificado** ([ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) que ative o módulo (`feature-wish` → `em_construcao`).
**Owner default:** Wagner [W]. Tracking via markdown.

> ⚠️ **Diferença vs Vestuario/ComunicacaoVisual:**
> Vestuario tem ROTA LIVRE em produção há 2 anos (prova social vertical). ComunicacaoVisual tem piloto Q3 saudável OfficeImpresso a confirmar. **OficinaAuto não tem nada vertical.** Pitch é **pioneer-first**, transparente sobre status feature-wish, oferecendo vantagens reais de pioneirismo.

## Premissas comuns

- **Tom:** PT-BR factual, profissional, sem hype, sem emojis
- **Léxico oficina:** placa/chassi/CRLV, tabela tempária Sindirepa, OS multi-mecânico, peça OEM/similar, garantia, reentrada, PCP — não usar linguagem genérica de varejo
- **Pioneer disclosure obrigatório:** "Estou construindo, você seria o piloto pioneer" — NÃO esconde status feature-wish
- **Prova de capacidade técnica (não de vertical):** "Stack moderna multi-tenant que sustenta ROTA LIVRE (loja vestuário SC, 3 lojas, 2 anos em produção). O Tier 0 é o mesmo que sustentaria sua operação"
- **Compromisso virar case público** — vídeo 90s + landing `oimpresso.com/cases/<empresa>`
- **NÃO citar concorrentes nominalmente** (Mecânico, Auto Manager, Lokoz, Ultracar, Oficina Integrada, Onmotor, IS2, Linx Microvix)
- **NÃO inventar features** — escopo amarrado ao [SPEC.md](../../../requisitos/OficinaAuto/SPEC.md)
- **Assinatura padrão:** `Wagner Rocha — Office Impresso · wagnerra@gmail.com · WhatsApp <WAGNER_TEL>`

## Pacote pioneer

- **Setup R$ [redacted Tier 0]** (pioneer)
- **Enterprise R$ [redacted Tier 0]/m grandfathered 24m** + 50% off primeiros 6m
  - Ano-1: R$ [redacted Tier 0]/m × 6 + R$ [redacted Tier 0]/m × 6 = **R$ [redacted Tier 0]**
  - Ano-2: R$ [redacted Tier 0]/m × 12 = **R$ [redacted Tier 0]**
  - Total 24m grandfathered: R$ [redacted Tier 0]
- **Migration completa** do sistema atual (Strangler Fig + parallel run 30d)
- **Modules/OficinaAuto entregue** quando construído (US-AUTO-001..N do SPEC.md):
  - Cadastro veículo (placa/chassi/CRLV) persistente
  - Tabela tempária Sindirepa
  - OS multi-mecânico (Kanban)
  - Catálogo peças OEM/similar
  - NFC-e (peça) + NFS-e (serviço) automática
  - Comissão por OS
  - Garantia loja vs fabricante
- **Jana IA ilimitada** com memória da operação (peça por aplicação no WhatsApp)
- **Compromisso pioneer**: virar case público anonimizável

## Top 30 — visão de tabela

> 🔍 **P1 SP/RJ/MG (alta densidade)** = abordagem prioritária; **P2 Sul** = case multi-loja real; **P3 CO/NE** = mercado emergente.

| # | UF | Empresa | Cidade | Sinal-chave | Canal | Prioridade | Status |
|---|----|---------|--------|-------------|-------|------------|--------|
| 1 | SP | Ferrino Reparos | Campinas+Paulínia+Americana | 3 unidades + todas seguradoras | LinkedIn DM dono | P1 multi-loja+seg | backlog |
| 2 | SP | Taciro Auto Center | Campinas | 56 anos + check-list digital WhatsApp | LinkedIn dono | P1 longevidade+digital | backlog |
| 3 | SP | Akikar / JR Fabiano | ABC | Especialistas câmbio automático | LinkedIn DM | P1 nicho técnico | backlog |
| 4 | SP | Mecânica HP | Bauru | Tier 1 polo médio interior | Email site | P1 interior dominante | backlog |
| 5 | SP | Car Concept | Santos | App cliente declarado, 5 anos | LinkedIn DM dono | P1 SaaS substituível | backlog |
| 6 | RJ | Faria Junior Bosch CS | Vigário Geral | 30+ anos + 12 clientes corporativos (Furnas/Vivo/Mapfre/Marinha) | Email comercial | P1 enterprise B2B | backlog |
| 7 | RJ | Mech Rio | Recreio | Premium BMW/Audi/Mercedes, ex-concessionária | LinkedIn DM | P1 premium | backlog |
| 8 | RJ | Mecânica Fusão | Petrópolis | 2.000m² + Bosch + cabine pintura | LinkedIn dono | P1 grande porte | backlog |
| 9 | MG | Brasil Centro Automotivo | JF + RJ (5 unidades) | Multi-loja real bi-estadual + Bosch | LinkedIn matriz | P1 multi-tenant | backlog |
| 10 | MG | Strong Car Services | Betim (3 unidades) | Especialista câmbio auto | LinkedIn dono | P1 multi-loja+nicho | backlog |
| 11 | MG | Oficina Barbosa Lima | JF | 50 anos + 2.500m² + 6 elevadores | Email comercial | P1 longevidade | backlog |
| 12 | MG | Resolução UV | Contagem | 38 anos + clientes McDonald's/Honda/Vale | LinkedIn DM | P1 enterprise | backlog |
| 13 | RS | Lisboa Car | POA (2 unidades) | 45 anos + premium + nacionais | LinkedIn dono | P2 multi-loja+premium | backlog |
| 14 | RS | Caprice | POA | 35 anos + seguradoras + frota = dor ERP máxima | LinkedIn DM | P2 dor confessada | backlog |
| 15 | RS | Auto Center Fabiano | Santa Maria (2 unidades) | Multi-tenant real interior | LinkedIn DM | P2 multi-loja interior | backlog |
| 16 | RS | Stern Premium | POA | BMW/Audi/Porsche/Ferrari | LinkedIn DM | P2 premium importadas | backlog |
| 17 | PR | WEJ Centro Automotivo | Curitiba/Cristo Rei | 16 elevadores + processo 8 etapas + Porto Seguro | LinkedIn DM | P2 grande porte | backlog |
| 18 | PR | Grid Auto Center | SJP (3 unidades) | Único multi-loja PR confirmado | LinkedIn dono | P2 multi-loja PR | backlog |
| 19 | PR | Mecânica Chile | Curitiba | 4 convênios frota simultâneos (MaxiFrota/Good Card/Prime/Vale Card) | Email comercial | P2 frota multi-conv | backlog |
| 20 | SC | Borchers | Jaraguá do Sul | 8 elevadores + 21k carros + 58k atendimentos | LinkedIn dono | P2 enterprise SC | backlog |
| 21 | SC | Eletrovel | São José | 32 anos + premium câmbio auto/híbridos | LinkedIn DM | P2 nicho técnico | backlog |
| 22 | SC | Finder Auto Center | Joinville | Bosch Car Service | LinkedIn DM | P2 Bosch CS | backlog |
| 23 | GO | R&R | Aparecida | Frota locadora (Unidas/Localiza/Movida/Arval/LeasePlan) | LinkedIn DM | P3 B2B frota top | backlog |
| 24 | GO | Goiânia Auto Center | Setor Coimbra | 22 anos pick-up specialist | LinkedIn DM | P3 nicho regional | backlog |
| 25 | GO | NS Injeção Diesel | Pq. Amazônia | Especialista diesel + CONAMA | Email site | P3 nicho compliance | backlog |
| 26 | DF | Nippon | SOF Sul + Asa Norte | 50+ anos multi-loja | LinkedIn DM | P3 multi-loja DF | backlog |
| 27 | DF | JM Auto Centro | Asa Sul | 41 anos + Bosch CS + ADAS | LinkedIn DM | P3 longevidade+ADAS | backlog |
| 28 | PE | AutoService Manutenção | Recife (3 unidades) | RMR + funilaria + frota | LinkedIn DM | P3 multi-loja NE | backlog |
| 29 | PE | New Car Service | Recife (multi-loja) | 12 seguradoras | Email comercial | P3 seguradora-heavy | backlog |
| 30 | BA | Centro Automotivo Porto | Salvador (3 lojas) | Único multi-tenant BA | LinkedIn dono | P3 multi-loja BA | backlog |

## Mensagens Cold #1 customizadas

### Fase 1 P1 SP/RJ/MG — alta densidade (12 prospects)

#### 1. Ferrino Reparos (Campinas+Paulínia+Americana) {#ferrino}
**Sinal:** 20+ anos, 3 unidades em 3 cidades + integração com TODAS as seguradoras declarada no site.

**Cold #1 (LinkedIn DM):**
> Pessoal d'A Ferrino, 3 unidades operando em Campinas+Paulínia+Americana com integração de todas as seguradoras é caso raríssimo no mercado SMB — workflow autorização-seguradora multi-loja sem ERP que entenda nativamente é despachante humano.
>
> Estou construindo o oimpresso, ERP brasileiro vertical comunicação visual e vestuário. Quero levar pra oficina mecânica e procuro 1 piloto pioneer pioneiro autopeças. Stack moderna multi-tenant, mesma base que sustenta uma loja em SC há 2 anos com 3 lojas. Pacote pioneer: setup R$ [redacted Tier 0] + 50% off 6m + 24 meses grandfathered + virar case público.
>
> 15 minutos de papo? Wagner Rocha · wagnerra@gmail.com · WhatsApp `<WAGNER_TEL>`

**Próximo passo:** LinkedIn DM dono Ferrino.

---

#### 2. Taciro Auto Center (Campinas) {#taciro}
**Sinal:** 56 anos (1969). Único Tier 1 com check-list digital via WhatsApp publicamente declarado — perfil "valoriza digitalização parcial".

**Cold #1 (LinkedIn DM):**
> Pessoal d'A Taciro, 56 anos no mercado é veterano de verdade — vocês já provaram resiliência. Vi também o check-list digital via WhatsApp que estão usando, sinal de que valorizam digitalização incremental sem ruptura.
>
> Construímos o oimpresso, ERP vertical com stack moderna multi-tenant. Ainda não temos cliente oficina mecânica em prod, então estou procurando 1 piloto pioneiro pra construir junto. Pacote pioneer: setup R$ [redacted Tier 0] + 50% off 6m + 24 meses grandfathered. Migration sem ruptura (Strangler Fig 30d).
>
> 15 minutos? Wagner Rocha · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

#### 3. Akikar / JR Fabiano (ABC) {#akikar-jrfabiano}
**Sinal:** Especialistas em câmbio automático no ABC. Câmbio auto = OS multi-dia, ticket alto, complexidade técnica.

**Cold #1 (LinkedIn DM):**
> Pessoal d'A Akikar/JR Fabiano, especialista câmbio automático no ABC é nicho técnico de alto ticket — OS multi-dia exige rastreabilidade que ERP genérico não cobre (peça em compra, técnico alocado, garantia separada por componente).
>
> Construímos o oimpresso, ERP vertical com stack moderna. Estou procurando 1 piloto pioneiro pra OficinaAuto. Pacote pioneer: setup R$ [redacted Tier 0] + grandfathering 24m + virar case público — câmbio automático especialista é case forte de marketing pra captar concorrência futura.
>
> 15 minutos de papo?
> Wagner Rocha · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

#### 4. Mecânica HP (Bauru) {#mec-hp}
**Sinal:** Único Tier 1 detectado em Bauru (polo médio interior SP).

**Cold #1 (Email comercial):**
> Pessoal d'A Mecânica HP, vocês são referência em Bauru — interior SP tem mercado oficina robusto mas pouco ERP vertical SMB realmente cobre a operação.
>
> Construímos o oimpresso, ERP vertical multi-tenant. Estou caçando 1 piloto pioneer pra OficinaAuto. Pacote: setup R$ [redacted Tier 0] + 50% off 6m + grandfathering 24m + case público. Stack moderna comprovada (ROTA LIVRE em SC, 3 lojas vestuário, 2 anos em prod).
>
> 15 minutos de papo? Wagner Rocha · wagnerra@gmail.com

**Próximo passo:** Email comercial site.

---

#### 5. Car Concept (Santos) {#car-concept}
**Sinal:** App cliente declarado, ~5 anos — provavelmente paga SaaS terceiro substituível.

**Cold #1 (LinkedIn DM dono):**
> Pessoal d'O Car Concept, vi que vocês já têm app cliente — sinal de operação madura digitalmente. Esse tipo de app costuma ser SaaS terceirizado com custo recorrente que cresce com volume.
>
> Construímos o oimpresso (ERP vertical) com app cliente nativo + multi-tenant + Jana IA conversacional. Estou procurando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering — app substitui o atual, sem custo extra.
>
> 15 minutos pra ver?
> Wagner Rocha · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

#### 6. Faria Junior Bosch CS (RJ/Vigário Geral) {#faria-junior}
**Sinal:** 30+ anos, Bosch Car Service, 12 clientes corporativos nominados (Furnas/Vivo/Mapfre/Marinha/Polícia Federal/Localiza/Movida/Unidas).

**Cold #1 (Email comercial):**
> Pessoal d'A Faria Junior, atender Furnas/Vivo/Mapfre/Marinha/Polícia Federal/Localiza/Movida/Unidas é volume B2B real — cada um tem auditoria de fornecedor, exigência de NFe certinha, SLA contratado. ERP horizontal não cobre essa malha.
>
> Construímos o oimpresso, ERP vertical multi-tenant + NFe automática + workflow autorização-frota. Estou caçando 1 piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público (clientes desse calibre = case publicitário forte).
>
> 15 minutos? Wagner Rocha · wagnerra@gmail.com

**Próximo passo:** Email comercial + LinkedIn da gerência.

---

#### 7. Mech Rio (RJ/Recreio) {#mech-rio}
**Sinal:** Premium BMW/Audi/Mercedes, staff ex-concessionária. Ticket alto, peça OEM importada.

**Cold #1 (LinkedIn DM):**
> Pessoal d'O Mech Rio, premium BMW/Audi/Mercedes com staff ex-concessionária é operação tier-1 — peça OEM importada exige catálogo + lead time + integração de fornecedor que ERP genérico não tem.
>
> Construímos o oimpresso, ERP vertical multi-tenant. Estou caçando 1 piloto pioneer OficinaAuto premium. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos?
> Wagner Rocha · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

#### 8. Mecânica Fusão (Petrópolis/RJ) {#mec-fusao}
**Sinal:** 2.000m² + Bosch CS + cabine pintura. Funilaria + mecânica = OS multi-departamento.

**Cold #1 (LinkedIn dono):**
> Pessoal d'A Mecânica Fusão, 2.000m² operando funilaria + mecânica + cabine pintura é OS multi-departamento real — cada etapa com prazo, técnico, recurso próprio. Sem ERP que entenda esse fluxo, gestor vira despachante humano.
>
> Construímos o oimpresso, ERP vertical com workflow multi-departamento nativo + IA pra apontar gargalo. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m.
>
> 15 minutos? Wagner Rocha · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

#### 9. Brasil Centro Automotivo (JF + RJ — 5 unidades) {#brasil-centro}
**Sinal:** 5 unidades multi-estado (JF/MG + RJ) + Bosch CS. Multi-tenant real raro no setor.

**Cold #1 (LinkedIn matriz):**
> Pessoal d'O Brasil Centro Automotivo, 5 unidades operando JF + RJ multi-estado é multi-tenant real — DRE consolidada, transferência peças entre filiais, NFe inter-estadual. Operação rara no setor SMB.
>
> Construímos o oimpresso, ERP vertical com multi-tenant Tier 0 nativo (1 conta cobre N filiais com isolamento real). Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público (multi-loja é case forte).
>
> 15 minutos?
> Wagner Rocha · wagnerra@gmail.com

**Próximo passo:** LinkedIn matriz JF.

---

#### 10. Strong Car Services (Betim/MG — 3 unidades) {#strong-car}
**Sinal:** 3 unidades em Betim + especialista câmbio automático.

**Cold #1 (LinkedIn dono):**
> Pessoal d'O Strong Car Services, 3 unidades em Betim + nicho câmbio automático é caso de multi-loja regional + nicho técnico de alto ticket. ERP horizontal não cobre.
>
> Construímos o oimpresso, ERP vertical multi-tenant + workflow OS multi-dia. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

#### 11. Oficina Barbosa Lima (JF/MG) {#barbosa-lima}
**Sinal:** 50 anos + 2.500m² + 6 elevadores. Operação grande, sistema legado provável.

**Cold #1 (Email comercial):**
> Pessoal d'A Barbosa Lima, 50 anos + 2.500m² + 6 elevadores é veterano consolidado — provavelmente sistema próprio antigo que ninguém quer mexer.
>
> Não estou oferecendo migração big bang. Construímos o oimpresso pra coexistir: stack moderna multi-tenant + portal cliente + IA + NFe automática como camada nova, sem quebrar o que já funciona. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + Strangler Fig 30d (rollback grátis).
>
> 15 minutos pra ver se faz sentido?
> Wagner · wagnerra@gmail.com

**Próximo passo:** Email comercial.

---

#### 12. Resolução UV (Contagem/MG) {#resolucao-uv-auto}
**Sinal:** 38 anos + clientes McDonald's/Honda/Vale/Klabin (carteira corporativa nacional).

> Nota: este prospect também está no plano ComunicacaoVisual — é gráfica/UV, talvez tenha braço auto. Verificar antes de abordar OU substituir por outro Tier 1 do índice MG/auto.

---

### Fase 2 P2 Sul — multi-loja real (8 prospects)

#### 13. Lisboa Car (POA — 2 unidades) {#lisboa-car}
**Sinal:** 45 anos + 2 unidades + atende premium + nacionais.

**Cold #1 (LinkedIn dono):**
> Pessoal d'A Lisboa Car, 45 anos + 2 unidades em POA atendendo premium + nacionais é operação consolidada multi-loja real.
>
> Construímos o oimpresso, ERP vertical multi-tenant nativo. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público. Stack comprovada: ROTA LIVRE em SC há 2 anos, 3 lojas vestuário multi-tenant.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

#### 14. Caprice (POA) {#caprice}
**Sinal:** 35 anos + atende seguradoras + frota = **"dor ERP máxima"** (palavra do agente prospector).

**Cold #1 (LinkedIn DM):**
> Pessoal d'A Caprice, atender seguradoras + frota há 35 anos é volume B2B com auditoria contínua — autorização Audatex/Cilia, NF certinha, comissão por convênio. Sem ERP vertical, planilha + caderno é gargalo.
>
> Construímos o oimpresso, ERP vertical multi-tenant + workflow autorização-frota + NFe automática. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM. Caso de dor confessada — vale visita presencial POA se sinalizar interesse.

---

#### 15. Auto Center Fabiano (Santa Maria/RS — 2 unidades) {#fabiano}
**Sinal:** 2 unidades multi-tenant real no interior RS.

**Cold #1 (LinkedIn DM):**
> Pessoal d'O Auto Center Fabiano, 2 unidades em Santa Maria é multi-tenant real no interior RS — modelo difícil de gerenciar com sistema horizontal.
>
> Construímos o oimpresso, ERP vertical multi-tenant Tier 0. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m.
>
> 15 minutos?
> Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM.

---

#### 16. Stern Premium (POA) {#stern}
**Sinal:** BMW/Audi/Porsche/Ferrari — premium importadas.

**Cold #1 (LinkedIn DM):**
> Pessoal d'A Stern Premium, BMW/Audi/Porsche/Ferrari é nicho top — peça OEM importada com lead time + ticket altíssimo + cliente exigente.
>
> Construímos o oimpresso, ERP vertical com catálogo OEM/similar nativo. Estou caçando piloto pioneer OficinaAuto premium. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público (premium é case publicitário forte).
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

#### 17. WEJ Centro Automotivo (Curitiba/PR) {#wej}
**Sinal:** **16 elevadores publicados** + processo 8 etapas + Porto Seguro. Operação grande + estruturada.

**Cold #1 (LinkedIn DM):**
> Pessoal d'O WEJ, 16 elevadores + processo em 8 etapas + Porto Seguro credenciada é operação tier-1 estruturada — esse rigor de processo só sustenta com ERP que entenda etapas como entidades, não fila genérica.
>
> Construímos o oimpresso, ERP vertical com workflow OS multi-etapa nativo. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono. Topcandidato técnico.

---

#### 18. Grid Auto Center (SJP/PR — 3 unidades) {#grid}
**Sinal:** Único multi-loja confirmado em PR — 3 unidades.

**Cold #1 (LinkedIn dono):**
> Pessoal d'O Grid, 3 unidades em SJP é o único multi-loja real PR mapeado. Multi-tenant é onde sistema horizontal mais vaza dinheiro.
>
> Construímos o oimpresso, ERP vertical multi-tenant Tier 0 nativo. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM.

---

#### 19. Mecânica Chile (Curitiba/PR) {#mec-chile}
**Sinal:** 4 convênios frota simultâneos (MaxiFrota/Good Card/Prime/Vale Card).

**Cold #1 (Email comercial):**
> Pessoal d'A Mecânica Chile, 4 convênios frota simultâneos é dor real — cada convênio tem regra própria, autorização, prazo, NF, comissão. Sem ERP que entenda multi-convênio, planilha eterna.
>
> Construímos o oimpresso, ERP vertical com workflow multi-convênio + NFe automática + boleto-pra-NF (US-RB-044) nativo. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** Email comercial.

---

#### 20. Borchers (Jaraguá do Sul/SC) {#borchers}
**Sinal:** 8 elevadores + 21k carros + 58k atendimentos publicados. KPIs visíveis = transparência operacional.

**Cold #1 (LinkedIn dono):**
> Pessoal d'A Borchers, publicar 21k carros e 58k atendimentos é raro — vocês confiam no número porque medem. Esse tipo de operação só sustenta esse volume com sistema que entenda OS como entidade rastreável.
>
> Construímos o oimpresso, ERP vertical com OS multi-mecânico + métricas embutidas. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público (números públicos = case publicitário forte).
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

#### 21. Eletrovel (São José/SC) {#eletrovel}
**Sinal:** 32 anos + premium câmbio automático + híbridos. Híbridos = compliance específico.

**Cold #1 (LinkedIn DM):**
> Pessoal d'A Eletrovel, 32 anos + premium câmbio automático + híbridos é nicho técnico de alto ticket. Híbridos especialmente exigem treinamento + peças específicas + diagnóstico ECU avançado.
>
> Construímos o oimpresso, ERP vertical com catálogo peças + diagnóstico nativo. Estou caçando piloto pioneer OficinaAuto premium. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM.

---

#### 22. Finder Auto Center (Joinville/SC) {#finder}
**Sinal:** Bosch Car Service credenciada.

**Cold #1 (LinkedIn DM):**
> Pessoal d'O Finder, Bosch Car Service é selo de qualidade reconhecido — operação madura sem ERP integrado.
>
> Construímos o oimpresso, ERP vertical multi-tenant. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM.

---

### Fase 3 P3 CO/NE — mercado emergente (10 prospects)

#### 23. R&R (Aparecida/GO) {#r-r}
**Sinal:** Frota locadora — Unidas/Localiza/Movida/Arval/LeasePlan.

**Cold #1 (LinkedIn DM):**
> Pessoal d'A R&R, atender Unidas+Localiza+Movida+Arval+LeasePlan é o time A das locadoras BR — cada uma tem regra própria de autorização, NF, comissão. Operação multi-convênio enterprise.
>
> Construímos o oimpresso, ERP vertical com workflow multi-convênio nativo + NFe automática. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público (locadora top é case publicitário fortíssimo).
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono. Topcandidato setor B2B.

---

#### 24. Goiânia Auto Center (Setor Coimbra/GO) {#goiania-auto}
**Sinal:** 22 anos + pick-up specialist.

**Cold #1 (LinkedIn DM):**
> Pessoal d'O Goiânia Auto Center, 22 anos + nicho pick-up é especialista regional — clientela conhecida, fluxo recorrente, peça específica.
>
> Construímos o oimpresso, ERP vertical com cadastro veículo (placa/chassi/CRLV) persistente + histórico cliente recorrente nativo. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM.

---

#### 25. NS Injeção Diesel (Pq. Amazônia/GO) {#ns-diesel}
**Sinal:** Especialista diesel + compliance CONAMA (DPF/EGR remap).

**Cold #1 (Email site):**
> Pessoal d'A NS Injeção Diesel, especialista diesel + remap DPF/EGR é nicho com compliance ambiental específico (CONAMA). Cada intervenção exige rastreio + log + emissão correta.
>
> Construímos o oimpresso, ERP vertical com audit log nativo + NFS-e/NFC-e automática. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** Email site + LinkedIn dono.

---

#### 26. Nippon (DF — SOF Sul + Asa Norte) {#nippon-df}
**Sinal:** 50+ anos multi-loja DF — veterano consolidado.

**Cold #1 (LinkedIn DM):**
> Pessoal d'A Nippon, 50 anos + 2 unidades DF é multi-tenant veterano. Sistema legado provável — não estamos oferecendo migração big bang.
>
> Construímos o oimpresso, ERP vertical multi-tenant + Strangler Fig 30d (rollback grátis). Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos pra ver se faz sentido coexistência?
> Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM matriz.

---

#### 27. JM Auto Centro (DF/Asa Sul) {#jm-auto}
**Sinal:** 41 anos + Bosch CS + ADAS. ADAS = câmera/sensor/calibração.

**Cold #1 (LinkedIn DM):**
> Pessoal d'O JM Auto Centro, 41 anos + Bosch CS + ADAS é operação tier-1 — calibração ADAS exige equipamento + processo + treinamento próprios.
>
> Construímos o oimpresso, ERP vertical com OS multi-mecânico + cadastro equipamento+calibração nativo. Estou caçando piloto pioneer OficinaAuto premium. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM.

---

#### 28. AutoService Manutenção (Recife/PE — 3 unidades) {#autoservice-pe}
**Sinal:** RMR + funilaria + frota + 3 unidades.

**Cold #1 (LinkedIn DM):**
> Pessoal d'A AutoService, 3 unidades RMR + funilaria + frota é caso de multi-loja + multi-departamento + B2B na mesma operação.
>
> Construímos o oimpresso, ERP vertical multi-tenant Tier 0 + workflow multi-departamento. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM.

---

#### 29. New Car Service (Recife/PE) {#new-car-service}
**Sinal:** Multi-loja + 12 seguradoras.

**Cold #1 (Email comercial):**
> Pessoal d'O New Car Service, multi-loja + 12 seguradoras na mesma operação é dor real de autorização + NF + comissão por convênio.
>
> Construímos o oimpresso, ERP vertical com workflow multi-convênio + NFe automática + boleto-pra-NF (US-RB-044). Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** Email comercial.

---

#### 30. Centro Automotivo Porto (Salvador/BA — 3 lojas) {#porto-ba}
**Sinal:** Único multi-tenant BA confirmado — 3 lojas Salvador.

**Cold #1 (LinkedIn dono):**
> Pessoal d'O Centro Automotivo Porto, 3 lojas em Salvador é único multi-loja real BA mapeado. Multi-tenant é gargalo natural de ERP horizontal.
>
> Construímos o oimpresso, ERP vertical multi-tenant Tier 0 nativo. Estou caçando piloto pioneer OficinaAuto. Pacote: setup R$ [redacted Tier 0] + grandfathering 24m + case público.
>
> 15 minutos? Wagner · wagnerra@gmail.com

**Próximo passo:** LinkedIn DM dono.

---

## Cold #2 — follow-up genérico (W2 nos não-respondidos)

> **Quando enviar:** 5-7 dias após Cold #1, sem resposta
> **Ângulo:** ROI concreto + transparência pioneer + opt-out leve
> **Personalização:** trocar `{empresa}` e `{sinal_observado}` (mesmos da Cold #1)

### Template

> Pessoal d{a/o} {empresa}, tudo bem?
>
> Mandei mensagem semana passada sobre {sinal_observado}. Seguem dois pontos pra encurtar a decisão:
>
> 1. **Pioneer disclosure honesto:** estamos construindo oimpresso pra OficinaAuto agora — vocês seriam o primeiro cliente vertical auto. Stack já testada (ROTA LIVRE em vestuário SC, 3 lojas, 2 anos em prod). Vocês ganham vantagens de pioneer (setup R$ [redacted Tier 0] + 50% off 6m + 24m grandfathered + virar case público), pagam o preço de construir junto (algumas features que demandarem feedback de vocês entram no roadmap em primeira mão).
>
> 2. **ROI alvo Enterprise pioneer:** R$ [redacted Tier 0]/m × 6 + R$ [redacted Tier 0]/m × 6 = R$ [redacted Tier 0] ano-1 (vs ERP horizontal R$ [redacted Tier 0]-1500/m × 12 = R$ [redacted Tier 0]-18.000 ano-1 + serviços extras). Se substituir 1 SaaS terceiro (app cliente, agendamento online), paga.
>
> Se não fizer sentido pra agora, responde "depois" que arquivo. Mas se quiser ver demo + conversar setup pioneer, abro 15min.
>
> Wagner Rocha · wagnerra@gmail.com · WhatsApp `<WAGNER_TEL>`

### Variações por arquétipo

| Arquétipo Cold #1 | Abertura customizada Cold #2 |
|---|---|
| **Multi-loja real** (Ferrino, Brasil Centro, Lisboa, Strong, Fabiano, Grid, Nippon, AutoService, Porto) | "Mandei semana passada sobre as N unidades. Multi-loja é onde ERP horizontal mais vaza dinheiro — DRE consolidada vira maratona, transferência peças entre filiais sem rastreio, NFe inter-loja..." |
| **Bosch CS credenciado** (Faria Junior, Mecânica Fusão, Brasil Centro, Finder, JM) | "Mandei semana passada — Bosch CS é selo de operação madura mas não vem com ERP. Vocês têm o processo, falta só a camada digital que entenda esse processo nativamente..." |
| **Especialista câmbio automático** (Akikar, Strong, Eletrovel, Goiânia Auto pick-up) | "Mandei semana passada sobre câmbio auto. OS multi-dia de alto ticket exige rastreabilidade fina — peça em compra, técnico alocado, garantia separada por componente — sem ERP que entenda fluxo, gestor vira despachante..." |
| **B2B frota/seguradora** (Faria Junior, Caprice, Mec Chile, R&R, AutoService, New Car) | "Mandei semana passada sobre os convênios. Cada convênio tem regra própria de autorização, NF, comissão, prazo — multi-convênio sem ERP é planilha que cresce com volume..." |
| **Diesel especialista CONAMA** (NS Diesel) | "Mandei semana passada sobre diesel. Compliance CONAMA + remap exige audit log + rastreio de intervenção — sistema horizontal não cobre essa malha fiscal/ambiental..." |
| **Premium importadas** (Mech Rio, Stern, Eletrovel híbridos) | "Mandei semana passada sobre o nicho premium. Peça OEM importada com lead time longo + ticket alto + cliente exigente exige catálogo + tracking de fornecedor que ERP genérico não tem..." |

---

## Cold #3 — última chamada / pioneer-honest (W3)

> **Quando enviar:** 12-14 dias após Cold #2, ainda sem resposta
> **Ângulo:** "última chamada" honesta + pioneer transparency + opt-out por inação
> **Filosofia:** se não responder 3 vezes, arquiva. Energia vale mais nos que respondem.

### Template

> Pessoal d{a/o} {empresa},
>
> Mandei 2 vezes nas últimas 3 semanas sobre {arquetipo_dor_curto}. Sem resposta — sem ressentimento. Vocês têm prioridades, gestor de oficina não tem tempo pra outbound.
>
> Vou parar de mandar mensagem depois dessa. Antes deixo a transparência:
>
> **O que estou caçando:** 1 piloto pioneer pra ativar Modules/OficinaAuto no oimpresso. Não tenho cliente vertical auto em prod ainda — quem topar é literalmente o primeiro. Pacote pioneer: setup R$ [redacted Tier 0] + 50% off 6m + 24m grandfathered + 3 features mínimas escopadas com input direto de vocês + virar case público anonimizável.
>
> **O que tenho:** stack moderna multi-tenant rodando em produção há 2 anos numa loja vestuário SC (ROTA LIVRE, 3 lojas, R$ [redacted Tier 0]k → R$ [redacted Tier 0]k em 18 meses). Tier 0 multi-tenant é o mesmo que sustentaria oficina. Migration Factory pattern Strangler Fig com rollback grátis 30d.
>
> **O que vocês ganham vs concorrência tradicional:** preço pioneer permanente (24 meses), prioridade de roadmap, posição privilegiada de marketing quando o caso vira público.
>
> **O que vocês pagam:** o "preço" de construir junto — algumas features virão em segunda iteração; suporte direto comigo (Wagner) durante 12 meses.
>
> Se em alguma dessas 3 mensagens fez sentido pelo menos curiosidade, responde "vamos conversar". Se chegou aqui e ainda não, sem problema — arquivo.
>
> Boa semana,
> Wagner Rocha — Office Impresso · wagnerra@gmail.com · WhatsApp `<WAGNER_TEL>`

### Notas Cold #3

1. **Nunca mandar #3 sem #1 e #2.** É última chamada de verdade.
2. Se prospect responder #3 com "agora não, depois" → marcar `arquivado-com-permissao-volta` e re-engajar em 90-180 dias com gancho novo (release de produto, segundo cliente vertical auto fechado).
3. Silêncio total em 3 toques → marcar `arquivado` na tabela, voltar pro Tier 2 do dossiê UF.
4. **Se Vargas (Modules/Autopecas) fechar antes** → reusar mensagem na cold #1 ("além do ROTA LIVRE em vestuário, fechamos primeiro autopeças com Vargas — segunda vertical em construção, OficinaAuto é a próxima").

---

## Cadência sugerida

| Semana | Ação |
|--------|------|
| W1 | Cold #1 nos 12 P1 SP/RJ/MG (alta densidade) |
| W2 | Quem respondeu → 15min/visita · Quem não → Cold #2 P1 + Cold #1 nos 8 P2 Sul |
| W3 | 15min calls P1+P2 + Cold #2 P2 + Cold #3 P1 não-respondidos |
| W4 | Cold #1 nos 10 P3 CO/NE + análise W1-W3 |
| W5+ | 15min calls P3 + Cold #2/#3 conforme cadência + cierre Q1/Q2 |

## Métricas de validação (pioneer-honest, alvos conservadores)

- **Taxa de resposta P1:** 15-25% em 30 dias (sem prova social vertical, expectativa abaixo de Vest/ComVis)
- **Taxa de resposta P2:** 10-20% em 30 dias
- **Taxa de resposta P3:** 5-15% em 30 dias
- **Taxa de qualificação respondentes:** 30%
- **1 piloto pioneer assinado em 90 dias** = ATIVA Modules/OficinaAuto (`feature-wish` → `em_construcao`)
- **<1 piloto em 90 dias** = revalidar ICP / pricing / mensagem / canais OU pausar OficinaAuto até segundo sinal vir naturalmente

Se taxa total < 8% em 30 dias → recalibrar mensagem ou ICP. Se nenhum lead em 90 dias → considerar `historical` + esperar gatilho [ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md).

## Após cada conversa

1. **Atualizar coluna Status** desta tabela: `backlog` → `doing` (15min agendado) → `done` (lead qualificado)
2. Se virar piloto pagante: criar entrada em `memory/clientes-legacy/<slug>.md` (formato similar rota-livre.md) + ADR de promoção Modules/OficinaAuto `feature-wish` → `em_construcao`
3. Apender US-AUTO-001..N ao SPEC.md OficinaAuto baseado em dor reportada pelo piloto

## Refs

- [00-INDEX-UFS.md auto](../../../research/2026-05-prospeccao-auto/00-INDEX-UFS.md) — fonte (288 oficinas)
- [SPEC OficinaAuto](../../../requisitos/OficinaAuto/SPEC.md) — contrato funcional do módulo
- [outbound-comvis-q2/00-PLAN.md](../outbound-comvis-q2/00-PLAN.md) — playbook irmão (ComunicacaoVisual)
- [outbound-vest-q3/00-PLAN.md](../outbound-vest-q3/00-PLAN.md) — playbook irmão (Vestuario)
- [memory/clientes/rota-livre/operacao.md](../../../clientes/rota-livre/operacao.md) — case piloto técnico de capacidade
- [ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
- [ADR 0121](../../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — vertical especializado
