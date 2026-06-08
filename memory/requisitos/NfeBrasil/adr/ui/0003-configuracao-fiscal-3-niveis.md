# ADR UI-0003 (NfeBrasil) · Configuração fiscal em 3 níveis (Defaults / Regras / Por Produto)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: ui
- **Relacionado**: ARQ-0006 (cascade), US-NFE-010

## Contexto

Configuração tributária BR é **densa** (NCM, CST/CSOSN, ICMS, ICMS-ST, MVA, IPI, PIS, COFINS, CFOP, FCP, DIFAL...). Esmagadora pra tenant que está apenas tentando vender.

Concorrentes BR mostram tudo em uma tela só (Tiny: tabela com 30 colunas). Resultado: abandono de configuração, emissões incorretas, tickets de suporte.

UX precisa **gradar a complexidade** — começar simples, progredir conforme tenant cresce.

## Decisão

**3 telas distintas, cada uma alinhada com 1 nível do cascade tributário (ARQ-0006):**

```
/nfe-brasil/configuracao              → Wizard inicial (Nível 4 + setup)
/nfe-brasil/tributacao/regras         → CRUD regras (Nível 2 + 3)
/products/{id}/edit#fiscal            → Override por produto (Nível 1)
```

Plus UI principal `/nfe-brasil/tributacao` com 3 abas:

```
╔═══════════════════════════════════════════════════════════════════════╗
║  Tributação Fiscal                                                    ║
║                                                                       ║
║  [Defaults Business]  [Regras NCM]  [Overrides Produto]              ║
║  ━━━━━━━━━━━━━━━━━                                                  ║
║                                                                       ║
║  ┌── ABA 1: Defaults Business ──────────────────────────────────────┐║
║  │ Aplicado quando produto não tem regra específica (Nível 4)        │║
║  │                                                                   │║
║  │  Regime: [Simples Nacional ▾]                                    │║
║  │                                                                   │║
║  │  CSOSN: [102 - Tributada sem permissão de crédito ▾]             │║
║  │  ICMS:  [0,00] %     PIS: [Isento]   COFINS: [Isento]            │║
║  │                                                                   │║
║  │  ⚠ 87% das suas emissões usam estes defaults                     │║
║  │     [Ver NCMs sugeridos para refinamento]                        │║
║  │                                                                   │║
║  │  [Salvar defaults]                                               │║
║  └───────────────────────────────────────────────────────────────────┘║
║                                                                       ║
║  ┌── ABA 2: Regras NCM (12 cadastradas) ───────────────────────────┐ ║
║  │ Override granular por NCM × UF (Nível 2 + 3 do cascade)         │ ║
║  │                                                                  │ ║
║  │ Filtros: [▼ NCM] [▼ UF] [▼ Sem uso há 90d]   [+ Nova] [↑ CSV] │ ║
║  │                                                                  │ ║
║  │ NCM      | Origem | Destino | CSOSN | ICMS | ICMS-ST   | Uso ↓ │ ║
║  │ 22021000 | SP     | (todos) | 102   | 18%  | 30% (MVA) | 145×  │ ║
║  │ 22021000 | SP     | RJ      | 500   | 12%  | 18%       │  47×  │ ║
║  │ 09011190 | SP     | (todos) | 102   |  7%  | -         │  23×  │ ║
║  │ ...                                                              │ ║
║  │                                                                  │ ║
║  │ [Excluir não-utilizadas]  [Auditar regras suspeitas]            │ ║
║  └──────────────────────────────────────────────────────────────────┘ ║
║                                                                       ║
║  ┌── ABA 3: Overrides Produto (3 cadastrados) ─────────────────────┐ ║
║  │ Tributação especial por produto (Nível 1 — caso raro)           │ ║
║  │                                                                  │ ║
║  │ Produto                | Override                       | Razão │ ║
║  │ Café Premium 250g      | NCM 09011190 + ICMS-ST especial| VIP   │ ║
║  │ Combo Festa 12u        | Tributação combinada           | Kit   │ ║
║  │ Brinde Promocional     | CFOP 5910 doação               | Free  │ ║
║  │                                                                  │ ║
║  │ ⚠ Use só pra exceções; preferir Regras NCM quando possível      │ ║
║  └──────────────────────────────────────────────────────────────────┘ ║
║                                                                       ║
╚═══════════════════════════════════════════════════════════════════════╝
```

## Wizard de onboarding `/nfe-brasil/configuracao`

5 passos guiados (executa só na primeira vez ou via "reset configuração"):

```
Passo 1: Regime tributário
  ○ MEI                           — defaults: CSOSN 102, ICMS 0%, PIS/COFINS isento
  ○ Simples Nacional              — calc DAS por anexo
  ● Lucro Presumido               — CST 000, ICMS UF, PIS 0,65%, COFINS 3%
  ○ Lucro Real                    — CST 000, ICMS UF, PIS 1,65%, COFINS 7,6%

Passo 2: UF principal e IE
  UF: [SP ▾]    IE: [_______________]

Passo 3: Certificado Digital A1
  [Upload .pfx]  Senha: [_____]
  ✓ Certificado válido até 2027-04-24

Passo 4: Ambiente SEFAZ
  ○ Homologação (testes)
  ● Produção

Passo 5: NFC-e (opcional)
  ☑ Emitir NFC-e
    Série: [1]  CSC: [______________]  Numeração inicial: [1]

[Voltar]  [Concluir]

→ Cria/atualiza nfe_business_configs com defaults pré-populados (Nível 4)
→ Tenant já pode emitir NFe imediato com defaults seguros
```

## Aba 1 — Defaults Business (detalhe)

Layout focado em **simplicidade**:

- Form único, ~5 campos
- Preview lado direito: "Como ficará no XML" (mostra tag fiscal preenchida exemplo)
- Métrica em tempo real: "X% das suas emissões nos últimos 30 dias usaram estes defaults"
- CTA proativo: "Ver NCMs que você usa muito mas não tem regra específica" → leva pra Aba 2 com pré-filtro

## Aba 2 — Regras NCM (detalhe)

**Tabela inteligente** com:

- **Coluna "Uso"** — quantas emissões usaram esta regra nos últimos 30d (sort default DESC)
- **Filtro "Sem uso há 90d"** — sugere limpeza
- **Filtro "Auditar"** — destaque regras suspeitas:
  - ICMS = 0% mas regime ≠ Simples
  - ICMS-ST sem MVA
  - CSOSN sem `aliquota_icms`
- **Importação CSV** — upload arquivo gov (Receita/CONFAZ) ou planilha
  - Preview antes de aplicar
  - Detecta duplicados
  - Override default: `--update-existing`

**Form de criação/edição:**

```
NCM:         [____________]  [🔍 Buscar dataset]
CEST:        [____________]  (auto-preenche se NCM tem CEST único)
UF Origem:   [SP ▾]
UF Destino:  [(todos) ▾] ou [RJ ▾]  (NULL = aplica a qualquer UF)

╭─ ICMS ─────────────────────────────────╮
│ CSOSN: [102 ▾]   ou   CST: [___]      │
│ Alíquota ICMS: [____]%                 │
│ Alíquota ICMS-ST: [____]%   MVA: [__]% │
│ FCP: [__]%                             │
╰────────────────────────────────────────╯

╭─ Outros tributos ──────────────────────╮
│ CST IPI: [__]   Alíquota IPI: [__]%   │
│ CST PIS: [__]   Alíquota PIS: [__]%   │
│ CST COFINS: [__] Alíquota COFINS: [__]%│
╰────────────────────────────────────────╯

╭─ CBS / IBS (Reforma Tributária) ───────╮
│ ⚠ Deixar vazio se não precisa hoje     │
│ CST CBS: [____] Alíquota CBS: [__]%   │
│ CST IBS: [____] Alíquota IBS: [__]%   │
╰────────────────────────────────────────╯

[Calcular preview com produto exemplo]
[Salvar]
```

## Aba 3 — Overrides Produto (detalhe)

Geralmente **vazia ou poucos itens** — caso de uso raro:
- Produto com CFOP especial (doação, brinde, retorno)
- Combo/kit com tributação combinada
- Cliente VIP com tributação negociada

Acesso primário via `/products/{id}/edit#fiscal` (override fica como aba do form do produto).

Esta aba só lista os overrides cadastrados, com link "Editar produto X".

## Outros componentes

**Banner em `/taxes` (core upstream)** quando NfeBrasil ativo:

```
╔═══════════════════════════════════════════════════════════════╗
║ 🇧🇷 NfeBrasil ativo — você está vendo as taxas geradas pelo  ║
║    motor tributário. Configure regras avançadas em            ║
║    [/nfe-brasil/tributacao] →                                ║
╚═══════════════════════════════════════════════════════════════╝
```

Linhas com `source='nfe_fiscal_rule'` ficam read-only com badge "Auto" (ARQ-0005).

**Monitor cStat** (UI-0002) ganha sugestão proativa:

> "cStat 729 (CFOP inválido) ocorreu 5× hoje — provavelmente sua regra NCM 22021000 SP→RJ tem CFOP errado. [Auditar regra]"

## Tests obrigatórios

- E2E (Playwright): wizard completo (5 passos) → defaults gravados
- Component test: cada aba renderiza independente
- Backend Feature: criação/edição/delete de regra propaga via bridge ARQ-0005
- Backend Feature: filtros tabela (uso, suspeitas, sem uso 90d)
- Backend Feature: importação CSV gov (fixture com 100 NCMs) preview correto
- Acessibilidade: navegação por teclado em form complexo

## Métricas a observar (post-launch)

- **Tempo médio do wizard** (concluir 5 passos) — meta < 5 min
- **% tenants que pulam wizard** — meta < 10% (se alto, simplificar mais)
- **# regras criadas por tenant** após 30/60/90 dias (curva de adoção)
- **% emissões por nível cascade** — se > 70% Nível 4 após 90d, UI proativa sugerindo refinamento
- **Tempo médio cadastro de 1 regra** (form simples vs avançado)

## Decisões em aberto

- [ ] **Buscador NCM** integrado (autocomplete com nome do produto)? Útil mas dataset é grande (15k entries) — talvez Onda 2
- [ ] **Auto-correct** para regras suspeitas (sugerir CSOSN compatível)? Risco de errar; preferir sugestão manual
- [ ] **Histórico de mudanças** em regras (audit log Spatie já cobre, mas UI específica)?
- [ ] Wizard pode ser pulado e retomado depois (state persistido)?
- [ ] Versão **mobile** das 3 abas (responsivo) ou desktop-only?

## Alternativas consideradas

- **Tela única com tudo** (modelo Tiny) — rejeitado: complexidade esmagadora
- **2 telas (Defaults + Regras juntas)** — rejeitado: Overrides Produto sumiriam, ficariam em /products only (esquecíveis)
- **Sem wizard** (formulário direto) — rejeitado: barreira de entrada alta
- **Wizard com dezenas de passos** — rejeitado: 5 é o sweet spot (estilo Stripe onboarding)

## Referências

- ARQ-0006 (cascade tributário 4 níveis)
- ARQ-0005 (bridge tax_rates)
- ARQ-0004 (schema CBS/IBS)
- US-NFE-010 (cadastro regra)
- UI-0002 (monitor cStat — feedback loop pra refinamento)
- `_DesignSystem/adr/ui/0006-padrao-tela-operacional.md`
- Stripe onboarding (referência UX wizard)
- Tiny / Bling / Conta Azul — todos usam tela única (oportunidade de diferenciar)
