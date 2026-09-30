import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    globals: true,
    environment: "node",
  },
  resolve: {
    alias: {
      "@oimpresso/shared": new URL("./packages/shared/src", import.meta.url).pathname,
    },
  },
});
