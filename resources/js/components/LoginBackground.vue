<script setup>
/**
 * Fond animé de l'écran de connexion : trois orbes vertes floues qui dérivent
 * et six tuiles-photos qui montent lentement. Purement décoratif, masqué aux
 * lecteurs d'écran, figé si l'utilisateur préfère moins de mouvement.
 */
const orbs = [
    { size: 420, left: '-8%', top: '-15%', animation: 'orb-a 18s ease-in-out infinite', opacity: 0.16 },
    { size: 360, left: '70%', top: '55%', animation: 'orb-b 24s ease-in-out infinite', opacity: 0.12 },
    { size: 260, left: '35%', top: '70%', animation: 'orb-a 30s ease-in-out infinite', opacity: 0.09 },
];

const tiles = [
    { left: '6%', width: 46, duration: 22, delay: 0, tilt: -8, peak: 0.35 },
    { left: '16%', width: 30, duration: 28, delay: 6, tilt: 5, peak: 0.25 },
    { left: '30%', width: 54, duration: 24, delay: 12, tilt: -4, peak: 0.3 },
    { left: '52%', width: 34, duration: 30, delay: 3, tilt: 9, peak: 0.2 },
    { left: '68%', width: 48, duration: 26, delay: 15, tilt: -6, peak: 0.3 },
    { left: '84%', width: 38, duration: 32, delay: 9, tilt: 4, peak: 0.25 },
];
</script>

<template>
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div
            v-for="(orb, index) in orbs"
            :key="`orb-${index}`"
            class="absolute rounded-full blur-[40px]"
            :style="{
                left: orb.left,
                top: orb.top,
                width: `${orb.size}px`,
                height: `${orb.size}px`,
                background: `radial-gradient(circle, rgba(61,255,122,${orb.opacity}), transparent 70%)`,
                animation: orb.animation,
            }"
        />
        <div
            v-for="(tile, index) in tiles"
            :key="`tile-${index}`"
            class="absolute bottom-0 rounded-sm border border-[rgba(61,255,122,0.5)] shadow-[0_0_18px_rgba(61,255,122,0.15)]"
            :style="{
                left: tile.left,
                width: `${tile.width}px`,
                height: `${tile.width * 0.75}px`,
                background: 'linear-gradient(160deg, rgba(61,255,122,0.14), rgba(61,255,122,0.03))',
                '--tilt': `${tile.tilt}deg`,
                '--peak': tile.peak,
                animation: `rise ${tile.duration}s linear ${-tile.delay}s infinite`,
            }"
        />
    </div>
</template>
