<template>
  <div class="max-w-page mx-auto">
    <Head :title="['Content Backup', __('Utilities')]" />

    <ui-header :title="__('Content Backup')" icon="package-box-crate">
      <ui-button
        :loading="creatingBackup || backupRunning"
        variant="primary"
        icon="save"
        @click="createBackup"
      >
        {{
          creatingBackup || backupRunning
            ? __("Creating Backup...")
            : __("Create Backup")
        }}
      </ui-button>

      <input
        ref="uploadInput"
        type="file"
        class="hidden"
        accept=".zip,application/zip"
        @change="handleUploadSelected"
      />

      <ui-button
        :loading="uploadLoading"
        icon="upload-cloud"
        @click="triggerUpload"
      >
        {{
          uploadLoading
            ? __("Uploading") + " (" + uploadProgress + "%)"
            : __("Upload Backup")
        }}
      </ui-button>
    </ui-header>

    <ui-panel class="h-full flex flex-col">
      <ui-panel-header class="flex items-center justify-between min-h-10">
        <div class="flex items-center gap-2">
          <div class="flex gap-2 flex-col">
            <ui-heading>{{ __("Backups") }}</ui-heading>
            <ui-description
              text="Create, upload, download, and restore full content backups directly from the control panel."
            />
          </div>

          <ui-badge
            v-if="runningBackupName"
            color="blue"
            icon="loading"
            :text="runningBackupName"
          />
        </div>

        <ui-badge :prepend="__('Total')">{{ backups.length }}</ui-badge>
      </ui-panel-header>

      <ui-card inset class="flex-1 overflow-hidden">
        <div class="pt-4">
          <table class="data-table">
            <thead>
              <tr>
                <th class="rounded-none group current-column sortable-column">
                  <span>{{ __("Name") }}</span>
                </th>
                <th class="rounded-none group current-column sortable-column">
                  <span>{{ __("Size") }}</span>
                </th>
                <th class="rounded-none group current-column sortable-column">
                  <span>{{ __("Created at") }}</span>
                </th>
                <th class="rounded-none actions-column"></th>
              </tr>
            </thead>
            <tbody class="text-gray-800 dark:text-dark-175">
              <tr v-if="!runningBackupName && backups.length === 0">
                <td colspan="4">{{ __("No backups found") }}</td>
              </tr>

              <tr v-if="runningBackupName">
                <td>{{ runningBackupName }}</td>
                <td></td>
                <td></td>
                <td>
                  <div class="flex items-center justify-end">
                    <ui-icon
                      name="loading"
                      class="size-4 animate-spin text-gray-500"
                    />
                  </div>
                </td>
              </tr>

              <tr v-for="backup in backups" :key="backup.name">
                <td>{{ backup.name }}</td>
                <td>{{ backup.size }}</td>
                <td>
                  {{ new Date(backup.created * 1000).toLocaleDateString() }}
                  {{
                    new Date(backup.created * 1000).toLocaleTimeString([], {
                      hour: "2-digit",
                      minute: "2-digit",
                    })
                  }}
                </td>
                <td>
                  <div class="flex items-center justify-end gap-2">
                    <ui-button
                      size="base"
                      round
                      icon-only
                      variant="subtle"
                      :loading="restoreRunning === backup.name"
                      :icon="restoreRunning === backup.name ? null : 'history'"
                      :aria-label="__('Restore')"
                      v-tooltip="__('Restore')"
                      @click="restoreBackup(backup.name)"
                    />

                    <ui-button
                      size="base"
                      round
                      icon-only
                      variant="subtle"
                      icon="download"
                      :aria-label="__('Download')"
                      v-tooltip="__('Download')"
                      @click="downloadBackup(backup.name)"
                    />

                    <ui-button
                      size="base"
                      round
                      icon-only
                      variant="subtle"
                      icon="trash"
                      :aria-label="__('Delete')"
                      v-tooltip="__('Delete')"
                      @click="deleteBackup(backup.name)"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </ui-card>
    </ui-panel>
  </div>
</template>

<script>
import { defineComponent } from "vue";
import { Head } from "@statamic/cms/inertia";
import Resumable from "resumablejs";

export default defineComponent({
  name: "Home",
  components: {
    Head,
  },

  props: {
    token: { type: String, required: true },
    listUrl: { type: String, required: true },
    statusUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    deleteUrl: { type: String, required: true },
    downloadUrl: { type: String, required: true },
    uploadUrl: { type: String, required: true },
    restoreUrl: { type: String, required: true },
    chunkSize: { type: Number, default: 1000 * 1000 },
  },

  data() {
    return {
      backups: [],
      backupsLoading: false,
      backupRunning: false,
      creatingBackup: false,
      runningBackupName: null,
      uploadLoading: false,
      uploadProgress: 0,
      restoreRunning: null,
      restoreStarting: false,
      statusInterval: null,
      uploader: null,
    };
  },

  mounted() {
    this.setupUploader();
    this.loadBackups();
    this.loadJobStatus();

    this.statusInterval = setInterval(() => {
      this.loadJobStatus();
    }, 2000);
  },

  beforeUnmount() {
    this.uploader?.cancel();

    if (this.statusInterval) {
      clearInterval(this.statusInterval);
      this.statusInterval = null;
    }
  },

  methods: {
    async loadBackups() {
      this.backupsLoading = true;

      try {
        const response = await fetch(this.listUrl);
        if (!response.ok) throw new Error("Error loading backups");
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
      try {
        const response = await fetch(this.statusUrl);
        if (!response.ok) throw new Error("Error retrieving job status");

        const data = await response.json();

        this.handleRestoreStatus(data.restore || {});

        if (data.error) {
          this.runningBackupName = null;
          this.backupRunning = false;
          this.creatingBackup = false;
          this.$toast.error(data.error);
          this.loadBackups();
          return;
        }

        if (data.success) {
          this.runningBackupName = null;
          this.backupRunning = false;
          this.creatingBackup = false;
          this.$toast.success(
            "Backup created successfully (" + data.success + ")",
          );
          this.loadBackups();
          return;
        }

        if (data.runningName) {
          this.runningBackupName = data.runningName;
          this.backupRunning = true;
          this.creatingBackup = false;
          return;
        }

        this.runningBackupName = null;
        if (!this.creatingBackup) {
          this.backupRunning = false;
        }
      } catch (error) {
        console.error("Error loading job status:", error);
      }
    },

    async createBackup() {
      if (this.creatingBackup || this.backupRunning) return;

      this.creatingBackup = true;
      this.backupRunning = true;

      try {
        await this.$axios.post(this.createUrl);
        await this.loadJobStatus();
      } catch (error) {
        console.error("Error creating backup:", error);
        const message =
          error?.response?.data?.message ||
          error?.response?.data?.error ||
          "Error creating backup";
        this.$toast.error(message);
        this.creatingBackup = false;
        this.backupRunning = false;
      }
    },

    async deleteBackup(backupName) {
      if (!confirm("Are you sure you want to delete this backup?")) return;

      try {
        const response = await fetch(
          this.withBackupName(this.deleteUrl, backupName),
          {
            method: "DELETE",
            headers: {
              "X-CSRF-TOKEN": this.token,
            },
          },
        );

        if (!response.ok) throw new Error("Error deleting backup");

        this.$toast.success("Backup deleted successfully");
        this.loadBackups();
      } catch (error) {
        console.error("Error deleting backup:", error);
        this.$toast.error("Error deleting backup");
      }
    },

    downloadBackup(backupName) {
      const link = document.createElement("a");
      link.href = this.withBackupName(this.downloadUrl, backupName);
      link.download = backupName;
      document.body.appendChild(link);
      link.click();
      link.remove();
    },

    triggerUpload() {
      if (this.uploadLoading) return;
      this.$refs.uploadInput?.click();
    },

    setupUploader() {
      this.uploader = new Resumable({
        target: this.uploadUrl,
        headers: {
          "X-CSRF-TOKEN": this.token,
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
        chunkSize: this.chunkSize,
        // Otherwise the last chunk can be up to twice the chunk size and hit the server upload limits
        forceChunkSize: true,
        // The server rejects a chunk that follows a missing one, so chunks must be sent in order
        simultaneousUploads: 1,
        testChunks: false,
        generateUniqueIdentifier: (file) => `${file.size}-${Date.now()}`,
        maxChunkRetries: 5,
        chunkRetryInterval: 2000,
        permanentErrors: [400, 401, 403, 404, 405, 409, 413, 415, 419, 422, 500, 501],
      });

      // Chunks are created asynchronously, so the upload can only start once the file has been added
      this.uploader.on("fileAdded", () => this.uploader.upload());

      this.uploader.on("progress", () => {
        this.uploadProgress = Math.floor(this.uploader.progress() * 100);
      });

      this.uploader.on("fileSuccess", (file, message) => {
        const payload = this.parseUploadResponse(message);
        if (payload.name) {
          this.$toast.success("Backup uploaded successfully (" + payload.name + ")");
        } else {
          this.$toast.error(payload.error || "Error uploading backup");
        }
        this.finishUpload();
      });

      this.uploader.on("fileError", (file, message) => {
        const payload = this.parseUploadResponse(message);
        this.$toast.error(
          payload.error || payload.message || "Error uploading backup",
        );
        this.finishUpload();
      });
    },

    handleUploadSelected(event) {
      const file = event?.target?.files?.[0];
      if (event?.target) event.target.value = "";
      if (!file) return;

      if (!file.name.toLowerCase().endsWith(".zip")) {
        this.$toast.error("Only .zip backups can be uploaded");
        return;
      }

      if (!this.uploader?.support) {
        this.$toast.error("Your browser does not support chunked uploads");
        return;
      }

      this.uploadLoading = true;
      this.uploadProgress = 0;

      this.uploader.addFile(file);
    },

    finishUpload() {
      this.uploader.cancel();
      this.uploadLoading = false;
      this.uploadProgress = 0;
      this.loadBackups();
    },

    parseUploadResponse(message) {
      try {
        return JSON.parse(message || "{}") || {};
      } catch (_) {
        // Non-JSON responses come from the web server (e.g. 413 from nginx)
        return {};
      }
    },

    async restoreBackup(backupName) {
      if (this.restoreRunning) {
        this.$toast.info("A restore is already running");
        return;
      }

      if (
        !confirm(
          "Are you sure you want to restore this backup? This will overwrite your current content.",
        )
      ) {
        return;
      }

      this.restoreRunning = backupName;
      this.restoreStarting = true;

      try {
        const response = await fetch(
          this.withBackupName(this.restoreUrl, backupName),
          {
            method: "POST",
            headers: {
              "X-CSRF-TOKEN": this.token,
              Accept: "application/json",
            },
          },
        );

        if (!response.ok) throw new Error("Error restoring backup");

        await this.loadJobStatus();
      } catch (error) {
        console.error("Error restoring backup:", error);
        this.$toast.error("Error restoring backup");
        this.restoreRunning = null;
      } finally {
        this.restoreStarting = false;
      }
    },

    handleRestoreStatus(status) {
      if (status.error) {
        this.restoreRunning = null;
        this.$toast.error(status.error);
        return;
      }

      if (status.success) {
        this.restoreRunning = null;
        this.$toast.success(
          "Backup restored successfully (" + status.success + ")",
        );
        this.loadBackups();
        return;
      }

      if (status.runningName) {
        this.restoreRunning = status.runningName;
        return;
      }

      if (!this.restoreStarting) {
        this.restoreRunning = null;
      }
    },

    withBackupName(url, backupName) {
      const endpoint = new URL(url, window.location.origin);
      endpoint.searchParams.set("name", backupName);
      return endpoint.toString();
    },
  },
});
</script>
