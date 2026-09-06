<script setup>
import { Check, Download, Play, Share2 } from '@lucide/vue';

const props = defineProps({
    media: { type: Object, required: true },
    /** « share » ouvre le partage (galerie) ; « download » télécharge (groupe, public). */
    action: { type: String, default: 'share' },
    /** Mode sélection : un clic coche la carte au lieu de l'ouvrir. */
    selectable: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['open', 'share', 'toggle']);

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
                <p class="truncate font-mono text-[10px] text-text-muted">{{ props.media.meta }}</p>
            </div>

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
                @click.stop
            >
                <Download class="size-4" />
            </a>
        </div>
    </article>
</template>
