<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ImageUp, Search } from '@lucide/vue';
import AppLogo from '../components/AppLogo.vue';
import FlashToast from '../components/FlashToast.vue';
import SavePhotosQueue from '../components/SavePhotosQueue.vue';
import UploadPanel from '../components/UploadPanel.vue';
import UserMenu from '../components/UserMenu.vue';
import { findResumableUploads, useUploader } from '../composables/useUploader';
import { connectRealtime } from '../realtime';
import { routes } from '../routes';

const page = usePage();
const { pickFiles } = useUploader();

/* Les écrans authentifiés s'abonnent au canal privé de l'utilisateur. */
onMounted(() => {
    connectRealtime(page.props.broadcast);
    // Un envoi interrompu par une sortie de l'application se propose à la reprise.
    findResumableUploads();
});

/*
 * La recherche vit dans l'en-tête et interroge toujours la galerie : depuis une
 * autre page, elle y ramène. La saisie est temporisée pour ne pas interroger le
 * serveur à chaque lettre.
 */
const search = ref(page.props.filters?.q ?? '');
let timer = null;

watch(
    () => page.props.filters?.q,
    (value) => {
        if ((value ?? '') !== search.value) {
            search.value = value ?? '';
        }
    },
);

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const current = page.props.filters ?? {};

        if ((current.q ?? '') === value) {
            return;
        }

        router.get(
            routes.dashboard,
            { ...current, q: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true, only: ['media', 'filters'] },
        );
    }, 300);
});

const chunkBytes = computed(() => page.props.upload?.chunk_bytes);

// Le composable lit la taille de morceau annoncée par le serveur.
watch(chunkBytes, (value) => (window.__dropChunkBytes = value), { immediate: true });
</script>

<template>
    <div class="flex min-h-dvh flex-col">
        <!-- L'app installée passe sous la barre de statut du téléphone : l'en-tête s'en écarte. -->
        <header
            class="sticky top-0 z-20 flex flex-wrap items-center gap-3 border-b border-neutral-800 bg-[rgba(10,14,10,0.92)] px-4 pt-[calc(env(safe-area-inset-top)+0.875rem)] pb-3.5 backdrop-blur-[8px] lg:gap-4 lg:px-5"
        >
            <Link :href="routes.dashboard" class="no-underline hover:no-underline">
                <AppLogo />
            </Link>

            <label class="relative order-last w-full flex-none lg:order-none lg:max-w-[340px] lg:min-w-[120px] lg:flex-[1_1_160px]">
                <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-text-muted" />
                <input v-model="search" type="search" class="field pl-9" placeholder="Rechercher…" aria-label="Rechercher un fichier" />
            </label>

            <span class="flex-1" />

            <button type="button" class="btn btn-primary" @click="pickFiles()">
                <ImageUp class="size-[18px]" />
                Déposer
            </button>

            <UserMenu />
        </header>

        <main class="mx-auto w-full max-w-[1160px] flex-1 px-4 pt-6 pb-[calc(env(safe-area-inset-bottom)+6rem)] lg:px-5">
            <!-- Le bouton « Déposer » est partout : le retour des dépôts aussi. -->
            <UploadPanel />
            <slot />
        </main>
    </div>

    <FlashToast />
    <!-- La file « Tout dans Photos » est unique dans la page : un seul dialogue la déroule. -->
    <SavePhotosQueue />
</template>
