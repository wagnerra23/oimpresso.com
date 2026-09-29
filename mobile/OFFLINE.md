# Offline-First (F3-04)

The mobile app keeps working when the device is offline using the
**TanStack Query persistence + mutation queue** pattern. This is intentionally
lighter than a full WatermelonDB / SQLite sync layer so it runs in Expo Go
with no native modules.

## What works offline

- **Leitura (queries).** Toda lista / detalhe carregado pelo menos uma vez
  enquanto online fica disponível offline pelos próximos **7 dias**. O cache do
  React Query é persistido em `AsyncStorage` por empresa (multi-tenant).
- **Escrita (mutations).** Qualquer `useMutation` do tRPC que falhar por erro
  de rede entra automaticamente em uma fila local (`mutation-queue:<companyId>`).
  Os updates otimistas existentes (pedidos, clientes, OPs, OS, inventário,
  veículos, transações, orçamentos) atualizam imediatamente o cache, então a
  UI reflete a alteração mesmo offline. O servidor recebe a operação quando a
  conexão volta.
- **Reconciliação automática.** Quando a rede volta (`NetInfo` detecta), a
  fila é drenada em ordem FIFO. Também há um drenar periódico a cada 60s.
- **Banner global.** `components/offline-banner.tsx` aparece no topo das tabs
  com "Você está offline" ou "N alterações pendentes de sincronização" +
  botão **Sincronizar agora**.

## What doesn't work offline

- **Mutations que dependem de serviços externos em tempo real**:
  - Emissão fiscal (`fiscal.documents.emit`) — exige resposta da SEFAZ.
  - Criação de cobranças Asaas (`payments.create`) — exige resposta do gateway.
  - Geração de PDFs de orçamento (`quotes.generatePdf`) — pesada, server-side.
  - Sugestões de IA (`ai.suggestDiagnosis`, `ai.suggestPricing`).
  - Envios de WhatsApp.
  
  Essas chamadas são enfileiradas como qualquer outra, mas vão falhar no
  replay com um erro de aplicação (não-rede). Elas permanecem na fila com
  `error` preenchido para o usuário ver — e podem ser retentadas manualmente
  via **Sincronizar agora**.
- **Queries que nunca rodaram online.** Sem cache prévio, retornam erro.
- **Login.** O fluxo OAuth precisa de internet para a primeira autenticação.
  Sessões já estabelecidas persistem (token em `SecureStore`).

## Como sincronizar manualmente

1. Confirme que está online (sem o banner âmbar "Você está offline").
2. Toque em **Sincronizar agora** no banner (visível enquanto houver itens
   na fila).
3. Itens com sucesso somem; itens com falha mantêm o último `error`.

## Limites e ressalvas

- **TTL do cache:** 7 dias (`maxAge: 7 * 24 * 60 * 60 * 1000`). Cache stale
  por mais que isso é descartado na próxima inicialização.
- **Cache buster:** atrelado a `Constants.expoConfig.version`. Subir a versão
  do app invalida o cache local — isso protege contra mudanças de shape.
- **Limpeza no logout:** o logout limpa o cache persistido **e** a fila de
  mutações da empresa ativa (`auth-context.tsx`).
- **Troca de empresa:** ao trocar empresa, a UI passa a usar a chave
  `erp-query-cache-<novoCompanyId>` (não vaza dados entre tenants).
- **Ordem da fila:** FIFO. Uma falha de rede pausa o ciclo atual; o próximo
  ciclo (reconexão ou timer de 60s) recomeça do mesmo ponto.
- **Sem retry exponencial:** uma tentativa por ciclo. Suficiente porque o
  ciclo dispara em reconexão e a cada 60s.
- **Não substitui WatermelonDB.** Para apps "offline-first puro" (semana sem
  internet, conflitos complexos, queries SQL locais), migrar para
  WatermelonDB + dev build continua na backlog.
