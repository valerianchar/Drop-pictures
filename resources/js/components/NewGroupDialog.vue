<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppDialog from './AppDialog.vue';
import FormField from './FormField.vue';
import { routes } from '../routes';

const props = defineProps({
    open: { type: Boolean, required: true },
    lifetimes: { type: Array, required: true },
});

const emit = defineEmits(['update:open', 'created']);

const form = useForm({ name: '', lifetime: 'illimite' });

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
        <form id="new-group" class="flex flex-col gap-4" @submit.prevent="submit">
            <FormField label="Nom du groupe" :error="form.errors.name">
                <input v-model="form.name" type="text" class="field" placeholder="Week-end à Annecy" maxlength="80" required autofocus />
            </FormField>

            <div>
                <p class="field-label">Durée de vie</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in props.lifetimes"
                        :key="option.value"
                        type="button"
                        class="chip"
                        :data-active="form.lifetime === option.value"
                        @click="form.lifetime = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <p v-if="form.errors.lifetime" class="mt-2 text-[13px] text-danger-strong">{{ form.errors.lifetime }}</p>
                <p v-else class="hint mt-2">
                    <template v-if="form.lifetime === 'illimite'">Le groupe reste tant que tu ne le supprimes pas.</template>
                    <template v-else>
                        À l'échéance, le groupe disparaît et <strong class="text-text">les fichiers déposés dedans sont détruits</strong> ;
                        ceux partagés depuis une galerie y restent.
                    </template>
                </p>
            </div>

            <p class="hint">Tu pourras inviter les membres par lien — ils reçoivent les fichiers en qualité d'origine.</p>
        </form>

        <template #actions>
            <button type="button" class="btn btn-secondary" @click="emit('update:open', false)">Annuler</button>
            <button type="submit" form="new-group" class="btn btn-primary" :disabled="form.processing">Créer le groupe</button>
        </template>
    </AppDialog>
</template>
