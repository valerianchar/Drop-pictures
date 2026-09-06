<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Download, LogOut, RefreshCw, Trash2, UserPlus } from '@lucide/vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import Dropzone from '../../components/Dropzone.vue';
import InviteDialog from '../../components/InviteDialog.vue';
import MediaDetailsDialog from '../../components/MediaDetailsDialog.vue';
import MediaGrid from '../../components/MediaGrid.vue';
import UploadPanel from '../../components/UploadPanel.vue';
import { useUploader } from '../../composables/useUploader';
import { formatBytes } from '../../format';
import { subscribeGroup, unsubscribeGroup } from '../../realtime';
import { routes } from '../../routes';

const props = defineProps({
    group: { type: Object, required: true },
    media: { type: Array, required: true },
    members: { type: Array, required: true },
    can_leave: { type: Boolean, required: true },
    can_delete: { type: Boolean, required: true },
    leave_url: { type: String, required: true },
    delete_url: { type: String, required: true },
    regenerate_url: { type: String, required: true },
});

const { setTargetGroup } = useUploader();

/*
 * Sur cette page, « Déposer » et la zone de dépôt envoient directement dans le
 * groupe ; et l'on écoute ce que les autres membres y déposent.
 */
onMounted(() => {
    setTargetGroup(props.group.id);
    subscribeGroup(props.group.id);
});

onUnmounted(() => {
    setTargetGroup(null);
    unsubscribeGroup(props.group.id);
});

const inviting = ref(false);
const details = ref(null);
const confirming = ref(null);
const processing = ref(false);

const currentDetails = computed(() => (details.value ? props.media.find((item) => item.id === details.value.id) ?? null : null));
const totalLabel = computed(() => formatBytes(props.media.reduce((sum, item) => sum + item.size_bytes, 0)));

const confirmations = {
    leave: {
        title: `Quitter « ${props.group.name} » ?`,
        description: 'Tu ne verras plus les fichiers partagés dans ce groupe. Un membre pourra te réinviter.',
        label: 'Quitter',
        run: () => router.post(props.leave_url),
    },
    delete: {
        title: `Supprimer « ${props.group.name} » ?`,
        description: 'Le groupe et ses invitations disparaissent. Les fichiers restent chez leurs déposants.',
        label: 'Supprimer',
        run: () => router.delete(props.delete_url),
    },
    regenerate: {
        title: 'Nouveau lien d’invitation ?',
        description: 'L’ancien lien, s’il circule encore, ne mènera plus nulle part.',
        label: 'Régénérer',
        run: () => router.post(props.regenerate_url, {}, { preserveScroll: true }),
    },
};

function confirm() {
    processing.value = true;
    confirmations[confirming.value].run();
    confirming.value = null;
    processing.value = false;
}
</script>

<template>
    <Head :title="group.name" />

    <Link :href="routes.dashboard" class="mb-4 inline-flex items-center gap-1.5 text-[13px] no-underline hover:underline">
        <ArrowLeft class="size-4" />
        Mes fichiers
    </Link>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="mb-1.5 text-[clamp(24px,4vw,34px)]">{{ group.name }}</h1>
            <p class="text-[14px] text-text-muted">
                {{ group.members_label }} · {{ group.detail }} —
                <span class="font-semibold text-accent-400">en qualité d'origine</span>
            </p>
            <p v-if="group.expires_label" class="mt-1 text-[13px]" :class="group.is_expiring_soon ? 'text-warning' : 'text-text-muted'">
                {{ group.expires_label }} — à l'échéance, les fichiers déposés ici sont détruits ; ceux partagés depuis une galerie y restent.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" @click="inviting = true">
                <UserPlus class="size-[18px]" />
                Inviter des membres
            </button>
            <a v-if="media.length" :href="group.download_url" class="btn btn-secondary no-underline hover:no-underline" download>
                <Download class="size-4" />
                Tout télécharger
                <span class="font-mono text-[12px] text-text-muted">{{ totalLabel }}</span>
            </a>
            <button v-if="group.is_owner" type="button" class="btn btn-secondary" @click="confirming = 'regenerate'">
                <RefreshCw class="size-4" />
                Nouveau lien
            </button>
            <button v-if="can_leave" type="button" class="btn btn-secondary" @click="confirming = 'leave'">
                <LogOut class="size-4" />
                Quitter
            </button>
            <button v-if="can_delete" type="button" class="btn btn-danger" @click="confirming = 'delete'">
                <Trash2 class="size-4" />
                Supprimer
            </button>
        </div>
    </div>

    <Dropzone class="mb-7" :group-name="group.name" />
    <UploadPanel />

    <section class="mb-7">
        <h2 class="mb-3 text-[16px]">Membres</h2>
        <ul class="flex flex-wrap gap-2">
            <li v-for="member in members" :key="member.id" class="flex items-center gap-2 rounded-pill border border-neutral-800 bg-surface py-1 pr-3 pl-1">
                <span class="inline-flex size-7 items-center justify-center rounded-full bg-accent-900 font-heading text-[11px] font-semibold text-accent-300">
                    {{ member.initials }}
                </span>
                <span class="text-[13px]">{{ member.name }}</span>
                <span v-if="member.is_owner" class="badge badge-accent h-[18px] text-[10px]">{{ member.role }}</span>
            </li>
        </ul>
    </section>

    <section>
        <h2 class="mb-3 text-[16px]">Fichiers partagés</h2>
        <MediaGrid v-if="media.length" :media="media" action="download" @open="details = $event" />
        <div v-else class="card py-12 text-center">
            <p class="text-text-muted">
                Rien pour l'instant. Glisse une photo ci-dessus, ou depuis
                <Link :href="routes.dashboard">ta galerie</Link> : Partager → <strong class="text-text">Vers un groupe</strong>.
            </p>
        </div>
    </section>

    <MediaDetailsDialog :media="currentDetails" @close="details = null" />
    <InviteDialog :group="inviting ? group : null" @close="inviting = false" />
    <ConfirmDialog
        :open="confirming !== null"
        :title="confirming ? confirmations[confirming].title : ''"
        :description="confirming ? confirmations[confirming].description : ''"
        :confirm-label="confirming ? confirmations[confirming].label : ''"
        :processing="processing"
        @update:open="(value) => !value && (confirming = null)"
        @confirm="confirm"
    />
</template>
