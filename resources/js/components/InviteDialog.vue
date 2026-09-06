<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Link as LinkIcon, Send } from '@lucide/vue';
import AppDialog from './AppDialog.vue';
import { useClipboard } from '../composables/useClipboard';
import { useToast } from '../composables/useToast';

const props = defineProps({
    group: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const { copy } = useClipboard();
const { toast } = useToast();

const open = computed(() => props.group !== null);
const form = useForm({ email: '' });
const shortLink = computed(() => (props.group?.invite_url ?? '').replace(/^https?:\/\//, ''));

watch(open, (value) => value && form.reset() && form.clearErrors());

async function copyInvite() {
    await copy(props.group.invite_url);
    toast('Lien d’invitation copié.');
}

function sendInvite() {
    form.post(props.group.invite_email_url, {
        preserveState: true,
        preserveScroll: true,
        only: ['flash', 'errors'],
        onSuccess: () => form.reset(),
    });
}

const sent = ref(false);
</script>

<template>
    <AppDialog :open="open" :title="group ? `Inviter dans « ${group.name} »` : ''" @update:open="(value) => !value && emit('close')">
        <template #description>
            Toute personne avec ce lien rejoint le groupe et reçoit les fichiers
            <strong class="text-accent-400">en qualité d'origine</strong>.
        </template>

        <div class="mb-4 flex gap-2">
            <input :value="shortLink" readonly class="field flex-1 font-mono text-[12px]!" aria-label="Lien d'invitation" @focus="$event.target.select()" />
            <button type="button" class="btn btn-primary" @click="copyInvite">
                <LinkIcon class="size-4" />
                Copier
            </button>
        </div>

        <form class="flex flex-col gap-2 text-[14px]" @submit.prevent="sendInvite">
            <label for="invite-mail" class="font-semibold">Ou envoie une invitation par e-mail</label>
            <div class="flex gap-2">
                <input
                    id="invite-mail"
                    v-model="form.email"
                    type="email"
                    class="field flex-1"
                    placeholder="leo@exemple.fr"
                    autocomplete="off"
                    required
                />
                <button type="submit" class="btn btn-secondary" :disabled="form.processing">
                    <Send class="size-4" />
                    Envoyer
                </button>
            </div>
            <p v-if="form.errors.email" class="text-[13px] text-danger-strong">{{ form.errors.email }}</p>
            <p v-else class="hint">L'invité crée un compte en un clic et arrive directement dans le groupe.</p>
        </form>

        <template #actions>
            <button type="button" class="btn btn-secondary" @click="emit('close')">Fermer</button>
        </template>
    </AppDialog>
</template>
