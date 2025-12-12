import Vue from "vue";
import App from "./App.vue";
import { translate, translatePlural } from "@nextcloud/l10n";

Vue.mixin({
  methods: {
    t: translate,
    n: translatePlural,
  },
});

document.addEventListener("DOMContentLoaded", () => {
  const el = document.getElementById("dtcassociations-content");

  if (el) {
    const View = Vue.extend(App);
    new View().$mount(el);
  }
});
