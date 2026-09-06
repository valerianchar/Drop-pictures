<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppDialog from './AppDialog.vue';

/**
 * Choisir le groupe qui reçoit une sélection de fichiers — en qualité d'origine.
 */
const props = defineProps({
    open: { type: Boolean, required: true },
    groups: { type: Array, required: true },
    mediaIds: { type: Array, required: true },
});

const emit = defineEmits(['update:open', 'sent']);

const picked = ref(null);
const processing = ref(false);

watch(() => props.open, (value) => value && (picked.value = null));

function send() {
    if (!picked.value) {
        return;
    }

    processing.value = true;

    router.post(
        picked.value.share_url,
        { media_ids: props.mediaIds },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['groups', 'flash', 'errors'],
            onSuccess: () => {
                emit('sent');
                emit('update:open', false);
            },
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <AppDialog
        :open="props.open"
        :title="`Envoyer ${props.mediaIds.length} ${props.mediaIds.length > 1 ? 'fichiers' : 'fichier'} à un groupe`"
        description="Les membres les reçoivent tels quels, sans aucune compression."
        @update:open="emit('update:open', $event)"
    >
        <div class="flex flex-col gap-2">
            <button
                v-for="group in props.groups"
                :key="group.id"
                type="button"
                class="flex items-center justify-between gap-2 rounded-md border px-3.5 py-2.5 text-left text-[14px] text-text transition-colors"
                :class="picked?.id === group.id ? 'border-accent-600 bg-tint-accent-14' : 'border-neutral-800 bg-surface hover:border-neutral-600'"
                @click="picked = group"
            >
                {{ group.name }}
                <span class="text-[12px] text-text-muted">{{ group.members_label }}</span>
            </button>
            <p v-if="!props.groups.length" class="hint">Tu n'as pas encore de groupe — crée-en un depuis l'onglet Groupes.</p>
        </div>

        <template #actions>
            <button type="button" class="btn btn-secondary" @click="emit('update:open', false)">Annuler</button>
            <button type="button" class="btn btn-primary" :disabled="!picked || processing" @click="send">
                {{ picked ? `Envoyer à « ${picked.name} »` : 'Choisis un groupe' }}
            </button>
        </template>
    </AppDialog>
</template>
