<script setup>
import { computed } from 'vue';
import { Download, ImageDown, LoaderCircle } from '@lucide/vue';
import { refreshDownloadsSoon, useSaveToPhotos } from '../composables/useSaveToPhotos';

/**
 * Le bouton qui rend l'original — un seul. Sur iPhone/iPad, une photo ou une
 * vidéo va dans Photos par la feuille de partage d'iOS (qui propose aussi
 * Fichiers à qui le veut). Partout ailleurs, le téléchargement classique : sur
 * Android, la galerie ramasse d'elle-même ce qui arrive dans Téléchargements.
 */
const props = defineProps({
    media: { type: Object, required: true },
    large: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const { saving, progress, save, isReady, isApple } = useSaveToPhotos();

const offersPhotos = computed(() => isApple && (props.media.kind === 'photo' || props.media.is_video) && props.media.view_url);
const ready = computed(() => isReady(props.media.id));
const sizeClass = computed(() => (props.large ? 'btn-lg' : ''));
const blockClass = computed(() => (props.block ? 'w-full' : ''));
const percent = computed(() => Math.round(progress.value * 100));
</script>

<template>
    <div class="flex flex-col gap-2" :class="props.block ? 'w-full' : 'items-end'">
        <button
            v-if="offersPhotos"
            type="button"
            class="btn btn-primary"
            :class="[sizeClass, blockClass, ready && 'animate-pop']"
            :disabled="saving"
            @click="save(props.media)"
        >
            <LoaderCircle v-if="saving" class="size-[18px] animate-spin" />
            <ImageDown v-else class="size-[18px]" />
            <template v-if="saving">Préparation… {{ percent }} %</template>
            <template v-else-if="ready">Prêt — enregistrer dans Photos</template>
            <template v-else>Enregistrer dans Photos<template v-if="props.large"> ({{ props.media.size_label }})</template></template>
        </button>

        <a
            v-else
            :href="props.media.download_url"
            class="btn btn-primary no-underline hover:no-underline"
            :class="[sizeClass, blockClass]"
            download
            @click="refreshDownloadsSoon()"
        >
            <Download class="size-[18px]" />
            Télécharger l'original<template v-if="props.large"> ({{ props.media.size_label }})</template>
        </a>
    </div>
</template>
