# CAPTERRA-FICHA — {Modulo}

> **Template canônico** — copie pra `memory/requisitos/{NomeModulo}/CAPTERRA-FICHA.md` e cure os campos.
> Esta ficha é a **fonte de verdade do benchmark** do módulo. Skill `comparativo-do-modulo` lê este arquivo + SPEC.md + código real.
> ADR de governança: [0089](../decisions/0089-capterra-driven-module-evolution.md).

---

## Identidade do módulo

- **Nome interno**: `{Modulo}` (ex: RecurringBilling, Financeiro)
- **Domínio de negócio**: `{descrição em 1 frase}` (ex: cobrança recorrente de assinaturas + emissão de boleto + reconciliação)
- **Cliente principal alvo**: `{persona/biz}` (ex: ROTA LIVRE / Larissa, biz=4)
- **Concorrentes-alvo direto** (3-5 mais próximos):
  - {Concorrente A} — {site} — {posicionamento curto}
  - {Concorrente B} — ...
  - {Concorrente C} — ...

## Comparativos de referência

Arquivos em `memory/comparativos/` que sustentam esta ficha:
- `{nome_do_capterra}.md` — {data + foco}
- ...

Se ainda não existir comparativo dedicado, criar via template `memory/comparativos/_TEMPLATE_capterra_oimpresso.md` antes de popular as capacidades abaixo.

## Capacidades baseline com score

> Cada capacidade é uma "feature de mercado" que clientes esperam. Score determina prioridade ao virar task.
>
> **P0** = Bloqueador de venda OU exigido por lei (LGPD, fiscal, segurança multi-tenant)
> **P1** = ≥80% concorrentes têm; cliente pede explicitamente
> **P2** = ≥50% mercado tem; oimpresso evolui sem por agora
> **P3** = Diferenciação opcional / nicho

```yaml
capacidades:
  - nome: "Capacidade A"
    score: P0
    descricao: "{1 frase do que faz}"
    quem_tem: ["Concorrente A", "Concorrente B"]
    referencias: ["link doc concorrente", "link feature page"]
    evidencia_de_pronto: "{Como saber que oimpresso JÁ tem isso? Ex: rota X funciona + teste Y passa + tela Z renderiza}"

  - nome: "Capacidade B"
    score: P1
    descricao: "..."
    quem_tem: ["Concorrente A"]
    evidencia_de_pronto: "..."

  - nome: "Capacidade C"
    score: P3
    descricao: "..."
    quem_tem: ["Concorrente exótico"]
    evidencia_de_pronto: "..."
```

## Como auditar este módulo (etapa específica)

> Esta seção é **lida pela skill** no passo 2.5 e usada para classificar capacidades em ✅/🟡/❌.
> Quanto mais específica, mais fiel o inventário.

**Ferramentas/locais a inspecionar:**
- Models a checar: `Modules/{X}/Models/{Classe}.php` — atributo `{X}` indica capacidade Y
- Migrations chave: `Modules/{X}/Database/Migrations/{padrão}.php`
- Services chave: `Modules/{X}/Services/{Pattern}/...`
- Endpoints: `Modules/{X}/Routes/web.php` linhas X-Y
- Telas Inertia: `resources/js/Pages/{X}/...` específicas
- Tests: `Modules/{X}/Tests/Feature/{padrão}Test.php`
- Migrations DB que materializam a capacidade
- Tabelas a inspecionar: `tabela_x` coluna Y indica feature Z

**Critérios customizados de classificação:**

| Capacidade | ✅ APROVADO requer | 🟡 PARCIAL aceita | ❌ AUSENTE quando |
|---|---|---|---|
| {Capacidade A} | Service X + teste + UI + cobertura prod | Service existe sem UI/teste | Sem service algum |
| {Capacidade B} | ... | ... | ... |

**Métricas de prod relevantes** (se houver):
- {métrica X} — meta {valor} — query: `SELECT ... FROM ...`

## UX heuristics (Capterra v2 — eixo Usabilidade)

> Capterra v2 ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) §3 eixos): além de medir features, mede **como** o concorrente entrega — cliques, tempo, recuperação de erro.

```yaml
ux_heuristics:
  - id: example-clicks
    nome: "Cliques pra ação X"
    score: P0
    benchmark: "Concorrente A: 1 clique. Concorrente B: 5."
    target: "<= 2 cliques"
    metrica: "navegacao_steps_X"
```

## Automation targets (Capterra v2 — eixo Automação)

> O que mercado faz **sem humano**? Listener? Cron? Job? Webhook?

```yaml
automation_targets:
  - id: example-auto-action
    nome: "Auto-disparar X quando Y"
    score: P0
    benchmark: "Concorrente A SIM, B SIM, C PARCIAL"
    target: "Listener event Y → JobDoX, p95 < 30s"
    metrica: "auto_X_p95_seconds"
```

## Métricas de adoção

- **Última auditoria**: `{YYYY-MM-DD}` (ou "nunca")
- **Capacidades P0 cobertas**: `{N}/{Total}`
- **Gap P0+P1 atual**: `{N}` capacidades faltantes ou parciais
- **UX heuristics curadas**: `{N}` (alvo: ≥3 P0)
- **Automation targets curadas**: `{N}` (alvo: ≥3 P0)
- **Próxima reauditoria sugerida**: `{YYYY-MM-DD}` (trimestral por padrão)

## Histórico de revisão da ficha

> Skill **não escreve** aqui — apenas humanos. Cada vez que você ajustar concorrentes/capacidades/scores, registre.

- `{YYYY-MM-DD}` — {Wagner/quem} — {motivo da revisão} (ex: "Asaas lançou PIX recorrente em mai/2026 → adicionei capacidade")

---

## Referências externas (links revisados na última auditoria)

- {Concorrente A} feature page: {url}
- {Comparativo G2 / Capterra}: {url}
- Regulação aplicável: {url SEFAZ/BCB/LGPD/etc}
