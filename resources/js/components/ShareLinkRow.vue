<script setup>
import { router } from '@inertiajs/vue3';
import { Copy, Trash2 } from '@lucide/vue';
import { useClipboard } from '../composables/useClipboard';
import { useToast } from '../composables/useToast';

const props = defineProps({
    link: { type: Object, required: true },
});

const { copy } = useClipboard();
const { toast } = useToast();

async function copyLink() {
    await copy(props.link.url);
    toast('Lien copié — qualité d’origine garantie.');
}

function revoke() {
    router.delete(props.link.delete_url, { preserveScroll: true, only: ['share_links', 'media', 'flash'] });
}
</script>

<template>
    <li class="card flex flex-wrap items-center gap-3 p-3.5 lg:flex-nowrap">
        <div class="tile-gradient relative size-12 shrink-0 overflow-hidden rounded-sm border border-neutral-800">
            <img v-if="props.link.media?.thumbnail_url" :src="props.link.media.thumbnail_url" alt="" class="size-full object-cover" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="truncate font-mono text-[12px]">{{ props.link.media?.name }}</p>
            <a :href="props.link.url" target="_blank" rel="noopener" class="block truncate font-mono text-[11px] text-accent-400">{{ props.link.short }}</a>
        </div>
        <div class="flex items-center gap-3 text-[12px] text-text-muted">
            <span :class="props.link.is_expired && 'text-warning'">{{ props.link.expires_label }}</span>
            <span class="font-mono">{{ props.link.downloads_count }} ↓</span>
        </div>
        <div class="flex gap-1">
            <button type="button" class="btn btn-ghost iconbtn btn-sm" aria-label="Copier le lien" @click="copyLink">
                <Copy class="size-4" />
            </button>
            <button type="button" class="btn btn-ghost iconbtn btn-sm text-danger-strong hover:text-danger-strong" aria-label="Désactiver le lien" @click="revoke">
                <Trash2 class="size-4" />
            </button>
        </div>
    </li>
</template>
