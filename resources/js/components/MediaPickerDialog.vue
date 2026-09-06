<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { LoaderCircle, Search } from '@lucide/vue';
import AppDialog from './AppDialog.vue';
import MediaGrid from './MediaGrid.vue';
import { request } from '../http';

/**
 * Depuis la page d'un groupe : piocher dans sa propre galerie les fichiers à y
 * ajouter. Ceux déjà dans le groupe n'apparaissent pas.
 */
const props = defineProps({
    open: { type: Boolean, required: true },
    group: { type: Object, required: true },
});

const emit = defineEmits(['update:open']);

const loading = ref(false);
const media = ref([]);
const selectedIds = ref([]);
const search = ref('');
const processing = ref(false);
let searchTimer = null;

async function load() {
    loading.value = true;

    try {
        const url = new URL(props.group.picker_url, window.location.origin);

        if (search.value.trim()) {
            url.searchParams.set('q', search.value.trim());
        }

        const data = await request(url.toString());
        media.value = data.media;
    } catch {
        media.value = [];
    } finally {
        loading.value = false;
    }
}

watch(() => props.open, (value) => {
    if (value) {
        selectedIds.value = [];
        search.value = '';
        load();
    }
});

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 300);
});

function toggle(item) {
    selectedIds.value = selectedIds.value.includes(item.id)
        ? selectedIds.value.filter((id) => id !== item.id)
        : [...selectedIds.value, item.id];
}

const count = computed(() => selectedIds.value.length);

function add() {
    processing.value = true;

    router.post(
        props.group.share_url,
        { media_ids: selectedIds.value },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['media', 'group', 'flash', 'errors'],
            onSuccess: () => emit('update:open', false),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <AppDialog
        :open="props.open"
        :title="`Ajouter depuis ma galerie`"
        :description="`Les fichiers arrivent dans « ${props.group.name} » en qualité d'origine.`"
        @update:open="emit('update:open', $event)"
    >
        <label class="relative mb-3 block">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-text-muted" />
            <input v-model="search" type="search" class="field pl-9" placeholder="Rechercher dans ma galerie…" aria-label="Rechercher" />
        </label>

        <div class="max-h-[50vh] overflow-y-auto pr-1">
            <div v-if="loading" class="flex justify-center py-10 text-text-muted"><LoaderCircle class="size-5 animate-spin" /></div>
            <MediaGrid v-else-if="media.length" :media="media" selectable :selected-ids="selectedIds" @toggle="toggle" />
            <p v-else class="hint py-8 text-center">Rien à ajouter : tous tes fichiers sont déjà dans le groupe, ou ta galerie est vide.</p>
        </div>

        <template #actions>
            <button v-if="media.length && count < media.length" type="button" class="btn btn-ghost btn-sm mr-auto" @click="selectedIds = media.map((item) => item.id)">Tout</button>
            <button type="button" class="btn btn-secondary" @click="emit('update:open', false)">Annuler</button>
            <button type="button" class="btn btn-primary" :disabled="!count || processing" @click="add">
                Ajouter {{ count || '' }} {{ count > 1 ? 'fichiers' : count === 1 ? 'fichier' : '' }}
            </button>
        </template>
    </AppDialog>
</template>
