<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FormField from '../../components/FormField.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { routes } from '../../routes';

defineOptions({ layout: null });

const page = usePage();
const pendingGroup = computed(() => page.props.pending_group);

const form = useForm({ name: '', email: '', password: '' });

function submit() {
    form.post(routes.register, { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Inscription" />

    <GuestLayout
        title="Crée ton compte"
        :subtitle="pendingGroup ? `Tu arriveras directement dans « ${pendingGroup} ».` : 'Gratuit — 10 Go pour commencer.'"
    >
        <form class="flex flex-col gap-[14px]" @submit.prevent="submit">
            <FormField label="Prénom" :error="form.errors.name">
                <input v-model="form.name" type="text" class="field" placeholder="Marie" autocomplete="given-name" required />
            </FormField>

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

            <FormField label="Mot de passe" :error="form.errors.password" hint="8 caractères au moins.">
                <input
                    v-model="form.password"
                    type="password"
                    class="field"
                    placeholder="••••••••"
                    autocomplete="new-password"
                    required
                />
            </FormField>

            <button type="submit" class="btn btn-primary btn-lg mt-1 w-full" :disabled="form.processing">
                Créer mon compte
            </button>
        </form>

        <template #footer>
            Déjà un compte ? <Link :href="routes.login">Connecte-toi</Link>
        </template>
    </GuestLayout>
</template>
