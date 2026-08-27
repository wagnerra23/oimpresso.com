# Pedido pro Claude Code — trio .md do módulo Crm (F1 → git)

> Gerado pelo Cowork em 2026-08-24 a partir da leitura do `main` neste turno
> (`Modules/Crm/Resources/views/*`, `Modules/Crm/Routes/web.php`, `Modules/Crm/Entities/Deal.php`,
> `Modules/Crm/Config/config.php` — module_version 2.1, pid 7).
> **Não está commitado.** Isto é a ponte: aplique no `main` você (ou [W] cola 1× zero-toque).

## O que aplicar

Criar o diretório `resources/js/Pages/Crm/` (hoje inexistente no `main` — só `Pages/Cliente/*` tem trio)
e mover estes arquivos para lá, preservando os nomes:

| Arquivo deste pacote | Destino no `main` |
|---|---|
| `Painel.charter.md` / `Painel.casos.md` | `resources/js/Pages/Crm/Painel.charter.md` / `.casos.md` |
| `Leads.charter.md` / `Leads.casos.md` | `resources/js/Pages/Crm/Leads.charter.md` / `.casos.md` |
| `Acompanhamentos.charter.md` / `Acompanhamentos.casos.md` | `resources/js/Pages/Crm/Acompanhamentos.charter.md` / `.casos.md` |
| `Portal.charter.md` / `Portal.casos.md` | `resources/js/Pages/Crm/Portal.charter.md` / `.casos.md` |

## Cuidados

1. **`status: draft`** em todos: a tela React não existe — o que existe é o Blade legado + o protótipo
   Cowork. Nenhum charter aqui afirma `live`.
2. **Todo UC nasce ⬜ (não verificado)**, sem teste citando o id. Regra G-2: UC declarado sem teste é
   órfão — os ids estão declarados para o `requisitos-status.mjs` contar a intenção, e a coluna de
   status diz a verdade. Não marcar 🧪/✅ sem `npm run casos:results` regravar o manifesto.
3. **`related_prototype`** aponta pros arquivos do export Cowork (`prototipo-ui/cowork/crm-blade*.jsx`,
   `crm-portal.jsx`) — confira se o export desta rodada já entrou; se não, o ponteiro nasce quebrado.
4. Se o repo exigir ADR pra criar um módulo de páginas novo, o número **não** foi reservado aqui —
   os `related_adrs` citam só ADRs existentes que já governam o caso (0093 multi-tenant, 0179 drawer,
   0264 trio, 0286 contrato de tela).
