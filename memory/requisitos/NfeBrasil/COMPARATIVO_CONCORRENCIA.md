# NfeBrasil — Comparativo Concorrência (estilo Capterra)

**Última atualização:** 2026-04-25 | **Próx. revisão:** 2026-07-25

## Sobre o módulo

| Campo | Valor |
|---|---|
| **Best for** | "PMEs varejo/serviços que emitem NF-e/NFC-e/NFS-e e já usam UltimatePOS" |
| **Setor** | Fiscal BR — emissão NF-e + integração SEFAZ |
| **Stage** | Spec-ready (sem código), promovido 2026-04-24 |
| **Persona** | Larissa-fiscal + Contador + ROTA LIVRE (NFC-e por venda) |
| **JTBD** | "Emitir NF-e/NFC-e válida na hora da venda sem ter que abrir outro sistema" |

## Cards comparados

### 🟢 NfeBrasil (oimpresso)
- ⭐ **Score:** 0/100 (não implementado)
- 💰 **Preço planejado:** R$ 49 Pro / R$ 149 Enterprise
- 🎯 **Best for:** Quem já usa UPos POS — emissão integrada na venda
- ✨ **Diferencial planejado:** Emissão SEM sair da tela de venda
- 📱 **Mobile:** ⚠ via UPos
- ☁️ **Deploy:** Cloud (UPos hosted)

### 🔴 TecnoSpeed
- ⭐ **Capterra:** 4,4/5 (~80 reviews)
- 💰 R$ 0,15-0,40 por nota emitida (volume) ou pacotes R$ 99+
- 🎯 **Best for:** Médios+ que emitem 1k+ notas/mês
- ✨ **Diferencial:** Volume infinito, infra robusta
- ☁️ Cloud + on-prem

### 🔴 Plug NotaFiscal
- ⭐ **Capterra:** 4,3/5 (~50 reviews)
- 💰 R$ 0,12-0,35 por nota
- 🎯 **Best for:** Marketplace + multi-emissor
- ✨ **Diferencial:** Multi-CNPJ + intermediação fiscal
- ☁️ Cloud

### 🟡 FocusNFE
- ⭐ **Capterra:** 4,2/5 (~40 reviews)
- 💰 R$ 0,10 por nota (mais barato)
- 🎯 **Best for:** PME bootstrap, tech-savvy (API-first)
- ✨ **Diferencial:** API limpa, dev-friendly
- ☁️ Cloud

### 🟡 NFE.io (Conta Azul)
- ⭐ **Capterra:** 4,1/5 (~60 reviews)
- 💰 Bundle Conta Azul (a partir de R$ 87)
- 🎯 **Best for:** Quem já usa Conta Azul
- ✨ **Diferencial:** Bundle financeiro
- ☁️ Cloud

## Matriz de features

| Feature | 🟢 Nós | TecnoSpeed | Plug | FocusNFE | NFE.io | Importância |
|---|---|---|---|---|---|---|
| NF-e | ❌ planejado | ✅ | ✅ | ✅ | ✅ | **P0** |
| NFC-e | ❌ planejado | ✅ | ✅ | ✅ | ✅ | **P0** ROTA LIVRE |
| NFS-e | ❌ Onda 2 | ✅ | ✅ | ✅ | ✅ | P1 |
| Emissão na tela de venda UPos | ✅ planejado killer | ❌ | ❌ | ❌ | ⚠ | **diferencial** |
| Cancelamento NF-e | ❌ | ✅ | ✅ | ✅ | ✅ | P0 |
| Carta de correção | ❌ | ✅ | ✅ | ✅ | ✅ | P1 |
| MDFe | ❌ | ✅ | ⚠ | ⚠ | ❌ | P2 |
| Inutilização | ❌ | ✅ | ✅ | ✅ | ✅ | P0 |
| API REST | ❌ | ✅ | ✅ | ✅ killer | ✅ | P1 |

## Score (Capterra-style)

| Critério | 🟢 Nós | TecnoSpeed | Plug | FocusNFE |
|---|---|---|---|---|
| Easy of use | 0 | 7 | 7 | **9** |
| Customer service | 0 | **9** | 8 | 7 |
| Features | 0 | **9** | 9 | 8 |
| Value for money | 0 | 7 | 8 | **9** |
| Integrations | 0 | 8 | 8 | **9** |
| Performance | 0 | **9** | 8 | 8 |
| **Total /60** | **0** | **49** | **48** | **50** |
| **Score /100** | **0** | **82** | **80** | **83** |

## Estratégia

### Posicionamento (planejado)
> _"NF-e direto da tela de venda — sem alt-tab, sem dupla digitação."_

### Track imitar
- **MVP:** NF-e + NFC-e + cancelamento + inutilização
- **Onda 2:** NFS-e + carta de correção
- **Onda 3:** API REST pública + MDFe

### Track diferenciar
- **Integração POS nativa** (única no mercado BR)
- **Emissão na tela de venda** sem sair do contexto

### Preço
- Pacote bundle UPos+NfeBrasil = R$ 49/mês ilimitado (até 500 notas/mês)
- Pay-per-note R$ 0,08 acima de 500 (vs FocusNFE R$ 0,10)

## Refs

- [TecnoSpeed — Capterra](https://www.capterra.com.br/software/.../tecnospeed)
- [FocusNFE — capterra](https://www.capterra.com.br/.../focusnfe)
- ADR _Ideias/NfeBrasil + roadmap promoção 2026-04-24
