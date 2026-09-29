// ──────────────────────────────────────────────────────────────
// Autenticação por senha + 2FA (TOTP). Caminho PRIMÁRIO de login,
// para não depender só de OAuth de terceiro (corrige v2 §10 do laudo
// de produto: dependência de "Manus"). OAuth fica como opção.
//
// Skeleton de contrato — plugue argon2 + otplib na implementação.
// ──────────────────────────────────────────────────────────────

export interface PasswordHasher {
  hash(plain: string): Promise<string>;
  verify(hash: string, plain: string): Promise<boolean>;
}

// Implementação real: argon2id (recomendado).
export const passwordHasher: PasswordHasher = {
  async hash(plain) {
    // return argon2.hash(plain, { type: argon2.argon2id });
    throw new Error("TODO: integrar argon2 (Passo 2 — implementação)");
  },
  async verify(hash, plain) {
    // return argon2.verify(hash, plain);
    throw new Error("TODO: integrar argon2 (Passo 2 — implementação)");
  },
};

export interface TotpProvider {
  generateSecret(): string;
  verify(secret: string, token: string): boolean;
}

// Implementação real: otplib (authenticator).
export const totp: TotpProvider = {
  generateSecret() {
    // return authenticator.generateSecret();
    throw new Error("TODO: integrar otplib (2FA)");
  },
  verify(secret, token) {
    // return authenticator.verify({ secret, token });
    throw new Error("TODO: integrar otplib (2FA)");
  },
};
