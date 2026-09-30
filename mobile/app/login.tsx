import React, { useCallback, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";
import { useRouter } from "expo-router";
import { LinearGradient } from "expo-linear-gradient";
import { SafeAreaView } from "react-native-safe-area-context";

import { OiBrandLogo } from "@/components/oi";
import { startOAuthLogin } from "@/constants/oauth";
import { useAuthContext } from "@/lib/auth-context";
import * as Auth from "@/lib/_core/auth";
import { DEMO_MODE } from "@/lib/demo";
import { brand, fonts, radius } from "@/lib/oi-theme";
import { trpc } from "@/lib/trpc";

type Mode = "login" | "register";

/**
 * Login screen — Oimpresso v2 brand gradient + dois caminhos:
 *   1) Email + senha (form local).
 *   2) Manus OAuth (botão secundário).
 *
 * Preserva 100% da lógica anterior — só a apresentação muda.
 */
export default function LoginScreen() {
  const router = useRouter();
  const { refresh, loading: authLoading } = useAuthContext();

  const [mode, setMode] = useState<Mode>("login");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [oauthSubmitting, setOauthSubmitting] = useState(false);

  const loginMutation = trpc.auth.loginWithPassword.useMutation();
  const registerMutation = trpc.auth.registerWithPassword.useMutation();

  const submitting = loginMutation.isPending || registerMutation.isPending;

  const handlePasswordSubmit = useCallback(async () => {
    setError(null);
    try {
      const result =
        mode === "login"
          ? await loginMutation.mutateAsync({
              email: email.trim(),
              password,
            })
          : await registerMutation.mutateAsync({
              email: email.trim(),
              password,
              name: name.trim(),
            });

      await Auth.setSessionToken(result.sessionToken);
      await Auth.setUserInfo({
        id: result.user.id,
        openId: result.user.openId,
        name: result.user.name,
        email: result.user.email,
        loginMethod: result.user.loginMethod,
        lastSignedIn: new Date(result.user.lastSignedIn),
      });
      await refresh();
      router.replace("/(tabs)");
    } catch (err: unknown) {
      const message =
        err && typeof err === "object" && "message" in err
          ? String((err as { message: string }).message)
          : mode === "login"
          ? "Não foi possível entrar. Verifique seus dados."
          : "Não foi possível criar a conta.";
      setError(message);
    }
  }, [mode, email, password, name, loginMutation, registerMutation, refresh, router]);

  const handleOAuth = useCallback(async () => {
    setError(null);
    setOauthSubmitting(true);
    try {
      if (DEMO_MODE) {
        await refresh();
        router.replace("/(tabs)");
      } else {
        await startOAuthLogin();
      }
    } catch (err) {
      console.error("[Login] startOAuthLogin failed:", err);
      setError(
        err instanceof Error
          ? err.message
          : "Não foi possível iniciar o login OAuth.",
      );
    } finally {
      setOauthSubmitting(false);
    }
  }, [refresh, router]);

  const switchMode = useCallback(() => {
    setError(null);
    setMode((m) => (m === "login" ? "register" : "login"));
  }, []);

  return (
    <LinearGradient
      colors={[brand.deep, brand.purple, brand.magenta]}
      start={{ x: 0, y: 0 }}
      end={{ x: 1, y: 1 }}
      style={{ flex: 1 }}
    >
      <SafeAreaView style={{ flex: 1 }} edges={["top", "bottom", "left", "right"]}>
        <ScrollView
          contentContainerStyle={{
            flexGrow: 1,
            justifyContent: "center",
            paddingHorizontal: 24,
            paddingVertical: 32,
          }}
          keyboardShouldPersistTaps="handled"
        >
          <View style={{ alignItems: "center", marginBottom: 32 }}>
            <OiBrandLogo size="lg" wordmark wordmarkColor="#fff" stacked />
            <Text
              style={{
                marginTop: 14,
                color: "rgba(255,255,255,0.82)",
                fontFamily: fonts.sans,
                fontSize: 14,
                textAlign: "center",
              }}
            >
              Sistema de gestão para gráficas
            </Text>
          </View>

          <View
            style={{
              backgroundColor: "rgba(255,255,255,0.10)",
              borderColor: "rgba(255,255,255,0.18)",
              borderWidth: 1,
              borderRadius: radius.lg,
              padding: 20,
            }}
          >
            <Text
              style={{
                color: "#fff",
                fontFamily: fonts.sansSemibold,
                fontSize: 16,
                textAlign: "center",
                marginBottom: 4,
              }}
            >
              {mode === "login" ? "Entrar" : "Criar conta"}
            </Text>
            <Text
              style={{
                color: "rgba(255,255,255,0.72)",
                fontFamily: fonts.sans,
                fontSize: 12.5,
                textAlign: "center",
                marginBottom: 16,
              }}
            >
              {mode === "login"
                ? "Use seu email e senha para acessar."
                : "Crie sua conta para começar."}
            </Text>

            {error ? (
              <View
                style={{
                  backgroundColor: "rgba(255,255,255,0.16)",
                  borderRadius: radius.md,
                  padding: 10,
                  marginBottom: 12,
                }}
              >
                <Text
                  style={{
                    color: "#fff",
                    fontFamily: fonts.sansMedium,
                    fontSize: 12.5,
                    textAlign: "center",
                  }}
                >
                  {error}
                </Text>
              </View>
            ) : null}

            {mode === "register" && (
              <LoginInput
                placeholder="Seu nome"
                value={name}
                onChangeText={setName}
                returnKeyType="next"
              />
            )}
            <LoginInput
              placeholder="voce@exemplo.com"
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
              returnKeyType="next"
            />
            <LoginInput
              placeholder={mode === "register" ? "Senha (8+ caracteres)" : "Sua senha"}
              value={password}
              onChangeText={setPassword}
              secureTextEntry
              returnKeyType="done"
              onSubmitEditing={handlePasswordSubmit}
            />

            {submitting || authLoading ? (
              <View style={{ paddingVertical: 12, alignItems: "center" }}>
                <ActivityIndicator color="#fff" />
              </View>
            ) : (
              <Pressable
                onPress={handlePasswordSubmit}
                style={({ pressed }) => ({
                  marginTop: 8,
                  backgroundColor: pressed ? "#ffffffd0" : "#ffffff",
                  borderRadius: radius.md,
                  paddingVertical: 12,
                  alignItems: "center",
                })}
              >
                <Text
                  style={{
                    color: brand.deep,
                    fontFamily: fonts.sansSemibold,
                    fontSize: 14,
                  }}
                >
                  {mode === "login"
                    ? "Entrar com email + senha"
                    : "Criar minha conta"}
                </Text>
              </Pressable>
            )}

            <Pressable onPress={switchMode} style={{ marginTop: 10 }}>
              <Text
                style={{
                  color: "rgba(255,255,255,0.92)",
                  fontFamily: fonts.sansMedium,
                  fontSize: 12.5,
                  textAlign: "center",
                }}
              >
                {mode === "login"
                  ? "Não tem conta? Criar agora"
                  : "Já tem conta? Entrar"}
              </Text>
            </Pressable>
          </View>

          <Pressable
            onPress={handleOAuth}
            disabled={oauthSubmitting}
            style={({ pressed }) => ({
              marginTop: 14,
              backgroundColor: pressed
                ? "rgba(255,255,255,0.16)"
                : "rgba(255,255,255,0.08)",
              borderColor: "rgba(255,255,255,0.20)",
              borderWidth: 1,
              borderRadius: radius.md,
              paddingVertical: 12,
              alignItems: "center",
              opacity: oauthSubmitting ? 0.6 : 1,
            })}
          >
            <Text
              style={{
                color: "#fff",
                fontFamily: fonts.sansSemibold,
                fontSize: 13.5,
              }}
            >
              {oauthSubmitting ? "Abrindo..." : "Entrar com Manus OAuth"}
            </Text>
          </Pressable>

          <Text
            style={{
              marginTop: 24,
              color: "rgba(255,255,255,0.62)",
              fontFamily: fonts.sans,
              fontSize: 11,
              textAlign: "center",
            }}
          >
            Ao continuar você concorda com os termos de uso do app.
          </Text>
        </ScrollView>
      </SafeAreaView>
    </LinearGradient>
  );
}

type LoginInputProps = React.ComponentProps<typeof TextInput>;

function LoginInput(props: LoginInputProps) {
  return (
    <TextInput
      placeholderTextColor="rgba(255,255,255,0.55)"
      {...props}
      style={[
        {
          backgroundColor: "rgba(255,255,255,0.12)",
          borderColor: "rgba(255,255,255,0.18)",
          borderWidth: 1,
          borderRadius: radius.md,
          paddingHorizontal: 12,
          paddingVertical: 11,
          color: "#fff",
          fontFamily: fonts.sans,
          fontSize: 14,
          marginBottom: 10,
        },
        props.style,
      ]}
    />
  );
}
