/**
 * useGps + useDeviceUuid + usePontoToken — peças do REP-P no app.
 * GPS real (expo-location, alta precisão). Sem câmera, sem biometria (ADR 0383).
 * NÃO entra na fila offline: o NSR e o hash vêm do servidor e o relógio é conferido
 * no ato (> 30 s recusa) — marcação guardada e enviada depois seria recusada.
 */
import { useCallback, useEffect, useState } from "react";
import * as Location from "expo-location";
import AsyncStorage from "@react-native-async-storage/async-storage";

export type Gps =
  | { estado: "buscando" }
  | { estado: "indisponivel"; motivo: string }
  | { estado: "ok"; lat: number; lng: number; accuracy: number };

export function useGps() {
  const [gps, setGps] = useState<Gps>({ estado: "buscando" });
  const localizar = useCallback(async () => {
    setGps({ estado: "buscando" });
    const perm = await Location.requestForegroundPermissionsAsync();
    if (perm.status !== "granted") {
      setGps({ estado: "indisponivel", motivo: "Localização negada — libere o GPS para o app nas configurações." });
      return;
    }
    try {
      const p = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Highest });
      setGps({ estado: "ok", lat: p.coords.latitude, lng: p.coords.longitude, accuracy: Math.round(p.coords.accuracy ?? 9999) });
    } catch {
      setGps({ estado: "indisponivel", motivo: "Não foi possível obter a localização. Tente em área aberta." });
    }
  }, []);
  useEffect(() => { void localizar(); }, [localizar]);
  return { gps, localizar };
}

/** Identifica o APARELHO (não a pessoa) → dispositivo_id = mobile:{uuid} no servidor. */
export function useDeviceUuid() {
  const [uuid, setUuid] = useState<string | null>(null);
  useEffect(() => {
    const k = "ponto.device_uuid";
    void AsyncStorage.getItem(k).then(async (v) => {
      const cryptoApi = (globalThis as { crypto?: { randomUUID?: () => string } }).crypto;
      const id = v ?? (cryptoApi?.randomUUID?.() ?? `${Date.now().toString(16)}-${Math.random().toString(16).slice(2)}`);
      if (!v) await AsyncStorage.setItem(k, id);
      setUuid(id);
    });
  }, []);
  return uuid;
}

/**
 * ⚠ A CONFIRMAR — origem do token Passport (ver lib/ponto-api.ts).
 * Placeholder: lê o token salvo pelo fluxo de login quando ele existir.
 */
export function usePontoToken(): string | null {
  const [t, setT] = useState<string | null>(null);
  useEffect(() => { void AsyncStorage.getItem("ponto.passport_token").then(setT); }, []);
  return t;
}
