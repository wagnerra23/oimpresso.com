# Chat — Comparativo Concorrência (estilo Capterra)

> Módulo perdido na migração 6.x; revisitar quando reanimar.

**Última atualização:** 2026-04-25 | **Próx. revisão:** 2026-07-25

## Sobre o módulo

| Campo | Valor |
|---|---|
| **Best for** | "PMEs que querem chat ao vivo no e-commerce + atendimento WhatsApp unificado" |
| **Setor** | Live chat / customer support |
| **Stage** | Perdido na migração; revisitar prioridade |
| **Persona** | Atendente loja + visitante site |
| **JTBD** | "Atender visitante do site na hora + manter histórico para vendedor" |

## Cards comparados

### 🟢 Chat (oimpresso)
- ⭐ **Score:** 0/100 (perdido na migração)
- 💰 Bundled UPos (planejado)
- 🎯 **Best for:** Tenant UPos com e-commerce
- ✨ **Diferencial planejado:** Histórico cross-canal + integração lead UPos

### 🔴 Intercom
- ⭐ **Capterra:** 4,5/5 (~3000 reviews)
- 💰 US$ 39 Essential / US$ 99 Advanced / US$ 139 Expert
- 🎯 **Best for:** SaaS B2B mid-market
- ✨ **Diferencial:** Bots + product tours + helpdesk integrado

### 🔴 Crisp
- ⭐ **Capterra:** 4,4/5 (~600 reviews)
- 💰 Free / US$ 25 Pro / US$ 95 Unlimited
- 🎯 **Best for:** PME e-commerce
- ✨ **Diferencial:** Free tier real + WhatsApp + Instagram

### 🟡 Tawk.to
- ⭐ **Capterra:** 4,5/5 (~300 reviews)
- 💰 Free + add-ons pagos
- 🎯 **Best for:** Negócio pequeno bootstrap
- ✨ **Diferencial:** Free pra sempre

### 🟡 JivoChat
- ⭐ **Capterra:** 4,3/5 (~400 reviews BR)
- 💰 Free / R$ 39 Pro / R$ 89 Enterprise
- 🎯 **Best for:** PME BR
- ✨ **Diferencial:** Foco BR + WhatsApp + telefonia integrada

## Matriz de features

| Feature | 🟢 Nós | Intercom | Crisp | Tawk | JivoChat | Importância |
|---|---|---|---|---|---|---|
| Live chat widget | ❌ | ✅ | ✅ | ✅ | ✅ | **P0** |
| WhatsApp integrado | ❌ | ⚠ | ✅ | ❌ | ✅ killer BR | **P0 BR** |
| Bot básico | ❌ | ✅ | ✅ | ⚠ | ⚠ | P1 |
| Histórico unificado | ❌ planejado | ✅ | ✅ | ⚠ | ✅ | **P0** |
| Triggers (proativo) | ❌ | ✅ | ✅ | ⚠ | ✅ | P1 |
| Helpdesk integrado | ❌ | ✅ killer | ⚠ | ❌ | ⚠ | P2 |
| Mobile app atendente | ❌ | ✅ | ✅ | ✅ | ✅ | P1 |
| Lead captura → UPos | ✅ planejado | ❌ | ❌ | ❌ | ❌ | **diferencial** |

## Score (Capterra-style)

| Critério | 🟢 Nós | Intercom | Crisp | Tawk | Jivo |
|---|---|---|---|---|---|
| Easy of use | 0 | 8 | **9** | **9** | 8 |
| Features | 0 | **9** | 8 | 6 | 7 |
| Value for money | 0 | 5 | 8 | **10** | **9** |
| WhatsApp BR | 0 | 6 | 8 | 4 | **10** |
| Performance | 0 | 8 | 8 | 7 | 7 |
| Mobile | 0 | **9** | 8 | 7 | 8 |
| **Total /60** | **0** | **45** | **49** | **43** | **49** |
| **Score /100** | **0** | **75** | **82** | **72** | **82** |

## Estratégia

### Posicionamento (planejado)
> _"Live chat + WhatsApp integrado ao seu POS — visitante do site vira cliente cadastrado sem digitação dupla."_

### Recomendação
**NÃO REIMPLEMENTAR DO ZERO.** Integrar com Crisp ou JivoChat via API + capturar lead no UPos. Custo dev menor + qualidade live chat melhor que faríamos.

### Alternativas
1. **Integração-only:** webhook Crisp/Jivo → UPos contact_id (1 sprint)
2. **Build próprio:** 6+ meses dev pra paridade — não vale ROI
3. **Bundle Crisp** revenda? (parceria comercial)

## Refs

- [Intercom — Capterra](https://www.capterra.com.br/.../intercom)
- [Crisp — Capterra](https://www.capterra.com.br/.../crisp) 
- [JivoChat — Capterra](https://www.capterra.com.br/.../jivochat) (BR-focused)
