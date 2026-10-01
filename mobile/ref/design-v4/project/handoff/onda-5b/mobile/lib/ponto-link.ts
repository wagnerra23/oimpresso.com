/**
 * abrirPonto — leva o colaborador ao REP-P sem duplicar o ponto no app Expo (Onda 5b).
 *
 * 1. Linking.openURL(https://<web>/ponto/mobile): se o app de ponto (Capacitor, ADR 0423)
 *    estiver instalado, o sistema entrega o App Link/Universal Link a ele.
 * 2. Se ninguém reivindicar o link, cai no navegador interno com a tela REP-P web
 *    (sessão web — as rotas 1b de routes.php não exigem Passport nem ponto.access).
 *
 * Sem câmera, sem biometria, sem NSR no cliente — tudo continua no servidor.
 */
import { Linking, Platform } from "react-native";
import * as WebBrowser from "expo-web-browser";

const WEB = (process.env.EXPO_PUBLIC_WEB_URL ?? "").replace(/\/$/, "");
export const PONTO_URL = `${WEB}/ponto/mobile`;

export async function abrirPonto(): Promise<"app" | "navegador" | "erro"> {
  if (!WEB) return "erro";
  try {
    // Android: openURL com https segue App Links verificados → abre o app de ponto se instalado.
    // iOS: Universal Links só disparam via openURL (não dentro de WebView).
    if (Platform.OS !== "web") {
      const ok = await Linking.canOpenURL(PONTO_URL);
      if (ok) {
        await Linking.openURL(PONTO_URL);
        return "app";
      }
    }
    await WebBrowser.openBrowserAsync(PONTO_URL, { presentationStyle: WebBrowser.WebBrowserPresentationStyle.PAGE_SHEET });
    return "navegador";
  } catch {
    return "erro";
  }
}
