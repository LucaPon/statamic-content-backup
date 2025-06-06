<template>
  <div>
    <h1>Backup</h1>

    <div class="flex gap-3">
      <button
        class="flex items-center gap-2 px-4 py-2 mt-4 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
        @click="createBackup"
      >
        <BackupIcon class="w-4" v-if="!backupRunning" />
        <LoadingIcon class="w-4 animate-spin" v-else />
        {{ backupRunning ? "Creating Backup..." : "Create Backup" }}
      </button>
      <button
        ref="uploadButton"
        @click="uploadBackup"
        class="flex items-center gap-2 px-4 py-2 mt-4 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
      >
        <UploadIcon class="w-4" />
        Upload Backup
      </button>
    </div>

    <div class="p-0 mt-3 overflow-hidden card">
      <table class="data-table">
        <thead>
          <tr>
            <th class="rounded-none group current-column sortable-column">
              <span>Name</span>
            </th>
            <th class="rounded-none group current-column sortable-column">
              <span>Size</span>
            </th>
            <th class="rounded-none group current-column sortable-column">
              <span>Created at</span>
            </th>
            <th class="rounded-none actions-column"></th>
          </tr>
        </thead>
        <tbody class="text-gray-800 dark:text-dark-175">
          <tr v-if="!runningBackupName && backups.length === 0" class="">
            <td class="">No backups found</td>
          </tr>

          <tr v-if="runningBackupName" class="">
            <td class="">{{ runningBackupName }}</td>
            <td class=""></td>
            <td class=""></td>
            <td class="">
              <LoadingIcon class="float-right w-4 h-4 animate-spin" />
            </td>
          </tr>

          <tr v-for="(backup, index) in backups" :key="index">
            <td class="">{{ backup.name }}</td>
            <td class="">{{ backup.size }}</td>
            <td class="">
              {{ new Date(backup.created * 1000).toLocaleDateString() }}
              {{
                new Date(backup.created * 1000).toLocaleTimeString([], {
                  hour: "2-digit",
                  minute: "2-digit",
                })
              }}
            </td>
            <td class="">
              <div class="flex gap-2 min-w-max">
                <button class="w-4 h-4 cursor-pointer" v-tooltip="'Restore'">
                  <RestoreIcon />
                </button>
                <button
                  class="relative w-4 h-4 cursor-pointer"
                  v-tooltip="'Download'"
                  @click="downloadBackup(backup.name)"
                >
                  <DownloadIcon v-if="downloadLoading != backup.name" />
                  <LoadingIcon v-else class="animate-spin" />
                </button>
                <button
                  v-tooltip="'Delete'"
                  @click="deleteBackup(backup.name)"
                  class="relative w-4 h-4 cursor-pointer"
                >
                  <DeleteIcon class="text-red-600" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import Resumable from "resumablejs";
import { defineComponent } from "vue";
import LoadingIcon from "../../icons/LoadingIcon.vue";
import DownloadIcon from "../../icons/DownloadIcon.vue";
import DeleteIcon from "../../icons/DeleteIcon.vue";
import RestoreIcon from "../../icons/RestoreIcon.vue";
import BackupIcon from "../../icons/BackupIcon.vue";
import UploadIcon from "../../icons/UploadIcon.vue";

export default defineComponent({
  name: "Home",
  components: {
    LoadingIcon,
    DownloadIcon,
    DeleteIcon,
    RestoreIcon,
    BackupIcon,
    UploadIcon,
  },
  mounted() {
    this.initResumable();
    this.loadBackups();
    this.loadJobStatus();

    setInterval(() => {
      this.loadJobStatus();
    }, 1000);
  },

  props: {
    token: String,
  },

  data() {
    return {
      backups: [],
      backupsLoading: false,
      backupRunning: false,
      runningBackupName: null,
      downloadLoading: null,
      uploadLoading: false,
    };
  },

  methods: {
    async loadBackups() {
      this.backupsLoading = true;

      try {
        const response = await fetch(
          route("statamic.cp.statamic-content-backup.list")
        );

        if (!response.ok) {
          throw new Error("Error loading backups");
        }

        const data = await response.json();
        this.backups = data || [];
      } catch (error) {
        this.$toast.error("Error loading backups");
        console.error("Error loading backups:", error);
      } finally {
        this.backupsLoading = false;
      }
    },
    async loadJobStatus() {
      if (!this.backupRunning) {
        return;
      }

      try {
        const response = await fetch(
          route("statamic.cp.statamic-content-backup.status")
        );

        if (!response.ok) {
          throw new Error("Error retrieving job status");
        }

        const data = await response.json();

        if (data.error) {
          this.runningBackupName = null;
          this.backupRunning = false;

          this.$toast.error(data.error);
          this.loadBackups();
        } else if (data.success) {
          this.runningBackupName = null;
          this.backupRunning = false;
          this.$toast.success(
            "Backup created successfully (" + data.success + ")"
          );
          this.loadBackups();
        }

        if (data.runningName) {
          this.runningBackupName = data.runningName;
        } else {
          this.runningBackupName = null;
        }
      } catch (error) {
        console.error("Error loading job status:", error);
      }
    },
    async createBackup() {
      if (this.backupRunning) {
        this.$toast.info("A backup is already running");
        return;
      }

      this.backupRunning = true;

      fetch(route("statamic.cp.statamic-content-backup.createBackup"), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": this.token,
        },
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error("Error creating backup");
          }
          return response.json();
        })
        .then((data) => {
          this.loadJobStatus();
        })
        .catch((error) => {
          console.error("Error creating backup:", error);
          this.$toast.error("Error creating backup");
        });
    },
    async deleteBackup(backupName) {
      if (!confirm("Are you sure you want to delete this backup?")) {
        return;
      }

      try {
        const response = await fetch(
          route("statamic.cp.statamic-content-backup.deleteBackup", {
            name: backupName,
          }),
          {
            method: "DELETE",
            headers: {
              "X-CSRF-TOKEN": this.token,
            },
          }
        );

        if (!response.ok) {
          throw new Error("Error deleting backup");
        }

        this.$toast.success("Backup deleted successfully");
        this.loadBackups();
      } catch (error) {
        console.error("Error deleting backup:", error);
        this.$toast.error("Error deleting backup");
      }
    },
    async downloadBackup(backupName) {
      if (this.downloadLoading) {
        return; // Prevent multiple downloads
      }
      this.downloadLoading = backupName;

      try {
        const response = await fetch(
          route("statamic.cp.statamic-content-backup.downloadBackup", {
            name: backupName,
          })
        );

        if (!response.ok) {
          throw new Error("Error downloading file");
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const fileName = response.headers
          .get("Content-Disposition")
          .split("filename=")[1]
          .replace(/"/g, "");

        const a = document.createElement("a");
        a.href = url;
        a.download = fileName;

        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        window.URL.revokeObjectURL(url);
      } catch (error) {
        this.$toast.error("Error downloading backup");
        console.error("Error downloading backup:", error);
      } finally {
        this.downloadLoading = null;
      }
    },
    uploadBackup() {
      this.restoreLoading = true;
      this.$refs.fileInput.click();
    },
    initResumable() {
      const resumable = new Resumable({
        target: route("statamic.cp.statamic-content-backup.restore"),
        query: { _token: this.token },
        fileType: ["zip"],
        simultaneousUploads: 1,
        maxFiles: 1,
        testChunks: false,
      });

      resumable.assignBrowse(this.$refs.uploadButton);

      resumable.on("fileAdded", (file, event) => {
        if (
          !confirm(
            "Are you sure you want to restore this backup? This will replace current content!"
          )
        ) {
          resumable.cancel();
          return;
        }

        this.restoreLoading = true;
        resumable.upload();
      });

      resumable.on("error", () => {
        this.restoreLoading = false;
        this.$toast.error("Error uploading backup");
      });

      resumable.on("fileSuccess", (file, message) => {
        this.restoreLoading = false;
        this.$toast.success("Backup uploaded successfully");
      });
    },
  },
});
</script>
