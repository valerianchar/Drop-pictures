<script setup>
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { X } from '@lucide/vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    title: { type: String, required: true },
    description: { type: String, default: null },
});

const emit = defineEmits(['update:open']);
</script>

<template>
    <DialogRoot :open="props.open" @update:open="emit('update:open', $event)">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-80 bg-[rgba(4,7,4,0.7)] backdrop-blur-[4px]" />
            <!-- Mobile : feuille collée en bas de l'écran ; desktop : carte centrée. -->
            <DialogContent
                class="dialog animate-pop fixed inset-x-0 bottom-0 z-90 max-h-[88dvh] overflow-y-auto rounded-b-none border-b-0 pb-[max(1.5rem,env(safe-area-inset-bottom))] outline-none lg:inset-x-auto lg:top-1/2 lg:left-1/2 lg:bottom-auto lg:w-[min(460px,90vw)] lg:max-h-[85vh] lg:-translate-x-1/2 lg:-translate-y-1/2 lg:rounded-lg lg:border-b lg:pb-6"
            >
                <div class="mx-auto mb-4 h-1 w-9 rounded-full bg-neutral-700 lg:hidden" aria-hidden="true" />

                <div class="flex items-start justify-between gap-3">
                    <DialogTitle class="font-heading text-[20px] leading-tight font-semibold tracking-tight">
                        {{ props.title }}
                    </DialogTitle>
                    <button
                        type="button"
                        class="btn btn-ghost iconbtn btn-sm -mt-1 -mr-2 shrink-0"
                        aria-label="Fermer"
                        @click="emit('update:open', false)"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <DialogDescription v-if="props.description || $slots.description" class="mt-1 text-[13px] text-text-muted">
                    <slot name="description">{{ props.description }}</slot>
                </DialogDescription>

                <div class="mt-4">
                    <slot />
                </div>

                <div v-if="$slots.actions" class="mt-5 flex flex-wrap justify-end gap-2">
                    <slot name="actions" />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
