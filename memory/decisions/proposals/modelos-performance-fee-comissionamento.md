# 3 Modelos de Performance Fee + Comissionamento — 2026-05-09

**Status**: proposed (Wagner valida)
**Domínio:** modelo comercial — performance-based deals pra base OfficeImpresso legacy + oimpresso novo
**Sinal qualificado** ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)): base OfficeImpresso real (snapshot Firebird multi-cliente) — R$ [redacted Tier 0] em A receber vencidas + R$ [redacted Tier 0] em A pagar vencidas + 132k clientes finais nos bancos. Não é hipótese.
**Não-objetivos:** substituir pricing recorrente (ADR proposals/pricing-recalibracao); inventar % de mercado (ranges BR são 5-10%, fixar caso a caso).

---

## 1) Filosofia — por que performance fee agora

Três motivos práticos:

1. **Reduz fricção de venda.** Cliente legacy pagando R$ [redacted Tier 0]/m por OfficeImpresso há 8-12 anos não vai assinar oimpresso R$ [redacted Tier 0] + setup R$ [redacted Tier 0]k mesmo com upgrade óbvio. Performance fee remove o "vou pensar" — não tem custo upfront, pagamento atrelado a resultado mensurável.
2. **Alinha incentivos.** Se Wagner cobra % sobre recovery/incremento, cliente sente que Wagner é parceiro (ganha quando ele ganha), não fornecedor que cobra mensalidade independente de valor entregue. Esse é exatamente o vetor que Asaas/Iugu não cobrem (eles cobram take rate sobre transação, não sobre resultado financeiro do cliente).
3. **Escala sem custo fixo proporcional.** IA roda recovery/régua/análise; Wagner não precisa contratar ninguém pra atender 5-10 clientes em performance fee em paralelo. CAC marginal próximo de zero pros 3 modelos.

**Limite honesto:** performance fee é arma comercial pra **conversão e expansão**, não substitui ARR. Cliente em performance fee puro é volátil (mês bom = R$ [redacted Tier 0]k, mês ruim = R$ [redacted Tier 0]). Modelo ideal: **oimpresso assinatura base + performance fee como camada adicional**.

**Restrição contratual** ([ADR 0040](../0040-policy-publicacao-claude-supervisiona.md)): qualquer cláusula contratual aqui é rascunho — advogado valida antes de assinar com cliente.

---

## 2) Modelo A — Recovery Fee (cobrança de inadimplência)

### Como funciona

1. Cliente conecta base OfficeImpresso ao oimpresso (snapshot 1-time + sync incremental opcional).
2. IA classifica A receber vencidas em 4 buckets — recuperável-curto (1-30d, 80% recovery), recuperável-médio (31-90d, 50%), recuperável-longo (91-180d, 25%), perdido (>180d, 5%).
3. Régua automática WhatsApp + email + boleto Asaas com tom calibrado por bucket (lembrete amigável vs cobrança formal vs proposta de acordo).
4. Cliente aprova régua antes de disparar (HITL — não-negociável; ninguém aceita IA mandando WhatsApp em nome dele sem revisão).
5. Wagner cobra **% sobre R$ efetivamente recuperado** (medido via Asaas/extrato bancário cliente).

### Pricing

| Bucket recovery | % sugerido | Justificativa |
|---|---|---|
| 1-30d (curto) | **5%** | Cliente provavelmente recuperaria sozinho com 1 ligação — Wagner agrega velocidade, não milagre |
| 31-90d (médio) | **8%** | Sweet spot — cliente já desistiu informalmente, IA resgata |
| 91-180d (longo) | **10%** | Cliente considerava perdido, recovery é puro upside |
| >180d (perdido) | **15%** | Trabalho próximo de cobrança terceirizada (mercado BR cobra 20-30%) |

**Range mercado BR:** escritórios cobrança terceirizada cobram 10-30% sobre recovery, mas trabalham com base "fria" (cliente já desistiu). Wagner com base quente do próprio ERP justifica % menor.

### Cláusula contratual modelo (rascunho — advogado valida)

> **Cláusula X — Recuperação de Recebíveis**
>
> 1. A CONTRATADA ([WR Sistemas]) prestará serviço de identificação, classificação e cobrança de recebíveis vencidos da CONTRATANTE através de sistema automatizado e revisão humana.
> 2. A CONTRATANTE pagará à CONTRATADA percentual sobre o valor efetivamente recuperado, conforme tabela em Anexo I, vinculada à idade do recebível na data de início da régua.
> 3. Considera-se "valor recuperado" o montante depositado em conta da CONTRATANTE até 90 dias após o início da régua, exceto se renegociado com prazo maior, hipótese em que o pagamento ocorrerá pro-rata conforme parcelas efetivamente quitadas.
> 4. A CONTRATADA não tem poder de dar quitação, conceder desconto ou negociar prazos sem autorização expressa da CONTRATANTE para cada caso.
> 5. A CONTRATADA registrará todas as ações em log auditável (data, canal, mensagem, resposta) acessível à CONTRATANTE.
> 6. Encerramento: qualquer parte pode rescindir com 60 dias de aviso. Recebíveis em régua na data de aviso seguem até 90 dias após sob a tabela vigente.
> 7. **LGPD:** o tratamento de dados de devedores segue base legal de execução de contrato (Art. 7º V LGPD). CONTRATANTE responde como controlador; CONTRATADA como operador.

### Caso piloto sugerido — Gold Comunicação

- **Volume A receber vencido:** R$ [redacted Tier 0] (snapshot Firebird)
- **Mix estimado** (sem amostra real, faixa conservadora): 20% curto + 35% médio + 30% longo + 15% perdido
- **Recovery realista 90d** (mix-weighted): R$ [redacted Tier 0]k × (0,20×80% + 0,35×50% + 0,30×25% + 0,15×5%) = **R$ [redacted Tier 0]k recuperado**
- **Wagner recebe** (mix-weighted %): R$ [redacted Tier 0]k × ~8.5% blended = **R$ [redacted Tier 0] em 90d**
- **Gold recebe líquido:** R$ [redacted Tier 0]k que estava parado.

**Importante:** essa simulação assume mix médio de mercado. Mix real do Gold pode ser pior (concentrado em >180d) — nesse caso recovery realista cai pra R$ [redacted Tier 0]-300k, Wagner ganha R$ [redacted Tier 0]-40k. Vale a conversa, não vale 100% upfront.

### ROI 12m se 5 clientes em Recovery Fee

Premissas conservadoras: 5 clientes × ticket médio recovery R$ [redacted Tier 0]k vencido × 35% recovery realista (mix médio mercado) × 8% blended fee = **R$ [redacted Tier 0]k/ano marginal Wagner**, R$ [redacted Tier 0]M devolvido pros clientes.

Realista — não otimista. Se mix dos clientes for melhor que média (mais curto), pode dobrar. Se for pior, cai 50%.

---

## 3) Modelo B — Migration Acceleration Fee

### Como funciona

1. Cliente OfficeImpresso legacy migra pro oimpresso novo (Modules/* canon, Inertia v3).
2. **Setup R$ [redacted Tier 0]** — Wagner absorve custo (estimado R$ [redacted Tier 0]-5k em horas time).
3. **Mensalidade R$ [redacted Tier 0] nos primeiros 6 meses** OU mensalidade base reduzida 50% (cliente escolhe).
4. A partir do 7º mês, Wagner cobra **% sobre a receita-incremento** vs baseline pré-migração, durante 24 meses.
5. Baseline: faturamento médio mensal do cliente nos 6 meses anteriores à migração (snapshot Firebird auditado).

### Pricing

**% sobre incremento mensal:**
- Faixa 0-20% incremento: **30%** de share Wagner
- Faixa 20-50% incremento: **25%** de share Wagner
- Faixa >50% incremento: **20%** de share Wagner (recompensa cliente que cresce muito)

Ranges baseados em SaaS revenue-share BR (Conta Azul tem deals desse tipo com contadores; ranges 20-35%).

**Cap honesto:** se cliente cresce 5x, Wagner não captura 30% disso pra sempre — após 24m cliente migra pra mensalidade fixa Pro/Enterprise.

### Cláusula contratual modelo

> **Cláusula Y — Migration Acceleration Fee**
>
> 1. A CONTRATADA migrará a CONTRATANTE da plataforma OfficeImpresso para a plataforma oimpresso sem custo de setup.
> 2. Por 6 meses contados da data de cutover, a CONTRATANTE não pagará mensalidade de licenciamento.
> 3. A partir do 7º mês e durante 24 meses, a CONTRATADA receberá percentual sobre o incremento de receita bruta da CONTRATANTE em relação ao baseline, conforme tabela em Anexo II.
> 4. **Baseline:** média de receita bruta dos 6 meses imediatamente anteriores à data de cutover, calculada a partir dos dados financeiros auditáveis da CONTRATANTE (snapshot Firebird homologado por ambas as partes em ata).
> 5. **Incremento:** diferença positiva entre receita bruta do mês e baseline. Meses com receita ≤ baseline: zero a pagar.
> 6. Encerrado o prazo de 24 meses, a CONTRATANTE migra para mensalidade fixa Pro/Enterprise vigente.
> 7. **Auditoria:** a CONTRATANTE concede acesso read-only ao módulo Financeiro do oimpresso para apuração mensal automática. Auditoria humana trimestral.
> 8. Saída: qualquer parte rescinde com 60 dias de aviso. Não há multa de saída — Wagner já capturou o trabalho de migração via shares mensais.

### Caso piloto sugerido — Vargas (saudável, em crescimento)

- **Baseline mensal estimado:** R$ [redacted Tier 0]k receita bruta (perfil saudável OfficeImpresso médio).
- **Incremento esperado pós-migração** (módulos novos: BoletoNFe, ADS, Repair com app Larissa): conservador +15% no 6º mês, +30% no 12º.
- **Share Wagner mês 7-12** (incremento R$ [redacted Tier 0]k/m × 30%): **R$ [redacted Tier 0]/m × 6m = R$ [redacted Tier 0]**
- **Share Wagner mês 13-24** (incremento R$ [redacted Tier 0]k/m × 25% — cruza faixa): **R$ [redacted Tier 0]/m × 12m = R$ [redacted Tier 0]**
- **Share Wagner mês 25-30** (incremento R$ [redacted Tier 0]k/m × 25%): **R$ [redacted Tier 0]/m × 6m = R$ [redacted Tier 0]**
- **Total 24m Wagner:** **R$ [redacted Tier 0]** vs ARR Pro R$ [redacted Tier 0]/24m = **R$ [redacted Tier 0] normal**.
- Cliente sente: "passei de R$ [redacted Tier 0]/m OfficeImpresso travado pra crescimento real onde Wagner ganha junto."

### ROI 12m se 3 clientes em Migration Fee

Premissa: 3 clientes em diferentes meses do ano. No 12º mês cumulativo, médio R$ [redacted Tier 0]k/m × 3 clientes × 6m maduros (já passaram dos 6 meses grátis) = **R$ [redacted Tier 0]k receita marginal 12m**.

Realista — 12m é janela curta pra modelo B (modelo é 30m completos). 24m completos ~R$ [redacted Tier 0]-400k pros 3 clientes somados.

---

## 4) Modelo C — Acquisition Bonus (referral)

### Como funciona

1. Cliente atual oimpresso indica novo prospect via link rastreável (UTM + referrer_id no checkout).
2. Se prospect fecha contrato pago (Pro R$ [redacted Tier 0]+ ou Enterprise) e mantém ≥3 meses ativo:
   - **Cliente atual recebe** 3 meses de mensalidade grátis + R$ [redacted Tier 0] cash bonus via Asaas.
   - **Novo cliente recebe** 20% off no primeiro ano (lock-in de 12 meses).
3. Se novo cliente cancela antes de 3 meses, o bonus do indicador é cancelado (e cobrado se já pago).
4. Sem limite de indicações por cliente — quem indicar 5 ganha 15 meses grátis + R$ [redacted Tier 0] cash.

### Cláusula contratual modelo

> **Cláusula Z — Programa Indica Aí**
>
> 1. A CONTRATANTE poderá indicar prospects à CONTRATADA através de link rastreável fornecido em sua área administrativa.
> 2. Caso o prospect indicado contrate plano pago e permaneça ativo por no mínimo 3 meses consecutivos, a CONTRATANTE fará jus a:
>    a) crédito de 3 meses de mensalidade integral em sua própria assinatura;
>    b) bônus de R$ [redacted Tier 0] (quinhentos reais) creditado via PIX ou conta Asaas;
> 3. O prospect indicado fará jus a 20% de desconto sobre a mensalidade contratada durante 12 meses, contra compromisso contratual de 12 meses (lock-in).
> 4. Caso o prospect indicado cancele antes de 3 meses ativos, os benefícios da CONTRATANTE são cancelados e os valores eventualmente creditados serão estornados.
> 5. Não há limite de indicações por CONTRATANTE.
> 6. A CONTRATADA reserva-se o direito de revisar o programa anualmente, mediante aviso prévio de 60 dias. Indicações em curso seguem regra vigente.

### Caso piloto sugerido — base saudável atual (6 clientes pagando R$ [redacted Tier 0]/m)

- **Premissa conservadora** (refletindo benchmark BR — Conta Azul tem ~7-12% indicação ativa em programas referral, não 17%): **8% indica/ano** ÷ 6 clientes saudáveis = ~0,5 indicações/ano. Programa só faz sentido com base ≥30 clientes pagantes.
- **Cenário Wagner futuro (base 30 clientes daqui 12m):** 30 × 8% = ~2,4 indicações/ano que fecham × R$ [redacted Tier 0]k LTV (Pro 12m × R$ [redacted Tier 0] net) = **R$ [redacted Tier 0] receita marginal/ano**.
- **Custo Wagner por indicação fechada:** 3 meses grátis (R$ [redacted Tier 0]) + R$ [redacted Tier 0] cash = **R$ [redacted Tier 0]/aquisição**.
- **CAC marginal:** R$ [redacted Tier 0] vs CAC orgânico atual estimado R$ [redacted Tier 0]-5k = **economia 30-50%**.

### ROI 12m se 6 clientes ativam programa (com base ainda pequena)

Premissa: 6 clientes atuais ativam, geram ~0,5 indicações fechadas no ano (base pequena ainda). **R$ [redacted Tier 0]k receita marginal 12m**. Modelo C é semente — fica forte quando base passa de 30 clientes.

---

## 5) Combinação dos 3 modelos por perfil de cliente

| Perfil cliente | Modelo recomendado | Sequência | ROI 12m Wagner estimado |
|---|---|---|---|
| **Gold-like** (déficit alto + inadimplência alta + GMV R$ [redacted Tier 0]M+) | A primeiro (recupera caixa) → B depois (incentiva crescimento) | A em mês 1-3, B kick-off mês 4 | R$ [redacted Tier 0]-90k/cliente |
| **Vargas-like** (saudável, em crescimento, ticket OfficeImpresso R$ [redacted Tier 0]/m) | B (cliente quer crescer, não recuperar) | B só | R$ [redacted Tier 0]-30k/cliente em 12m, R$ [redacted Tier 0]-130k em 30m |
| **TechPress-like** (legacy inativo, base parada, relação esfriada) | A (re-engaja via valor entregue antes de pedir mensalidade) | A em mês 1-6, oferece B só se cliente engajar | R$ [redacted Tier 0]-40k/cliente |
| **Saudável estável** (paga R$ [redacted Tier 0]/m, sem crescimento, sem inadimplência) | C como complemento (vira indicador) | mensalidade base + C | R$ [redacted Tier 0]-3k/ano cash bonus + 3m grátis |
| **Prospect novo via referral** | C lado prospect (entra com 20% off) → migra pra mensalidade cheia mês 13 | C lock-in 12m | R$ [redacted Tier 0]-12k LTV ano 1 |

**Cenário Wagner — 6 contratos performance fee em 12m (mix realista):**
- 2 Modelo A puro (Gold-like + TechPress-like): R$ [redacted Tier 0]k + R$ [redacted Tier 0]k = **R$ [redacted Tier 0]k**
- 2 Modelo B puro (Vargas-like): R$ [redacted Tier 0]k × 2 = **R$ [redacted Tier 0]k** (12m apenas; modelo matura em 30m)
- 1 Modelo A→B combo (Gold-like avançado): **R$ [redacted Tier 0]k**
- 1 Modelo C (programa referral semente): **R$ [redacted Tier 0]k**

**Total ROI 12m: R$ [redacted Tier 0]k receita marginal.** Cumulativo 24m projetado R$ [redacted Tier 0]-550k (B madura).

> **Honesto:** receita performance é volátil. Mês com 2 recoveries grandes pode fazer R$ [redacted Tier 0]k; mês seco R$ [redacted Tier 0]k. Não substitui ARR — complementa. Se Wagner depender disso pra folha, problema operacional sério.

---

## 6) Implementação operacional

### Contrato

- **2 páginas + 1 anexo de tabelas** — não confundir cliente. Advogado revisa rascunhos acima.
- **Padrão master agreement + scope amendments:** assina master 1x, cada cliente novo é 1 amendment 1-página apontando perfil (A/B/C/combo).

### Tracking

- **Recovery Fee:** módulo Financeiro do oimpresso já tem trilha A receber. Adicionar campo `recovery_origin` em `transaction_payment` pra marcar pagamento que veio via régua (idempotente — se cliente já pagaria sem régua, não conta — heurística: pagamento em <72h após disparo régua).
- **Migration Acceleration Fee:** snapshot baseline congelado em tabela `migration_baseline` (campos: business_id, mes_ref, receita_bruta, hash_assinatura). Apuração mensal automática via job scheduled.
- **Acquisition Bonus:** `referral_links` table com UTM tracking + idempotency key + status (pending/qualified/paid/reversed).

### Pagamento ao Wagner

- **Mensal proporcional**, NF emitida WR Sistemas → cliente, vencimento dia 10 do mês seguinte (recebível, não retenção).
- **Asaas split fee setup** se cliente já é cliente Asaas oimpresso — facilita reconciliação.

### Auditoria

- **Trimestral.** Cliente recebe relatório PDF com: ações executadas (régua disparos), recoveries detectados, valores faturados, hash de auditoria. Cliente tem 30 dias pra contestar — se silente, considera-se aceito.

### Saída

- **60 dias aviso prévio**, sem multa. Recebíveis em régua na data de aviso seguem até 90 dias após sob tabela vigente. Migration Fee em curso continua até completar 24m do cliente OU rescisão antecipada com Wagner abrindo mão dos meses futuros.

---

## 7) Riscos honestos

| Risco | Severidade | Mitigação |
|---|---|---|
| **Cliente esconde recovery** (paga off-the-books, declara que nunca recebeu) | Alta — modelo A todo depende de visibilidade | Integração API Asaas/banco direto (acesso read-only OFX/extrato), não depender só de relato cliente. Cláusula contratual com auditoria trimestral. |
| **Disputa sobre "Wagner fez ou não fez"** (cliente paga sem régua e diz que pagou sozinho) | Média — modelo A | Log imutável de ações (data, canal, mensagem, response do devedor). Heurística <72h após régua = atribui à régua salvo prova contrária. |
| **Concorrente copia modelo** (Iugu/Asaas lança recovery fee) | Média | Ser primeiro + Wagner tem diferencial (contexto vertical comunicação visual; Iugu é horizontal). 12m de janela. |
| **Mês seco (zero recovery, zero incremento)** | Alta — operacional | Não depender de performance pra folha. Manter Pro/Enterprise base como ARR. |
| **Cliente cresce muito e quer renegociar** (Modelo B vira "Wagner está ficando rico de graça") | Média | Cap implícito 24m + saída sem multa. Conversa franca: "se quiser sair antes, sem multa." Reputação > captura de valor curto. |
| **LGPD em recovery (mensagem WhatsApp pra devedor)** | Alta legal | Base legal: execução de contrato (Art. 7º V LGPD). Mensagem usa dados do próprio contrato cliente-devedor, não enriquece com fontes externas. Opt-out claro em cada disparo. |
| **Cliente usa programa C pra indicar laranjas** (autoindicação via terceiro) | Baixa | Lock-in 12m no indicado + KYC Asaas + cap implícito 5 indicações/ano por cliente revisado anualmente. |
| **Wagner trabalha grátis em modelo A se nada recuperar** | Alta — operacional | Filtrar piloto. Não aceitar cliente com mix >70% perdido (>180d). Pré-análise IA antes de assinar — recusar se não tem upside. |
| **Múltiplos modelos = cliente confuso** | Média | Master agreement padronizado + amendment 1-página por modelo. Sales script: "qual sua dor? Caixa parado (A) / Quero crescer (B) / Sou indicador (C)." |

---

## 8) Métricas pra validar (90d / 6m / 12m)

| Janela | Métrica | Threshold "modelo funciona" | Threshold "abortar" |
|---|---|---|---|
| 90d | Contratos performance fee assinados | ≥3 | <2 |
| 90d | Piloto Recovery Gold-like fechado e sob régua | sim | não (sinal: ninguém topa modelo A, virar B-only) |
| 6m | Recoveries efetivos (Modelo A) | ≥R$ [redacted Tier 0]k devolvido pros clientes | <R$ [redacted Tier 0]k (régua não funciona — recalibrar IA) |
| 6m | Receita marginal Wagner cumulativa | ≥R$ [redacted Tier 0]k | <R$ [redacted Tier 0]k |
| 12m | Contratos performance fee ativos | ≥6 | <3 (mata o programa) |
| 12m | NPS dos clientes performance fee | ≥60 | <40 (cliente sente que paga injusto) |
| 12m | Receita marginal Wagner cumulativa | ≥R$ [redacted Tier 0]k | <R$ [redacted Tier 0]k |
| 12m | Disputas formais sobre apuração | <10% dos contratos | ≥30% (modelo está mal desenhado) |

---

## 9) Decisão pendente Wagner

1. Aprovar rascunhos contratuais pra advogado revisar (qual advogado? sugiro pegar 1 contrato de revenue-share Conta Azul de referência)?
2. Escolher cliente piloto Modelo A — sugestão: Gold Comunicação (sinal mais forte: R$ [redacted Tier 0]M parado).
3. Alocar 1 sprint pra implementar tracking (campos `recovery_origin`, tabela `migration_baseline`, `referral_links`) — estimo 1 sprint completo time pequeno.
4. Confirmar: Modelo C entra agora ou espera base passar de 30 clientes (sugiro esperar — overhead operacional não compensa com base atual)?

---

## 10) Não-objetivos (escopo proibido sem nova ADR)

- ❌ Performance fee substituir ARR Pro/Enterprise — sempre camada adicional.
- ❌ Aceitar cliente Modelo A sem auditoria mix prévia — Wagner não trabalha de graça.
- ❌ Cláusula de cobrança terceirizada formal (CPC, protesto, SPC) — fora do escopo IA-régua. Se cliente quer cobrança formal, indicar parceiro (Serasa Limpa Nome, escritório).
- ❌ Modelo D (% sobre GMV) — fica pra ADR separada quando 6 clientes performance fee maduros (validação prévia).
