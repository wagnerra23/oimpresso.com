---
sessao: "_saida-07e"
thread: "07 · Acompanhamentos — PR-c4 (notificação no antecipado) + prova estrutural para o índice"
dono: "[CL]"
data: 2026-10-07
base_lida: wagnerra23/oimpresso.com@main c8777ae00a (fetch em 2026-10-07)
complementa: "_saida-07c.md (pendente 2) e _saida-07d.md"
---
# _saida-07e · Notificação no antecipado e prova que a máquina lê

## Estado ao abrir (medido no `main`)
- PR-a #8649, PR-b #8678, PR-b2 #8680, PR-c1 #8734, PR-c2 #8735, PR-c3 #8736: **todos mergeados**.
- `Index.tsx` sem nenhum `classico` (`grep -c classico` = 0): os 3 botões da toolbar abrem modal.
- O placar marca a 07 `em curso (indecidível)`: a prova do índice é do tipo `execucao`, que o
  `placar-indice.mjs` não avalia (sai NÃO MEDIDA por desenho). Não é falha da entrega.
- Restava 1 pendente executável: o modal do antecipado gravava `allow_notification: 0` fixo,
  enquanto a Blade `create_advance_follow_up` tem o bloco "Enviar notificação".

## Entregue (PR-c4)
- `FormAntecipado.tsx`: bloco "Enviar notificação" (SMS/e-mail, notificar antes, unidade), com os
  padrões da Blade (desligada, e-mail marcado, 1 hora). O backend já normalizava esses campos em
  `CrmUtil::addFollowUp`; nada mudou no PHP.
- UC-CRMACO-21 e teste em `CrmAcompanhamentosContratoTest.php` (lane `verticais-pest`): com
  notificação, cada acompanhamento grava SMS, 2 dias antes (valores fora do padrão, para o assert
  discriminar); sem ela, grava desligada.
- `Index.charter.md`: os 3 Non-Goals (antecipado, registros, rodapé/drawer) estavam vencidos desde o
  c1..c3; viraram Goal, com nota datada.

## Achado do `_saida-07b` refutado
"O comando recorrente monta o input sem `business_id`, e `addFollowUp` não o preenche": falso.
`CrmUtil::addFollowUp` faz `$input['business_id'] = $user->business_id` antes do `create`.

## Prova estrutural proposta para o índice (aplicar no Cowork)
O `00-INDICE.md` é do Cowork e editar o espelho à mão arma o gate de frescor (§5 2026-09-24); por
isso a troca fica proposta aqui. Avaliadas com `avaliarProva` do `placar-indice.mjs` neste branch,
todas OK, e o controle negativo (padrão ausente, arquivo inexistente) sai NÃO:

```json
[
  { "tipo": "nao_contem", "path": "${MPAGES}/Crm/Acompanhamentos/Index.tsx", "padrao": "classico=1" },
  { "tipo": "arquivo", "path": "${MPAGES}/Crm/Acompanhamentos/_components/FormAntecipado.tsx" },
  { "tipo": "arquivo", "path": "${MPAGES}/Crm/Acompanhamentos/_components/FormRecorrente.tsx" },
  { "tipo": "arquivo", "path": "${MPAGES}/Crm/Acompanhamentos/_components/FormRegistro.tsx" },
  { "tipo": "arquivo", "path": "${MPAGES}/Crm/Acompanhamentos/_components/DrawerAcompanhamento.tsx" },
  { "tipo": "contem", "path": "${MOD}/Tests/Feature/CrmAcompanhamentosContratoTest.php", "padrao": "UC-CRMACO-21" }
]
```
A última só fica OK depois do merge deste PR.

## Pendente
- **Decisão [W]**, não executada: `ScheduleLogController@store` filtra só o negócio — quem tem só
  `crm.access_own_schedule` registra em acompanhamento de colega do mesmo negócio (`_saida-07`,
  pendente 3). Restringir muda permissão de uso, não é conserto de vazamento.
- Fora do prefixo da thread, não executado: a Blade `create_recursive_follow_up` manda
  `is_recursive=true` e a regra `boolean` do `StoreScheduleRequest` recusa `"true"` (422 na tela
  clássica, só alcançável por `?classico=1`). Não medido em produção.
- Smoke em produção depois do merge (R1).

## Placar
`07`: entregue; o estado `em curso` vem só do tipo da prova. O `00-INDICE.md` não foi editado.
