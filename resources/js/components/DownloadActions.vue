<script setup>
import { computed } from 'vue';
import { Download, ImageDown, LoaderCircle } from '@lucide/vue';
import { useSaveToPhotos } from '../composables/useSaveToPhotos';

/**
 * Le ou les boutons qui rendent l'original. Sur iPhone/iPad, une photo ou une
 * vidéo propose d'abord « Enregistrer dans Photos » ; le téléchargement vers
 * Fichiers reste disponible en second. Partout ailleurs : un seul bouton.
 */
const props = defineProps({
    media: { type: Object, required: true },
    large: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const { saving, progress, save, isApple } = useSaveToPhotos();

const offersPhotos = computed(() => isApple && (props.media.kind === 'photo' || props.media.is_video) && props.media.view_url);
const sizeClass = computed(() => (props.large ? 'btn-lg' : ''));
const blockClass = computed(() => (props.block ? 'w-full' : ''));
const percent = computed(() => Math.round(progress.value * 100));
</script>

<template>
    <div class="flex flex-col gap-2" :class="props.block ? 'w-full' : 'items-end'">
        <template v-if="offersPhotos">
            <button type="button" class="btn btn-primary" :class="[sizeClass, blockClass]" :disabled="saving" @click="save(props.media)">
                <LoaderCircle v-if="saving" class="size-[18px] animate-spin" />
                <ImageDown v-else class="size-[18px]" />
                <template v-if="saving">Préparation… {{ percent }} %</template>
                <template v-else>Enregistrer dans Photos</template>
            </button>
            <a
                :href="props.media.download_url"
                class="btn btn-ghost btn-sm no-underline hover:no-underline"
                :class="blockClass"
                download
            >
                <Download class="size-4" />
                Télécharger dans Fichiers ({{ props.media.size_label }})
            </a>
        </template>

        <a v-else :href="props.media.download_url" class="btn btn-primary no-underline hover:no-underline" :class="[sizeClass, blockClass]" download>
            <Download class="size-[18px]" />
            Télécharger l'original<template v-if="props.large"> ({{ props.media.size_label }})</template>
        </a>
    </div>
</template>
