# Programa de afiliados oimpresso — 2026-05-09

> Status: rascunho v1 pra revisão Wagner [W] — não publicado, não commitado em prod
> Owner: Wagner [W] · Revisão legal: pendente · Lançamento alvo: 2026-06-15 (Tier Bronze apenas)

## Princípios

- **Pagamento só após cliente fechar contrato + 60d sem churn** — zerada a janela de chargeback/desistência típica B2B SaaS BR
- **Sem commission stuffing** — 1 prospect = 1 atribuição (last-click com janela 60d cookie + email match como tiebreaker)
- **Transparência total** no dashboard afiliado — clicks, signups, trials, fechados, churn, comissão a receber, próximo pagamento
- **LGPD by design** — afiliado NUNCA recebe dados pessoais do prospect; só status agregado (`qualified` / `trialing` / `closed` / `churned`) + valor da comissão
- **Anti-fraude CNPJ** — afiliado precisa CNPJ ativo (sócio diferente de sócios oimpresso, validado via RFB) pra Silver+
- **Append-only audit** — toda atribuição registrada com timestamp + UTM origem + IP hash; disputa só via abertura de chamado, nunca edição direta

## 3 níveis de partnership

### Tier Bronze — Indicação simples

- **Quem:** cliente atual oimpresso, profissional do setor sem capacidade de venda (designer freelance, ex-funcionário gráfica, dono de gráfica satisfeito)
- **Como funciona:** link pessoal `oimpresso.com/r/{slug}` → prospect clica → trial 14d → fecha contrato → afiliado entra em janela de comissão
- **Comissão:** 10% do MRR primeiros 12 meses
  - Ex Pro R$ 599/m × 12 = R$ 7.188 → afiliado recebe R$ 718,80 ao longo de 12m (R$ 59,90/m)
  - Ex Starter R$ 299/m × 12 = R$ 3.588 → afiliado recebe R$ 358,80 (R$ 29,90/m)
  - Setup fee NÃO conta (mantém com oimpresso pra cobrir onboarding)
- **Pagamento:** mensal via PIX, primeira parcela após 60d sem churn (mês 3 do cliente); para imediatamente se cliente churnar
- **Onboarding:** zero treinamento; afiliado se cadastra em `oimpresso.com/parceiros`, recebe link
- **Material:** link rastreável, 1 banner 1080×1080 (LinkedIn/Instagram), 1 banner 1200×628 (LinkedIn feed), 1 cold email template editável
- **Co-branding:** nenhum (Bronze é silencioso)

### Tier Silver — Parceiro educado

- **Quem:** contador especialista PME, consultor de gestão fazendo turnaround, ex-gerente de gráfica virando consultor
- **Como funciona:** prospect afiliado-originated → afiliado faz call de descoberta → call técnica conjunta com Wagner [W] (ou Felipe [F]) → afiliado conduz fechamento; oimpresso emite contrato
- **Comissão:**
  - 20% do MRR primeiros 12m
  - 10% do setup fee uma vez
  - **Bônus 30%** se afiliado fechar 3+ clientes em 12m rolling (recalcula retroativo nos 12m anteriores)
  - Ex 3 clientes Pro: 3 × R$ 599 × 12 × 20% = R$ 4.312,80 + R$ 1.293,84 bônus = R$ 5.606,64
- **Pagamento:** mensal PIX, mesma regra 60d
- **Onboarding:** 1 treinamento ao vivo de 4h com Wagner [W] (ou gravado após 3ª turma) + quiz de 15 perguntas pra liberar selo
- **Material:** deck co-branded (logo afiliado + "Parceiro Silver oimpresso"), demo script de 30min, FAQ avançado (precificação m², regime tributário gráfica, integração Asaas), canal Slack `#parceiros-silver` privado
- **Co-branding:** selo "Parceiro Silver oimpresso" no site afiliado + listagem em `oimpresso.com/parceiros`
- **Compromisso afiliado:** mínimo 1 cliente fechado em 6m senão downgrade pra Bronze

### Tier Gold — Reseller (revenda autorizada)

- **Quem:** empresa de tecnologia/consultoria que faz revenda + implementação + suporte L1 (perfil tipo "house de RH" mas pra ERP gráfico)
- **Como funciona:** reseller compra licença com desconto wholesale, revende com preço próprio (não inferior ao tabelado oimpresso -10%), faz onboarding técnico + suporte L1; oimpresso entra só em L2/L3
- **Comissão:**
  - 30% off pricing list em todas as licenças do portfólio dele
  - Reseller retém 100% do markup acima do wholesale (se vender Pro a R$ 599 cheio, fica com R$ 599 - R$ 419 = R$ 180/m + os R$ 419 que pagou já têm margem de oimpresso embutida)
  - Setup fee: reseller cobra do cliente final, divide 50/50 com oimpresso (justo pelo onboarding técnico)
- **Pagamento:** reseller emite NF de revenda mensal pra oimpresso; oimpresso fatura cliente final ou repassa cobrança (a definir contrato)
- **Onboarding:** 16h treinamento (4 sessões 4h) + certificação técnica + acesso ambiente sandbox (`sandbox.oimpresso.com`)
- **Material:** tudo Silver + sandbox + roadmap privado 6m + linha direta WhatsApp Wagner [W] + early access features beta
- **Co-branding:** "Gold Reseller Authorized oimpresso" + página dedicada em `oimpresso.com/parceiros/{reseller-slug}` + co-marketing 1×/trimestre (post LinkedIn conjunto)
- **Compromisso reseller:** contrato 24m + meta 12 clientes ativos em 12m (downgrade pra Silver se não atingir)

## Casos de uso por persona

| Persona | Tier provável | Volume esperado 12m | ROI Wagner (1 afiliado típico) | Churn esperado afiliados |
|---|---|---|---|---|
| **Contador 5-30 gráficas** | Silver | 2-4 clientes/ano | R$ 24-48k MRR adicionado | 30%/ano (afiliado some se não fecha) |
| **Consultor gestão PME** | Silver | 1-3 clientes/ano | R$ 12-36k MRR | 40%/ano |
| **Cliente atual indicando** | Bronze | 0-2 clientes/ano | R$ 0-14k MRR | 60%/ano (esquece o link) |
| **Influenciador LinkedIn** | Bronze ou Silver | 1-5 clientes/ano (se conteúdo bom) | R$ 12-60k MRR | 50%/ano |
| **Fornecedor complementar** (insumos/máquinas) | Silver ou Gold | 3-8 clientes/ano | R$ 36-96k MRR | 20%/ano (relacionamento longo) |

## Sistema técnico

- **Dashboard afiliado:** módulo novo `Modules/Afiliados/` (segue padrão Jana/Repair — ADR 0011) — Inertia/React em `oimpresso.com/afiliados/dashboard`
- **Tracking:** UTM (`?utm_source=afiliado&utm_campaign={slug}`) + cookie 60d + email match no signup como tiebreaker
- **Atribuição:** last-click dentro da janela 60d; se prospect já é cliente, reatribuição bloqueada (anti-self-referral)
- **Pagamento:** PIX mensal automatizado via Asaas API (já integrada — `reference_asaas_como_banco.md`)
- **Reporting afiliado:** clicks (UTM), signups (criou conta trial), trials ativos, fechados, churnados, comissão accrued, comissão paga, próximo PIX
- **Reporting interno (Wagner):** funil completo + LTV por afiliado + CAC efetivo (comissão / receita gerada)

## Implementação técnica MVP (~8h IA-pair = ~1.5h wallclock pós-recalibração ADR 0106)

1. Migração: tabela `afiliados` (id, business_id null=global, cnpj, nome, slug, email, tier, created_at, status)
2. Migração: tabela `indicacoes` (id, afiliado_id, prospect_business_id null até fechar, utm_payload, ip_hash, status enum, mrr_at_close, comissao_total, comissao_paga, created_at)
3. Hook: ao criar `business` novo, listener detecta UTM em sessão → cria `indicacoes` com status `trialing`
4. Hook: ao primeiro pagamento confirmado de business referido (mês 3 — espera 60d) → status `closed` + começa accrual mensal
5. Hook: ao churnar (cancelamento / inadimplência 30d) → status `churned` + para accrual
6. Cron mensal (dia 5): calcula comissão do mês anterior, gera lote PIX via Asaas API
7. Página Inertia `Modules/Afiliados/Resources/views/Pages/Dashboard.tsx` (charter incluído — ADR 0104 MWART)
8. Página pública `Modules/Cms/.../parceiros.tsx` com cadastro inicial (Bronze auto-aprovado, Silver/Gold com aprovação Wagner)

## Termo de adesão (rascunho — pendente review jurídico)

- Afiliado declara CNPJ ativo (Silver+) e não tem conflito societário com oimpresso
- Afiliado NÃO pode usar Google Ads em palavra-chave "oimpresso" (proteger SEM brand)
- Afiliado NÃO pode prometer features fora do roadmap público
- Afiliado NÃO pode redistribuir credenciais ou material co-branded sem aprovação
- oimpresso pode rescindir programa com aviso 30d; comissões accrued continuam até fim do ciclo 12m do cliente já fechado
- Disputas de atribuição: afiliado abre chamado em 30d do fechamento; decisão final oimpresso (audit log é fonte de verdade)
- LGPD: afiliado é controlador independente dos próprios contatos pré-link; oimpresso é controlador dos dados pós-signup
- Foro: comarca de São Paulo/SP

## Marketing do programa

- Página `oimpresso.com/parceiros` com 3 tiers + cadastro Bronze + form Silver/Gold
- Post LinkedIn lançamento Wagner [W] — copy "verbo-de-ação" estilo concorrentes (`reference_concorrentes_com_visual.md`)
- Email pra ROTA LIVRE/Larissa: "Larissa, indique outra gráfica e ganhe 10% por 12 meses — sem letra miúda"
- Outreach pra 20 contadores SP que atendem PME indústria/serviços (Felipe [F] ou Eliana [E] executa em 2h cada)
- 1 case study Bronze convertendo (~mês 3) → mover pra LinkedIn

## KPIs 6 meses (até 2026-11-15)

- 30 afiliados Bronze ativos (clientes oimpresso + indicações orgânicas)
- 5 afiliados Silver aprovados (contadores/consultores)
- 1 afiliado Gold piloto (reseller — fim do horizonte 6m, talvez slip pra 9m)
- 5 clientes pagantes via afiliado (10% do crescimento esperado total)
- CAC via afiliado < R$ 1.500 (vs CAC outbound atual desconhecido — provavelmente R$ 3-5k)

## Riscos

| Risco | Probabilidade | Mitigação |
|---|---|---|
| Concorrente vira "afiliado fake" pra extrair info | Média | Validação CNPJ Silver+ + sandbox separado de prod + roadmap privado só Gold |
| Commission stuffing (multi-link mesmo prospect) | Alta | 1 atribuição por prospect (UNIQUE em `prospect_email`) + last-click 60d |
| LGPD violation (afiliado vê dado pessoal) | Baixa-Média | Dashboard mostra só status enum + valor — nunca nome/CPF/email do prospect |
| Cliente reclama do contador "vendendo software" | Média | Termo Silver proíbe pressão; auditoria via NPS pós-onboarding |
| Cliente Bronze esquece o link existe | Alta (60%) | Email mensal "você ganhou R$ X esse mês" + recall trimestral |
| Reseller Gold vira concorrente fork (whitelabel pirata) | Baixa-Média | Contrato 24m + non-compete 12m pós + código fechado |
| Comissão impagável se cliente atrasar | Média | Pagamento só após confirmação de pagamento do cliente (não na fatura emitida) |

## Próximos passos

1. Wagner [W] revisa este draft (espera-se feedback em 2 dias)
2. Legal review do termo de adesão (advogado externo Wagner usa) — ~1 semana
3. Felipe [F] estima `Modules/Afiliados/` MVP (cabe em 1 cycle de 2 semanas?)
4. Lançar Bronze em 2026-06-15 com 5 afiliados convidados (clientes atuais top); Silver em 2026-08-01; Gold avaliar em 2026-12 com base em demanda real
5. Métrica de matar: se 6m em 0 clientes via afiliado, repensar tese; ADR de pivot ou kill
