import { defineStore } from 'pinia';

import { searchDemoData } from '@/admin/services/demoData';
import type { GlobalSearchGroupedResult } from '@/admin/types/api';

const emptyResults = (): GlobalSearchGroupedResult => ({
    purchases: [],
    licenses: [],
    domains: [],
    items: [],
});

export const useSearchStore = defineStore('searchStore', {
    state: () => ({
        open: false,
        loading: false,
        query: '',
        results: emptyResults() as GlobalSearchGroupedResult,
    }),
    actions: {
        openPalette(): void {
            this.open = true;
        },

        closePalette(): void {
            this.open = false;
            this.query = '';
            this.results = emptyResults();
        },

        async search(value: string): Promise<void> {
            this.query = value;

            if (value.trim() === '') {
                this.results = emptyResults();
                return;
            }

            this.loading = true;

            try {
                this.results = searchDemoData(value);
            } finally {
                this.loading = false;
            }
        },
    },
});
