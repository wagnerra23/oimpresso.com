import { useRouter, useSegments } from "expo-router";
import { useEffect } from "react";
import { ActivityIndicator, View } from "react-native";

import { useAuthContext } from "@/lib/auth-context";

/**
 * Routes that should remain accessible to unauthenticated users.
 * `oauth` covers the OAuth callback deep link; `login` is the login screen.
 */
const PUBLIC_ROUTES = new Set<string>(["login", "oauth"]);

/**
 * AuthGate redirects:
 *  - unauthenticated users to /login (unless already on a public route)
 *  - authenticated users away from /login back into the app
 *
 * While auth status is still loading, renders a centered spinner so the
 * gated content never flashes onto the screen.
 */
export function AuthGate({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, loading } = useAuthContext();
  const segments = useSegments();
  const router = useRouter();

  useEffect(() => {
    if (loading) return;

    const root = segments[0];
    const isPublic = root ? PUBLIC_ROUTES.has(root) : false;

    if (!isAuthenticated && !isPublic) {
      router.replace("/login");
    } else if (isAuthenticated && root === "login") {
      router.replace("/(tabs)");
    }
  }, [isAuthenticated, loading, segments, router]);

  if (loading) {
    return (
      <View className="flex-1 items-center justify-center bg-background">
        <ActivityIndicator size="large" />
      </View>
    );
  }

  return <>{children}</>;
}
