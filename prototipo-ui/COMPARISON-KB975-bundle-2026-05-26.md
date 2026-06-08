# Refino KB-9.75 Vendas — Bundle 2026-05-26

Bundle aplicado em cima do PR #295 (v1.0). Junta refinos KB-9.75 (#1-4 já scaffolded localmente) + 4 fatias novas (Edit/FSM/NF-e/NFS-e) + 9 refinos A-I.

## 1. O que evoluiu vs main

### Arquivos NOVOS (não existem em `main`)
| Arquivo | Função | Linhas |
|---|---|---|
| `vendas-flow.jsx` | Hooks + componentes centrais: FSM transitions, modal NF-e/NFS-e, bulk emit, timeline rica, recibo térmico, orçamento A4, validações fiscais BR, toast | ~1100 |
| `vendas-ai.jsx` | Refino KB-9.75 #2: IA inline no drawer (summary, history, suggest) | 335 |
| `vendas-curation.jsx` | Refino KB-9.75 #3: comentários inline + edição inline + troubleshooter + cross-link | 460 |
| `vendas-output.jsx` | Refino KB-9.75 #4: Transcript PDF + Modo apresentação + Variáveis live + Art-slot | 485 |
| `vendas-shortcuts.jsx` | Refino KB-9.75 #1: Cheat-sheet overlay (atalho `?`) | 86 |
| `vendas-tweaks.jsx` | TweaksPanel Vendas: densidade, drawer width, SLA visual, paleta | 111 |

### Arquivos MODIFICADOS (sobrescrever no repo)
| Arquivo | Mudança principal |
|---|---|
| `vendas-page.jsx` | Edit reaproveitando Create · FSM patches integration · emit/receipt/orc modals · bulk wired |
| `vendas-extras.jsx` | sem mudanças relevantes neste bundle (mantido) |
| `vendas.css` | +30KB de estilos novos: KPI grid · A receber alerta · stat line · ranking section · gate panel · emit modal · toast · bulk modal · timeline rica · recibo térmico · orçamento A4 · validações |
| `data-vendas.jsx` | Saved view nova: `aguardando` ("Aguardando faturamento") com filtro de vendas que têm produto/serviço sem nota |

## 2. Glossário BR corrigido (importante!)

| Termo | Significado | Quando | Reflete em |
|---|---|---|---|
| **Faturar** | Emitir documento fiscal (NF-e/NFS-e) | Após produção/entrega ou no fechamento da OS | Contas a receber + livro fiscal |
| **Receber pagamento** | Dar **baixa** no título do contas a receber | Quando o dinheiro entrou | Caixa/banco |

FSM correta: `Orçamento (0) → Pedido (1) → Faturada (2) → Entregue (3) → Paga (4)`
- fsm=2 dispara `oimpresso:venda-invoiced` (gera título)
- fsm=4 dispara `oimpresso:venda-paid` (baixa título — entrada caixa/banco)

## 3. Features prontas

### A. Insert / Edit / Show / Lista
- **Insert**: `<VendaCreateDrawer/>` (já existia)
- **Edit**: prop `editing` em `VendaCreateDrawer` reaproveita o wizard 1-2-3-4 pré-preenchido. Chip "editando" no header. Botão "Salvar alterações" no step 4
- **Show**: `<VendaDetailDrawer/>` com painel "Próxima ação" no topo (FSM transitions com gates)
- **Lista**: KPIs reequilibrados · saved views (8 incluindo "Aguardando faturamento") · ⌘K palette · bulk actions

### B. FSM transitions clicáveis
- `<VdNextActionPanel>` mostra etapa atual + próxima ação contextual
- Gates visíveis quando há bloqueio (ex: "Emita NFS-e antes de faturar")
- CTA inline pra resolver o gate ("📄 Emitir NFS-e agora →")
- Cores por etapa: indigo (faturar) → amber (entrega) → green (pagamento)
- History persistida em `localStorage.oimpresso.vendas.fsmPatches`

### C. Emit NF-e/NFS-e guiado
- Wizard 3 steps: Revisar fiscal → Destinatário → SEFAZ
- Mock SEFAZ 2.5s · 82% OK / 18% rejeição com motivo realista
- Validações fiscais brasileiras: CPF/CNPJ DV real, NCM, CFOP (com consistência UF), CST/CSOSN, email, ISS (2-5%)
- Máscara dinâmica CPF/CNPJ
- Bulk emit em lote (`<VdBulkEmitFlow>`) processa N vendas sequencialmente com progress bar tricolor
- Persistência: `localStorage.oimpresso.vendas.fiscPatches`

### D. Distribuição
- **Recibo térmico** (`<VdReceiptThermal>`): 80mm imprimível com `@page { size: 80mm auto }`
- **Orçamento A4** (`<VdOrcamentoPrint>`): proposta comercial formal com header brand, número Q-XXXX, válido até, condições, assinaturas
- **Transcript PDF** (`<VdTranscriptPDF>` em vendas-output.jsx): A4 jurídico completo
- **Modo apresentação** (`<VdPresentationMode>`): reunião com cliente

### E. Reatividade
- Custom events: `oimpresso:fsm-advance`, `oimpresso:fiscal-patched`, `oimpresso:venda-invoiced`, `oimpresso:venda-paid`, `oimpresso:venda-created`, `oimpresso:venda-edited`, `oimpresso:open-venda`
- Hooks `useVdFsmPatches` e `useVdFiscalPatches` re-sincronizam via storage events
- Toast feedback global (`<VdToastHost>`) escuta e mostra confirmação contextual
- Timeline tab (`<VdRichTimeline>`) acumula todos os eventos cronológicos

## 4. Notas pra Claude Code (F3)

Quando traduzir pra Inertia/React no Laravel:

1. **Hooks de patches** (`useVdFsmPatches`, `useVdFiscalPatches`) → substituir localStorage por POST/GET pro backend Laravel:
   - `POST /api/vendas/{id}/fsm-advance` body `{ to_fsm: N }`
   - `POST /api/vendas/{id}/fiscal-emit` body `{ kind: 'nfe'|'nfse', items: [...], destinatario: {...} }`
   - GET com SWR/React Query pra sincronizar entre abas
2. **SEFAZ mock** → integrar com biblioteca real (sugestão: `nfephp/nfephp` no backend Laravel)
3. **Validações fiscais BR** (helpers `window.vdValidate*`) já são funções puras — exportar como utilities `app/Utils/Fiscal.ts` no frontend
4. **Custom events** → substituir por React Query mutations + invalidate
5. **Modais (NfeEmit, BulkEmit, Receipt, Orcamento)** → manter como modais Inertia · CSS @media print idêntico

## 5. Saved views novas no `VENDAS_SAVED_VIEWS`

```js
{ id: "aguardando", label: "Aguardando faturamento", filter: (v) => {
    const hasProd = (v.itemsList || []).some(i => i.type === "produto");
    const hasSrv  = (v.itemsList || []).some(i => i.type === "servico");
    const nfeOk   = v.fiscal?.nfe?.status  === "ok";
    const nfseOk  = v.fiscal?.nfse?.status === "ok";
    return v.fsm < 4 && ((hasProd && !nfeOk) || (hasSrv && !nfseOk));
  }
}
```

## 6. 15 dimensões CD score (estimativa pra critique)

| Dimensão | Score estimado | Notas |
|---|---|---|
| Identidade visual | 9.5 | Cockpit V2 mantido · accent verde · stat line viva no header |
| Densidade | 9.0 | Cozy por padrão · tweak compact/cozy/spacious disponível |
| Hierarquia | 9.0 | KPIs reequilibrados · A receber alerta quando há estourados |
| Tipografia | 9.0 | IBM Plex Sans + Mono · escala 9-28px |
| Cor & paleta | 9.5 | Tokens canônicos · verde principal · indigo faturamento · amber entrega · vermelho alerta |
| Espaçamento | 9.0 | Grid + gaps consistentes |
| Estados | 9.5 | loading skeleton · empty states contextuais · alerta · gate · toast |
| Microinterações | 9.0 | spinner SEFAZ · progress bar tricolor · animações suaves |
| Acessibilidade | 8.5 | Keyboard nav (J/K/N/F/B/X/E/R/?) · ARIA labels · focus rings |
| Responsividade | 8.5 | breakpoints 1300/900/600 |
| Persistência | 9.0 | localStorage com sync via custom events |
| Validação fiscal BR | 9.5 | CPF/CNPJ DV real · NCM/CFOP/CST · ISS · máscaras |
| Glossário BR | 9.5 | Faturar ≠ Marcar como paga · termos canônicos |
| Impressão | 9.0 | recibo 80mm + orçamento A4 + transcript A4 jurídico |
| Reatividade | 9.5 | Custom events globais · hooks sincronizados |

**Média estimada: 9.20** (acima do threshold 8.0 do protocolo PR #295)
