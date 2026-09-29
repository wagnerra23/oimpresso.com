import { useNetInfo } from "@react-native-community/netinfo";

/**
 * Thin wrapper around NetInfo for the F3-04 Offline-First flow.
 *
 * `isOnline` follows NetInfo's `isInternetReachable` when known, falling back
 * to `isConnected` (which only knows about the link layer). On the very first
 * tick both are `null` — we optimistically treat that as online so the UI
 * doesn't flash an offline banner during cold start.
 */
export function useNetworkState(): { isOnline: boolean; type: string } {
  const state = useNetInfo();
  const reachable = state.isInternetReachable;
  const connected = state.isConnected;
  const isOnline =
    reachable === null ? connected !== false : reachable !== false;
  return { isOnline, type: state.type ?? "unknown" };
}
