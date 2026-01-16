import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue2";
import path from "path";

export default defineConfig({
  plugins: [vue()],
  define: {
    "process.env": "({})",
    process: "({ env: {} })",
  },
  build: {
    outDir: path.resolve(__dirname, "./"),
    lib: {
      entry: path.resolve(__dirname, "src/main.js"),
      name: "dtcassociations",
      formats: ["umd"],
      fileName: (format) => `js/dtcassociations-main.js`,
    },
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name == "style.css")
            return "css/dtcassociations-main.css";
          return assetInfo.name;
        },
      },
    },
    minify: true,
    sourcemap: false,
    emptyOutDir: false,
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "src"),
    },
  },
});
