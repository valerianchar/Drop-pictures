<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '../../components/FormField.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { routes } from '../../routes';

defineOptions({ layout: null });

const form = useForm({ email: '' });

function submit() {
    form.post(routes.forgotPassword);
}
</script>

<template>
    <Head title="Mot de passe oublié" />

    <GuestLayout title="Mot de passe oublié ?" subtitle="On t'envoie un lien pour en choisir un nouveau.">
        <form class="flex flex-col gap-[14px]" @submit.prevent="submit">
            <FormField label="E-mail" :error="form.errors.email">
                <input
                    v-model="form.email"
                    type="email"
                    class="field"
                    placeholder="marie@exemple.fr"
                    autocomplete="email"
                    required
                />
            </FormField>

            <button type="submit" class="btn btn-primary btn-lg mt-1 w-full" :disabled="form.processing">
                Envoyer le lien
            </button>
        </form>

        <template #footer>
            <Link :href="routes.login">Retour à la connexion</Link>
        </template>
    </GuestLayout>
</template>
