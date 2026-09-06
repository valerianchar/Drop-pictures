<script setup>
const props = defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col gap-2 text-[14px]">
        <!-- Le champ est enveloppé par son libellé : l'association est implicite. -->
        <label class="block">
            <span class="mb-2 block font-semibold text-text">{{ props.label }}</span>
            <slot />
        </label>
        <p v-if="props.error" class="text-[13px] text-danger-strong">{{ props.error }}</p>
        <p v-else-if="props.hint || $slots.hint" class="hint">
            <slot name="hint">{{ props.hint }}</slot>
        </p>
    </div>
</template>
