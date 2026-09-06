<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import { LogOut, Settings } from '@lucide/vue';
import { routes } from '../routes';

const page = usePage();
const user = computed(() => page.props.auth.user);

function logout() {
    router.post(routes.logout);
}
</script>

<template>
    <DropdownMenuRoot>
        <DropdownMenuTrigger
            class="inline-flex size-[34px] cursor-pointer items-center justify-center rounded-full border-none bg-accent-900 font-heading text-[13px] font-semibold text-accent-300 transition-colors hover:bg-accent-800"
            :aria-label="`Compte de ${user.name}`"
        >
            {{ user.initials }}
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent
                align="end"
                :side-offset="8"
                class="animate-pop z-30 min-w-[220px] rounded-md border border-neutral-700 bg-surface-2 p-1.5 shadow-lg outline-none"
            >
                <div class="px-2.5 py-2">
                    <p class="text-[14px] font-semibold">{{ user.name }}</p>
                    <p class="truncate font-mono text-[11px] text-text-muted">{{ user.email }}</p>
                </div>
                <DropdownMenuItem
                    v-if="user.is_admin"
                    class="flex cursor-pointer items-center gap-2 rounded-sm px-2.5 py-2 text-[14px] outline-none data-[highlighted]:bg-tint-accent-8 data-[highlighted]:text-accent-300"
                    @select="router.visit(routes.settings)"
                >
                    <Settings class="size-4" />
                    Réglages
                </DropdownMenuItem>
                <DropdownMenuItem
                    class="flex cursor-pointer items-center gap-2 rounded-sm px-2.5 py-2 text-[14px] outline-none data-[highlighted]:bg-tint-accent-8 data-[highlighted]:text-accent-300"
                    @select="logout"
                >
                    <LogOut class="size-4" />
                    Se déconnecter
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
