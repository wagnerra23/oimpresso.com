# ADR ARQ-0001 (NFSe) · Cliente é oimpresso (não ROTA LIVRE) + módulo standalone

- **Status**: accepted
- **Data**: 2026-04-30
- **Decisores**: Wagner (ordem direta)
- **Categoria**: arq
- **Substitui parcialmente**: `RecurringBilling/adr/arq/0002-nfse-submodulo-vs-nfebrasil.md` (que previa NFSe dentro de RecurringBilling)

## Contexto

ADR-0002 RecurringBilling (2026-04-24) decidia colocar NFSe como sub-módulo de `Modules/RecurringBilling/`. Sessão de planejamento 2026-04-30 trouxe correções de premissa que invalidam essa estrutura:

1. **Cliente alvo é a empresa oimpresso (Wagner)**, não ROTA LIVRE/Larissa — emissão de NFSe pela própria oimpresso pra cobrar mensalidades de software dos clientes do ERP. ROTA LIVRE NÃO usará NFSe.
2. **UltimatePOS já tem recorrência nativa** (`recurring_invoice`/`recurring_expense` em `app/Http/Controllers/SellPosController.php` e `ExpenseController.php`) — não precisa do módulo `RecurringBilling` pra isso.
3. **NFSe Nacional** (LC 214/2025) é o padrão alvo. Tubarão-SC (sede oimpresso) provavelmente já está no Sistema Nacional NFSe — confirmar como primeira tarefa Eliana.

Forçar NFSe dentro de RecurringBilling cria coupling com módulo que pode nem ser construído (UltimatePOS resolve recorrência), e mistura concerns (NFSe é fiscal puro; RecurringBilling seria orquestração de cobrança recorrente avançada).

## Decisão

**`Modules/NFSe/` é módulo standalone**, sem dependência de `Modules/RecurringBilling/`.

### Cliente alvo

| Negócio | Usa NFSe? |
|---|---|
| **oimpresso** (empresa Wagner, biz_id principal) | ✅ **SIM** — emite NFSe pra clientes finais do ERP |
| **ROTA LIVRE** (biz_id=4, Larissa) | ❌ **NÃO** — gráfica não emite NFSe pelo sistema |
| Outros tenants | configurável (default OFF) |

### Origem da venda (trigger emissão)

NFSe é gerada a partir de:

1. **Venda manual** — tela `/nfse/emitir` (form livre)
2. **Recurring invoice nativo UltimatePOS** — listener no fechamento do invoice → cria NFSe automaticamente
3. **Pagamento de boleto** — listener pode disparar emissão (opcional, configurável)

NÃO depende de `Modules/RecurringBilling/` (que segue como roadmap futuro pra cobrança recorrente avançada — assinaturas com gateway próprio, take rate, etc.).

### Owner & paralelização

- **Owner**: Eliana[E] (já é dona oficial de NFSe/Boletos/Recorrência conforme TEAM.md)
- **Paralelo a outras tasks Eliana** — não bloqueia Cycle 01 (foco Copiloto/Larissa)
- **Capacidade**: 2-4h/dia → ~3-5 semanas calendário pro MVP completo
- **Wagner supervisiona** decisão fiscal (provider, certificado A1) e UI

### Estrutura

```
Modules/NFSe/
├── Adapters/
│   ├── NfseProvider.php (interface)
│   ├── NfseNacionalAdapter.php (se Tubarão estiver no SN-NFSe)
│   └── FocusNFeAdapter.php (fallback municipal/ABRASF)
├── Services/
│   └── NfseEmissaoService.php
├── Models/
│   ├── NfseEmissao.php
│   └── NfseProviderConfig.php
├── Listeners/
│   └── EmitirNfseAposRecurringInvoice.php
├── Http/Controllers/NfseController.php
├── Database/Migrations/
│   ├── nfe_certificados.php (compartilhado, neutro pra futuro NfeBrasil)
│   ├── nfse_emissoes.php
│   └── nfse_provider_configs.php
└── Resources/views/ (se Blade) / resources/js/Pages/Nfse/ (Inertia)
```

## Consequências

### Positivas
- NFSe entrega valor pra oimpresso ASAP sem esperar RecurringBilling
- Não força criação de tenant Pro pra usar (ROTA LIVRE pode ficar OFF)
- ADR-0002 RecurringBilling fica para depois; quando RecurringBilling for construído, importa `Modules/NFSe/` como dep
- Eliana trabalha em paralelo sem bloquear Cycle 01

### Negativas
- ADR-0002 RecurringBilling fica parcialmente superseded (anotar no header dela)
- Listener `EmitirNfseAposRecurringInvoice` precisa hookar no `recurring_invoice` nativo do UltimatePOS — coupling leve com core (mitigável via event)

## Alternativas consideradas

- **Manter NFSe dentro de RecurringBilling** (ADR-0002 original) — REJEITADO: cliente é oimpresso, RecurringBilling não vai existir tão cedo, UPOS resolve recorrência
- **Usar Modules/NfeBrasil/ pra emitir NFSe** — REJEITADO: NfeBrasil é pra produto (NF-e/NFC-e/MDF-e); NFSe tem 5570 webservices municipais, escopo conflituoso. Mas **compartilha cert A1** via tabela `nfe_certificados` (neutra)

## Próximos passos

Lista de tarefas detalhada em [`SPEC.md`](../../SPEC.md). Owner: Eliana[E].

## Refs

- TEAM.md → Eliana[E] já lista NFSe/RecurringBilling
- ADR-0002 RecurringBilling (parcialmente superseded)
- LC 214/2025 — NFSe Nacional federal
- `app/Http/Controllers/SellPosController.php` — recurring_invoice nativo UPOS
