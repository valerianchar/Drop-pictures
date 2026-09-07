<script setup>
import { computed } from 'vue';
import { BadgeCheck, CircleAlert, LoaderCircle, RotateCcw, X } from '@lucide/vue';
import { useUploader } from '../composables/useUploader';
import { formatBytes } from '../format';

const { items, resumables, active, cancel, clearFinished, resume, forgetResumable } = useUploader();

const finishedCount = computed(() => items.filter((item) => item.status === 'termine').length);

const labels = {
    attente: 'En attente',
    preparation: 'Préparation…',
    envoi: 'Envoi',
    verification: 'Vérification de l’empreinte…',
    termine: 'Déposé — qualité d’origine conservée',
    erreur: 'Échec',
    annule: 'Annulé',
};
</script>

<template>
    <!--
        Un envoi interrompu. Sur iPhone, aucune page web ne peut téléverser en
        tâche de fond : quitter Safari suspend l'envoi. Ce qui était monté reste
        pourtant chez le serveur 24 h — il suffit de redésigner le fichier pour
        que seuls les morceaux manquants repartent.
    -->
    <section v-if="resumables.length" class="card mb-4 border-warning/40 p-4 lg:p-5">
        <h2 class="mb-1 text-[16px]">
            {{ resumables.length > 1 ? `${resumables.length} envois interrompus` : 'Envoi interrompu' }}
        </h2>
        <p class="mb-3 text-[13px] text-text-muted">
            Ce qui était déjà monté est gardé. Redésigne le fichier : seuls les morceaux manquants repartent.
        </p>

        <ul class="flex flex-col gap-2.5">
            <li v-for="entry in resumables" :key="entry.uuid" class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="truncate font-mono text-[12px]">{{ entry.name }}</span>
                        <span class="shrink-0 font-mono text-[10px] text-text-muted">
                            {{ formatBytes(entry.receivedBytes) }} / {{ formatBytes(entry.size) }}
                        </span>
                    </div>
                    <div class="mt-1 h-1 overflow-hidden rounded-full bg-neutral-800">
                        <div class="h-full rounded-full bg-warning" :style="{ width: `${Math.round(entry.progress * 100)}%` }" />
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm shrink-0" @click="resume(entry)">
                    <RotateCcw class="size-4" />
                    Reprendre
                </button>
                <button
                    type="button"
                    class="btn btn-ghost iconbtn btn-sm shrink-0 text-text-muted"
                    aria-label="Abandonner cet envoi"
                    @click="forgetResumable(entry)"
                >
                    <X class="size-4" />
                </button>
            </li>
        </ul>
    </section>

    <section v-if="items.length" class="card mb-7 p-4 lg:p-5" aria-live="polite">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-[16px]">
                <template v-if="active.length">
                    {{ active.length }} dépôt{{ active.length > 1 ? 's' : '' }} en cours
                </template>
                <template v-else>{{ finishedCount }} fichier{{ finishedCount > 1 ? 's' : '' }} déposé{{ finishedCount > 1 ? 's' : '' }}</template>
            </h2>
            <button v-if="!active.length" type="button" class="btn btn-ghost btn-sm" @click="clearFinished">Masquer</button>
        </div>

        <ul class="flex flex-col gap-2.5">
            <li v-for="item in items" :key="item.id" class="flex items-center gap-3">
                <span class="shrink-0">
                    <BadgeCheck v-if="item.status === 'termine'" class="size-[18px] text-accent-400" />
                    <CircleAlert v-else-if="item.status === 'erreur'" class="size-[18px] text-danger-strong" />
                    <X v-else-if="item.status === 'annule'" class="size-[18px] text-text-muted" />
                    <LoaderCircle v-else class="size-[18px] animate-spin text-accent-400" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="truncate font-mono text-[12px]">{{ item.name }}</span>
                        <span class="shrink-0 font-mono text-[10px] text-text-muted">
                            <template v-if="item.status === 'envoi'">{{ formatBytes(item.sent) }} / </template>{{ formatBytes(item.size) }}
                        </span>
                    </div>
                    <div class="mt-1 h-1 overflow-hidden rounded-full bg-neutral-800">
                        <div
                            class="h-full rounded-full transition-[width] duration-(--duration-fast)"
                            :class="item.status === 'erreur' ? 'bg-danger' : 'bg-accent-500'"
                            :style="{ width: `${Math.round(item.progress * 100)}%` }"
                        />
                    </div>
                    <p class="mt-1 text-[12px]" :class="item.status === 'erreur' ? 'text-danger-strong' : 'text-text-muted'">
                        {{ item.error ?? labels[item.status] }}
                        <span v-if="item.status === 'termine' && item.media" class="text-accent-400">· {{ item.media.quality }}</span>
                    </p>
                </div>

                <button
                    type="button"
                    class="btn btn-ghost iconbtn btn-sm shrink-0 text-text-muted"
                    :aria-label="['termine', 'erreur', 'annule'].includes(item.status) ? 'Retirer de la liste' : 'Annuler le dépôt'"
                    @click="cancel(item)"
                >
                    <X class="size-4" />
                </button>
            </li>
        </ul>
    </section>
</template>
