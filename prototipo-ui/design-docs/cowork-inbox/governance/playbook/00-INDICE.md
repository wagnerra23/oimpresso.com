---
sessao: "00"
titulo: SINCRONIZAR Governança — índice do playbook (fonte da máquina em §6)
autor: "[CC]"
criado: 2026-09-08
base: wagnerra23/oimpresso.com@main (árvore 0d159eb84a10 · lida 2026-09-08 17:09 UTC)
constituicao: CONSTITUICAO-COWORK.md (C1–C12) + memory/proibicoes.md
destino_no_main: prototipo-ui/design-docs/cowork-inbox/governance/playbook/
---

# SINCRONIZAR Governança — playbook

> **O módulo mais adiantado do ERP, e o mais desigual.** Backend maduro (22 Services, 19 Commands, 13 Checkers, **57 testes Feature**), **9 telas React todas com `Inertia::render`**, 9 charters — e **1 único `casos.md`** entre as nove. O trio não fecha em 8 telas.
> **Produção está À FRENTE do meu protótipo** (C4): meu build tem **5 vistas**, produção tem **9**. Quatro telas nasceram lá e nunca chegaram aqui: `Custos`, `DsRollout`, `QualidadeIa`, `ModuleGrades/Show`. Isso é defeito **meu** → thread 04, não pedido.

> **Compatibilidade verificada em 08/09/2026:** este índice não declarou recibos de execução. A estrutura pode ser avaliada, mas nenhuma tarefa recebe fechamento automático até definir sua evidência no contrato do placar. E2E, comparação visual e medição exigem os respectivos produtores de evidência; não preencher um resumo Pest fictício.

## 0 · LEVANTAR — 4 denominadores

**D1 rota** `Modules/Governance/Http/routes.php` (5.982 B, **editado hoje** — ADR 0392 §D-D passo 2): `/` **redireciona pra `/ia`** (302, canon [W] 2026-05-22) · `/dashboard` · `/policies` (+`POST /policies/{id}/toggle`) · `/audit` · `/drift` · `/module-grades` (+`/{name}`) · `/ds-rollout` · `/custos` · `/qualidade-ia` + 3 hooks de install.
**D2 nav** `_shared/GovernancaSubNav.tsx` (3.178 B) — sub-nav própria, já existe.
**D3 protótipo** `governance-page.jsx` + `governance-telas.jsx` + `governance-data.jsx`, 5 rotas no `app.jsx` (`governance` · `gov-politicas` · `gov-auditoria` · `gov-drift` · `gov-notas`).
**D4 runtime** — **9 de 9 `Inertia::render`**, com o nome de tela em **minúsculo** (`governance/Audit`, `governance/ModuleGrades/Show`, …). **O `g` minúsculo do contrato estava certo**: `resources/js/Pages/governance/` é o caminho real, e é exceção declarada ao `Pages/<Mod>/` maiúsculo do resto do app. Não "corrigir".

## 1 · Estado por tela (medido, não recordado)

| tela | `.tsx` | charter | **casos** | contrato | e2e | no meu build? |
|---|---:|:---:|:---:|:---:|:---:|:---:|
| `Dashboard` | 42.343 B | ✅ | **✕** | ✕ | ✕ | ✅ painel |
| `Policies` | 4.889 B | ✅ | **✕** | ✕ | ✕ | ✅ |
| `Audit` | 8.390 B | ✅ | **✕** | ✕ | ✕ | ✅ |
| `DriftAlerts` | 8.556 B | ✅ | **✕** | ✕ | ✕ | ✅ |
| `ModuleGrades/Index` | 22.324 B | ✅ | **✕** | ✕ | ✕ | ✅ notas |
| `ModuleGrades/Show` | 28.806 B | ✅ | **✕** | ✕ | ✕ | **✕** |
| `Custos` | 13.884 B | ✅ | **✕** | ✕ | ✕ | **✕** |
| `DsRollout` | 32.411 B | ✅ | ✅ 8.618 B | ✕ | ✕ | **✕** |
| `QualidadeIa` | 20.788 B | ✅ | **✕** | ✕ | ✕ | **✕** |

**Três lacunas de máquina:** `casos.md` **1 de 9** · contrato **0 de 9** (as **duas** pastas medidas hoje: `prototipo-ui/contrato/` = 31 vigentes, `design-docs/contrato-cowork/` = 3 em estágio; **nenhum** de governança nas duas — o meu nunca desceu) · e2e **0 de 17 specs**.

## 2 · Threads

| # | thread | dono | ficha (leitura · escrita · prefixo · dec.) | veredito |
|---|---|---|---|---|
| 01 | Descer o contrato pra pasta de **estágio** (cobre 5 de 9 — declarado) | [CL] | 4 KB · ~10 ln · 1 · 0 | **CABE** |
| 02 | Rede: 2 specs E2E (`dashboard` + `policies` toggle) | [CL] | ~12 KB · ~160 ln · 2 · 0 | **CABE** |
| 03 | `casos.md` — **frente**, 1 tela por PR (G-2 proíbe big-bang) | [CL] | por tela | **DIVIDE** → 03a primeiro |
| 04 | Meu build está 4 telas atrás da produção | [CC] | — | **CABE** (medição + decisão) |
| 05 | `Gate::before` deixa admin passar por qualquer `can:` | [W] | — | **BLOQUEADA** (D-GATE) |

**Vaga 1:** 01 ∥ 02 ∥ 04. **Vaga 2:** 03a (depois que 01 fixar o formato do contrato).
**03 é frente, não thread:** 8 `casos.md` num PR reprova no `casos-gate` G-2 (`1 seção = 1 PR`, prosa é o que conta). E `Dashboard.tsx` sozinho tem **42 KB** — estoura o teto de 40 KB de leitura antes de escrever a primeira linha. Ordem proposta pela ficha, do menor para o maior: `Policies` (4,9 KB) → `Audit` (8,4) → `DriftAlerts` (8,6) → `Custos` (13,9) → `QualidadeIa` (20,8) → `ModuleGrades/Index` (22,3) → `Show` (28,8) → `Dashboard` (42,3, **fatiar por seção**).

## 2-bis · ESTADO — derivado, nunca escrito
`node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/governance/playbook/00-INDICE.md --root . --proximo`
Render esperado: `Governança: entregue 0 de 5 · próximo 3 · bloqueada 1`.

## 3 · Abertura de thread (colar como 1ª mensagem — sessão limpa)
```
Sessão fresca. ANTES de abrir: gh pr list --state open e cruze com os arquivos do seu prefixo.
Leia, do main: (1) CONSTITUICAO-COWORK.md — C1–C12
(2) este índice §0/§1/§2/§5  (3) o seu NN-*.md  (4) o charter da SUA tela
(5) a faixa da sua ÂNCORA — e SÓ ela.
NÃO leia: Dashboard.tsx (42 KB) nem ModuleGradeService.php (91 KB) salvo se a sua thread os nomear.
As tabelas mcp_* são CROSS-TENANT POR DESIGN (exceção formal ao Tier 0, Constituição Art. 6+8,
coberta por CrossTenantPolicyTest) — não "consertar" isso.
Você escreve SOMENTE no seu prefixo e no seu _saida-NN.md. Terminou: escreva o _saida e pare.
```

## 4 · Não inventar (reusar)
`_shared/GovernancaSubNav.tsx` · os **57 Feature tests** como oráculo (`CrossTenantPolicyTest`, `GovernanceRotasCanGateTest`, `ModuleGradeControllerTest`, `InertiaDeferAuditTest`) · `DsRollout.casos.md` como **molde** do formato (é o único que existe) · `PolicyToggleService`, `AuditDrillDownService`, `DriftAlertService`, `ModuleGradeService` — nenhuma regra de domínio se reimplementa em tela.

## 5 · RESÍDUO — fila [W]
1. **D-GATE (trava a thread 05).** O próprio `routes.php` declara hoje: `AuthServiceProvider` registra `Gate::before` que devolve `true` para a role `Admin#{business_id}` em **qualquer** ability fora de backup/superadmin/manage_modules. Logo o `can:` recém-adicionado **não barra admin de business** — barra só não-admin sem permission. É o passo 1 da ADR 0392 (conflito A×B da CONCESSÃO), **decisão [W] em aberto**. Nenhum PR de tela fecha isso.
2. **`/custos` e `/qualidade-ia` sem `can:` na rota** — de propósito: as permissions são `jana.admin.custos.view` e `jana.mcp.usage.all`, vivem no construtor e são **diferentes entre si**; renomear pra `governance.*` revogaria acesso em silêncio (ADR 0087). Confirmar que segue intencional.
3. **`compliancePct` é soma literal** `(7*10)+(2*5)+0` escrita à mão no `DashboardController`. A UI rotula "auto-declarado" e mostra a régua. Promover a apurado exige fonte — [W] decide quando.
4. **`mcp_governance_rule_history` não existe** → alternar política não deixa rastro. Prometido pra Fase 5+1.
5. **`mcp_alertas` não tem categoria `module_drift`** → histórico de drift fica vazio com motivo à mostra.
6. **Meu contrato cobre 5 de 9 telas.** Estender pras 4 novas custa ler 96 KB (`Custos` 13,9 + `DsRollout` 32,4 + `QualidadeIa` 20,8 + `Show` 28,8) — **RECUSA por teto**; vira frente própria depois da 03.

## 6 · Fonte da máquina
```json
{
  "modulo": "Governanca",
  "sha": "0d159eb84a10",
  "gerado": "2026-09-08",
  "constituicao": "CONSTITUICAO-COWORK.md",
  "nota_caminho": "Pages/governance/ com g MINUSCULO e o caminho REAL (9 de 9 Inertia::render usam), excecao declarada ao Pages/<Mod>/ do resto do app. Nao normalizar.",
  "decisoes": [
    { "id": "D-GATE", "pergunta": "Gate::before devolve true para role Admin#{business_id} em qualquer ability — o can: por rota nao barra admin de business. Passo 1 da ADR 0392 (conflito AxB da CONCESSAO).", "respondida": false, "destrava": ["05"] },
    { "id": "D-CONTRATO-9", "pergunta": "Estender o contrato de 5 para 9 telas custa 96 KB de leitura (RECUSA por teto). Vira frente propria depois da 03?", "respondida": false }
  ],
  "threads": [
    { "id": "01", "titulo": "Descer o contrato de governanca pra contrato-cowork (estagio; cobre 5 de 9, declarado)", "dono": "CL", "vaga": 1, "arquivo": "01-contrato-desce.md",
      "prefixo": ["prototipo-ui/design-docs/contrato-cowork/governance.contract.json"],
      "nao_toca": ["prototipo-ui/contrato/", ".github/workflows/contrato-de-tela.yml", "resources/js/Pages/governance/", "Modules/Governance/"],
      "provas": [
        { "tipo": "arquivo", "path": "prototipo-ui/design-docs/contrato-cowork/governance.contract.json" },
        { "tipo": "contem", "path": "prototipo-ui/design-docs/contrato-cowork/governance.contract.json", "padrao": "cobertura_parcial" }
      ] },
    { "id": "02", "titulo": "Rede: 2 specs E2E (dashboard + policies toggle)", "dono": "CL", "vaga": 1, "arquivo": "02-rede-e2e.md",
      "prefixo": ["e2e/governance-dashboard.spec.ts", "e2e/governance-policies.spec.ts"],
      "nao_toca": ["resources/js/Pages/governance/", "Modules/Governance/"],
      "provas": [
        { "tipo": "arquivo", "path": "e2e/governance-dashboard.spec.ts" },
        { "tipo": "arquivo", "path": "e2e/governance-policies.spec.ts" }
      ] },
    { "id": "03a", "titulo": "casos.md de Policies (a menor tela) — abre a frente do trio", "dono": "CL", "vaga": 2, "arquivo": "03a-casos-policies.md",
      "prefixo": ["resources/js/Pages/governance/Policies.casos.md"],
      "nao_toca": ["resources/js/Pages/governance/Policies.tsx", "Modules/Governance/"],
      "provas": [
        { "tipo": "arquivo", "path": "resources/js/Pages/governance/Policies.casos.md" },
        { "tipo": "arquivo", "path": "resources/js/Pages/governance/DsRollout.casos.md", "guarda": true, "nota": "o unico casos.md existente e o MOLDE — nao editar" }
      ] },
    { "id": "04", "titulo": "Meu build esta 4 telas atras da producao", "dono": "CC", "vaga": 1, "arquivo": "04-build-atras.md",
      "prefixo": [], "nao_toca": ["resources/js/", "Modules/"],
      "nota_provas": "read-only + decisao [W]: prova = _saida-04.md com o custo de cada tela e a recomendacao. Nao vira PR no main.",
      "provas": [] },
    { "id": "05", "titulo": "Gate::before deixa admin passar por qualquer can:", "dono": "W", "arquivo": "00-INDICE.md",
      "prefixo": [], "nao_toca": ["app/Providers/AuthServiceProvider.php"],
      "bloqueio": "D-GATE: passo 1 da ADR 0392, decisao [W] em aberto. Thread bloqueada nao ganha arquivo proprio.",
      "depende_decisoes": ["D-GATE"], "provas": [] }
  ]
}
```
