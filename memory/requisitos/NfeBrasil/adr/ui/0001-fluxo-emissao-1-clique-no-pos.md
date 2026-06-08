# ADR UI-0001 (NfeBrasil) · Fluxo de emissão 1-clique no POS, async via broadcast

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: ui
- **Relacionado**: US-NFE-002, R-NFE-005, R-NFE-012

## Contexto

Larissa-caixa em ROTA LIVRE finaliza ~50 vendas/dia em horário de pico (14h-18h). Cada venda dura em média 1 min — tempo de emissão fiscal não pode adicionar fricção.

Concorrentes BR (Tiny, Bling, Conta Azul) têm padrões diferentes:
- **Tiny:** clica "Finalizar" → bloqueia tela com spinner ~3s → mostra DANFE → imprime
- **Bling:** clica "Finalizar" → modal "Emitindo..." → após retorno SEFAZ, oferece imprimir
- **Mercado-livre/Hyperlocal:** finalizar não bloqueia; e-mail com DANFE depois

Trade-off:
- **Sync bloqueante:** UX previsível ("imprimi a nota") mas falha SEFAZ trava caixa
- **Async com broadcast:** UX otimista, finalize libera caixa, status emerge

ROTA LIVRE: SEFAZ-SP cai ~1x/mês. Larissa não pode parar.

## Decisão

**Async com broadcast Echo, UI otimista, fallback contingência.**

Fluxo:

```
┌─────────────┐  click   ┌──────────────────────┐
│ /sells/     │ "finalizar"│  POST sell.create   │
│  create     │ ───────▶ │  (transaction core)  │
└─────────────┘          └──────────────────────┘
                                    │ event TransactionCompleted
                                    ▼
┌──────────────────────────────────────────────────┐
│ Observer NfeBrasil dispatches EmitirNfceJob      │
│ (queue=nfe, idempotency=transaction_id)          │
└──────────────────────────────────────────────────┘
                                    │
                                    ▼
┌──────────────────────────────────────────────────┐
│ Frontend (sells/created.tsx) abre redirect:      │
│  ┌──────────────────────────────────────────┐   │
│  │ ✓ Venda salva (R$ 150,00)                │   │
│  │                                           │   │
│  │ [   Emitindo NFC-e...    ⟳   ]          │   │
│  │                                           │   │
│  │ [Continuar para nova venda] (libera caixa)│   │
│  └──────────────────────────────────────────┘   │
└──────────────────────────────────────────────────┘
                                    │ Echo channel: business.{id}.nfe-status
                                    ▼
                  ┌──────────────────────────┐
                  │ status=autorizada        │
                  │ DANFE PDF disponível     │
                  │ → atualiza modal: ✓      │
                  │ → imprime auto (config)  │
                  └──────────────────────────┘
                            OU
                  ┌──────────────────────────┐
                  │ status=rejeitada         │
                  │ cStat + sugestão         │
                  │ → modal vermelho         │
                  │ → CTA "Reemitir corrigido"│
                  └──────────────────────────┘
```

Princípios:

1. **Caixa libera imediatamente** após venda salva (botão "Continuar para nova venda" sempre clicável)
2. **Status emerge em background** via Echo (~2-5s normalmente)
3. **Auto-print habilitável** (default: imprime quando autorizada)
4. **Falha não bloqueia** — banner global "1 NFC-e pendente" + monitor
5. **Em contingência**: tag visual roxa + auto-print com aviso "DANFE contingência - sujeita transmissão"

## Consequências

**Positivas:**
- Caixa nunca para por causa de SEFAZ
- UX moderna (otimista) — Larissa não fica olhando spinner
- Falhas viram fila → resolvidas no monitor (`/nfe-brasil/monitor`)
- Reuso: padrão Echo já usado em outros módulos

**Negativas:**
- DANFE pode imprimir alguns segundos depois da venda → confunde cliente esperando recibo (mitigar: imprimir comprovante interno na hora + DANFE depois)
- Tenant precisa ter Echo/Reverb funcionando (config Pusher ou Reverb self-hosted)
- Falha de broadcast = status fica "preso" na UI → fallback: refresh manual recupera estado da tabela

## Pattern obrigatório

```tsx
// resources/js/Pages/NfeBrasil/Emissao/Status.tsx
function NfeStatusModal({ transactionId }: Props) {
  const { data, status } = useEchoChannel(`business.${businessId}.nfe-status`, {
    filter: (e) => e.transactionId === transactionId,
    timeoutMs: 30_000,
    fallback: () => fetch(`/nfe-brasil/emissoes/by-transaction/${transactionId}`)
  });

  if (status === 'pending') return <Skeleton />;
  if (status === 'autorizada') return <Success danfe={data.danfeUrl} autoPrint={true} />;
  if (status === 'rejeitada') return <Error cstat={data.cstat} sugestao={data.sugestao} />;
  if (status === 'contingencia') return <Contingency aviso="DANFE em contingência..." />;
  if (status === 'timeout') return <FallbackInstructions />;
}
```

## Tests obrigatórios

- `EmissaoAssincronaTest` (Backend) — venda + dispatch job + sem bloqueio
- `EchoBroadcastTest` (Backend) — autorizada → broadcast com payload correto
- Component test (Vitest) — modal renderiza cada estado
- E2E (Playwright) — finalizar venda → modal aparece → aguarda autorizada → DANFE link clicável

## Métricas a observar (post-launch)

- Tempo médio "venda → DANFE imprime" — meta < 5s p95
- % vendas com NFC-e em contingência — meta < 0.5%
- % vendas com NFC-e rejeitada — meta < 1% (configuração tributária boa)
- Abandono modal "rejeitada" sem reemitir — meta 0% (tenant tem que entender)

## Alternativas consideradas

- **Sync bloqueante** — rejeitado: SEFAZ down trava caixa
- **Sync com timeout 5s + fallback async** — rejeitado: 5s ainda parece muito ao olho da Larissa
- **Email-only DANFE** — rejeitado: legislação exige DANFE impresso na operação presencial
- **Imprimir DANFE contingência sempre** — rejeitado: DANFE em contingência tem custos pra resolver depois (caso melhor é online quando dá)

## Referências

- US-NFE-002, R-NFE-012 (SPEC)
- `auto-memória: cliente_rotalivre.md` — Larissa monitor 1280px, fluxo POS
- Tiny / Bling / Conta Azul — todas usam sync bloqueante (oportunidade de diferenciar)
