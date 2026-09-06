<script setup>
import { SwitchRoot, SwitchThumb } from 'reka-ui';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="inline-flex cursor-pointer items-center gap-2 text-[14px]" :class="props.disabled && 'cursor-default'">
        <SwitchRoot
            :model-value="props.modelValue"
            :disabled="props.disabled"
            :aria-label="props.label"
            class="relative h-[22px] w-[38px] shrink-0 rounded-pill transition-[background-color,box-shadow] duration-(--duration-med) ease-(--ease-out) disabled:opacity-45"
            :class="props.modelValue ? 'bg-accent-600 shadow-glow-soft' : 'bg-neutral-700'"
            @update:model-value="$emit('update:modelValue', $event)"
        >
            <SwitchThumb
                class="absolute top-[3px] block size-4 rounded-full transition-[left,background-color] duration-(--duration-med) ease-(--ease-out)"
                :class="props.modelValue ? 'left-[19px] bg-on-accent' : 'left-[3px] bg-neutral-300'"
            />
        </SwitchRoot>
        <span><slot>{{ props.label }}</slot></span>
    </label>
</template>
