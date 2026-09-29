// ──────────────────────────────────────────────────────────────
// OAuth — caminho SECUNDÁRIO de login (corporativo). Skeleton de
// porta: troca code → perfil. Nunca o único caminho (ver password.ts).
// ──────────────────────────────────────────────────────────────

export interface OAuthProfile {
  email: string;
  nome: string;
  externalId: string;
}

export interface OAuthProvider {
  /** URL para iniciar o fluxo (redirect). */
  authorizeUrl(state: string): string;
  /** Troca o code recebido no callback por um perfil. */
  exchange(code: string): Promise<OAuthProfile>;
}

// Implemente um provider concreto (ex.: Google Workspace, Microsoft Entra).
export function makeOAuthProvider(_config: {
  clientId: string; clientSecret: string; redirectUri: string;
}): OAuthProvider {
  return {
    authorizeUrl() { throw new Error("TODO: implementar provider OAuth"); },
    async exchange() { throw new Error("TODO: implementar provider OAuth"); },
  };
}
