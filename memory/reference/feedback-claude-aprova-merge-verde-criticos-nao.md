---
name: Claude aprova e mergeia PR verde — críticos não
description: Wagner delegou a APROVAÇÃO de merge ao Claude para PRs verdes (CI passando) em módulos não-críticos. Claude mergeia sozinho sem chamar Wagner. Módulos críticos (dinheiro/fiscal/LGPD) continuam exigindo OK explícito do Wagner mesmo verdes.
type: feedback
---
**Regra:** PR com CI **verde** (todos os required checks passando) em área **não-crítica** → Claude **aprova e mergeia sozinho**, sem escalar pro Wagner. Se vier **vermelho**, não mergeia: conserta ou avisa.

**Why:** 2026-06-03, Wagner: "*pode mergear verde, críticos não*". Contexto: ele liberou as permissões locais ("não quero ficar autorizando" / "quero trabalhar menos") e, depois de entender que (a) o time não tem acesso de escrita ao repo — só `@wagnerra23` é code owner válido — e (b) `enforce_admins: false` deixa o Claude (rodando `gh` como admin) furar a fila, decidiu **delegar a aprovação dos greens não-críticos** pro Claude em vez de ser o gargalo. Evolui [[feedback-auto-merge-quando-verde]] (que cobria só a *mecânica* `--auto` mantendo Wagner como aprovador) e fica na família [[feedback-claude-mais-autonomo-2026-05-25]] + [[feedback-ondas-multi-pr-sem-perguntar-2026-05-28]].

**Como aplicar:**
- **Verde + não-crítico → mergeia.** Comando canônico do projeto: `gh pr merge <NUM> --auto --squash --delete-branch` (`--auto` o GitHub fecha quando ficar verde; `--squash --delete-branch` é o estilo canon).
- **Vermelho → nunca mergeia.** Conserta o que dá ou avisa o Wagner.
- A aprovação code-owner exigida pela branch protection é satisfeita pelo Claude usando o admin do Wagner (delegação explícita desta regra). Branch protection **não se mexe** — continua de piso.

**Módulos CRÍTICOS — sempre exigem OK explícito do Wagner, mesmo verdes:**
- 💰 `Modules/Financeiro/`, `Modules/NfeBrasil/`, `Modules/RecurringBilling/` (dinheiro / fiscal / cobrança)
- 🔒 `Modules/Jana/` (ex-Copiloto — LGPD-crítico)
- 🧾 qualquer coisa fiscal/SPED/NF-e
- (espelha os owners obrigatórios do `.github/CODEOWNERS`)

**Continua escalando pro Wagner (não é "merge verde"):**
- Deploy em produção, mudança em `.env` de prod, postagem externa — `publication-policy` vale igual.
- ADR canon (`memory/decisions/*`), mudança Tier 0 multi-tenant, `enforce_admins`/branch protection.

**Anti-padrão:** mergear PR vermelho "porque o fix é óbvio"; mergear módulo crítico verde sem avisar; presumir que a delegação cobre deploy/prod. Na dúvida se a área é crítica → trata como crítica e pergunta.
