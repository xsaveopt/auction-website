import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: "jsdom",
        globals: true,
        include: ["resources/js/**/*.spec.ts"],
        css: true,
    },
    resolve: {
        alias: {
            "@": new URL("./resources/js", import.meta.url).pathname,
        },
    },
});
