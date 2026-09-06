<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { Plus } from '@lucide/vue';
import Dropzone from '../components/Dropzone.vue';
import GroupCard from '../components/GroupCard.vue';
import InviteDialog from '../components/InviteDialog.vue';
import MediaDetailsDialog from '../components/MediaDetailsDialog.vue';
import MediaGrid from '../components/MediaGrid.vue';
import NewGroupDialog from '../components/NewGroupDialog.vue';
import ShareDialog from '../components/ShareDialog.vue';
import ShareLinkRow from '../components/ShareLinkRow.vue';
import TagFilter from '../components/TagFilter.vue';
import UploadPanel from '../components/UploadPanel.vue';
import { routes } from '../routes';

const props = defineProps({
    storage: { type: Object, required: true },
    filters: { type: Object, required: true },
    kinds: { type: Array, required: true },
    tags: { type: Array, required: true },
    media: { type: Array, required: true },
    groups: { type: Array, required: true },
    share_links: { type: Array, required: true },
});

const page = usePage();
const firstName = computed(() => page.props.auth.user.first_name);

const tab = ref('fichiers');
const sharing = ref(null);
const details = ref(null);
const inviting = ref(null);
const creatingGroup = ref(false);

const hasFilters = computed(() => Boolean(props.filters.tag || props.filters.type || props.filters.q));

function applyFilters(filters) {
    router.get(routes.dashboard, filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['media', 'filters'],
    });
}

/*
 * Le dialogue de détails et celui de partage lisent le média dans la liste :
 * après une modification (tags, lien créé), ils reflètent la version fraîche.
 */
const currentDetails = computed(() => (details.value ? props.media.find((item) => item.id === details.value.id) ?? null : null));
const currentSharing = computed(() => (sharing.value ? props.media.find((item) => item.id === sharing.value.id) ?? null : null));

function openShare(media) {
    details.value = null;
    sharing.value = media;
}

const storageLine = computed(() => {
    const files = props.storage.count;

    return `${files.toLocaleString('fr-FR')} ${files > 1 ? 'fichiers' : 'fichier'} · ${props.storage.used_label} sur ${props.storage.quota_label}`;
});
</script>

<template>
    <Head title="Mes fichiers" />

    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="mb-1.5 text-[clamp(24px,4vw,34px)]">Salut {{ firstName }}, tes fichiers t'attendent</h1>
            <p class="text-[14px] text-text-muted">
                {{ storageLine }} — <span class="font-semibold text-accent-400">toujours en qualité d'origine</span>
            </p>
        </div>
        <div class="h-1.5 w-full max-w-[220px] overflow-hidden rounded-full bg-neutral-800" role="progressbar" :aria-valuenow="Math.round(storage.ratio * 100)" aria-valuemin="0" aria-valuemax="100" aria-label="Espace utilisé">
            <div class="h-full rounded-full bg-accent-600" :style="{ width: `${Math.max(2, storage.ratio * 100)}%` }" />
        </div>
    </div>

    <Dropzone class="mb-7" />
    <UploadPanel />

    <TabsRoot v-model="tab">
        <TabsList class="mb-4 flex gap-1 border-b border-neutral-800" aria-label="Sections">
            <TabsTrigger value="fichiers" class="tab">Fichiers</TabsTrigger>
            <TabsTrigger value="partages" class="tab">Partages<span v-if="share_links.length" class="ml-1.5 font-mono text-[11px] text-text-muted">{{ share_links.length }}</span></TabsTrigger>
            <TabsTrigger value="groupes" class="tab">Groupes<span v-if="groups.length" class="ml-1.5 font-mono text-[11px] text-text-muted">{{ groups.length }}</span></TabsTrigger>
        </TabsList>

        <TabsContent value="fichiers">
            <TagFilter :tags="tags" :kinds="kinds" :filters="filters" @change="applyFilters" />

            <MediaGrid v-if="media.length" :media="media" @open="details = $event" @share="openShare" />

            <div v-else class="card py-12 text-center">
                <p class="text-text-muted">
                    <template v-if="hasFilters">Rien ne correspond à ce filtre.</template>
                    <template v-else>Aucun fichier pour l'instant — glisse une photo ou une vidéo pour commencer.</template>
                </p>
                <button v-if="hasFilters" type="button" class="btn btn-ghost btn-sm mt-3" @click="applyFilters({})">Tout afficher</button>
            </div>
        </TabsContent>

        <TabsContent value="partages">
            <ul v-if="share_links.length" class="flex flex-col gap-2.5">
                <ShareLinkRow v-for="link in share_links" :key="link.id" :link="link" />
            </ul>
            <div v-else class="card py-12 text-center">
                <p class="text-text-muted">Aucun lien pour l'instant — partage un fichier depuis la galerie.</p>
            </div>
        </TabsContent>

        <TabsContent value="groupes">
            <div class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-3.5">
                <GroupCard v-for="group in groups" :key="group.id" :group="group" @invite="inviting = $event" />
                <button
                    type="button"
                    class="card flex min-h-24 items-center justify-center gap-2 border-dashed border-accent-800 bg-transparent font-body text-[14px] font-semibold text-accent-400 transition-colors hover:border-accent-600 hover:bg-tint-accent-8"
                    @click="creatingGroup = true"
                >
                    <Plus class="size-[18px]" />
                    Créer un groupe
                </button>
            </div>
        </TabsContent>
    </TabsRoot>

    <MediaDetailsDialog :media="currentDetails" @close="details = null" @share="openShare" />
    <ShareDialog :media="currentSharing" :groups="groups" @close="sharing = null" />
    <InviteDialog :group="inviting" @close="inviting = null" />
    <NewGroupDialog v-model:open="creatingGroup" @created="tab = 'groupes'" />
</template>
