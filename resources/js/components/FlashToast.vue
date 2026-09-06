<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ToastProvider, ToastRoot, ToastTitle, ToastViewport } from 'reka-ui';
import { BadgeCheck, CircleAlert } from '@lucide/vue';

const page = usePage();
const isOpen = ref(false);
const message = ref('');
const isError = ref(false);

/**
 * La clé change à chaque message : la notification est remontée, si bien que deux
 * messages identiques à la suite rouvrent bien la notification.
 */
const shownCount = ref(0);

function show(text, asError) {
    message.value = text;
    isError.value = asError;
    shownCount.value += 1;
    isOpen.value = true;
}

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        if (error) {
            show(error, true);
        } else if (success) {
            show(success, false);
        }
    },
    { immediate: true },
);

/* Les messages locaux (lien copié, dépôt terminé) passent par un événement DOM. */
const onLocalToast = (event) => show(event.detail.message, event.detail.error);

onMounted(() => document.addEventListener('drop:toast', onLocalToast));
onUnmounted(() => document.removeEventListener('drop:toast', onLocalToast));

/*
 * Un formulaire incomplet répond par des erreurs de validation, affichées sous
 * chaque champ — parfois hors écran. La notification reprend la première pour
 * qu'on sache toujours pourquoi rien ne s'est passé.
 */
watch(
    () => page.props.errors,
    (errors) => {
        const messages = Object.values(errors ?? {});
        const others = messages.length - 1;

        if (messages.length === 1) {
            show(messages[0], true);
        } else if (messages.length > 1) {
            show(`${messages[0]} (+${others} ${others > 1 ? 'autres champs' : 'autre champ'})`, true);
        }
    },
);
</script>

<template>
    <ToastProvider>
        <ToastRoot
            :key="shownCount"
            v-model:open="isOpen"
            :duration="isError ? 6000 : 3500"
            class="animate-pop flex items-center gap-3 rounded-md border border-neutral-700 border-l-[3px] bg-surface-2 px-4 py-3 text-[14px] shadow-md data-[state=closed]:opacity-0"
            :class="isError ? 'border-l-danger' : 'border-l-accent-500'"
        >
            <CircleAlert v-if="isError" class="size-[18px] shrink-0 text-danger-strong" />
            <BadgeCheck v-else class="size-[18px] shrink-0 text-accent-400" />
            <ToastTitle>{{ message }}</ToastTitle>
        </ToastRoot>
        <ToastViewport
            class="fixed bottom-[calc(env(safe-area-inset-bottom)+1.25rem)] left-1/2 z-60 flex w-max max-w-[calc(100vw-2rem)] -translate-x-1/2 flex-col gap-2 lg:right-5 lg:left-auto lg:translate-x-0"
        />
    </ToastProvider>
</template>
