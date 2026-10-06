---
sessao: "02"
titulo: "Suporte — tela Log de acessos — entregue (trio + rota + teste numa lane de PR)"
autor: "[CL]"
data: 2026-10-06
base: origin/main aeb05dff73
thread: 02-suporte-log.md
veredito: "entregue — Log.tsx + charter (draft) + casos (UC-SUP-08..10) + teste na lane acessos-pest. Motivo e duração do protótipo ficam fora: o schema não grava."
---

# _saida-02 · Suporte — tela Log de acessos

## 0 · Portão

- Placar em `aeb05dff73`: 02 `[proximo]`. A 01 entrou no main pelo #8839 como `_saida-01`
  (bloqueada dentro do prefixo, 0 de 3 contratos) — a fila liberou a 02 assim mesmo.

## 1 · Feito

| arquivo | o quê |
|---|---|
| `resources/js/Pages/Suporte/Log.tsx` | PT-01 Lista read-only: `PageHeader` + `Deferred` + `DataTable` (Quando · Agente · Acessou como · Empresa) + `EmptyState`. Âncoras `data-contract` `cabecalho` e `log` (os ids do contrato-fonte desta pasta). |
| `resources/js/Pages/Suporte/Log.charter.md` | `status: draft` (sai com o screenshot aprovado por [W]); `related_prototype` = `suporte-page.jsx` (`ancora.mjs` resolve ✓). |
| `resources/js/Pages/Suporte/Log.casos.md` | UC-SUP-08 lê a trilha · UC-SUP-09 operadora fora (com controle positivo) · UC-SUP-10 não-agente 403. |
| `tests/Feature/Support/SuporteLogContratoTest.php` | cita os 3 UCs + guarda de rota (só GET, `support.access`, `AdminSidebarMenu`). |
| `app/Http/Controllers/Support/SupportController.php` | `log()` — `Inertia::defer` de paginate(50) escopado a `accessibleBusinessIds()` (fonte única da exclusão da operadora). |
| `routes/web.php` | `GET /suporte/log` → `suporte.log`, no grupo que já tem `support.access`. |
| `.github/workflows/acessos-pest.yml` | o teste entra **por arquivo** no comando + código sob teste no gatilho. |
| `memory/requisitos/Suporte/RUNBOOK-log.md` | F1 do MWART (o hook exige antes da Page). |

**Dono do backend (a ficha pediu `arquivo:linha`):** `app/Http/Controllers/Support/SupportController.php`
(mesmo controller de Empresas/Visão) + grupo `support.access` em `routes/web.php` (rota em `:1294-1295` neste PR).

## 2 · Não feito, e por quê

- **Motivo declarado / Duração** (colunas do protótipo) — `support_access_logs` não tem as
  colunas; exige schema + fluxo de captura do motivo antes do "Acessar como". Volta ao Cowork:
  ou o protótipo tira as colunas, ou vira thread com migration.
- **Botão "Log de acessos"** em Empresas e Visão — `nao_toca` desta thread. Hoje a tela é
  alcançada só pela URL. Precisa de thread própria (ou o [W] liberar o toque).
- **Contrato `suporte-log`** em `governance/design/contracts/` — fora do prefixo, e a 01 já
  mediu que contrato ali é required e reprova sem as âncoras/copy (B1/B3 do `_saida-01`).
  As âncoras `cabecalho` e `log` já nascem no `Log.tsx`.
- **Pest no CT 100** — não rodado: o teste grava linhas append-only (o Model barra delete) e o
  banco do CT 100 é persistente e compartilhado. A prova é a lane `acessos-pest` (banco novo).
  `php -l` dos 3 PHP rodado no CT 100 via stdin: sem erros.
- **Smoke em produção** — depois do merge (merge é da sessão da fila).

## 3 · Escopo de leitura — decisão técnica, registrada

O log mostra as linhas de **todos os agentes**, só de empresas em `accessibleBusinessIds()`.
Consequência: as **negações contra a operadora** (biz=1) não aparecem — a proibição "não mostrar
a operadora em vista nenhuma" (contrato-fonte §proibicoes) vence o valor de auditoria delas.
Se o [W] quiser vê-las, é decisão dele, não da tela.

## 4 · Tropeços desta sessão (registro)

- `criar-tela.mjs --out <path POSIX>` escreveu fora do repo em `D:/c/Users/...` (o mesmo literal
  `/c/...` vira caminho diferente no Node do Windows — LC-36). Nada entrou no repo; usei a saída
  só como referência.
- Duas escritas com barra invertida colapsaram no heredoc (LC-26) — refeitas pelo Edit tool;
  o YAML da lane foi validado com parser depois.
