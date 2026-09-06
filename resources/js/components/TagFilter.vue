<script setup>
const props = defineProps({
    tags: { type: Array, required: true },
    kinds: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const emit = defineEmits(['change']);

function pickTag(tag) {
    emit('change', { ...props.filters, tag: tag || undefined });
}

function pickKind(kind) {
    emit('change', { ...props.filters, type: kind || undefined });
}
</script>

<template>
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <div class="scroll-hidden flex min-w-0 flex-1 gap-2 overflow-x-auto">
            <button type="button" class="chip" :data-active="!props.filters.tag" @click="pickTag(null)">Tout</button>
            <button
                v-for="tag in props.tags"
                :key="tag"
                type="button"
                class="chip"
                :data-active="props.filters.tag === tag"
                @click="pickTag(tag)"
            >
                {{ tag }}
            </button>
        </div>

        <div class="flex gap-1 rounded-pill border border-neutral-800 bg-surface p-0.5">
            <button
                type="button"
                class="rounded-pill px-2.5 py-1 text-[12px] font-semibold transition-colors"
                :class="!props.filters.type ? 'bg-tint-accent-14 text-accent-400' : 'text-text-muted hover:text-text'"
                @click="pickKind(null)"
            >
                Tous
            </button>
            <button
                v-for="kind in props.kinds"
                :key="kind.value"
                type="button"
                class="rounded-pill px-2.5 py-1 text-[12px] font-semibold transition-colors"
                :class="props.filters.type === kind.value ? 'bg-tint-accent-14 text-accent-400' : 'text-text-muted hover:text-text'"
                @click="pickKind(kind.value)"
            >
                {{ kind.label }}
            </button>
        </div>
    </div>
</template>
