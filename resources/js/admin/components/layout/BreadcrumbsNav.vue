<template>
    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
            <li v-for="(crumb, index) in breadcrumbs" :key="crumb.to" class="inline-flex items-center gap-1.5">
                <RouterLink v-if="index < breadcrumbs.length - 1" :to="crumb.to" class="rounded-md px-1.5 py-0.5 transition hover:bg-white/60 hover:text-slate-700 dark:hover:bg-slate-800/60 dark:hover:text-slate-200">
                    {{ crumb.label }}
                </RouterLink>
                <span v-else class="rounded-md px-1.5 py-0.5 font-semibold gradient-text">{{ crumb.label }}</span>
                <span v-if="index < breadcrumbs.length - 1" aria-hidden="true" class="text-slate-300 dark:text-slate-600">/</span>
            </li>
        </ol>
    </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

interface Breadcrumb {
    label: string;
    to: string;
}

const route = useRoute();

const breadcrumbs = computed<Breadcrumb[]>(() => {
    const segments = route.path.split('/').filter(Boolean);
    const values: Breadcrumb[] = [
        {
            label: 'Admin',
            to: '/admin/dashboard',
        },
    ];

    if (segments.length <= 1) {
        return values;
    }

    let runningPath = '';

    segments.forEach((segment) => {
        runningPath += `/${segment}`;

        if (segment === 'admin') {
            return;
        }

        const label = segment
            .replace(/-/g, ' ')
            .split(' ')
            .map((token) => token.charAt(0).toUpperCase() + token.slice(1))
            .join(' ');

        values.push({
            label,
            to: runningPath,
        });
    });

    return values;
});
</script>
