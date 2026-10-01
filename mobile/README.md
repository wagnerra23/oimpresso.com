# Oimpresso Mobile

App Expo (SDK 54, React Native 0.81, expo-router) do Oimpresso — pedidos, produção, oficina (OS),
estoque, orçamentos, financeiro e fiscal. Backend tRPC + Drizzle no mesmo projeto (`server/`).

Projeto independente do Laravel na raiz: tem o próprio `package.json`, `tsconfig.json` e lint.
Detalhes de arquitetura, rotas e histórico de fases em [HANDOFF.md](HANDOFF.md).

## Rodar

Requer Node 20.19+ (o Expo 54 não roda no Node 18).

```bash
cd mobile
npm ci                      # package-lock.json é o lockfile válido (o pnpm-lock.yaml está desatualizado)
cp .env.example .env        # preencha — o .env NUNCA vai pro git (repo público)
npx expo start
```

## Testes

```bash
npm test               # Vitest — regras do app (tests/**/*.test.ts)
npx tsc --noEmit       # tipos
```

- `tests/money-format.test.ts` — dinheiro em centavos, parcelas sem perder centavo, formatação, prazos.
- `tests/demo-backend.test.ts` — fluxos de negócio pelo backend de demonstração (pedido → OP,
  orçamento → pedido, totais de OS, estoque, cobrança, nota fiscal, DRE e relatórios) e a checagem de
  que **toda chamada tRPC do app tem resposta no modo demonstração**.

## Modo demonstração

Com `EXPO_PUBLIC_DEMO_MODE=1` o app entra com um usuário fixo e troca o backend tRPC por um
servidor simulado no navegador, com dados de exemplo (`lib/demo/`). Serve para mostrar o app sem
banco, servidor ou OAuth. Os dados de exemplo são tipados contra o `AppRouter`, então `tsc` acusa
se o formato divergir do servidor real.

```bash
EXPO_PUBLIC_DEMO_MODE=1 npx expo start --web
```

Build web estático (SPA) para publicar a demonstração: `EXPO_WEB_OUTPUT=single`
(e `EXPO_WEB_BASE_URL` se for servido numa subpasta) com `npx expo export --platform web`.
