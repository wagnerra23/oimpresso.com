# ADR UI-0001 (RecurringBilling) · Portal B2C self-service white-label

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: ui
- **Relacionado**: ARQ-0001, US-RB-005

## Contexto

Cliente final (assinante) hoje precisa **falar com Larissa-financeiro** pra:
- Atualizar cartão antes de expirar
- Baixar 2ª via de fatura passada
- Cancelar assinatura
- Ver histórico de cobranças
- Mudar de plano

Cada tarefa = 1 ticket suporte. Tenant com 100 contratos = ~30 tickets/mês só dessa categoria.

Soluções de mercado:
- **Stripe Customer Portal** — hospedado pela Stripe; chega configurado
- **Lago Customer Portal** — open source, customizável
- **Recurly** — completo, caro

oimpresso quer:
- White-label (tenant aparece como dono — não "powered by oimpresso")
- Brasileiro (PT-BR, Pix Automático nativo, NFSe download)
- Customizável (tenant Pro → cores; tenant Enterprise → subdomain)

## Decisão

**Portal Inertia + React white-label, com 2 tiers de customização:**

| Tier | Branding | Domínio | Customização |
|---|---|---|---|
| **Starter / Pro** | Logo do tenant + cores primária/secundária | `portal.oimpresso.com/{tenant-slug}` | Limitada |
| **Enterprise** | Tudo customizável + favicon | `billing.{cliente-domain}.com` (subdomain CNAME) | Completa |

Telas mínimas (sequência de aparição):

1. **Login** — magic link via e-mail (sem senha) ou Pix-link assinado
2. **Dashboard** — próxima cobrança + status assinatura + histórico curto
3. **Cartões/Métodos** — adicionar/atualizar/remover cartão; ativar Pix Automático
4. **Faturas** — listagem + download PDF + NFSe
5. **Plano** — upgrade/downgrade self-service (com proração explicada)
6. **Cancelar** — wizard com motivo + confirmação + opção pause vs cancel

Stack:
- **Inertia + React 19** (mesmo do oimpresso admin) — reusa stack
- **Componentes shadcn/ui** com tema configurável por tenant via CSS variables
- **Tailwind 4** — CSS custom properties pra cores tenant
- **TanStack Query** — fetch dados do cliente
- **Auth** — Sanctum tokens via magic link

## Magic link (auth flow)

```
1. Cliente acessa portal.oimpresso.com/{tenant-slug}
2. Digita e-mail
3. Sistema envia link com token JWT (15 min validade)
4. Cliente clica → autenticado por sessão
5. Sessão dura 7 dias
```

Sem senha = menos atrito + maior segurança (sem reset password / phishing).

## Consequências

**Positivas:**
- Suporte tenant cai 50%+ (90% das tarefas viram self-service)
- Cliente final tem experiência moderna (não precisa ligar nem mandar email)
- Branding tenant: cliente vê tenant, não oimpresso
- Pix Automático fica fácil de ativar (UI dedicada vs ligar pra suporte)
- Métricas: % cancelamentos, tempo médio retenção, motivos comuns

**Negativas:**
- Custo de UI: 6 telas × responsivo × dark mode = ~3 semanas de design+frontend
- Multi-domain (Enterprise): SSL Let's Encrypt automático + CNAME setup → docs claras pro tenant
- Magic link depende de e-mail funcionar — fallback (suporte humano) sempre disponível
- White-label tem limites: footer "Powered by oimpresso" só some no Enterprise

## Pattern de tema

```tsx
// resources/js/Pages/Portal/Layout.tsx
function PortalLayout({ tenant, children }: Props) {
  return (
    <div style={{
      '--brand-primary': tenant.colors.primary,
      '--brand-secondary': tenant.colors.secondary,
    } as CSSProperties}>
      <PortalHeader logo={tenant.logo_url} />
      {children}
      <PortalFooter showPoweredBy={tenant.plan !== 'enterprise'} />
    </div>
  );
}
```

## Tests obrigatórios

- Login magic link (geração + validação token + expiração 15min)
- Dashboard sob auth + scope correto (cliente A não vê dados de B)
- Atualizar cartão — chama provider tokenize via iframe (nunca envia PAN)
- Cancelar — wizard com motivo + audit log
- Component test (Vitest) — temas tenant aplicados

## Métricas a observar (post-launch)

- % suporte tickets de "atualizar cartão" (meta: -80%)
- Tempo médio resolução task (era ~24h via humano; meta < 5 min self-service)
- NPS portal — feedback in-app
- Adoção Pix Automático via portal (meta: 30% dos pagantes)

## Decisões em aberto

- [ ] Auth fallback OAuth (Google/Apple) ou só magic link?
- [ ] Push notifications (lembretes vencimento)?
- [ ] App móvel nativo ou só responsivo?
- [ ] Onda de implementação: Pro tem portal já na Onda 2? Ou só Onda 5?

## Alternativas consideradas

- **Sem portal** — rejeitado: tenant vai pedir; oportunidade de revenue
- **Stripe Customer Portal** — rejeitado: branding limitado, US-centric, cobra
- **Lago Portal** — promissor; pode ser inspiração mas Lago não cobre Pix Automático BR
- **Apenas e-mail templates customizáveis** — rejeitado: não cobre atualização de cartão

## Referências

- ARQ-0001 (sub-módulos event-driven)
- Stripe Customer Portal (referência rejeitada)
- Lago (https://github.com/getlago/lago) — referência
- `auto-memória: preference_persistent_layouts.md` — Inertia pattern
