<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppDialog from './AppDialog.vue';
import FormField from './FormField.vue';
import { routes } from '../routes';

const props = defineProps({
    open: { type: Boolean, required: true },
});

const emit = defineEmits(['update:open', 'created']);

const form = useForm({ name: '' });

watch(
    () => props.open,
    (value) => {
        if (value) {
            form.reset();
            form.clearErrors();
        }
    },
);

function submit() {
    form.post(routes.groups, {
        preserveState: true,
        preserveScroll: true,
        only: ['groups', 'flash', 'errors'],
        onSuccess: () => {
            emit('created');
            emit('update:open', false);
        },
    });
}
</script>

<template>
    <AppDialog :open="props.open" title="Nouveau groupe" @update:open="emit('update:open', $event)">
        <form id="new-group" @submit.prevent="submit">
            <FormField
                label="Nom du groupe"
                :error="form.errors.name"
                hint="Tu pourras inviter les membres par lien — ils reçoivent les fichiers en qualité d'origine."
            >
                <input v-model="form.name" type="text" class="field" placeholder="Week-end à Annecy" maxlength="80" required autofocus />
            </FormField>
        </form>

        <template #actions>
            <button type="button" class="btn btn-secondary" @click="emit('update:open', false)">Annuler</button>
            <button type="submit" form="new-group" class="btn btn-primary" :disabled="form.processing">Créer le groupe</button>
        </template>
    </AppDialog>
</template>
