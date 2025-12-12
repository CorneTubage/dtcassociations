import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue2";
import path from "path";

export default defineConfig({
  plugins: [vue()],
  // Correction pour l'erreur "process is not defined" et le problème de syntaxe {}.NODE_ENV
  define: {
    // On utilise '({})' au lieu de {} pour éviter que l'objet soit interprété comme un bloc de code
    "process.env": "({})",
    // On définit process globalement pour les librairies qui l'utilisent directement
    process: "({ env: {} })",
  },
  build: {
    // On génère les fichiers dans les dossiers standards de Nextcloud
    outDir: path.resolve(__dirname, "./"),
    lib: {
      entry: path.resolve(__dirname, "src/main.js"),
      name: "dtcassociations",
      formats: ["umd"],
      fileName: (format) => `js/dtcassociations-main.js`,
    },
    rollupOptions: {
      output: {
        // Le CSS sera extrait ici
        assetFileNames: (assetInfo) => {
          if (assetInfo.name == "style.css")
            return "css/dtcassociations-main.css";
          return assetInfo.name;
        },
      },
    },
    // Minimisation pour la prod, sourcemap utile pour le debug
    minify: true,
    sourcemap: false,
    emptyOutDir: false, // Important : ne pas supprimer les autres fichiers de l'app !
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "src"),
    },
  },
});
