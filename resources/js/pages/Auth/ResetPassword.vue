<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FormField from '../../components/FormField.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { routes } from '../../routes';

defineOptions({ layout: null });

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(routes.resetPassword, { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Nouveau mot de passe" />

    <GuestLayout title="Nouveau mot de passe" subtitle="Choisis-le bien, puis on te reconnecte.">
        <form class="flex flex-col gap-[14px]" @submit.prevent="submit">
            <FormField label="E-mail" :error="form.errors.email">
                <input v-model="form.email" type="email" class="field" autocomplete="email" required />
            </FormField>

            <FormField label="Nouveau mot de passe" :error="form.errors.password" hint="8 caractères au moins.">
                <input
                    v-model="form.password"
                    type="password"
                    class="field"
                    placeholder="••••••••"
                    autocomplete="new-password"
                    required
                />
            </FormField>

            <FormField label="Confirme-le" :error="form.errors.password_confirmation">
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    class="field"
                    placeholder="••••••••"
                    autocomplete="new-password"
                    required
                />
            </FormField>

            <button type="submit" class="btn btn-primary btn-lg mt-1 w-full" :disabled="form.processing">
                Changer le mot de passe
            </button>
        </form>
    </GuestLayout>
</template>
