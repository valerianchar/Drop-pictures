<script setup>
import { computed } from 'vue';
import { Download, X } from '@lucide/vue';
import { formatBytes } from '../format';

/**
 * La barre du mode sélection : combien de fichiers, quel poids, et le ZIP à
 * télécharger — un archive « store », chaque original y est tel quel.
 */
const props = defineProps({
    selected: { type: Array, required: true },
    total: { type: Number, required: true },
    downloadUrl: { type: String, required: true },
});

const emit = defineEmits(['all', 'clear', 'close']);

const bytes = computed(() => props.selected.reduce((sum, item) => sum + item.size_bytes, 0));
const zipUrl = computed(() => `${props.downloadUrl}?${props.selected.map((item) => `ids[]=${item.id}`).join('&')}`);
</script>

<template>
    <div
        class="animate-pop fixed inset-x-4 bottom-[calc(env(safe-area-inset-bottom)+1rem)] z-30 mx-auto flex max-w-[720px] flex-wrap items-center gap-3 rounded-lg border border-neutral-700 bg-surface-2 px-4 py-3 shadow-lg"
    >
        <p class="text-[14px]">
            <strong class="font-heading">{{ props.selected.length }}</strong>
            {{ props.selected.length > 1 ? 'fichiers sélectionnés' : 'fichier sélectionné' }}
            <span v-if="props.selected.length" class="font-mono text-[12px] text-text-muted">· {{ formatBytes(bytes) }}</span>
        </p>
        <span class="flex-1" />
        <button v-if="props.selected.length < props.total" type="button" class="btn btn-ghost btn-sm" @click="emit('all')">Tout</button>
        <button v-if="props.selected.length" type="button" class="btn btn-ghost btn-sm" @click="emit('clear')">Aucun</button>
        <a
            :href="zipUrl"
            class="btn btn-primary btn-sm no-underline hover:no-underline"
            :class="!props.selected.length && 'pointer-events-none opacity-45'"
            :aria-disabled="!props.selected.length"
            download
        >
            <Download class="size-4" />
            Télécharger en ZIP
        </a>
        <button type="button" class="btn btn-secondary iconbtn btn-sm" aria-label="Quitter la sélection" @click="emit('close')">
            <X class="size-4" />
        </button>
    </div>
</template>
