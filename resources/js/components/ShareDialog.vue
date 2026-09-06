<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Link as LinkIcon } from '@lucide/vue';
import AppDialog from './AppDialog.vue';
import AppSwitch from './AppSwitch.vue';
import { useClipboard } from '../composables/useClipboard';
import { useToast } from '../composables/useToast';
import { routes } from '../routes';

const props = defineProps({
    media: { type: Object, default: null },
    groups: { type: Array, default: () => [] },
    defaultMode: { type: String, default: 'lien' },
});

const emit = defineEmits(['close']);

const page = usePage();
const { copy } = useClipboard();
const { toast } = useToast();

const mode = ref(props.defaultMode);
const limited = ref(false);
const pickedGroup = ref(null);
const processing = ref(false);
const createdLink = ref(null);

const open = computed(() => props.media !== null);

// Un nouveau fichier : le dialogue repart de zéro.
watch(
    () => props.media?.id,
    () => {
        mode.value = props.defaultMode;
        limited.value = false;
        pickedGroup.value = null;
        createdLink.value = null;
    },
);

/* Le lien tout juste créé arrive par le flash de la réponse. */
watch(
    () => page.props.flash?.share_link,
    (link) => {
        if (link && props.media && link.media_id === props.media.id) {
            createdLink.value = link;
        }
    },
);

function createLink() {
    processing.value = true;

    router.post(
        routes.mediaShareLinks(props.media.id),
        { limited: limited.value },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['flash', 'share_links', 'media', 'errors'],
            onFinish: () => (processing.value = false),
        },
    );
}

async function copyLink() {
    await copy(createdLink.value.url);
    toast('Lien copié — qualité d’origine garantie.');
}

function sendToGroup() {
    if (!pickedGroup.value) {
        return;
    }

    processing.value = true;

    router.post(
        pickedGroup.value.share_url,
        { media_id: props.media.id },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['flash', 'groups', 'errors'],
            onSuccess: () => emit('close'),
            onFinish: () => (processing.value = false),
        },
    );
}

const cta = computed(() => {
    if (mode.value === 'lien') {
        return 'Partager le lien';
    }

    return pickedGroup.value ? `Envoyer à « ${pickedGroup.value.name} »` : 'Choisis un groupe';
});
</script>

<template>
    <AppDialog :open="open" :title="media ? `Partager ${media.name}` : ''" @update:open="(value) => !value && emit('close')">
        <template #description>
            {{ media?.meta }} — envoyé <strong class="text-accent-400">sans aucune compression</strong>, exactement le fichier d'origine.
        </template>

        <div class="mb-4 flex gap-1.5 rounded-md border border-neutral-800 bg-surface p-1">
            <button
                type="button"
                class="flex-1 rounded-[7px] border-none py-2 text-[13px] font-semibold transition-colors"
                :class="mode === 'lien' ? 'bg-accent-500 text-on-accent' : 'bg-transparent text-text-muted hover:text-text'"
                @click="mode = 'lien'"
            >
                Par lien
            </button>
            <button
                type="button"
                class="flex-1 rounded-[7px] border-none py-2 text-[13px] font-semibold transition-colors"
                :class="mode === 'groupe' ? 'bg-accent-500 text-on-accent' : 'bg-transparent text-text-muted hover:text-text'"
                @click="mode = 'groupe'"
            >
                Vers un groupe
            </button>
        </div>

        <template v-if="mode === 'lien'">
            <div v-if="createdLink" class="mb-4 flex gap-2">
                <input :value="createdLink.short" readonly class="field flex-1 font-mono text-[12px]!" aria-label="Lien de partage" @focus="$event.target.select()" />
                <button type="button" class="btn btn-primary" @click="copyLink">
                    <LinkIcon class="size-4" />
                    Copier
                </button>
            </div>
            <p v-if="createdLink" class="hint mb-4">{{ createdLink.expires_label }} · toute personne avec ce lien télécharge le fichier d'origine.</p>
        </template>

        <div v-else class="mb-4 flex flex-col gap-2">
            <button
                v-for="group in groups"
                :key="group.id"
                type="button"
                class="flex items-center justify-between gap-2 rounded-md border px-3.5 py-2.5 text-left text-[14px] text-text transition-colors"
                :class="pickedGroup?.id === group.id ? 'border-accent-600 bg-tint-accent-14' : 'border-neutral-800 bg-surface hover:border-neutral-600'"
                @click="pickedGroup = group"
            >
                {{ group.name }}
                <span class="text-[12px] text-text-muted">{{ group.members_label }}</span>
            </button>
            <p v-if="!groups.length" class="hint">Tu n'as pas encore de groupe — crée-en un depuis l'onglet Groupes.</p>
        </div>

        <div class="flex flex-col gap-2.5">
            <AppSwitch :model-value="true" disabled :label="`Qualité d'origine (${media?.quality ?? ''})`" />
            <AppSwitch v-if="mode === 'lien' && !createdLink" v-model="limited" label="Lien à durée limitée (7 jours)" />
        </div>

        <template #actions>
            <button type="button" class="btn btn-secondary" @click="emit('close')">Fermer</button>
            <button
                v-if="mode === 'lien' ? !createdLink : true"
                type="button"
                class="btn btn-primary"
                :disabled="processing || (mode === 'groupe' && !pickedGroup)"
                @click="mode === 'lien' ? createLink() : sendToGroup()"
            >
                {{ cta }}
            </button>
        </template>
    </AppDialog>
</template>
