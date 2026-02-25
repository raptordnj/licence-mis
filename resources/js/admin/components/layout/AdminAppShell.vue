<template>
    <div class="min-h-dvh bg-slate-100 text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100">
        <div class="relative mx-auto flex min-h-dvh w-full max-w-[1800px]">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-950/45 lg:hidden"
                aria-hidden="true"
                @click="sidebarOpen = false"
            ></div>

            <aside
                class="fixed inset-y-0 left-0 z-40 w-72 border-r border-slate-200 bg-white p-4 transition-transform dark:border-slate-800 dark:bg-slate-900 lg:static lg:z-0"
                :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-24' : 'lg:w-72']"
            >
                <div class="flex items-center justify-between">
                    <div class="overflow-hidden">
                        <p class="truncate text-xs font-semibold uppercase tracking-[0.15em] text-cyan-600 dark:text-cyan-400">
                            Envato License MIS
                        </p>
                        <h2 class="truncate text-lg font-semibold text-slate-900 dark:text-slate-50">{{ collapsed ? 'MIS' : 'Admin Portal' }}</h2>
                    </div>
                    <UiButton variant="ghost" class="hidden lg:inline-flex" @click="collapsed = !collapsed">
                        {{ collapsed ? 'Expand' : 'Collapse' }}
                    </UiButton>
                </div>

                <nav class="mt-5 grid gap-1.5">
                    <RouterLink
                        v-for="item in visibleNavigation"
                        :key="item.to"
                        :to="item.to"
                        class="group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-slate-100 dark:hover:bg-slate-800"
                        :class="
                            route.path.startsWith(item.to)
                                ? 'bg-cyan-100 text-cyan-900 dark:bg-cyan-500/20 dark:text-cyan-200'
                                : 'text-slate-600 dark:text-slate-300'
                        "
                        @click="sidebarOpen = false"
                    >
                        <component :is="item.icon" class="h-4 w-4 shrink-0" />
                        <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
                    </RouterLink>
                </nav>

                <div class="mt-5 border-t border-slate-200 pt-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <p class="font-semibold uppercase tracking-wide">Tip</p>
                    <p class="mt-1">Press <kbd class="rounded bg-slate-200 px-1 py-0.5 dark:bg-slate-800">Ctrl + K</kbd> to search quickly.</p>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90 lg:px-6"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <UiButton variant="secondary" class="lg:hidden" @click="sidebarOpen = true">
                            Menu
                        </UiButton>

                        <button
                            type="button"
                            class="group relative flex min-w-[220px] flex-1 items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-left text-sm text-slate-500 transition hover:border-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:hover:border-slate-500"
                            aria-label="Open command palette"
                            @click="searchStore.openPalette()"
                        >
                            <Search class="h-4 w-4" />
                            <span class="truncate">Search purchase code, license, domain, item...</span>
                            <span class="ml-auto hidden rounded-md bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500 dark:bg-slate-800 lg:inline">
                                Ctrl+K
                            </span>
                        </button>

                        <UiButton variant="secondary" @click="goToQuickVerify">Manual Verify</UiButton>
                        <UiButton variant="secondary" @click="goToCreateItem">Create Item</UiButton>
                        <UiButton variant="ghost" @click="theme.toggle()">
                            <Moon v-if="theme.isDark.value" class="h-4 w-4" />
                            <Sun v-else class="h-4 w-4" />
                        </UiButton>
                        <UiButton variant="danger" @click="logout">Logout</UiButton>
                    </div>
                </header>

                <main class="min-h-0 flex-1 px-4 py-5 lg:px-6">
                    <BreadcrumbsNav />
                    <RouterView />
                </main>
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
