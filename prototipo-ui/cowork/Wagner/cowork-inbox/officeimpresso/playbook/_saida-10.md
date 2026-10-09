---
sessao: "_saida-10"
thread: "10 · Clientes (ClientController@index) → Inertia"
dono: "[CL]"
data: 2026-10-09
tipo: entregue (tela React atrás de flag OFF; cutover é do [W])
---
# _saida-10

## O que entregou
- `ClientController::index` ganhou o caminho dual pela flag `useV2OfficeimpressoClientes`
  (const `FLAG_V2`). Desligada (o default: o GrowthBook não conhece a chave) a Blade
  `clients/index` segue servindo; ligada, a rota responde `Officeimpresso/Clientes/Index`.
- Props: `credencial` e `permissions` eager — a credencial é o flash da criação e, numa prop
  adiada, chegaria num 2º request com o flash já consumido —; `clientes` em `Inertia::defer`,
  DTO `{id, name, tipo}`, sem `secret` (regra da thread 05). O escopo é o mesmo da Blade:
  dono do client (JOIN `users`) no negócio da sessão. A guarda é a mesma `authorizeLiberar()`.
- Trio: `Pages/Officeimpresso/Clientes/Index.tsx` + `.charter.md` + `.casos.md` (UC-OICLI-01..06),
  e `memory/requisitos/Officeimpresso/RUNBOOK-clientes.md` (F1 do MWART, que o hook exige).
- Excluir e "Regenerar chaves" só aparecem para `superadmin`, que é o que o controller aceita.
  A Blade mostrava o botão de excluir para todo mundo e devolvia 403 no clique.
- O "revelar/copiar secret" por linha do protótipo (`ViewClientes()`) NÃO entrou — contradiz a
  thread 05. "Em uso / último handshake" também não: não há fonte desse dado hoje.

## Prova
`Modules/Officeimpresso/Tests/Feature/ClientesIndexContratoTest.php`, na allowlist da lane
`officeimpresso-pest.yml` (MySQL, tenant 98 × 99): Blade com a flag OFF, React com a flag ON,
[T0] isolamento 98×99, [T0] secret ausente do payload, secret da criação uma vez, 403 sem
permissão e excluir/regenerar falsos para o delegado. Veredito é o do CI do PR.

## Fica para o [W]
Ligar `useV2OfficeimpressoClientes` em produção e, depois, apagar a Blade e a flag
(RUNBOOK-clientes §F5).
