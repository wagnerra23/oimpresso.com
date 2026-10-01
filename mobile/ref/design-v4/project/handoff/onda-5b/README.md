# Onda 5b — Ponto por link para o app de ponto (recomendada)

**Item 04:** o app Expo não tem ponto, mas a empresa **já tem** um app de ponto (Capacitor, nas lojas — ADR 0423) e o REP-P web (`/ponto/mobile`). A 5b liga o Expo a eles em vez de duplicar.

## Como funciona
O módulo "Ponto" do Expo abre `https://<domínio>/ponto/mobile`:
- **App de ponto instalado** → o Android/iOS entrega o link a ele (App Links / Universal Links já servidos em `/.well-known/assetlinks.json` e `apple-app-site-association`, routes.php 1d).
- **Não instalado** → abre no navegador interno, na tela REP-P web (sessão web, sem Passport).

## Por que recomendo 5b em vez da 5 nativa
| | 5b link | 5 nativa |
| --- | --- | --- |
| Token Passport no Expo | não precisa | precisa (bloqueio) |
| Rota nova no servidor | nenhuma | espelho mensal + motivos |
| Dois lugares para manter o REP-P | não | sim (web/Capacitor + Expo) |
| Lembrete de bater ponto (push, ADR 0423) | já existe no app de ponto | refazer |
| Fluxo dentro do Expo | sai para outro app | fica no Expo |

A 5 nativa continua em `onda-5/` (rotas conferidas) para quando a decisão for unificar.

## Arquivos
| Arquivo | Ação |
| --- | --- |
| `mobile/lib/ponto-link.ts` | **Criar.** `abrirPonto()` — App Link → navegador interno. |
| `mais.patch` | **Aplicar.** Módulo "Ponto" na tela Mais / perfis de menu. |
| Env | `EXPO_PUBLIC_WEB_URL` (ex.: `https://oimpresso.com`). |
| Dependência | `expo-web-browser` (`npx expo install expo-web-browser`) — provavelmente já instalado pelo template; conferir. |

## Decisão do Wagner
Confirmar 5b como padrão. Se a resposta for "um app só, o Expo", seguir para `onda-5/`.
