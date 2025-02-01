

import Home from "./pages/Home.vue";

Statamic.booting(() => {
    Statamic.$components.register("home", Home);
});
