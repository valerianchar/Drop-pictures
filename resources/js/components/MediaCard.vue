<script setup>
import { computed } from 'vue';
import { Check, Download, ImageDown, LoaderCircle, Play, Share2 } from '@lucide/vue';
import { goesToPhotos, refreshDownloadsSoon, useSaveToPhotos } from '../composables/useSaveToPhotos';

const props = defineProps({
    media: { type: Object, required: true },
    /** « share » ouvre le partage (galerie) ; « download » télécharge (groupe, public). */
    action: { type: String, default: 'share' },
    /** Mode sélection : un clic coche la carte au lieu de l'ouvrir. */
    selectable: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['open', 'share', 'toggle']);

/*
 * Sur iPhone, l'icône « photothèque » vit à côté de celle du téléchargement :
 * l'une range dans Photos, l'autre dans Fichiers. Ailleurs, un téléchargement
 * atterrit déjà dans la galerie du téléphone — un second bouton n'aurait rien
 * à faire de plus.
 */
const { saving, progress, save, isReady, isApple } = useSaveToPhotos();

const offersPhotos = computed(() => isApple && goesToPhotos(props.media) && !props.selectable);
const percent = computed(() => Math.round(progress.value * 100));

function onClick() {
    if (props.selectable) {
        emit('toggle', props.media);
    } else {
        emit('open', props.media);
    }
}
</script>

<template>
    <article
        class="card card-interactive animate-pop overflow-hidden p-0"
        :class="props.selected && 'border-accent-600 shadow-glow-soft'"
        :aria-selected="props.selectable ? props.selected : undefined"
        @click="onClick"
    >
        <div class="tile-gradient relative aspect-[4/3] w-full">
            <img
                v-if="props.media.thumbnail_url"
                :src="props.media.thumbnail_url"
                :alt="props.media.name"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover"
            />
            <span
                v-else
                class="absolute bottom-2.5 left-2.5 max-w-[calc(100%-1.25rem)] truncate font-mono text-[10px] text-neutral-500"
            >{{ props.media.extension.toUpperCase() }}</span>

            <span
                v-if="props.media.is_video"
                class="absolute bottom-2.5 left-2.5 inline-flex items-center gap-1.5 rounded-pill bg-[rgba(4,7,4,0.75)] px-2.5 py-[3px] font-mono text-[10px] text-text"
            >
                <Play class="size-2.5" />
                {{ props.media.duration_label ?? 'Vidéo' }}
            </span>

            <!-- Posé sur la photo elle-même : fond sombre opaque, sinon il se noie dans une image claire. -->
            <span class="badge absolute top-2.5 right-2.5 bg-[rgba(4,7,4,0.8)] text-accent-400 backdrop-blur-[2px]">Original · {{ props.media.quality }}</span>

            <!--
                Une coche, pas une étiquette : sur une carte de galerie à deux
                colonnes, « Dans Photos » passerait sous le badge de qualité.
                Le libellé complet est dans l'infobulle et dans la fiche.
            -->
            <span
                v-if="!props.selectable && props.media.download"
                class="absolute top-2.5 left-2.5 inline-flex size-6 items-center justify-center rounded-pill border border-accent-700 bg-[rgba(4,7,4,0.8)] text-accent-400 backdrop-blur-[2px]"
                role="img"
                :aria-label="`${props.media.download.label} — ${props.media.download.at_label}`"
                :title="`${props.media.download.label} — ${props.media.download.at_label}`"
            >
                <Check class="size-3.5" />
            </span>

            <span
                v-if="props.selectable"
                class="absolute top-2.5 left-2.5 inline-flex size-6 items-center justify-center rounded-sm border-[1.5px] transition-colors"
                :class="props.selected ? 'border-accent-500 bg-accent-500 text-on-accent' : 'border-neutral-300 bg-[rgba(4,7,4,0.6)]'"
                aria-hidden="true"
            >
                <Check v-if="props.selected" class="size-4" />
            </span>
        </div>

        <div class="flex items-center justify-between gap-2 px-3.5 py-2.5">
            <div class="min-w-0">
                <p class="truncate font-mono text-[11px] text-text">{{ props.media.name }}</p>
                <p class="truncate font-mono text-[10px] text-text-muted">
                    {{ props.media.meta }}<span v-if="props.media.archived" class="text-warning"> · archivé</span>
                </p>
            </div>

            <button
                v-if="offersPhotos"
                type="button"
                class="btn btn-ghost iconbtn btn-sm shrink-0"
                :aria-label="isReady(props.media.id) ? 'Prêt — appuie de nouveau pour enregistrer dans Photos' : 'Enregistrer dans Photos'"
                :title="isReady(props.media.id) ? 'Prêt — appuie de nouveau' : 'Enregistrer dans Photos'"
                :disabled="saving"
                @click.stop="save(props.media)"
            >
                <LoaderCircle v-if="saving" class="size-4 animate-spin" />
                <ImageDown v-else class="size-4" :class="isReady(props.media.id) && 'text-accent-400'" />
                <span v-if="saving" class="font-mono text-[10px]">{{ percent }} %</span>
            </button>

            <button
                v-if="props.action === 'share' && !props.selectable"
                type="button"
                class="btn btn-ghost iconbtn btn-sm shrink-0"
                aria-label="Partager"
                @click.stop="emit('share', props.media)"
            >
                <Share2 class="size-4" />
            </button>
            <a
                v-else-if="props.action === 'download' && !props.selectable"
                :href="props.media.download_url"
                class="btn btn-ghost iconbtn btn-sm shrink-0"
                aria-label="Télécharger l'original"
                download
                @click.stop="refreshDownloadsSoon()"
            >
                <Download class="size-4" />
            </a>
        </div>
    </article>
</template>
