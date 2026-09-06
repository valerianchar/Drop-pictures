<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FormField from '../../components/FormField.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { routes } from '../../routes';

defineOptions({ layout: null });

const page = usePage();
const registrationOpen = computed(() => page.props.registration_open || page.props.pending_group);

const form = useForm({ email: '', password: '', remember: true });

function submit() {
    form.post(routes.login, { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Connexion" />

    <GuestLayout title="Content de te revoir" subtitle="Connecte-toi pour retrouver tes fichiers.">
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

            <FormField label="Mot de passe" :error="form.errors.password">
                <input
                    v-model="form.password"
                    type="password"
                    class="field"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                />
                <template #hint>
                    <Link :href="routes.forgotPassword">Mot de passe oublié ?</Link>
                </template>
            </FormField>

            <button type="submit" class="btn btn-primary btn-lg mt-1 w-full" :disabled="form.processing">
                Se connecter
            </button>
        </form>

        <template v-if="registrationOpen" #footer>
            Pas encore de compte ? <Link :href="routes.register">Crée-en un</Link>
        </template>
    </GuestLayout>
</template>
