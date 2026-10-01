---
sessao: "03"
titulo: "Acompanhamentos (ScheduleController) → Inertia — saída da thread"
autor: "[CL]"
data: 2026-10-01
base: origin/main a1f9b0a68
thread: 04-acompanhamentos.md §03
veredito: "Entregue a LISTA (index) em Inertia, com contrato + charter + casos + teste no mesmo PR (lei IT2). Escrita segue na Blade (?classico=1)."
---

# _saida-03 · Acompanhamentos → Inertia

## Estado ao abrir

O placar mostrou `pendente`: sem `_saida`, prova ainda não satisfeita e `depende de 01 (não
feita)`. A 01 foi entregue no #8345 só com o recibo, porque o check required "Contratos de tela"
reprova contrato sem a Page. Seguindo a opção 1 do `_saida-01`, o contrato
`crm-acompanhamentos` entra aqui, junto da Page. D4 respondida em `_DECISOES-W-2026-10-01.md`.

## O que entrou

| arquivo | o quê |
|---|---|
| `Modules/Crm/Http/Controllers/ScheduleController.php` | `index()` passa a renderizar `Crm/Acompanhamentos/Index`. A tela nova recebe a MESMA consulta do DataTables (business_id, filtros, "só os meus"). `?classico=1` mantém a Blade |
| `Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/Index.tsx` | filtros, abas (avulso/recorrente), lista paginada (25), busca, rodapé com total |
| `governance/design/contracts/crm-acompanhamentos.contract.json` | copiado do `_saida-01`; só o `alvo` mudou para o caminho real |
| `Index.charter.md` · `Index.casos.md` | 7 UCs (UC-CRMACO-01..07) |
| `Modules/Crm/Tests/Feature/CrmAcompanhamentosContratoTest.php` | 1 teste por UC, tenant 98 (99 como "outro negócio") |
| `.github/workflows/verticais-pest.yml` | a lane MySQL passa a rodar o teste e dispara com o controller/Page |

## Decisões técnicas (do Code)

- **Desvio Inertia × DataTables:** `ajax()` sozinho mandaria a visita Inertia pro JSON, porque o
  Inertia envia `X-Requested-With` junto do `X-Inertia` (§5 2026-09-08). A tela decide por
  `X-Inertia`. UC-CRMACO-02 prova os dois lados.
- **Namespace:** `Modules/Crm/Resources/js/Pages/Crm/...` → componente `Crm/Acompanhamentos/Index`
  (o `app.tsx` normaliza o glob de módulo; não colide com `resources/js/Pages`).
- **Datas** saem como `d/m/Y H:i` do valor gravado, sem o shift +3h do `format_date` legado.

## Provas do json conferidas

- `Modules/Crm/Http/Controllers/ScheduleController.php` contém `Inertia::render(` — ✅ neste PR.
- `contrato-de-tela.mjs --contract crm-acompanhamentos.contract.json` → 4 seções, âncora + copy +
  ordem, **limpo**. `--map --check` e `--anti-tautologia` limpos.
- `casos-coverage-guard` sem violação nova. `validate.mjs` no charter: conforme.
- Pest: **NÃO rodado local** (regra do repo). A prova é a lane `verticais-pest` do PR.

## Pendente (não feito, e por quê)

1. **Escrita em Inertia** (adicionar, recorrente, antecipado, editar, log, excluir): os modais
   continuam na Blade, e os três botões da toolbar levam pra `?classico=1`. É a próxima fatia.
2. **Rodapé por status/tipo, densidade e drawer de detalhe** do protótipo: não entraram para caber
   no PR. O rodapé mostra só `Total:`, que é a copy do contrato.
3. **Busca abaixo das abas**, não na toolbar: é a busca do `DataTable` compartilhado. A copy está no
   arquivo e o contrato passa, mas a posição difere do protótipo.
4. **RUNBOOK MWART** (`memory/requisitos/Crm/RUNBOOK-acompanhamentos.md`): não criado; o hook não
   cobra Pages de módulo. Fica para a fatia de escrita.
5. **Aviso prévio/canary F5 (ADR 0104):** a tela troca para todos os negócios com o módulo. Não há
   flag; a saída de emergência é `?classico=1`. Decisão [W] se quiser canary.
6. **Índice:** a prova da 01 (os 3 contratos) passa a ser cumprida thread a thread (opção 1 do
   `_saida-01`). Editar o `00-INDICE.md` é do Cowork.

## Placar

Esperado após o merge: `03` com prova verde e `_saida`, mas `pendente`/`em curso` enquanto a 01
não virar `feito` no placar (a dependência é por recibo + provas da 01, e as provas da 01 são os 3
contratos — este PR cumpre 1 de 3).

## PR

O PR da branch `claude/crm-thread-03`.
