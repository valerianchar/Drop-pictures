<script setup>
import MediaCard from './MediaCard.vue';

const props = defineProps({
    media: { type: Array, required: true },
    action: { type: String, default: 'share' },
});

const emit = defineEmits(['open', 'share']);
</script>

<template>
    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-[repeat(auto-fill,minmax(190px,1fr))] sm:gap-3.5">
        <MediaCard
            v-for="item in props.media"
            :key="item.id"
            :media="item"
            :action="props.action"
            @open="emit('open', $event)"
            @share="emit('share', $event)"
        />
    </div>
</template>
