
import Home from "./pages/Home.vue";

Statamic.booting(() => {
    Statamic.$components.register("content-backup-home", Home);
    Statamic.$inertia.register("statamic-content-backup::ContentBackupUtility", Home);
});
