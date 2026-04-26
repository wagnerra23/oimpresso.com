# Spec: Login + Cadastro profissional — oimpresso.com

> **Criado:** 2026-04-26 via skill `/design-system`
> **Alvo:** trigger remoto `trig_01CVAhiLRSS5Tt6YRDyKGcjL` (Cms PR3, Opus, agendado 2026-04-29) — quando agente clonar repo, ler este arquivo na Parte C "Login Social + Cadastro Facil".
> **Stack:** React + Inertia v3 + Tailwind v4 + shadcn-like (`@/Components/ui/*`)

## 1. Decisão de hierarquia: **Social-first**

```
┌─────────────────────────────────┐
│      [logo "oi" oimpresso]      │
│                                 │
│   Bem-vindo de volta            │
│   Acesse com sua conta          │
│                                 │
│  ┌──────────────────────────┐   │
│  │ [G] Continuar com Google │   │ ← PRIMÁRIO
│  └──────────────────────────┘   │
│  ┌──────────────────────────┐   │
│  │ [▦] Continuar com        │   │ ← SECUNDÁRIO
│  │     Microsoft            │   │
│  └──────────────────────────┘   │
│                                 │
│  ─────────── ou ────────────    │
│                                 │
│   E-mail                        │
│   ┌──────────────────────────┐  │
│   │ voce@empresa.com         │  │
│   └──────────────────────────┘  │
│                                 │
│   Senha               [olho]    │
│   ┌──────────────────────────┐  │
│   │ ••••••••                 │  │
│   └──────────────────────────┘  │
│                                 │
│              [Esqueci a senha]  │
│  ┌──────────────────────────┐   │
│  │       Entrar             │   │ ← CTA primary
│  └──────────────────────────┘   │
│                                 │
│  Não tem conta? Cadastre-se     │
└─────────────────────────────────┘
   max-w-md, centrado vertical
```

**Justificativa social-first:** ICP é dono de PME/gráfica e operadores. Google onipresente em Workspace BR, Microsoft em corporativo. Social cobre 80% da fricção de signup; senha é fallback. Vercel, Linear, Notion seguem esse padrão. Stripe inverte (form-first) porque mira dev — não é nosso caso.

## 2. Botões sociais — branding guidelines oficial

| Provider | Background | Texto | Ícone | Borda | Hover | Ordem |
|---|---|---|---|---|---|---|
| **Google** | `bg-white` (mantém branco em dark mode) | `text-[#1f1f1f]` | G 4-cor oficial (azul/vermelho/amarelo/verde) | `border-[#dadce0]` 1px | `bg-[#f8f9fa]` + sombra leve | 1º |
| **Microsoft** | `bg-white` (mantém branco em dark mode) | `text-[#5e5e5e]` | 4 quadrados (vermelho/verde/azul/amarelo) | `border-[#8c8c8c]` 1px | `bg-[#f3f3f3]` | 2º |
| **Apple** | `bg-black` / `dark:bg-white` (inverte) | branco / preto | maçã monocromática | sem borda | opacidade 90% | 3º opcional |
| **GitHub** | `bg-[#24292f]` / `dark:bg-white` | branco / `text-[#24292f]` | octocat | sem borda | `bg-[#1a1f25]` | 4º opcional |

**Crítico:** Google e Microsoft **mantém fundo branco mesmo em dark mode** (regra de branding). Apple e GitHub invertem.

**Ordem default oimpresso:** Google + Microsoft. Apple/GitHub adiar pra próximo PR.

## 3. Form e-mail/senha: **Label fixo + helper inline**

Floating label (Material 3) perdeu popularidade 2024-2026. Stripe, Linear, Vercel voltaram pra label fixo — a11y melhor, density igual.

| Estado | Visual |
|---|---|
| Default | `border-input` (slate-200), label slate-700, placeholder slate-400 |
| Focus | `border-primary` ring `ring-2 ring-primary/30` |
| Filled válido | igual default |
| Erro | `border-destructive` + ícone alert + msg vermelha abaixo |
| Disabled | `opacity-50 cursor-not-allowed` |

**Validação:** inline pós-blur (não enquanto digita) + mostrar erro também onSubmit. Senha tem botão "olho" pra revelar (estado controlado, ícone lucide `Eye`/`EyeOff`).

## 4. Layout responsivo

- **Mobile (< 640px):** card `max-w-sm` com `px-6 py-8`, SiteLayout sem header (só footer simplificado).
- **Desktop (≥ 640px):** card `max-w-md`, centrado vertical (`min-h-screen flex items-center`), com SiteLayout completo.
- **Spacing scale:** `gap-3` entre social buttons; `gap-4` entre social group → divider → form; `gap-1.5` em label→input; `mt-2` em error msg.

## 5. Estados de submit

- **Loading:** botão social/CTA mostra `<Loader2 className="animate-spin h-5 w-5" />` à esquerda do texto, `disabled`. Outros botões da página também `disabled`.
- **Error:** **inline acima do form** com `<Alert variant="destructive" role="alert">`, NÃO toast. Mensagens em PT-BR humano:
  - "Não encontramos uma conta com esse e-mail. **Cadastre-se grátis**" (link)
  - "Senha incorreta. **Esqueceu?**" (link)
- **Success:** redirect imediato pra `/home` com flash success. Sem alert intermediário.

## 6. Microcopy (PT-BR)

| Onde | Copy |
|---|---|
| Título login | **Bem-vindo de volta** |
| Sub login | Acesse com sua conta do oimpresso |
| Título cadastro | **Comece em 1 clique** |
| Sub cadastro | Sem cartão. Sem fidelidade. |
| Botão social | **Continuar com [Provider]** (neutro pra login E cadastro) |
| Divisor | **ou** (lowercase, `text-muted-foreground text-xs`) |
| Label e-mail | E-mail |
| Label senha | Senha |
| CTA login | **Entrar** |
| CTA cadastro | **Criar conta grátis** |
| Forgot pwd | Esqueci a senha |
| Switch login→cadastro | Não tem conta? **Cadastre-se** |
| Switch cadastro→login | Já tem conta? **Entrar** |
| Pós OAuth toast | "Conta criada e workspace pronto. Bem-vindo!" |

## 7. Cadastro fácil — sinalização visual

Pra reforçar "1 clique = workspace pronto" pós OAuth:

- **Badge no botão Google na tela /register:** `Recomendado · 1 clique` (top-right do botão, primary background)
- **Subhead na /register:** "Sem cartão. Sem fidelidade." — confiança imediata
- **Pós OAuth flow:** toast "Workspace criado em 2s" + onboarding contextual no /home (não interromper com modal)

## 8. Component tree

```
<SiteLayout title="Entrar">
  <main className="flex min-h-[calc(100vh-4rem)] items-center justify-center px-4 py-12">
    <Card className="w-full max-w-md p-8">
      <LoginHeader mode="login" />
      <SocialButtonsGroup>
        <SocialButton provider="google" recommended={mode === 'register'} />
        <SocialButton provider="microsoft" />
      </SocialButtonsGroup>
      <Divider>ou</Divider>
      <EmailPasswordForm mode="login" />
      <ModeSwitcher mode="login" />
    </Card>
  </main>
</SiteLayout>
```

`/register` reusa tudo, só muda `mode="register"` que troca microcopy + adiciona campo "Nome".

## 9. TSX referência — `SocialButton.tsx`

```tsx
import { Loader2 } from 'lucide-react';
import { GoogleIcon, MicrosoftIcon, AppleIcon, GitHubIcon } from './icons/SocialIcons';

type Provider = 'google' | 'microsoft' | 'apple' | 'github';

const PROVIDER_CONFIG: Record<Provider, {
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  classes: string;
}> = {
  google: {
    label: 'Continuar com Google',
    icon: GoogleIcon,
    classes:
      'bg-white text-[#1f1f1f] border border-[#dadce0] hover:bg-[#f8f9fa] hover:shadow-sm dark:bg-white dark:text-[#1f1f1f]',
  },
  microsoft: {
    label: 'Continuar com Microsoft',
    icon: MicrosoftIcon,
    classes:
      'bg-white text-[#5e5e5e] border border-[#8c8c8c] hover:bg-[#f3f3f3] dark:bg-white dark:text-[#5e5e5e]',
  },
  apple: {
    label: 'Continuar com Apple',
    icon: AppleIcon,
    classes: 'bg-black text-white hover:bg-black/90 dark:bg-white dark:text-black',
  },
  github: {
    label: 'Continuar com GitHub',
    icon: GitHubIcon,
    classes: 'bg-[#24292f] text-white hover:bg-[#1a1f25] dark:bg-white dark:text-[#24292f]',
  },
};

interface SocialButtonProps {
  provider: Provider;
  isLoading?: boolean;
  recommended?: boolean;
}

export default function SocialButton({ provider, isLoading, recommended }: SocialButtonProps) {
  const cfg = PROVIDER_CONFIG[provider];
  const Icon = cfg.icon;
  return (
    <a
      href={`/auth/${provider}/redirect`}
      className={`relative inline-flex w-full items-center justify-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 ${cfg.classes} ${isLoading ? 'pointer-events-none opacity-70' : ''}`}
      aria-label={cfg.label}
      data-loading={isLoading}
    >
      {isLoading ? (
        <Loader2 className="h-5 w-5 animate-spin" />
      ) : (
        <Icon className="h-5 w-5 shrink-0" />
      )}
      <span>{cfg.label}</span>
      {recommended && (
        <span className="absolute -top-2 right-3 rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground">
          1 clique
        </span>
      )}
    </a>
  );
}
```

**Ícones SVG inline** em `Components/Site/icons/SocialIcons.tsx` — baixar SVG oficial de:
- Google G 4-cor: https://developers.google.com/static/identity/images/g-logo.png (versão SVG no zip de branding)
- Microsoft 4 quadrados: https://learn.microsoft.com/en-us/azure/active-directory/develop/howto-add-branding-in-apps
- Apple/GitHub: lucide-react já tem variantes monocromáticas suficientes

## 10. Acessibilidade (WCAG 2.1 AA)

- ✅ **Focus rings visíveis** em todos elementos: `focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2`
- ✅ **Tab order**: logo → social Google → social MS → e-mail → senha → revelar senha → forgot → submit → mode switch
- ✅ **Aria labels**: cada botão social `aria-label="Continuar com [Provider]"`; botão revelar senha `aria-label="Mostrar/Ocultar senha"` toggle
- ✅ **Contraste**: Google `#1f1f1f` sobre `#fff` = 16.7:1; Primary `hsl(221.2 83.2% 53.3%)` sobre branco = 4.6:1 ✓ (AA limite 4.5:1)
- ✅ **Heading hierarchy**: `<h1>` "Bem-vindo de volta", form sem h2 (uso de labels associados via `htmlFor`)
- ✅ **Erro a11y**: `<Alert role="alert" aria-live="polite">` pra erros submit; inputs em erro têm `aria-invalid="true"` + `aria-describedby="email-error"`
- ✅ **Touch targets**: botões mínimo `h-10` (40px), recomendado `py-2.5` = ~44px (Apple HIG)
- ✅ **Dark mode contraste**: testar com `prefers-color-scheme: dark` — primary fica em `hsl(217.2 91.2% 59.8%)` mantendo AA sobre slate-900
- ✅ **prefers-reduced-motion**: respeitar via `useReducedMotion()` em qualquer animation (loader spinner pode ficar)

## 11. Tokens usados

| Token | Onde |
|---|---|
| `--color-primary` | CTA "Entrar"/"Criar conta", focus ring, badge "1 clique" |
| `--color-card` | Background do card |
| `--color-border` | Borda do card e inputs |
| `--color-foreground` / `--color-muted-foreground` | Textos |
| `--color-destructive` | Erros |
| `--radius-lg` | Card e botões |

**Hardcoded** (necessário, são branding oficial dos providers): `#dadce0`, `#1f1f1f`, `#f8f9fa`, `#5e5e5e`, `#8c8c8c`, `#f3f3f3`, `#24292f`, `#1a1f25`. Comentar no código:
```tsx
// hardcoded: Google branding guidelines (developers.google.com/identity/branding-guidelines)
```

## 12. Checklist pro trigger Cms PR3

- [ ] `Pages/Site/Login.tsx` + `Pages/Site/Register.tsx` (compartilham componentes via `mode` prop)
- [ ] `Components/Site/auth/SocialButton.tsx` (TSX acima)
- [ ] `Components/Site/auth/SocialButtonsGroup.tsx` (wrapper `flex flex-col gap-3`)
- [ ] `Components/Site/auth/EmailPasswordForm.tsx` (useForm Inertia, mode-aware)
- [ ] `Components/Site/auth/Divider.tsx` (linha + texto centralizado)
- [ ] `Components/Site/auth/ModeSwitcher.tsx` (link entre login/register)
- [ ] `Components/Site/auth/LoginHeader.tsx` (logo + título + sub)
- [ ] `Components/Site/icons/SocialIcons.tsx` (SVGs Google + MS oficiais inline; Apple + GitHub stub pra futuro)
- [ ] Pest test: a11y axe-core scan, submit happy path, erro inline visível
- [ ] Dark mode testado nos 2 estados de cada botão social
- [ ] Mobile responsive: 375px (iPhone SE) e 768px+ (tablet)
- [ ] Cache/estado preservado: `<Link preserveScroll>` em ModeSwitcher (transição login↔register sem reload total)

## 13. Referências externas

- [Google Sign-in branding](https://developers.google.com/identity/branding-guidelines)
- [Microsoft brand guidelines](https://learn.microsoft.com/en-us/azure/active-directory/develop/howto-add-branding-in-apps)
- Vercel Login (vercel.com/login) — social-first moderno
- Linear Login (linear.app/login) — density e dark mode
- Stripe Dashboard login — form-first (oposto, educacional)

## 14. Conexão com decisões existentes

- **ADR 0025** — redesign Cms Inertia/React: este login é PR3 do roadmap
- **ADR 0026** — posicionamento "ERP gráfico com IA": login profissional reforça "estado da arte"
- **`preference_persistent_layouts.md`** — Component.layout pattern (NÃO envolver em SiteLayout no return; usar `Login.layout = (page) => <SiteLayout>{page}</SiteLayout>`)
- **`preference_cache_estado_preservado.md`** — Links com preserveScroll/preserveState
- **`feedback_testes_com_nova_feature.md`** — Pest test obrigatório

---

**Versão:** 1.0 · **Última revisão:** 2026-04-26
