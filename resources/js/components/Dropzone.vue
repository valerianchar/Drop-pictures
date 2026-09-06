<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { HardDriveUpload } from '@lucide/vue';
import { useUploader } from '../composables/useUploader';
import { formatBytes } from '../format';

const { addFiles, pickFiles } = useUploader();
const page = usePage();
const active = ref(false);

/* La taille maximale annoncée est celle du serveur : elle change avec le .env, pas avec le code. */
const maxLabel = computed(() => formatBytes(page.props.upload?.max_file_bytes ?? 0));

/*
 * Le glisser-déposer s'écoute sur toute la fenêtre : dès qu'un fichier survole
 * la page, la zone s'allume, et lâcher n'importe où dépose. Sans cela, le
 * navigateur ouvrirait le fichier à la place de l'application.
 */
let depth = 0;

function hasFiles(event) {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files');
}

function onDragEnter(event) {
    if (!hasFiles(event)) {
        return;
    }

    depth += 1;
    active.value = true;
}

function onDragLeave() {
    depth = Math.max(0, depth - 1);

    if (depth === 0) {
        active.value = false;
    }
}

function onDragOver(event) {
    if (hasFiles(event)) {
        event.preventDefault();
    }
}

function onDrop(event) {
    if (!hasFiles(event)) {
        return;
    }

    event.preventDefault();
    depth = 0;
    active.value = false;
    addFiles(event.dataTransfer.files);
}

onMounted(() => {
    document.addEventListener('dragenter', onDragEnter);
    document.addEventListener('dragleave', onDragLeave);
    document.addEventListener('dragover', onDragOver);
    document.addEventListener('drop', onDrop);
});

onUnmounted(() => {
    document.removeEventListener('dragenter', onDragEnter);
    document.removeEventListener('dragleave', onDragLeave);
    document.removeEventListener('dragover', onDragOver);
    document.removeEventListener('drop', onDrop);
});
</script>

<template>
    <button
        type="button"
        class="dropzone block w-full bg-transparent px-5 py-[34px] font-body text-text"
        :data-active="active"
        @click="pickFiles()"
    >
        <HardDriveUpload class="mx-auto mb-2 size-7 text-accent-400" />
        <span class="mb-1 block font-heading font-semibold">
            {{ active ? 'Lâche, on s’en occupe' : 'Glisse tes photos et vidéos ici' }}
        </span>
        <span class="block text-[14px] text-text-muted">
            Aucune compression : le fichier source est conservé tel quel — JPG, PNG, RAW, MP4, MOV jusqu'à {{ maxLabel }}
        </span>
    </button>
</template>
