<template>
    <div class="gradient-mesh min-h-dvh bg-slate-100 text-slate-900 antialiased transition-colors dark:bg-slate-950 dark:text-slate-100">
        <div class="relative mx-auto flex min-h-dvh w-full max-w-[1800px]">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-950/40 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="sidebarOpen = false"
            ></div>

            <aside
                class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-white/20 bg-white/70 backdrop-blur-xl transition-transform dark:border-slate-700/30 dark:bg-slate-900/70 lg:static lg:z-0"
                :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-20' : 'lg:w-72']"
            >
                <div class="flex items-center justify-between p-4 pb-0">
                    <div class="overflow-hidden" :class="collapsed ? 'lg:hidden' : ''">
                        <p class="truncate font-display text-xs font-semibold uppercase tracking-[0.15em] gradient-text">
                            Envato License MIS
                        </p>
                        <h2 class="truncate font-display text-lg font-semibold text-slate-900 dark:text-slate-50">Admin Portal</h2>
                    </div>
                    <div v-if="collapsed" class="hidden lg:block">
                        <p class="text-center font-display text-lg font-bold gradient-text">M</p>
                    </div>
                </div>

                <nav class="glass-scroll mt-4 flex-1 overflow-y-auto px-3">
                    <div class="grid gap-1">
                        <RouterLink
                            v-for="item in visibleNavigation"
                            :key="item.to"
                            :to="item.to"
                            class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all hover:bg-white/60 hover:backdrop-blur dark:hover:bg-slate-800/60"
                            :class="[
                                route.path.startsWith(item.to)
                                    ? 'bg-gradient-to-r from-violet-500/15 to-indigo-500/15 text-violet-900 shadow-sm shadow-violet-500/5 dark:from-violet-500/20 dark:to-indigo-500/20 dark:text-violet-200'
                                    : 'text-slate-600 dark:text-slate-300',
                                collapsed ? 'lg:justify-center lg:px-2' : '',
                            ]"
                            :title="collapsed ? item.label : undefined"
                            @click="sidebarOpen = false"
                        >
                            <component :is="item.icon" class="h-4 w-4 shrink-0" />
                            <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
                        </RouterLink>
                    </div>
                </nav>

                <div class="border-t border-slate-200/30 p-3 dark:border-slate-700/20">
                    <button
                        type="button"
                        class="hidden w-full items-center justify-center gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-500 transition hover:bg-white/60 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-slate-200 lg:inline-flex"
                        @click="collapsed = !collapsed"
                    >
                        <template v-if="!collapsed">Collapse sidebar</template>
                        <template v-else>+</template>
                    </button>
                    <p v-if="!collapsed" class="type-caption mt-2 text-center text-slate-400 dark:text-slate-500 lg:mt-1">
                        <kbd class="type-caption rounded bg-white/50 px-1 py-0.5 dark:bg-slate-800/50">Ctrl+K</kbd> to search
                    </p>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="sticky top-0 z-20 border-b border-white/20 bg-white/60 px-4 py-2.5 backdrop-blur-xl dark:border-slate-700/30 dark:bg-slate-900/60 lg:px-6"
                >
                    <div class="flex items-center gap-2">
                        <UiButton variant="ghost" size="compact" class="lg:hidden" @click="sidebarOpen = true">
                            Menu
                        </UiButton>

                        <button
                            type="button"
                            class="group relative flex min-w-0 flex-1 items-center gap-2 rounded-xl border border-white/30 bg-white/50 px-3 py-2 text-left text-sm text-slate-500 shadow-sm backdrop-blur transition hover:border-violet-300/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/70 dark:border-slate-700/50 dark:bg-slate-900/50 dark:text-slate-300 dark:hover:border-violet-400/30 lg:max-w-md"
                            aria-label="Open command palette"
                            @click="searchStore.openPalette()"
                        >
                            <Search class="h-4 w-4 shrink-0" />
                            <span class="truncate">Search purchase code, license, domain...</span>
                            <span class="type-caption ml-auto hidden shrink-0 rounded-md bg-white/60 px-1.5 py-0.5 text-slate-500 backdrop-blur dark:bg-slate-800/60 lg:inline">
                                Ctrl+K
                            </span>
                        </button>

                        <div class="ml-auto flex items-center gap-1.5">
                            <UiButton variant="secondary" size="compact" class="hidden xl:inline-flex" @click="goToQuickVerify">Verify</UiButton>
                            <UiButton variant="secondary" size="compact" class="hidden xl:inline-flex" @click="goToCreateItem">New Item</UiButton>
                            <UiButton variant="ghost" size="compact" @click="theme.toggle()">
                                <Moon v-if="theme.isDark.value" class="h-4 w-4" />
                                <Sun v-else class="h-4 w-4" />
                            </UiButton>
                            <UiButton variant="danger" size="compact" @click="logout">Logout</UiButton>
                        </div>
                    </div>
                </header>

                <main class="min-h-0 flex-1 px-4 py-5 lg:px-6 lg:py-6">
                    <BreadcrumbsNav />
                    <RouterView />
                </main>

                <footer class="type-caption border-t border-white/10 px-4 py-3 text-center text-slate-400 dark:border-slate-700/10 dark:text-slate-500 lg:px-6">
                    Envato License MIS &middot; Admin Portal
                </footer>
            </div>
        </div>

        <CommandPalette
            :open="searchStore.open"
            :loading="searchStore.loading"
            :results="searchStore.results"
            @close="searchStore.closePalette()"
            @search="searchStore.search"
            @select="onSearchSelect"
        />
    </div>
</template>

<script setup lang="ts">
import { Moon, Search, Sun } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import BreadcrumbsNav from '@/admin/components/layout/BreadcrumbsNav.vue';
import CommandPalette from '@/admin/components/search/CommandPalette.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import { useTheme } from '@/admin/composables/useTheme';
import { sidebarNavigation } from '@/admin/constants/navigation';
import { usePermissions } from '@/admin/composables/usePermissions';
import { useAuthStore } from '@/admin/stores/auth';
import { useSearchStore } from '@/admin/stores/search';
import { useToastStore } from '@/admin/stores/toast';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const searchStore = useSearchStore();
const toastStore = useToastStore();
const theme = useTheme();
const { can } = usePermissions();

const collapsed = ref(false);
const sidebarOpen = ref(false);

const visibleNavigation = computed(() => sidebarNavigation.filter((item) => can(item.permission)));

const onGlobalShortcut = (event: KeyboardEvent): void => {
    const isSearchShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';

    if (!isSearchShortcut) {
        return;
    }

    event.preventDefault();
    searchStore.openPalette();
};

const onSearchSelect = async (to: string): Promise<void> => {
    searchStore.closePalette();
    await router.push(to);
};

const goToQuickVerify = async (): Promise<void> => {
    await router.push('/admin/checker');
};

const goToCreateItem = async (): Promise<void> => {
    await router.push('/admin/items');
};

const logout = async (): Promise<void> => {
    await authStore.logout();
    toastStore.push({
        tone: 'info',
        title: 'Signed out',
        message: 'Your admin session has ended.',
    });
    await router.push('/admin/login');
};

onMounted(() => {
    window.addEventListener('keydown', onGlobalShortcut);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalShortcut);
});
</script>
