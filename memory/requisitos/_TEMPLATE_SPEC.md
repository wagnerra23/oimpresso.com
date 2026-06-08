<!--
  USE COMO BASE — NÃO EDITAR (canônico).
  Copie pra `memory/requisitos/<NomeModulo>/SPEC.md` e cure os placeholders {{...}}.
  Validado pelo CI gate `memory-schema-gate-extended.yml` (D6 #4 audit memoria-senior).

  Regras Tier 0:
  - frontmatter YAML obrigatório (module/last_updated/version/owner)
  - seções obrigatórias: `## US ativas` (ou `## Backlog ativo` ou `## User stories`)
  - recomendadas: `## Histórico`, `## Referências`
  - US no formato US-<MOD>-<NNN> (MOD em UPPER, NNN 3-4 dígitos)

  Override emergencial: linha `<!-- schema-allowlist: <razão> -->` pula validação.
-->
---
module: {{PascalCase}}
last_updated: {{YYYY-MM-DD}}
version: v0.1.0
owner: W
status: rascunho
us_count: 0
us_list: []
related_adrs: []
---

# Especificação funcional — {{Modulo}}

> Owner: {{Wagner|Felipe|Maiara|Luiz|Eliana}} · Última revisão: {{YYYY-MM-DD}} · ADR canônica: [{{NNNN-slug}}](../decisions/{{NNNN-slug}}.md)

## 1. Personas

| Persona | Contexto | Acesso |
|---|---|---|
| **{{Persona 1}}** | {{Ex: Larissa ROTA LIVRE — quer clareza de rumo}} | `business_id` scoped |
| **{{Persona 2}}** | {{Ex: Superadmin oimpresso}} | global |

## 2. US ativas

> **Convenção:** `US-{{SIGLA}}-NNN` (sigla 2-8 letras UPPER, número 3-4 dígitos).
> **DoD mínimo:** rota autorizada (403 se não), scope `business_id`, FormRequest, JSON `transform()`, Pest feature, dark mode, mobile responsivo, toast `sonner`.

### US-{{MOD}}-001 · {{título curto}}
- **Rota:** `GET /caminho`
- **Controller:** `{{Controller}}@{{action}}`
- **Como** {{persona}} **quero** {{ação}} **para** {{outcome}}.
- **DoD extra:** {{aceite específico}}.

### US-{{MOD}}-002 · {{título curto}}
- **Rota:** `POST /caminho`
- **Controller:** `{{Controller}}@{{action}}`
- **Como** {{persona}} **quero** {{ação}} **para** {{outcome}}.

## 3. Backlog ativo (P0/P1/P2/P3)

| ID | P | Descrição | Owner | Estimativa |
|---|---|---|---|---|
| US-{{MOD}}-003 | P0 | {{título}} | {{W}} | {{2h}} |
| US-{{MOD}}-004 | P1 | {{título}} | {{F}} | {{4h}} |

## 4. Tabelas DB / contratos

```sql
CREATE TABLE {{tabela}} (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  -- ...
  INDEX idx_business (business_id),
  FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE
);
```

## 5. Integrações / dependências

- Módulos chamados: `{{Modules/X}}`, `{{Modules/Y}}`
- ADRs relacionadas: [{{NNNN}}](../decisions/{{NNNN-slug}}.md)
- RUNBOOKs: [RUNBOOK-{{nome}}](RUNBOOK-{{nome}}.md)

## 6. Histórico

| Data | Quem | O que mudou |
|---|---|---|
| {{YYYY-MM-DD}} | {{W}} | {{descrição}} |

## 7. Referências

- ADR [{{NNNN-slug}}](../decisions/{{NNNN-slug}}.md) — {{título}}
- RUNBOOK [{{nome}}](RUNBOOK-{{nome}}.md)
- Capterra ficha [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (se houver)
