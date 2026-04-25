# Template Capterra/G2 oimpresso — padrão pra comparativos competitivos

> **Como usar:** copia este template pra um arquivo novo em `memory/comparativos/<assunto>_<data>.md`,
> substitui `[PLACEHOLDERS]`, mantém a estrutura. Atualiza `_INDEX.md` da pasta com o novo doc.
>
> **Quando criar um novo comparativo:**
> - Antes de tomar decisão de roadmap que custe >2 sprints
> - Quando lançar/repensar pricing de um módulo
> - Quando entrar num vertical novo (não só CV — varejo, multi-loja, serviços)
> - Quando concorrente lança feature que pode ameaçar nosso posicionamento

---

## Frontmatter (obrigatório)

```markdown
> **Assunto:** [O QUE está sendo comparado — produto inteiro? Módulo X? Pricing?]
> **Data:** YYYY-MM-DD
> **Autor:** [Wagner / Claude / agente / consultor]
> **Concorrentes incluídos:** [lista — mín. 4, máx. 10]
> **Decisão que vai sair daqui:** [1 frase — qual decisão isso vai informar]
> **Companion docs:** [links pra outros comparativos relacionados]
```

---

## 1. TL;DR (5 frases — obrigatório)

5 frases, no máximo. Estrutura sugerida:
1. **Onde estamos hoje** (1 frase factual sem suavizar)
2. **Diferencial real** (o que temos que outros não têm bem feito)
3. **Onde perdemos contra grupo X** (genéricos, escalados, etc)
4. **Onde perdemos contra grupo Y** (verticais, especialistas)
5. **O dilema/escolha** que esse comparativo joga na mesa

---

## 2. Concorrentes incluídos

Tabela curta:

| Nome | URL | Tier de mercado | Observação relevante |
|---|---|---|---|
| [Nome 1] | [URL] | [Líder/Desafiante/Nicho/Genérico] | [+X usuários, Y anos, etc] |

Categorize em **mínimo 2 grupos**: vertical (do nicho específico) e benchmark (fora do nicho mas relevante de UX/preço).

---

## 3. Matriz Feature-by-Feature

**Legenda padrão (usar SEMPRE estes 4):**
- ✅ Tem completo (com qualidade)
- 🟡 Tem básico/limitado (presente mas com gambiarra)
- ❌ Não tem
- ❓ Não consegui confirmar (não exposto no site, sem reviews)

**Categorias padrão pra ERP/SaaS** (adapte ao contexto, mas mantenha a estrutura):

### Categoria 1 — Específico do nicho [NOME DO NICHO]
Features que SÓ esse vertical exige (ex: cálculo m² pra CV, escala 12x36 pra ponto, lote/validade pra alimentos).
4-6 features.

### Categoria 2 — Fiscal BR
NF-e, NFC-e, NFS-e, CT-e, MDF-e, SPED, regimes (Simples/MEI/LR).
6-8 features.

### Categoria 3 — Operacional core
PDV, Multi-loja, Estoque, Compras, CRM.
4-6 features.

### Categoria 4 — Financeiro
Contas P/R, Boletos CNAB, Conciliação, Antecipação, Pix, Conta digital.
5-7 features.

### Categoria 5 — Diferencial moderno
App mobile, API, BI, IA, Marketplace, Importação, Assinatura digital.
5-8 features.

### Categoria 6 — Operação SaaS
Onboarding, Suporte 24/7, WhatsApp, Treinamento, LGPD, Backup.
5-6 features.

**Total:** 30-40 features cobertas. Menos disso = comparativo raso. Mais disso = ninguém lê.

**Formato da tabela:**

| Feature | oimpresso | [Conc.1] | [Conc.2] | [Conc.3] | [Conc.4] | [Conc.5] | [Conc.6] |
|---|---|---|---|---|---|---|---|
| [Feature] | [✅/🟡/❌/❓] | ... | ... | ... | ... | ... | ... |

Sempre: **oimpresso PRIMEIRO** (coluna fixa pra leitura rápida).

---

## 4. Notas estimadas (escala G2 1-5)

4 critérios padrão (mantenha sempre):

| Critério | oimpresso | [Conc.1] | ... |
|---|---|---|---|
| Facilidade de uso | [nota] | [nota] | ... |
| Suporte | [nota] | [nota] | ... |
| Custo-benefício | [nota] | [nota] | ... |
| Específico pro nicho | [nota] | [nota] | ... |

**Regras pra dar nota:**
- Se tiver review G2/Capterra/B2BStack/App Store **com >50 reviews**, usa a nota deles
- Se for inferência minha, **anotar "(estimado)"** ao lado
- Escala: 5=excelente, 4=bom, 3=ok, 2=fraco, 1=ruim, "❓" se sem dados
- Se oimpresso ficar com nota chumbada (1-2) em algum critério, **assumir e listar nos GAPs**

---

## 5. Top 3 GAPS críticos (oimpresso vs concorrentes)

Pra cada gap:

### GAP N — [Nome curto e específico]
**O que falta:** [descrição factual + qual concorrente faz bem + métrica se tiver — "Mubisys processa 2M orçamentos/mês"]
**Esforço estimado:** [Baixo (<2sem) / Médio (2-6sem) / Alto (>6sem) / Muito alto (>3m)] + breakdown
**Impacto se não fechar:** [perde prospect X / perde mercado Y / churn aumenta]

**Apenas 3 gaps.** Mais que isso = sem prioridade.

---

## 6. Top 3 VANTAGENS reais

Mesma estrutura. Foco em:

### V1 — [Vantagem]
**Por que é vantagem:** [defensável por quanto tempo? Quem mais tem?]
**Como capitalizar:** [vira hero feature do site? alavanca de preço?]
**Risco de erodir:** [concorrente X pode replicar em 12m? IA commodifica?]

---

## 7. Posicionamento sugerido (mín. 3 caminhos)

Tabela:

| Caminho | Tese curta | Veredito |
|---|---|---|
| **A** — "[posicionamento]" | [como vamos competir] | ✅/❌ + por quê |
| **B** — "[posicionamento]" | [como vamos competir] | ✅/❌ + por quê |
| **C** — "[posicionamento]" | [como vamos competir] | ✅/❌ + por quê |

**Recomendado: [A/B/C].**
**Frase de posicionamento que sai dessa decisão:** [tagline literal pra usar no site]

---

## 8. Math da meta

Sempre conectar com **ADR 0022** (R$5mi/ano).

Template:
- Meta = R$5mi/ano = R$417k/mês de MRR
- Ticket médio assumido: R$[valor]/mês (justificar — por que esse?)
- Clientes necessários: [cálculo]
- Churn assumido: [3%/mês default]
- Funil necessário: [cálculo]
- Realidade hoje: [N clientes ativos / R$X MRR]
- **Gap:** [N clientes em N meses = N novos/mês]

Ser explícito sobre **assunções não validadas** (ex: "ticket médio R$497 ainda não validado em prospect real").

---

## 9. Recomendação concreta

Estrutura obrigatória:

### N features prioritárias pra construir nos próximos 6 meses (em ordem)
1. **[Feature]** — [por que é #1] **[N sprints]**
2. **[Feature]** — [por que é #2] **[N sprints]**
3. **[Feature]** — [por que é #3] **[N sprints]**

Máximo 3 features. Mais é dispersão.

### O que NÃO fazer agora
Lista do que **explicitamente** vamos adiar/cortar (com motivo).

### Métrica de fé (90 dias)
"Se em 90 dias [X] estiver pronto e [Y métrica observável] acontecer, **confirma a tese**. Senão, **pivota pra [caminho alternativo]**."

---

## 10. Sources

Sempre listar URLs literais (não "vi no Google"). Mín. 1 por concorrente + 1 source de regulação/tendência se relevante.

```markdown
- [Nome] (URL)
- [Reviews G2/Capterra/B2BStack se houver]
- [Notícias/blog regulatório se relevante — ex: SINIEF CT-e]
```

---

## Convenções de naming dos arquivos

- Comparativo de produto inteiro: `[produto]_vs_concorrentes_capterra_YYYY_MM_DD.md`
- Comparativo de módulo específico: `[modulo]_vs_concorrentes_capterra_YYYY_MM_DD.md`
- Research só de marketing/copy: `site_marketing_concorrentes_[nicho]_YYYY_MM_DD.md`
- Template/padrão: prefixo `_TEMPLATE_` ou `_INDEX_`

---

## Checklist antes de commitar

- [ ] TL;DR cabe em 5 frases?
- [ ] Mín. 4 concorrentes incluídos?
- [ ] 30+ features na matriz?
- [ ] Notas escala 1-5 preenchidas com source ou marcado "(estimado)"?
- [ ] **Exatamente 3 GAPS e 3 VANTAGENS** (não mais, não menos)?
- [ ] **Mín. 3 caminhos de posicionamento** com veredito?
- [ ] Math da meta R$5mi feito?
- [ ] **3 features prioritárias** em ordem (não mais)?
- [ ] **Métrica de fé** com prazo (90d) e gatilho de pivot?
- [ ] Sources literais com URL?
- [ ] Companion docs linkados no frontmatter?

Se fechar todos os checks: comita. Se não, **comparativo está raso e não vai informar decisão**.

---

**Versão do template:** 1.0 (2026-04-25)
**Próxima revisão:** após 3º comparativo usando este template — ajustar o que travou.
