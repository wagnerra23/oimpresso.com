# _DECISOES-W-2026-10-01b — Officeimpresso (Code → Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` DO COWORK. O Code não edita o espelho.
> **Fonte:** [W] 2026-10-01 (2ª rodada), resposta textual item a item às decisões que a 1ª rodada (`_DECISOES-W-2026-10-01.md`, "Aprove todos") devolveu ou não cobria.
> **Precedência:** onde esta rodada contradiz a 1ª, **vale esta** — a 1ª foi critério genérico ("a opção que a thread já assume"); esta é a palavra do [W] por item.

| id | resposta [W] (textual) | efeito | revoga |
|---|---|---|---|
| D4 | *"não"* | **NÃO dropar** `senha`/`contra_senha`. A thread 03 não roda a migration destrutiva. | 1ª rodada D4 "sim" |
| D1 | *"todos meus funcionários da empresa 1 podem ter acessos, e pode revogar minhas decisões anteriores. só não esqueça de testar o Delphi conectado"* | **manter** o painel de credenciais; delegação de acesso permitida a todo funcionário do negócio 1 (operador). A regra do charter do Connector que proibia a delegação fica revogada. **DoD:** smoke com o Delphi conectado antes de declarar pronto. | 1ª rodada D1 "aposentar e redirecionar" + Non-Goal de delegação no charter do Connector |
| D3 (QR) | *"pode ajustar primeiro"* | antes de apontar o QR pro catálogo novo, **ajustar os pacotes** para quem tem só Officeimpresso não tomar 403. Ordem: pacotes → redirect. | — (condiciona a D3) |
| contrato Delphi | *"sim contrato desatualizado. porque eu descriptografo e gravo a senha nova no php"* | o contrato que diz "o segredo não pode ser criptografado" está **desatualizado**: o PHP descriptografa e grava a senha nova; segredo criptografado/hasheado é aceito. Corrigir o contrato, não o código. | — |
| flag | *"pode ligar"* | `useV2OfficeimpressoLicencas` ligada por default (`FeatureFlagService::$fallbackDefaults`). | — |

## Edição pedida no json
```json
[{ "id": "D4", "respondida": true, "resposta": "NÃO dropar senha/contra_senha" },
 { "id": "D1", "respondida": true, "resposta": "manter painel; delegação a todo funcionário do negócio 1; testar Delphi conectado" },
 { "id": "D3", "respondida": true, "resposta": "ajustar pacotes antes do redirect do QR" }]
```
