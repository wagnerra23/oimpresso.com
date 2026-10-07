---
sessao: "_saida-08b"
thread: "08 · Leads: escopo — prova estrutural para o índice"
dono: "[CL]"
data: 2026-10-07
base_lida: wagnerra23/oimpresso.com@main c8777ae00a (fetch em 2026-10-07)
complementa: "_saida-08.md"
---
# _saida-08b · A 08 está entregue; só a prova do índice não é lida pela máquina

## Estado (medido no `main`)
- Achado 1 (ficha sem `type = lead`): consertado no #8645. `LeadController::show` filtra
  `->where('type', 'lead')` (linha 458); teste `CrmLeadShowEscopoTest.php`.
- Achado 2 (SELECT com colunas removidas): consertado na raiz no #8373; `CrmUtil.php` não contém
  `'contacts.prefix'`; teste `CrmLeadsListQueryTest.php`.
- Pendente 1 do `_saida-08` (`edit`/`update`/`destroy` sem `type = lead`): **fechado pela thread 09**.
  Os três passam por `leadQuery()` (`business_id` + `type = lead` + "só os meus").
- O placar marca a 08 `em curso (indecidível)` porque a prova é `execucao`, que o
  `placar-indice.mjs` não avalia. Nada a executar.

## Prova estrutural proposta para o índice (aplicar no Cowork)
O `00-INDICE.md` é do Cowork (editar o espelho à mão arma o gate de frescor, §5 2026-09-24).
Avaliadas com `avaliarProva` neste `main`: todas OK; controle negativo (`contem` do mesmo padrão
no `CrmUtil.php`) sai NÃO.

```json
[
  { "tipo": "contem", "path": "${MOD}/Http/Controllers/LeadController.php", "padrao": "->where('type', 'lead')" },
  { "tipo": "nao_contem", "path": "${MOD}/Utils/CrmUtil.php", "padrao": "'contacts.prefix'" },
  { "tipo": "arquivo", "path": "${MOD}/Tests/Feature/CrmLeadShowEscopoTest.php" },
  { "tipo": "arquivo", "path": "${MOD}/Tests/Feature/CrmLeadsListQueryTest.php" }
]
```

## Medição
- **Antes** (`node scripts/qa/placar.mjs --indice`, `main` de 2026-10-07):
  `08 [em curso ] (indecidível) … prova "execucao" precisa do avaliador de recibo`.
  O Crm aparece como `entregue 6 de 10 · em curso 2`.
- **Depois** (não dá para rodar o placar com o índice trocado sem editar o espelho; medi prova a
  prova com `avaliarProva` do `scripts/qa/placar-indice.mjs`): **4 de 4 OK**. Com o JSON acima no
  índice, a 08 deixa de ser indecidível.

## Placar
`08`: entregue. O `00-INDICE.md` não foi editado.
