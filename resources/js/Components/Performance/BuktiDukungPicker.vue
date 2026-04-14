<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{
    reportId: number;
}>();

// ── File tab state ──────────────────────────────────────────────────────────

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const fileTitle = ref('');
const isDragging = ref(false);
const fileError = ref('');
const fileProcessing = ref(false);

const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
const ALLOWED_EXT = '.pdf, .jpg, .jpeg, .png, .webp';
const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

function validateFile(file: File): string {
    if (!ALLOWED_MIME.includes(file.type)) return 'Format tidak didukung. Gunakan PDF, JPG, PNG, atau WEBP.';
    if (file.size > MAX_BYTES) return `Ukuran file maksimal 10 MB (file ini ${formatBytes(file.size)}).`;
    return '';
}

function pickFile(file: File) {
    const err = validateFile(file);
    if (err) { fileError.value = err; selectedFile.value = null; return; }
    fileError.value = '';
    selectedFile.value = file;
}

function onFileInputChange(e: Event) {
    const f = (e.target as HTMLInputElement).files?.[0];
    if (f) pickFile(f);
}

function onDrop(e: DragEvent) {
    isDragging.value = false;
    const f = e.dataTransfer?.files?.[0];
    if (f) pickFile(f);
}

function clearFile() {
    selectedFile.value = null;
    fileTitle.value = '';
    fileError.value = '';
    if (fileInput.value) fileInput.value.value = '';
}

function submitFile() {
    if (!selectedFile.value) { fileError.value = 'Pilih file terlebih dahulu.'; return; }
    const fd = new FormData();
    fd.append('type', 'file');
    fd.append('file', selectedFile.value);
    if (fileTitle.value.trim()) fd.append('title', fileTitle.value.trim());
    fileProcessing.value = true;
    router.post(route('report-attachments.store', props.reportId), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => clearFile(),
        onFinish: () => { fileProcessing.value = false; },
    });
}

// ── URL tab state ───────────────────────────────────────────────────────────

const urlValue = ref('');
const urlTitle = ref('');
const urlError = ref('');
const urlProcessing = ref(false);

function submitUrl() {
    urlError.value = '';
    if (!urlValue.value.trim()) { urlError.value = 'URL tidak boleh kosong.'; return; }
    try { new URL(urlValue.value.trim()); } catch {
        urlError.value = 'Format URL tidak valid.';
        return;
    }
    urlProcessing.value = true;
    router.post(route('report-attachments.store', props.reportId), {
        type: 'link',
        url: urlValue.value.trim(),
        title: urlTitle.value.trim() || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { urlValue.value = ''; urlTitle.value = ''; },
        onFinish: () => { urlProcessing.value = false; },
        onError: (errors: Record<string, string>) => { urlError.value = errors.url ?? errors.title ?? 'Terjadi kesalahan.'; },
    });
}
</script>

<template>
    <Tabs default-value="file" class="w-full">
        <TabsList class="h-8 w-auto rounded-lg bg-gray-100 p-0.5">
            <TabsTrigger value="file" class="h-7 rounded-md px-3 text-xs data-[state=active]:bg-white data-[state=active]:shadow-sm">
                File
            </TabsTrigger>
            <TabsTrigger value="url" class="h-7 rounded-md px-3 text-xs data-[state=active]:bg-white data-[state=active]:shadow-sm">
                URL
            </TabsTrigger>
        </TabsList>

        <!-- ── File tab ─────────────────────────────────────────────────── -->
        <TabsContent value="file" class="mt-3 space-y-2">
            <!-- Drop zone -->
            <div
                v-if="!selectedFile"
                :class="[
                    'relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors',
                    isDragging
                        ? 'border-blue-400 bg-blue-50'
                        : 'border-gray-200 bg-gray-50 hover:border-blue-300 hover:bg-blue-50/50',
                ]"
                @click="fileInput?.click()"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onDrop"
            >
                <!-- Upload icon -->
                <div :class="['flex h-10 w-10 items-center justify-center rounded-full', isDragging ? 'bg-blue-100' : 'bg-gray-100']">
                    <svg :class="['h-5 w-5', isDragging ? 'text-blue-500' : 'text-gray-400']" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700">
                        <span class="text-blue-600">Klik untuk pilih file</span> atau seret ke sini
                    </p>
                    <p class="mt-0.5 text-xs text-gray-400">PDF, JPG, PNG, WEBP · Maks 10 MB</p>
                </div>
                <input
                    ref="fileInput"
                    type="file"
                    :accept="ALLOWED_EXT"
                    class="sr-only"
                    @change="onFileInputChange"
                />
            </div>

            <!-- Selected file preview -->
            <div
                v-else
                class="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50/60 px-4 py-3"
            >
                <!-- File type icon -->
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm border border-gray-100">
                    <svg v-if="selectedFile.type === 'application/pdf'" class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8.5 14.5h1.25c.69 0 1.25.56 1.25 1.25S10.44 17 9.75 17H8.5v1.5H7V13h2.75c1.24 0 2.25 1.01 2.25 2.25S10.99 17.5 9.75 17.5H8.5v-3zm5 0h1.5c1.38 0 2.5 1.12 2.5 2.5s-1.12 2.5-2.5 2.5H13v-5zm1.5 3.75c.69 0 1.25-.56 1.25-1.25s-.56-1.25-1.25-1.25H14.5v2.5h.5z"/>
                    </svg>
                    <svg v-else class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 20.25h18M3.75 3h16.5a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75H3.75a.75.75 0 01-.75-.75V3.75A.75.75 0 013.75 3z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-700">{{ selectedFile.name }}</p>
                    <p class="text-xs text-gray-400">{{ formatBytes(selectedFile.size) }}</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                    @click="clearFile"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <InputError :message="fileError" />

            <!-- Optional title -->
            <div v-if="selectedFile" class="space-y-1">
                <Label class="text-xs text-gray-500">Label (opsional)</Label>
                <Input v-model="fileTitle" placeholder="mis. Foto dokumentasi kegiatan" class="h-8 text-xs" />
            </div>

            <Button
                v-if="selectedFile"
                size="sm"
                :disabled="fileProcessing"
                class="w-full"
                @click="submitFile"
            >
                <svg v-if="fileProcessing" class="mr-1.5 h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                {{ fileProcessing ? 'Mengunggah…' : 'Upload File' }}
            </Button>
        </TabsContent>

        <!-- ── URL tab ──────────────────────────────────────────────────── -->
        <TabsContent value="url" class="mt-3 space-y-2">
            <div class="space-y-1">
                <Label class="text-xs text-gray-500">URL</Label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                        </svg>
                    </div>
                    <Input
                        v-model="urlValue"
                        type="url"
                        placeholder="https://drive.google.com/…"
                        class="h-9 pl-8 text-sm"
                        @keydown.enter.prevent="submitUrl"
                    />
                </div>
                <InputError :message="urlError" />
            </div>

            <div class="space-y-1">
                <Label class="text-xs text-gray-500">Label (opsional)</Label>
                <Input
                    v-model="urlTitle"
                    placeholder="mis. Laporan di Google Drive"
                    class="h-8 text-xs"
                    @keydown.enter.prevent="submitUrl"
                />
            </div>

            <Button
                size="sm"
                :disabled="urlProcessing || !urlValue.trim()"
                class="w-full"
                @click="submitUrl"
            >
                <svg v-if="urlProcessing" class="mr-1.5 h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                {{ urlProcessing ? 'Menyimpan…' : 'Tambah URL' }}
            </Button>
        </TabsContent>
    </Tabs>
</template>
