<script setup>
import AppDialog from './AppDialog.vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    title: { type: String, required: true },
    description: { type: String, required: true },
    confirmLabel: { type: String, default: 'Confirmer' },
    cancelLabel: { type: String, default: 'Annuler' },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'confirm']);
</script>

<template>
    <AppDialog :open="props.open" :title="props.title" :description="props.description" @update:open="emit('update:open', $event)">
        <template #actions>
            <button type="button" class="btn btn-secondary" @click="emit('update:open', false)">{{ props.cancelLabel }}</button>
            <button type="button" class="btn btn-danger" :disabled="props.processing" @click="emit('confirm')">
                {{ props.confirmLabel }}
            </button>
        </template>
    </AppDialog>
</template>
