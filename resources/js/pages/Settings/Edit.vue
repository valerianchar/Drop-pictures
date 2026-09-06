<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, HardDrive } from '@lucide/vue';
import FormField from '../../components/FormField.vue';
import { routes } from '../../routes';

const props = defineProps({
    limits: { type: Object, required: true },
    disk: { type: Object, required: true },
    update_url: { type: String, required: true },
});

const form = useForm({
    quota_gb: props.limits.quota_gb,
    photo_gb: props.limits.photo_gb,
    video_gb: props.limits.video_gb,
    autre_gb: props.limits.autre_gb,
});

function submit() {
    form.put(props.update_url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Réglages" />

    <Link :href="routes.dashboard" class="mb-4 inline-flex items-center gap-1.5 text-[13px] no-underline hover:underline">
        <ArrowLeft class="size-4" />
        Mes fichiers
    </Link>

    <div class="mb-6">
        <h1 class="mb-1.5 text-[clamp(24px,4vw,34px)]">Réglages de l'instance</h1>
        <p class="text-[14px] text-text-muted">
            Les limites de dépôt. Rien n'est jamais compressé : chaque Go annoncé est un Go réel sur le disque.
        </p>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <form class="card flex flex-col gap-5" @submit.prevent="submit">
            <FormField label="Espace par compte (Go)" :error="form.errors.quota_gb" hint="Tous les fichiers d'un compte, cumulés.">
                <input v-model="form.quota_gb" type="number" step="0.1" min="1" class="field font-mono" required />
            </FormField>

            <div class="grid gap-5 sm:grid-cols-3">
                <FormField label="Photo (Go max)" :error="form.errors.photo_gb">
                    <input v-model="form.photo_gb" type="number" step="0.1" min="0.1" class="field font-mono" required />
                </FormField>
                <FormField label="Vidéo (Go max)" :error="form.errors.video_gb">
                    <input v-model="form.video_gb" type="number" step="0.1" min="0.1" class="field font-mono" required />
                </FormField>
                <FormField label="Autre fichier (Go max)" :error="form.errors.autre_gb">
                    <input v-model="form.autre_gb" type="number" step="0.1" min="0.1" class="field font-mono" required />
                </FormField>
            </div>

            <p class="hint">
                La famille d'un fichier se lit sur son extension : JPG, HEIC, RAW, TIFF… sont des photos ; MP4, MOV, MKV… des vidéos.
                Un dépôt est refusé à l'ouverture s'il dépasse la limite de sa famille, le quota du compte, ou la place disponible sur le serveur.
            </p>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary" :disabled="form.processing || !form.isDirty">Enregistrer</button>
            </div>
        </form>

        <aside class="card">
            <div class="mb-3 flex items-center gap-2">
                <HardDrive class="size-[18px] text-accent-400" />
                <h2 class="text-[16px]">Le disque du serveur</h2>
            </div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-[13px]">
                <dt class="text-text-muted">Libre</dt>
                <dd class="font-mono">{{ disk.free_label ?? '—' }}</dd>
                <dt class="text-text-muted">Réserve</dt>
                <dd class="font-mono">{{ disk.reserve_label }}</dd>
                <dt class="text-text-muted">Disponible pour les dépôts</dt>
                <dd class="font-mono text-accent-400">{{ disk.usable_label ?? '—' }}</dd>
            </dl>
            <p class="hint mt-3">
                Les quotas par compte s'additionnent : c'est cette place-là qui borne réellement le total. Pour aller au-delà, il faut agrandir le disque du serveur.
            </p>
        </aside>
    </div>
</template>
