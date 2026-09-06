<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Copy, Play, Share2, Trash2, X } from '@lucide/vue';
import AppDialog from './AppDialog.vue';
import ConfirmDialog from './ConfirmDialog.vue';
import DownloadActions from './DownloadActions.vue';
import { useClipboard } from '../composables/useClipboard';
import { useToast } from '../composables/useToast';
import { routes } from '../routes';

const props = defineProps({
    media: { type: Object, default: null },
});

const emit = defineEmits(['close', 'share']);

const { copy } = useClipboard();
const { toast } = useToast();

const open = computed(() => props.media !== null);
const tags = ref([]);
const newTag = ref('');
const confirmDelete = ref(false);
const processing = ref(false);

watch(
    () => props.media,
    (media) => {
        tags.value = [...(media?.tags ?? [])];
        newTag.value = '';
        confirmDelete.value = false;
    },
    { immediate: true },
);

const tagsChanged = computed(() => JSON.stringify(tags.value) !== JSON.stringify(props.media?.tags ?? []));

function addTag() {
    const name = newTag.value.trim();

    if (name && !tags.value.includes(name) && tags.value.length < 10) {
        tags.value.push(name);
    }

    newTag.value = '';
}

function removeTag(name) {
    tags.value = tags.value.filter((tag) => tag !== name);
}

function saveTags() {
    processing.value = true;

    router.put(
        routes.mediaTags(props.media.id),
        { tags: tags.value },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['media', 'tags', 'flash', 'errors'],
            onFinish: () => (processing.value = false),
        },
    );
}

async function copyChecksum() {
    await copy(props.media.checksum);
    toast('Empreinte SHA-256 copiée.');
}

function destroy() {
    processing.value = true;

    router.delete(routes.mediaDelete(props.media.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmDelete.value = false;
            emit('close');
        },
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <AppDialog :open="open" :title="media?.name ?? ''" @update:open="(value) => !value && emit('close')">
        <template #description>
            {{ media?.meta }} · déposé {{ media?.created_label }}
        </template>

        <template v-if="media">
            <div class="tile-gradient relative mb-4 aspect-[3/2] w-full overflow-hidden rounded-md border border-neutral-800">
                <img v-if="media.thumbnail_url" :src="media.thumbnail_url" :alt="media.name" class="absolute inset-0 size-full object-contain" />
                <span v-else class="absolute inset-0 flex items-center justify-center font-mono text-[12px] text-neutral-500">
                    {{ media.processed ? `Pas d'aperçu pour ce ${media.extension.toUpperCase()} — l'original est intact` : 'Aperçu en préparation…' }}
                </span>
                <span
                    v-if="media.is_video"
                    class="absolute bottom-2.5 left-2.5 inline-flex items-center gap-1.5 rounded-pill bg-[rgba(4,7,4,0.75)] px-2.5 py-[3px] font-mono text-[10px]"
                >
                    <Play class="size-2.5" />{{ media.duration_label ?? 'Vidéo' }}
                </span>
                <span class="badge absolute top-2.5 right-2.5 bg-[rgba(4,7,4,0.8)] text-accent-400 backdrop-blur-[2px]">Original · {{ media.quality }}</span>
            </div>

            <dl class="mb-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-[13px]">
                <dt class="text-text-muted">Taille</dt>
                <dd class="font-mono text-[12px]">{{ media.size_label }}</dd>
                <dt class="text-text-muted">Empreinte</dt>
                <dd class="flex min-w-0 items-center gap-1.5">
                    <span class="truncate font-mono text-[11px]" :title="media.checksum">{{ media.checksum }}</span>
                    <button type="button" class="btn btn-ghost iconbtn btn-sm -my-1 shrink-0" aria-label="Copier l'empreinte" @click="copyChecksum">
                        <Copy class="size-3.5" />
                    </button>
                </dd>
            </dl>

            <div v-if="media.can_edit" class="mb-1">
                <p class="field-label">Tags</p>
                <div class="flex flex-wrap gap-2">
                    <span v-for="tag in tags" :key="tag" class="chip" data-active="true">
                        {{ tag }}
                        <button type="button" class="-mr-1 inline-flex" :aria-label="`Retirer ${tag}`" @click="removeTag(tag)">
                            <X class="size-3" />
                        </button>
                    </span>
                    <input
                        v-model="newTag"
                        type="text"
                        class="field h-7! w-auto! min-w-[120px] flex-1 rounded-pill! text-[12px]!"
                        placeholder="Ajouter un tag…"
                        maxlength="40"
                        @keydown.enter.prevent="addTag"
                        @blur="addTag"
                    />
                </div>
                <button v-if="tagsChanged" type="button" class="btn btn-secondary btn-sm mt-2.5" :disabled="processing" @click="saveTags">
                    Enregistrer les tags
                </button>
            </div>
            <div v-else-if="media.tags?.length" class="flex flex-wrap gap-2">
                <span v-for="tag in media.tags" :key="tag" class="chip" data-active="true">{{ tag }}</span>
            </div>
        </template>

        <template #actions>
            <button v-if="media?.can_edit" type="button" class="btn btn-danger mr-auto" :disabled="processing" @click="confirmDelete = true">
                <Trash2 class="size-4" />
                Supprimer
            </button>
            <button v-if="media?.can_edit" type="button" class="btn btn-ghost" @click="emit('share', media)">
                <Share2 class="size-4" />
                Partager
            </button>
            <DownloadActions v-if="media" :media="media" />
        </template>
    </AppDialog>

    <ConfirmDialog
        v-model:open="confirmDelete"
        :title="`Supprimer ${media?.name ?? ''} ?`"
        description="Le fichier d'origine, ses liens de partage et ses envois aux groupes disparaissent pour de bon."
        confirm-label="Supprimer"
        cancel-label="Garder"
        :processing="processing"
        @confirm="destroy"
    />
</template>
