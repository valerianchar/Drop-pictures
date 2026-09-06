<script setup>
/**
 * La marque s'écrit en plain type — « drop*picture », astérisque en accent.
 * Aucun logo n'existe : ne pas en inventer.
 */
const props = defineProps({
    size: { type: String, default: 'md' },
});
</script>

<template>
    <span
        class="font-heading font-bold tracking-tight text-text"
        :class="props.size === 'lg' ? 'text-[20px]' : 'text-[18px]'"
    >drop<span class="text-accent-400">*</span>picture</span>
</template>
