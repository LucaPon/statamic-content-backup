
<template>
    <div>
        <h1 class="mb-6">Backup</h1>

        <div class="p-4 card content">
            <div class="flex flex-col justify-between gap-3 cursor-pointer md:flex-row">
                <div @click="downloadBackup" class="flex w-full p-4 rounded-md md:w-1/2 hover:bg-gray-200 group">
                    <div class="w-8 h-8 mr-4 text-gray-800 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M.752 21.751a1.5 1.5 0 0 0 1.5 1.5m0-22.5a1.5 1.5 0 0 0-1.5 1.5m22.5 0a1.5 1.5 0 0 0-1.5-1.5m0 22.5a1.5 1.5 0 0 0 1.5-1.5m0-15.75v1.5m0 3.75v1.5m0 3.75v1.5m-22.5-12v1.5m0 3.75v1.5m0 3.75v1.5m5.25 5.25h1.5m3.75 0h1.5m3.75 0h1.5m-12-22.5h1.5m3.75 0h1.5m3.75 0h1.5m-6 5.25v12m4.5-4.5-4.5 4.5-4.5-4.5"></path></svg>
                    </div>
                    <div class="flex flex-col">
                            <div class="flex flex-row items-center gap-2 mb-2">
                                <h3 class="mb-0 text-blue">Download Backup</h3>
                                <svg v-show="backupLoading" class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10.72,19.9a8,8,0,0,1-6.5-9.79A7.77,7.77,0,0,1,10.4,4.16a8,8,0,0,1,9.49,6.52A1.54,1.54,0,0,0,21.38,12h.13a1.37,1.37,0,0,0,1.38-1.54,11,11,0,1,0-12.7,12.39A1.54,1.54,0,0,0,12,21.34h0A1.47,1.47,0,0,0,10.72,19.9Z"><animateTransform attributeName="transform" type="rotate" dur="0.75s" values="0 12 12;360 12 12" repeatCount="indefinite"/></path></svg>
                            </div>
                        <p class="text-xs">Download content backup zip.</p>
                    </div>
                </div>
                <a ref="restoreButton" class="flex w-full p-4 rounded-md md:w-1/2 hover:bg-gray-200 group">
                    <div class="w-8 h-8 mr-4 text-gray-800 rotate-180 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M.752 21.751a1.5 1.5 0 0 0 1.5 1.5m0-22.5a1.5 1.5 0 0 0-1.5 1.5m22.5 0a1.5 1.5 0 0 0-1.5-1.5m0 22.5a1.5 1.5 0 0 0 1.5-1.5m0-15.75v1.5m0 3.75v1.5m0 3.75v1.5m-22.5-12v1.5m0 3.75v1.5m0 3.75v1.5m5.25 5.25h1.5m3.75 0h1.5m3.75 0h1.5m-12-22.5h1.5m3.75 0h1.5m3.75 0h1.5m-6 5.25v12m4.5-4.5-4.5 4.5-4.5-4.5"></path></svg>
                    </div>
                    <div class="flex flex-col">
                        <div class="flex flex-row items-center gap-2 mb-2">
                            <h3 class="mb-0 text-blue">Restore Backup</h3>
                            <svg v-show="restoreLoading" class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10.72,19.9a8,8,0,0,1-6.5-9.79A7.77,7.77,0,0,1,10.4,4.16a8,8,0,0,1,9.49,6.52A1.54,1.54,0,0,0,21.38,12h.13a1.37,1.37,0,0,0,1.38-1.54,11,11,0,1,0-12.7,12.39A1.54,1.54,0,0,0,12,21.34h0A1.47,1.47,0,0,0,10.72,19.9Z"><animateTransform attributeName="transform" type="rotate" dur="0.75s" values="0 12 12;360 12 12" repeatCount="indefinite"/></path></svg>
                        </div>
                        <p class="text-xs">Restore content backup previously downloaded.</p>
                        <p class="text-xs">This will replace current content!</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</template>

<script>

    import Resumable from 'resumablejs';

    export default {

        mounted() {
            this.initResumable();
        },

        props:{
            token: String
        },

        data() {
            return {
                backupLoading: false,
                restoreLoading: false,
            }
        },

        methods: {
            async downloadBackup() {
                this.backupLoading = true;

                try {
                    const response = await fetch(route('statamic.cp.statamic-content-backup.backup'));

                    if (!response.ok) {
                        throw new Error('Errore nel download del file');
                    }

                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const fileName = response.headers.get('Content-Disposition').split('filename=')[1].replace(/"/g, '');

                    const a = document.createElement('a');
                    a.href = url;
                    a.download = fileName;

                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    window.URL.revokeObjectURL(url);

                } catch (error) {
                    this.$toast.error('Error creating backup');
                } finally {
                    this.backupLoading = false;
                }
            },
            restoreBackup() {
                this.restoreLoading = true;
                this.$refs.fileInput.click();
            },
            initResumable(){

                const resumable = new Resumable({
                    target: route('statamic.cp.statamic-content-backup.restore'),
                    query: {_token: this.token},
                    fileType: ['zip'],
                    simultaneousUploads: 1,
                    maxFiles: 1,
                    testChunks: false
                });

                resumable.assignBrowse(this.$refs.restoreButton);

                resumable.on('fileAdded', (file, event) => {
                    if(!confirm('Are you sure you want to restore this backup? This will replace current content!')) {
                        resumable.cancel();
                        return;
                    }

                    this.restoreLoading = true;
                    resumable.upload();
                });

                resumable.on('error', () => {
                    this.restoreLoading = false;
                    this.$toast.error('Error restoring backup');
                });

                resumable.on('fileSuccess', (file, message) => {
                    this.restoreLoading = false;
                    this.$toast.success('Backup restored successfully');
                });
            }
        }

    }
</script>

<style>

</style>
