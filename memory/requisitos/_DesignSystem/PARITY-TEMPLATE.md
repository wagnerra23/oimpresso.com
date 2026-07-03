---
titulo: Template canônico — Paridade de migração Blade↔React (-parity.md)
tipo: template
status: active
owner: W
criado: '2026-07-02'
related:
  - ../_Governanca/programa-ondas/onda-0-fundacao/0d-paridade-migracao.md
  - ../../../.claude/skills/mwart-process/SKILL.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0256-knowledge-survival-meia-vida-catraca-sentinela'
  - '0264-governanca-executavel-trio-dominio-e2e'
---

# Template — `<tela>-parity.md` (paridade de migração Blade→React)

> **O que é:** mapa **campo-a-campo** entre a tela Blade legada e a tela React (Inertia)
> que a substitui. Prova de que a migração **preservou função** — âncora SOTA
> _parallel-run / GitHub Scientist / strangler fig_ ("proof rather than hope").
> Entregável do **passo F2** do processo MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)); os
> itens de **severidade alta** viram **teste de comportamento** verificado em **F4**.
>
> **Origem:** [Onda 0d](../_Governanca/programa-ondas/onda-0-fundacao/0d-paridade-migracao.md).
> Piloto validado: [`User/perfil-parity.md`](../User/perfil-parity.md).

---

## Como usar (3 passos)

1. **Copie este arquivo** para `memory/requisitos/<Mod>/<tela>-parity.md` (ex: `Sells/create-parity.md`).
2. **Preencha a tabela** lendo os dois lados — o Blade legado (`resources/views/<...>.blade.php`
   + partials `@include`) e o React (`resources/js/Pages/<Mod>/<Tela>.tsx` + o controller que
   faz o `store`/`update`). Uma passada read-only de 1 agente basta (custo ~1 agente; a `/perfil`
   custou isso).
3. **Para cada linha de severidade `alta`**, garanta que existe um **teste que quebra se o campo
   sumir** — e cite o id do teste/UC na coluna "Defendido por". Sem teste citado, o item é órfão
   (regra G-2 da [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)); ele
   NÃO fecha F4.

> ⚠️ **Enforcement é de comportamento, não de presença.** O gate NÃO é "o `-parity.md` existe"
> (proibicoes §"Gate de CI charter foi tocado" — presença ≠ correção). É o **teste derivado**:
> o campo mapeado **persiste** (red/green). Um `-parity.md` sem teste pros itens `alta` é débito,
> não conclusão.

---

## Preencher: metadados

- **Tela React:** `resources/js/Pages/<Mod>/<Tela>.tsx`
- **Blade legado:** `resources/views/<...>.blade.php` (+ partials)
- **Controller (persistência):** `App\...\<X>Controller::<metodo>()` — a rota `POST` que grava
- **Rota nova / legada:** `<GET/POST novo>` · `<rota Blade — intacta? cutada?>`
- **Auditado em:** `YYYY-MM-DD` · **por:** `<agente/pessoa>`

## Preencher: mapa campo-a-campo

Uma linha por **campo/feature observável** do Blade. Severidade = o que se perde se o React
**não** tiver o item.

| # | Feature do Blade | Está no React? | Evidência (arquivo:linha) | Severidade se perdido | Defendido por (UC/teste) |
|---|---|---|---|---|---|
| 1 | `<campo/comportamento>` | ✅ / 🟡 parcial / ❌ | `Perfil.tsx:NNN` ↔ `form.blade.php:NN` | **alta** / média / baixa | `UC-XXX` · `TesteX` |

**Legenda "Está no React?"**
- ✅ **presente** — mesmo dado persiste/renderiza.
- 🟡 **parcial/divergente** — existe mas mudou comportamento (ex: formato de data, validação
  mais/menos estrita, help text perdido). Descreva a diferença.
- ❌ **ausente** — o React não tem. Regressão de migração.

**Legenda severidade** (o que decide se vira teste em F4):
- **alta** — perde/deturpa **dado do usuário** ou **regra Tier 0** (persistência de campo,
  isolamento multi-tenant, cálculo de valor/estoque, troca de senha). ➜ **exige teste de
  comportamento** que quebra se o item sumir.
- **média** — degrada UX de forma sentível (validação, máscara, ordenação, formato). ➜ registra;
  teste recomendado, não bloqueia.
- **baixa** — cosmético/ajuda (tooltip, placeholder, ícone). ➜ registra; sem teste.

## Preencher: divergências deliberadas (React ≠ Blade de propósito)

> Nem toda diferença é regressão — algumas são **melhorias conscientes**. Liste aqui pra não
> serem "corrigidas de volta" numa sessão futura (evita re-regressão).

- `<campo>` — **melhoria:** `<o que mudou e por quê>` (ex: senha ganhou `min:8|confirmed` que o
  legado não tinha). Decidido por `<quem/ADR/charter>`.

## Preencher: itens de severidade alta → teste (F4)

> Esta é a ponte pro `casos-gate`. Cada item `alta` da tabela acima precisa de **1 asserção
> que falha** se o campo deixar de persistir/funcionar. Espelha o `<Tela>.casos.md`.

| Item alta | Asserção (Dado/Quando/Então) | Teste (arquivo::caso) | Status |
|---|---|---|---|
| Persistência dos campos-chave | Dado usuário logado · Quando `POST <rota/update>` com todos os campos · Então a linha do DB tem cada um (incl. `custom_field_*`, `bank_details` JSON) | `<TelaParityTest>::<caso>` | 🧪 / ✅ / ❌ |

---

## Critério de pronto do `-parity.md`

- [ ] Tabela campo-a-campo completa (todo campo do Blade tem linha).
- [ ] Toda linha `alta` tem um UC/teste citado que a defende (não presença — comportamento).
- [ ] Divergências deliberadas listadas (anti-re-regressão).
- [ ] Referenciado no PR da migração (F2) + no `<Tela>.casos.md`.

## Refs

- Processo: [`mwart-process`](../../../.claude/skills/mwart-process/SKILL.md) F2 (entregável) + F4 (teste dos `alta`).
- Onda mãe: [0d-paridade-migracao.md](../_Governanca/programa-ondas/onda-0-fundacao/0d-paridade-migracao.md).
- Piloto real: [`User/perfil-parity.md`](../User/perfil-parity.md).
- Não é gate de presença: [proibicoes §"Ideias descartadas"](../../proibicoes.md) (charter-sync-gate, 2026-07-01).
</content>
</invoke>
