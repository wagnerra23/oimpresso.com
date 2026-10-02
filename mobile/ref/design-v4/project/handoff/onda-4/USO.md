# Onda 4 — como ligar nas telas

## 1. Erro de rede não é erro
Em todo `catch` de mutação, trocar:
```ts
notify("erro", err instanceof Error ? err.message : "Erro ao salvar X");
```
por
```ts
const notifyError = useNotifyError();   // topo do componente
notifyError(err, "Erro ao salvar X");
```
Arquivos com o padrão (main): oss, oss/[id], veiculos, orcamentos, estoque, estoque/[id], fiscal, pagamentos, clientes, produtos, producao, vendas.

## 2. Aviso em Fiscal e Pagamentos
No topo da lista de `fiscal.tsx`:
```tsx
<OiPendingNote path="fiscal.documents.emit"
  offlineMsg="Sem conexão. A emissão fica guardada e vai para a SEFAZ quando a internet voltar — sem duplicar."
  pendingMsg={(n) => `${n} emissão(ões) aguardando envio à SEFAZ.`} />
```
Em `pagamentos.tsx`:
```tsx
<OiPendingNote path="payments.create"
  offlineMsg="Sem conexão. O link de pagamento é gerado no Asaas quando a internet voltar — sem cobrar duas vezes."
  pendingMsg={(n) => `${n} cobrança(s) aguardando o Asaas.`} />
```
Os dois caminhos já têm chave de idempotência na fila (`IDEMPOTENT_PATHS` em mutation-queue.ts).

## 3. Marcar registro pendente na lista
```tsx
const pend = usePendingSync("serviceOrders.");
…
{pend.has("serviceOrders.update", (i) => i.id === os.id) ? <OiStatus label="Pendente" variant="warn" /> : null}
```
