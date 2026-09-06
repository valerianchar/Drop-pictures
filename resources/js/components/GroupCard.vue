<script setup>
import { Link } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';

const props = defineProps({
    group: { type: Object, required: true },
});

const emit = defineEmits(['invite']);
</script>

<template>
    <Link :href="props.group.url" class="card card-interactive animate-pop block no-underline hover:no-underline">
        <div class="mb-1.5 flex items-center justify-between gap-2">
            <h3 class="truncate text-[16px] text-text">{{ props.group.name }}</h3>
            <span class="badge badge-neutral shrink-0">{{ props.group.members_label }}</span>
        </div>
        <p class="mb-3 text-[13px] text-text-muted">{{ props.group.detail }}</p>
        <button type="button" class="btn btn-ghost btn-sm" @click.prevent.stop="emit('invite', props.group)">
            <UserPlus class="size-4" />
            Inviter des membres
        </button>
    </Link>
</template>
