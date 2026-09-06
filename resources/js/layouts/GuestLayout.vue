<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLogo from '../components/AppLogo.vue';
import FlashToast from '../components/FlashToast.vue';
import LoginBackground from '../components/LoginBackground.vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
});

const page = usePage();
const pendingGroup = computed(() => page.props.pending_group);
</script>

<template>
    <div
        class="relative flex min-h-dvh flex-wrap items-stretch justify-center overflow-hidden"
        style="
            background:
                radial-gradient(900px 500px at 15% -10%, rgba(61, 255, 122, 0.09), transparent 60%),
                radial-gradient(700px 500px at 110% 110%, rgba(61, 255, 122, 0.06), transparent 60%),
                var(--color-bg);
        "
    >
        <LoginBackground />

        <!-- Colonne de gauche : la promesse du produit. -->
        <section
            class="relative z-1 flex min-w-[300px] flex-[1_1_380px] flex-col justify-center px-[clamp(24px,6vw,80px)] pt-[calc(env(safe-area-inset-top)+2.5rem)] pb-6 lg:py-12"
        >
            <AppLogo size="lg" class="mb-8 lg:mb-10" />
            <h1 class="mb-[18px] max-w-[480px] text-[clamp(34px,5vw,56px)] leading-[1.05] tracking-[-0.03em]">
                Dépose.<br />Partage.<br />
                <span class="text-accent-400 [text-shadow:0_0_32px_rgba(61,255,122,0.35)]">Qualité d'origine.</span>
            </h1>
            <p class="mb-8 max-w-[400px] text-[16px] leading-[1.55] text-text-muted">
                Tes photos et vidéos voyagent sans compression — le fichier que reçoivent tes proches est exactement
                celui que tu as pris.
            </p>
            <div class="flex flex-wrap gap-6 font-mono text-[12px] text-text-muted">
                <span><span class="text-accent-400">4K · RAW · ProRes</span><br />tels quels</span>
                <span><span class="text-accent-400">0 %</span><br />de compression</span>
                <span><span class="text-accent-400">5 Go</span><br />par fichier</span>
            </div>
        </section>

        <!-- Colonne de droite : la carte de formulaire. -->
        <section
            class="relative z-1 flex min-w-[300px] max-w-[560px] flex-[1_1_340px] items-center justify-center px-[clamp(20px,4vw,56px)] pt-2 pb-[calc(env(safe-area-inset-bottom)+2.5rem)] lg:py-12"
        >
            <div
                class="animate-pop w-full max-w-[380px] rounded-lg border border-neutral-800 bg-[rgba(17,22,17,0.85)] p-[clamp(24px,4vw,36px)] shadow-[var(--shadow-lg),0_0_60px_rgba(61,255,122,0.07)] backdrop-blur-[6px]"
            >
                <div v-if="pendingGroup" class="badge badge-accent mb-4">Invitation · {{ pendingGroup }}</div>

                <div class="mb-6">
                    <h2 class="mb-1.5 text-[24px] leading-[1.15]">{{ props.title }}</h2>
                    <p class="text-[14px] text-text-muted">{{ props.subtitle }}</p>
                </div>

                <slot />

                <p v-if="$slots.footer" class="mt-5 text-center text-[13px] text-text-muted">
                    <slot name="footer" />
                </p>
            </div>
        </section>
    </div>

    <!-- Les écrans invités doivent aussi pouvoir dire qu'une session a expiré. -->
    <FlashToast />
</template>
