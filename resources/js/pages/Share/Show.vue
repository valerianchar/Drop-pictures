<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Copy, Download, Play, ShieldCheck } from '@lucide/vue';
import AppLogo from '../../components/AppLogo.vue';
import FlashToast from '../../components/FlashToast.vue';
import LoginBackground from '../../components/LoginBackground.vue';
import { useClipboard } from '../../composables/useClipboard';
import { useToast } from '../../composables/useToast';
import { routes } from '../../routes';

defineOptions({ layout: null });

const props = defineProps({
    media: { type: Object, required: true },
    owner: { type: String, required: true },
    expires_label: { type: String, default: null },
});

const page = usePage();
const { copy } = useClipboard();
const { toast } = useToast();

const signedIn = computed(() => page.props.auth.user !== null);

async function copyChecksum() {
    await copy(props.media.checksum);
    toast('Empreinte SHA-256 copiée.');
}
</script>

<template>
    <Head :title="media.name" />

    <div class="relative flex min-h-dvh flex-col overflow-hidden">
        <LoginBackground />

        <header class="relative z-1 flex items-center justify-between px-5 pt-[calc(env(safe-area-inset-top)+1rem)] pb-4 lg:px-8">
            <Link :href="signedIn ? routes.dashboard : routes.login" class="no-underline hover:no-underline"><AppLogo /></Link>
            <Link v-if="!signedIn" :href="page.props.registration_open ? routes.register : routes.login" class="btn btn-ghost btn-sm no-underline hover:no-underline">
                Crée ton espace
            </Link>
        </header>

        <main class="relative z-1 flex flex-1 items-center justify-center px-4 pb-[calc(env(safe-area-inset-bottom)+2rem)]">
            <article class="animate-pop w-full max-w-[560px] overflow-hidden rounded-lg border border-neutral-800 bg-[rgba(17,22,17,0.9)] shadow-[var(--shadow-lg),0_0_60px_rgba(61,255,122,0.07)] backdrop-blur-[6px]">
                <div class="tile-gradient relative aspect-[3/2] w-full">
                    <img v-if="media.thumbnail_url" :src="media.thumbnail_url" :alt="media.name" class="absolute inset-0 size-full object-contain" />
                    <span v-else class="absolute inset-0 flex items-center justify-center font-mono text-[13px] text-neutral-500">{{ media.extension.toUpperCase() }}</span>
                    <span v-if="media.is_video" class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-pill bg-[rgba(4,7,4,0.75)] px-2.5 py-[3px] font-mono text-[10px]">
                        <Play class="size-2.5" />{{ media.duration_label ?? 'Vidéo' }}
                    </span>
                    <span class="badge absolute top-3 right-3 bg-[rgba(4,7,4,0.8)] text-accent-400 backdrop-blur-[2px]">Original · {{ media.quality }}</span>
                </div>

                <div class="p-6">
                    <p class="mb-1 text-[13px] text-text-muted">{{ owner }} partage avec toi</p>
                    <h1 class="mb-1 break-all font-mono text-[18px] tracking-normal">{{ media.name }}</h1>
                    <p class="mb-5 font-mono text-[12px] text-text-muted">{{ media.meta }}</p>

                    <a :href="media.download_url" class="btn btn-primary btn-lg w-full no-underline hover:no-underline" download>
                        <Download class="size-[18px]" />
                        Télécharger l'original ({{ media.size_label }})
                    </a>

                    <div class="mt-5 flex items-start gap-2.5 rounded-md border border-neutral-800 bg-surface p-3.5 text-[13px]">
                        <ShieldCheck class="mt-px size-[18px] shrink-0 text-accent-400" />
                        <div class="min-w-0">
                            <p class="font-semibold">Exactement le fichier d'origine — zéro compression.</p>
                            <p class="mt-1 text-text-muted">Vérifie-le : l'empreinte SHA-256 du fichier téléchargé doit être</p>
                            <div class="mt-1 flex items-center gap-1.5">
                                <code class="truncate font-mono text-[11px] text-accent-300">{{ media.checksum }}</code>
                                <button type="button" class="btn btn-ghost iconbtn btn-sm -my-1 shrink-0" aria-label="Copier l'empreinte" @click="copyChecksum">
                                    <Copy class="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="expires_label" class="mt-4 text-center text-[12px] text-text-muted">Ce lien expire le {{ expires_label }}.</p>
                </div>
            </article>
        </main>
    </div>

    <FlashToast />
</template>
