import { useEffect, useRef } from "react";
import { Platform } from "react-native";
import Constants from "expo-constants";
import { router } from "expo-router";

import { trpc } from "@/lib/trpc";
import { useAuthContext } from "@/lib/auth-context";

/**
 * Expo Go on Android (SDK 53+) removed remote-push support and emits a noisy
 * error just from importing `expo-notifications` (DevicePushTokenAutoRegistration
 * runs at module load). We detect Expo Go via `appOwnership === "expo"` and
 * skip both the import and the registration entirely.
 */
const isExpoGo = Constants.appOwnership === "expo";

function pickPlatform(): "ios" | "android" | "web" {
  if (Platform.OS === "ios") return "ios";
  if (Platform.OS === "android") return "android";
  return "web";
}

type NotificationsModule = typeof import("expo-notifications");

async function loadNotifications(): Promise<NotificationsModule | null> {
  if (isExpoGo) return null;
  try {
    const mod = await import("expo-notifications");
    // Foreground display config — SDK 54 banner/list flags.
    mod.setNotificationHandler({
      handleNotification: async () => ({
        shouldShowBanner: true,
        shouldShowList: true,
        shouldPlaySound: true,
        shouldSetBadge: false,
      }),
    });
    return mod;
  } catch (err) {
    console.warn("[push] failed to load expo-notifications:", err);
    return null;
  }
}

/**
 * Registers the device for Expo push notifications once the user is
 * authenticated, and wires up tap-to-navigate handling.
 *
 * Safe to call from a component mounted inside AuthProvider — the hook is a
 * no-op until `isAuthenticated` flips true, and short-circuits on web.
 */
export function usePushRegistration() {
  const { isAuthenticated } = useAuthContext();
  const registerToken = trpc.notifications.registerToken.useMutation();
  const didRegisterRef = useRef(false);

  // One-shot registration after auth is ready.
  useEffect(() => {
    if (!isAuthenticated || didRegisterRef.current) return;
    if (Platform.OS === "web" || isExpoGo) return;

    let cancelled = false;
    didRegisterRef.current = true;

    (async () => {
      const Notifications = await loadNotifications();
      if (!Notifications || cancelled) return;

      try {
        if (Platform.OS === "android") {
          try {
            await Notifications.setNotificationChannelAsync("default", {
              name: "default",
              importance: Notifications.AndroidImportance.DEFAULT,
            });
          } catch (err) {
            console.warn("[push] failed to set Android channel:", err);
          }
        }

        const settings = await Notifications.getPermissionsAsync();
        let granted =
          settings.granted ||
          settings.ios?.status ===
            Notifications.IosAuthorizationStatus.PROVISIONAL;
        if (!granted) {
          const req = await Notifications.requestPermissionsAsync();
          granted =
            req.granted ||
            req.ios?.status ===
              Notifications.IosAuthorizationStatus.PROVISIONAL;
        }
        if (!granted) return;

        const projectId =
          (Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined)
            ?.eas?.projectId ??
          (Constants.easConfig as { projectId?: string } | undefined)?.projectId;

        let token: string | undefined;
        try {
          const result = await Notifications.getExpoPushTokenAsync(
            projectId ? { projectId } : undefined,
          );
          token = result.data;
        } catch (err) {
          console.warn("[push] getExpoPushTokenAsync failed:", err);
          return;
        }

        if (!token || cancelled) return;

        try {
          await registerToken.mutateAsync({ token, platform: pickPlatform() });
        } catch (err) {
          console.error("[push] registerToken mutation failed:", err);
        }
      } catch (err) {
        console.error("[push] registration error:", err);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [isAuthenticated, registerToken]);

  // Tap-to-open: route based on notification data.
  useEffect(() => {
    if (isExpoGo || Platform.OS === "web") return;

    let sub: { remove: () => void } | null = null;
    let cancelled = false;

    (async () => {
      const Notifications = await loadNotifications();
      if (!Notifications || cancelled) return;
      sub = Notifications.addNotificationResponseReceivedListener((response) => {
        const data = response.notification.request.content.data as
          | { type?: string }
          | undefined;
        if (!data?.type) return;
        try {
          if (data.type === "pedido") router.push("/(tabs)/vendas");
          else if (data.type === "op") router.push("/(tabs)/producao");
        } catch (err) {
          console.warn("[push] navigation failed:", err);
        }
      });
    })();

    return () => {
      cancelled = true;
      sub?.remove();
    };
  }, []);
}
