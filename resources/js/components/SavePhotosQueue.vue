<script setup>
import { computed } from 'vue';
import { Check, ImageDown, LoaderCircle, SkipForward } from '@lucide/vue';
import AppDialog from './AppDialog.vue';
import { usePhotosQueue } from '../composables/useSaveToPhotos';
import { formatBytes, plural } from '../format';

/**
 * « Tout enregistrer dans Photos », lot par lot.
 *
 * iOS n'accepte pas vingt vidéos dans une seule feuille de partage, et
 * n'ouvre la feuille que juste après un appui — aucune tâche de fond ne peut
 * déposer à notre place dans la photothèque. Ce dialogue fait donc défiler des
 * lots que la feuille accepte : un appui, un lot, et il enchaîne.
 */
const { queue, currentLot, totalFiles, done, lotBytes, percent, saveLot, skipLot, close } = usePhotosQueue();

const lotLabel = computed(() => `Lot ${Math.min(queue.index + 1, queue.lots.length)} sur ${queue.lots.length}`);
const remaining = computed(() => totalFiles.value - queue.savedCount - queue.skipped.length);
</script>

<template>
    <AppDialog :open="queue.open" title="Enregistrer dans Photos" @update:open="!$event && close()">
        <template #description>
            <template v-if="done">
                {{ plural(queue.savedCount, 'fichier enregistré') }} sur {{ totalFiles }}.
            </template>
            <template v-else>
                {{ lotLabel }} — iOS n’accepte qu’un lot à la fois, et seulement juste après un appui.
            </template>
        </template>

        <!-- Avancement : ce qui est fait, ce qui reste. -->
        <div class="mb-4">
            <div class="h-1.5 w-full overflow-hidden rounded-pill bg-neutral-800">
                <div
                    class="h-full rounded-pill bg-accent-500 transition-[width] duration-300"
                    :style="{ width: `${totalFiles ? Math.round((100 * (queue.savedCount + queue.skipped.length)) / totalFiles) : 0}%` }"
                />
            </div>
            <p class="mt-2 font-mono text-[11px] text-text-muted">
                {{ queue.savedCount }} enregistré{{ queue.savedCount > 1 ? 's' : '' }}
                <template v-if="queue.skipped.length"> · {{ queue.skipped.length }} passé{{ queue.skipped.length > 1 ? 's' : '' }}</template>
                <template v-if="remaining > 0"> · {{ remaining }} à venir</template>
            </p>
        </div>

        <div v-if="!done" class="rounded-md border border-neutral-800 bg-surface p-3">
            <p class="text-[13px] font-semibold">
                {{ plural(currentLot.length, 'fichier') }} · {{ formatBytes(lotBytes) }}
            </p>
            <ul class="mt-2 space-y-1">
                <li v-for="media in currentLot" :key="media.id" class="truncate font-mono text-[11px] text-text-muted">
                    {{ media.name }}
                </li>
            </ul>
        </div>

        <div v-else-if="queue.savedCount > 0" class="flex items-start gap-2.5 rounded-md border border-neutral-800 bg-surface p-3 text-[13px]">
            <Check class="mt-px size-[18px] shrink-0 text-accent-400" />
            <p>C’est fait — les originaux sont dans ta photothèque, octets inchangés.</p>
        </div>

        <div v-if="queue.tooBig.length" class="mt-3 rounded-md border border-neutral-800 bg-surface p-3 text-[13px]">
            <p class="font-semibold text-warning">{{ plural(queue.tooBig.length, 'fichier trop lourd') }} pour la feuille de partage</p>
            <p class="mt-0.5 text-text-muted">
                Au-delà de 1,5 Go, iOS refuse le partage : passe par « Télécharger » — le fichier arrive dans l’app Fichiers.
            </p>
        </div>

        <template #actions>
            <button v-if="!done" type="button" class="btn btn-ghost" :disabled="queue.busy" @click="skipLot">
                <SkipForward class="size-4" />
                Passer
            </button>
            <button v-if="!done" type="button" class="btn btn-primary" :disabled="queue.busy" @click="saveLot">
                <LoaderCircle v-if="queue.busy" class="size-[18px] animate-spin" />
                <ImageDown v-else class="size-[18px]" />
                <template v-if="queue.busy">Préparation… {{ percent }} %</template>
                <template v-else-if="queue.ready">Prêt — appuie de nouveau</template>
                <template v-else>Enregistrer ce lot</template>
            </button>
            <button v-else type="button" class="btn btn-primary" @click="close">Terminer</button>
        </template>
    </AppDialog>
</template>
