---
sessao: "_saida-11b"
thread: "11 · Computadores + Empresas — adendo: ações da ficha da empresa"
dono: "[CL]"
data: 2026-10-13
tipo: adendo ao _saida-11 (ações na tela React atrás de flag OFF; cutover é do [W])
---
# _saida-11b

Adendo ao `_saida-11`, que fica intacto. Lá, a ficha `Officeimpresso/Empresa/Show` era só leitura: editar
a configuração, bloquear a empresa e bloquear máquina só existiam pela Blade (`?legacy=1`). As três ações
entraram na ficha React em dois PRs. Conferido no `main` `f363d90b35`.

## O que entregou
- **Editar e bloquear a empresa** — [#9178](https://github.com/wagnerra23/oimpresso.com/pull/9178).
  - "Editar" abre um Dialog com caminho do banco no servidor, versão obrigatória e versão disponível.
    O erro de validação volta no campo.
  - "Bloquear/Liberar empresa" pede confirmação e manda a intenção (`bloquear`). Se a empresa já está
    no estado pedido, o backend recusa, então um duplo clique não desfaz o bloqueio.
  - A ficha recebe a prop `permissions` (`empresa`, `gerenciar`). Ela só esconde botão; quem autoriza
    é o controller.
  - Backend: `businessupdate` passa a validar `caminho_banco_servidor`, o campo que o form manda e o
    service grava (empresa-parity D3). A validação saiu de dentro do `try`, então a Blade passa a
    receber o erro de validação no lugar do flash genérico. `businessbloqueado` aceita a intenção; sem
    ela, a Blade segue com o toggle de sempre.
- **Bloquear e liberar máquina** — [#9182](https://github.com/wagnerra23/oimpresso.com/pull/9182).
  - Cada linha da tabela de computadores ganha "Bloquear/Liberar", com motivo obrigatório de 5 a 500
    caracteres.
  - Usa o mesmo `POST toggle-block` do drawer da lista de licenças. O motivo vai para o `licenca_log`
    com o negócio do equipamento, e a mensagem do desktop não muda.

`?legacy=1` continua como rota de fuga até o cutover. O que a ficha ainda não faz: editar
`officeimpresso_numerodemaquinas`, que a Blade também não oferece.

## Prova
`Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresaAcoesContratoTest.php`, na lane
`officeimpresso-pest` (MySQL, tenant 98 × 99):

| UC | o que prova | run |
|---|---|---|
| UC-EMP-08 (×2) | editar grava os 3 campos; erro de validação no `caminho_banco_servidor` sem gravar | 38389308176 (#9178) |
| UC-EMP-09 | bloquear é intenção: o 2º pedido não desfaz; liberar libera | 38389308176 (#9178) |
| UC-EMP-22 | `permissions` reflete `empresa.gerenciar` e `licencas.gerenciar` | 38393297252 (#9182) |
| UC-EMP-23 [T0] | usuário de empresa cliente com a permissão: 403 ao editar e ao bloquear outra empresa, nada muda | 38389308176 (#9178) |
| UC-EMP-16 | bloquear máquina exige motivo, grava no histórico com o negócio do equipamento, e a tabela mostra o novo status | 38393297252 (#9182) |
| UC-EMP-24 [T0] | usuário de empresa cliente com `licencas.gerenciar`: 403 na máquina de outra empresa, nada no histórico | 38393297252 (#9182) |

No run do #9182 a lane fechou com `116 passed (607 assertions)`. No run do #9178, este arquivo passou
5/5, mas a lane saiu vermelha por outro teste: o caso 5 do `CatalogoRedirectTest`, quebrado pelo #9166,
que pôs o Catálogo QR atrás de uma chave desligada. O conserto entrou no
[#9179](https://github.com/wagnerra23/oimpresso.com/pull/9179).

## Fica para o [W]
- Ligar `useV2OfficeimpressoEmpresa` em produção.
- Depois, apagar a Blade `computadores` e a flag (RUNBOOK-empresa §F5).
