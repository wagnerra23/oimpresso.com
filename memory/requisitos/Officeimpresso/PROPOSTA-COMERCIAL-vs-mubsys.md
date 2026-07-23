---
id: requisitos-officeimpresso-proposta-comercial-vs-mubsys
type: template-proposta-comercial
module: Officeimpresso
status: template
caso_primario: Gold Comunicação Visual (sessão 2026-05-09, ADR 0115)
related:
  - memory/decisions/0115-recuperacao-cliente-gold-via-bundle-oimpresso.md
  - memory/requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md
  - memory/comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md
created_at: 2026-05-09
last_updated: 2026-05-09
trigger:
  - "Cliente on-prem migrando para Mubsys (ou Zênite/Calcgraf/Visua) por gap de NF-e 55"
  - "Reativação business dormente vertical comunicação visual"
---

# Proposta Comercial — Recuperação cliente vs Mubsys

> **Use:** copie este arquivo pra `memory/clientes/<cliente-slug>/proposta-2026-MM-DD.md` (gitignored — contém PII), preencha placeholders `{{...}}`, ajuste pricing e envie em PDF.
> **NÃO commite a versão preenchida com PII real do cliente.**

---

## Cabeçalho personalizável

**Cliente:** {{Razão Social}} (CNPJ {{CNPJ}})
**Contato:** {{Nome operador/dono}}
**Data:** {{2026-MM-DD}}
**Validade da proposta:** 15 dias
**Plano sugerido:** Recuperação on-prem + ativação NF-e 55

---

## 1. Por que ficar no oimpresso (e não migrar)

### Você já tem N anos de dados aqui

- {{N}} anos de histórico de vendas, clientes, produtos cadastrados
- Customizações on-prem feitas sob medida pra sua operação
- Time treinado no fluxo atual — recadastrar tudo no Mubsys = **{{X}} semanas de produtividade perdida**
- Risco de erro de migração (produtos com NCM errado, clientes duplicados, histórico fiscal incompleto)

> **Cálculo prático:** {{N produtos}} × ~3min por produto pra recadastrar no concorrente = **{{horas}} horas de digitação**. Sem contar que a equipe vai cometer erros.

### O que falta hoje (NF-e 55) **já está pronto** na versão atual do oimpresso

Você está pensando em migrar pra emitir nota fiscal eletrônica. **A capacidade já existe** no oimpresso atual, módulo `NfeBrasil` ([README NfeBrasil](../NfeBrasil/README.md)):

- ✅ NF-e modelo 55 (B2B) — produção
- ✅ NFC-e modelo 65 (B2C) — produção
- ✅ Cancelamento + Carta de Correção (CCe)
- ✅ Motor tributário ICMS-ST + DIFAL + FCP + Simples Nacional + Lucro Presumido + Lucro Real
- ✅ Templates regionais (incl. SP industrial gráfico)
- ✅ Cert digital A1 cifrado por business
- ✅ Monitor de rejeições com sugestão de correção
- ⏳ MDF-e (Fase 6 NfeBrasil — entrega futura, sem custo extra pro plano Enterprise)

**A questão é só fazer o upgrade na sua versão.** Não precisa migrar de sistema.

---

## 2. Onde o oimpresso ganha de Mubsys

> _Material de apoio: [comparativo Capterra 2026-04-25](../../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md). Mubsys é referência do nicho (4.9/5 com 300+ reviews) — não negamos isso. Mas há vetores onde oimpresso entrega o que ela não entrega._

| Capacidade | oimpresso | Mubsys |
|---|---|---|
| **Stack moderna** (Laravel 13.6 + React 19 + Tailwind v4) | ✅ | ❌ legado |
| **Jana — chat IA contextual** ("quanto faturei semana passada?") | ✅ | ❌ |
| **MemCofre — cofre de memórias** (subir print de erro vira ticket) | ✅ | ❌ |
| **Multi-tenant nativo** (filiais sem repaginar app) | ✅ | 🟡 |
| **API + webhook integração custom** | ✅ | 🟡 |
| **Customização on-prem** sob medida | ✅ | ❌ (SaaS engessado) |
| **Cert A1 sob seu controle** (servidor próprio) | ✅ | ❌ (Mubsys hospeda) |
| **Roadmap aberto** (você vota onde investimos) | ✅ | ❌ |

**Resumo:** Mubsys é "produto de prateleira polido"; oimpresso é "plataforma viva sob seu controle".

---

## 3. Onde Mubsys ganha (assumido, não escondido)

| Capacidade | Mubsys | oimpresso |
|---|---|---|
| Cálculo automático por m² (FPV) | ✅ nativo | 🟡 roadmap M2-M3 |
| App mobile nativo iOS/Android | ✅ | ❌ (responsive web) |
| PCP de impressão com etapas | ✅ | 🟡 parcial |
| Marca consolidada + 300+ reviews | ✅ 4.9/5 | 🟡 emergente |

**Honestidade:** se o seu maior gargalo é orçamento por m² em segundos, Mubsys resolve hoje. **Mas o seu gargalo declarado foi NF-e 55 — e isso o oimpresso resolve em {{X}} dias.**

PricingFpv (cálculo m² nativo) está no roadmap M2-M3 — entregamos sem custo extra pro plano Enterprise.

---

## 4. Pricing on-prem

> ⚠️ **TEMPLATE — pricing definitivo a ser fixado por Wagner antes de envio.**
> Decisão registrada em US-NFE-048 (refinar runbook).

### Opção A — On-prem (manter no servidor do cliente)

| Item | Valor | Observação |
|---|---|---|
| Setup recuperação (upgrade + NfeBrasil + treinamento) | R$ {{XXXX}} one-time | inclui Fases 1-6 do [runbook](RUNBOOK-recuperacao-on-prem.md) |
| Manutenção anual (atualizações + suporte) | R$ {{XXXX}}/ano | ~{{X}}% do setup |
| Cert A1 digital | por conta do cliente | ~R$ [redacted Tier 0]/ano (terceiros) |

### Opção B — SaaS Hostinger (alternativa, se cliente preferir)

Plano Enterprise NfeBrasil ([README NfeBrasil §pricing](../NfeBrasil/README.md)):
- **R$ [redacted Tier 0]/mês** — ilimitado + MDF-e + CT-e + SPED + cert gerenciado
- Migra dados on-prem → SaaS (incluso no setup)
- Sem custo de manutenção on-prem

> **Recomendação:** se cliente já tem servidor estável e prefere autonomia → Opção A. Se quer reduzir overhead operacional → Opção B.

---

## 5. SLA + cláusulas críticas

### SLA suporte

| Severidade | Definição | Response time | Resolution time |
|---|---|---|---|
| **P0** — Sistema fora do ar | Ninguém consegue logar / vender | 1h horário comercial | 4h |
| **P1** — Função crítica falha | NF-e rejeitada em massa, caixa não abre | 2h horário comercial | 8h |
| **P2** — Bug com workaround | Tela X lenta, relatório Y errado | 1 dia útil | 5 dias úteis |
| **P3** — Melhoria | Pedido de feature nova | 5 dias úteis | roadmap |

**Horário comercial:** seg-sex 8h-18h BRT. Plantão fim-de-semana via WhatsApp pra P0/P1 com surcharge.

### Cláusulas de proteção mútua

- **Cert A1 digital:** responsabilidade do cliente (compra + rotação anual). oimpresso/WR2 não acessa cert sem autorização escrita.
- **Backup pré-upgrade:** obrigatório. WR2 faz no início; cliente mantém cópia separada.
- **PII (CPF/CNPJ clientes finais):** preservada em servidor do cliente; LGPD Art. 7º.
- **SEFAZ outbound:** cliente garante firewall libera HTTPS pra `nfe.fazenda.{uf}.gov.br`.
- **Janela de manutenção:** combinada por escrito com 48h antecedência.
- **Rescisão:** qualquer parte com 30 dias aviso. Dados do cliente sempre dele.

---

## 6. Cronograma de 14 dias

| Dia | Fase | Entregável |
|---|---|---|
| D+0 | Aceite proposta | Contrato assinado + 50% antecipado |
| D+1-2 | Discovery (US-NFE-042) | Documento técnico audit instalação |
| D+3 | Confirmação GO | Aprovação cliente sobre estratégia upgrade |
| D+4-6 | Upgrade plataforma (US-NFE-044) | oimpresso atual rodando + Modules/NfeBrasil ativo |
| D+7 | Configuração fiscal (US-NFE-045) | Cert + IE + regime + template aplicados |
| D+8 | Smoke homologação SEFAZ-SP (US-NFE-046) | 1ª NF-e cstat 100 em homol |
| D+9 | Treinamento operadora (US-NFE-047) | Operador emitindo NF-e sozinho |
| D+10 | Cutover produção | `NFE_AMBIENTE=1` + 1ª NF-e prod |
| D+11-14 | Canary 7d | Acompanhamento rejeições + ajustes |
| D+15 | Fechamento + 50% restante | Cliente assina termo de aceite |

---

## 7. Próximas ações (cliente)

- [ ] Ler proposta + comparar com cotação Mubsys
- [ ] Agendar call 30min pra dúvidas técnicas
- [ ] Confirmar GO/NO-GO em até **{{validade}}**
- [ ] Se GO: assinar contrato + transferir 50% antecipado
- [ ] Indicar operadora pro treinamento (D+9)

---

**Assinatura WR2 Sistemas**
Wagner Rocha — wagnerra@gmail.com
oimpresso.com — ERP gráfico com IA

---

## Apêndice A — Manifestação do Destinatário automática (diferencial cego do Mubsys)

> Adicionado 2026-05-09 após pivot ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).
> Use este apêndice se o gap declarado do cliente é **manifestar NF-e recebidas** (não emitir).

### O problema escondido

Sua empresa **recebe** NF-e de fornecedores. A SEFAZ exige que você **manifeste** (Confirmação da Operação) cada nota dentro de **180 dias**. Sem isso:
- Risco de restrição de IE (caso destinatário obrigatório)
- Falta de rastro fiscal pro contador
- Risco de fornecedor cancelar nota e você não saber

Mubsys/Bling/Omie te empurram NF-e emitida — mas **manifestar o que vem de fornecedor** é manual, painel SEFAZ, fora do ERP.

### Como o oimpresso resolve

1. **Distribuição DFe automática** — todo dia 06h o oimpresso conversa com SEFAZ ambiente nacional e baixa **todos** os XMLs emitidos contra seu CNPJ. Sem depender de email do fornecedor.
2. **UI com countdown** — cada NF-e recebida mostra: dias restantes pra Confirmação (verde > 30d, amarelo ≤ 30d, vermelho ≤ 7d).
3. **Bulk Confirmar** — operadora marca 50 notas em 1 clique, oimpresso envia 50 eventos 220 pra SEFAZ. Mubsys faz 1 a 1.
4. **Audit log** — quem manifestou o quê, quando, com qual cert. Auditoria pronta.

### Exemplo de cenário Gold

| Operação | Tempo Mubsys | Tempo oimpresso |
|---|---|---|
| Receber 50 NFes do fornecedor por mês | XML manual via email + painel SEFAZ | automático às 06h |
| Confirmar 50 NFes | ~30min/dia × 22 dias = **11h/mês** | bulk 1 clique = **2min/mês** |
| Risco prazo 180d | sem alerta | countdown vermelho ≤ 7d |
| Auditoria contábil | manual | activity_log filtrado |

**Economia estimada:** ~10h/mês de operação. À R$ [redacted Tier 0]/h = **R$ [redacted Tier 0]/mês economia operacional**.

### Pricing apêndice manifestação

Se o cliente tem **só** demanda de manifestação (sem emitir):
- **R$ {{XXX}}/mês** SaaS — ilimitado em recebidos + 1 cert + suporte P2
- OU **R$ {{XXXX}} one-time** on-prem + manutenção anual

Se tem manifestação **e** emissão NF-e 55: pacote único Plano Enterprise R$ [redacted Tier 0]/mês (incluso) ou on-prem one-time conforme Opção A do corpo principal.

### Cronograma adaptado (caso só manifestação — 7 dias)

| Dia | Fase | Entregável |
|---|---|---|
| D+0 | Aceite | Contrato + 50% antecipado |
| D+1-2 | Discovery + upgrade | Manifestação módulo ativo |
| D+3 | Configuração cert + DistribuicaoDFe | Job rodando 06h diário |
| D+4 | Smoke homologação | 1ª Confirmação cstat 135 |
| D+5-7 | Treinamento + cutover prod | Operadora batendo bulk Confirmar |
