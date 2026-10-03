---
id: resources-js-pages-sells-shipments-index-casos
casos: Remessas · /shipments
irmaos: Index.charter.md (lei) · Index.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a fila de entrega reflete o que está gravado na venda — isso não muda quando a tela troca de Blade pra React.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Remessas

> **Fonte:** UC-REM-01..04 vêm do texto revisado em
> `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Remessas.casos.md` (Cowork, 2026-08-22).
> UC-REM-05..07 nascem nesta thread pra defender o que a migração pode quebrar
> (isolamento entre empresas, permissão e o caminho Inertia).
>
> **Teste:** `tests/Feature/Sells/SellsShipmentsContratoTest.php` — tenant 98 × 99
> (`seededSupportClientTenant()`), `DatabaseTransactions`. Nunca biz=4.
>
> ⚖️ **Lane:** `PHP / Pest (Sells · MySQL)` — [`.github/workflows/sells-pest.yml`](../../../../../.github/workflows/sells-pest.yml).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-REM-01 · Mudar o status salva na venda `[must]`
- **Persona:** quem monta o romaneio.
- **Aceite:** Dado uma remessa "Pedido", quando mudo pra "Enviado" e salvo, então a linha reflete e o aviso nomeia o novo status.
- **Teste:** `SellsShipmentsContratoTest` — `UC-REM-01 salvar pelo endpoint existente grava o novo status na própria venda` (o `PUT /sells/update-shipping/{id}` grava `shipped` na transação; o drawer lê o mesmo dado de volta em `GET /sells/edit-shipping/{id}`). O texto do aviso é do front e não tem teste de render.
- **Regressão que defende:** drawer salvando por outro caminho, ou criando registro novo em vez de atualizar a venda.
- **Status: 🧪**

## UC-REM-02 · Filtro por entregador `[must]`
- **Persona:** o entregador conferindo a própria fila.
- **Aceite:** Dado filtro por entregador, então só as remessas dele restam.
- **Teste:** `SellsShipmentsContratoTest` — `UC-REM-02 filtro por entregador deixa só as remessas dele` (`GET /sells?only_shipments=true&delivery_person=X`, a mesma fonte do Blade).
- **Regressão que defende:** filtro ignorado pela fonte de dados.
- **Status: 🧪**

## UC-REM-03 · Célula sem dado mostra "—"
- **Persona:** quem lê a fila.
- **Aceite:** Dado uma venda sem rastreio (no legado, o dado de envio fica em "Detalhes de envio"), então a coluna mostra "—", nunca vazio mudo.
- **Teste:** `SellsShipmentsContratoTest` — `UC-REM-03 a tela troca célula vazia por travessão` (estrutural: a função de célula do `.tsx`).
- **Regressão que defende:** coluna em branco que parece dado faltando por erro.
- **Status: 🧪**

## UC-REM-04 · Romaneio sem valores
- **Persona:** quem monta o romaneio.
- **Aceite:** Dado clique em "Imprimir romaneio", então sai a folha sem valores.
- **Teste:** `SellsShipmentsContratoTest` — `UC-REM-04 imprimir romaneio usa o modo packing_slip` (estrutural: a tela chama `printSaleReceipt` no modo `packing_slip`, que vira `?package_slip=true`).
- **Regressão que defende:** romaneio saindo como fatura com preço.
- **Status: 🧪**

## UC-REM-05 · Remessa de outra empresa não abre nem salva `[T0]` `[must]`
- **Persona:** qualquer operador — nunca vê nem altera a venda de outra empresa.
- **Aceite:** Dado uma venda do business 99 · Quando o usuário do 98 pede `GET /sells/edit-shipping/{id}` ou `PUT /sells/update-shipping/{id}` · Então não recebe os dados e o status da venda do 99 não muda.
- **Teste:** `SellsShipmentsContratoTest` — `UC-REM-05 [T0] remessa de outro business não abre nem muda`.
- **Regressão que defende:** endpoint de remessa perdendo o `where business_id` (ADR 0093).
- **Status: 🧪**

## UC-REM-06 · Sem permissão de remessa, sem tela `[must]`
- **Persona:** usuário sem `access_shipping`, `access_own_shipping` nem `access_commission_agent_shipping`.
- **Aceite:** Dado esse usuário · Quando abre `/shipments` pelo Inertia ou o drawer pede a remessa · Então recebe 403.
- **Teste:** `SellsShipmentsContratoTest` — `UC-REM-06 sem permissão de remessa a tela e o drawer devolvem 403`.
- **Regressão que defende:** tela nova sem a checagem que o Blade tinha.
- **Status: 🧪**

## UC-REM-07 · O browser recebe a tela React
- **Persona:** quem navega dentro do shell novo.
- **Aceite:** Dado uma requisição com `X-Inertia` e `X-Requested-With` (o que o cliente Inertia manda) · Quando abre `/shipments` · Então o componente é `Sells/Shipments/Index` com status de envio, filtros e as URLs dos endpoints existentes; sem `X-Inertia` segue o Blade.
- **Teste:** `SellsShipmentsContratoTest` — `UC-REM-07 Inertia renderiza Sells/Shipments/Index e o Blade segue como fallback`.
- **Regressão que defende:** o `ajax()` engolindo a requisição Inertia, ou a tela apontando pra rota inexistente.
- **Status: 🧪**

## Trilha do tempo
- 2026-10-01 · [CL] trio nasce na thread 02 do playbook venda-menu (charter + casos + teste juntos). Refs: ADR 0104 · ADR 0264 G-1/G-2.
