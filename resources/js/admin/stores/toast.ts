import { defineStore } from 'pinia';

export interface ToastMessage {
    id: number;
    title: string;
    message: string;
    tone: 'success' | 'error' | 'info';
}

export const useToastStore = defineStore('admin-toast', {
    state: () => ({
        messages: [] as ToastMessage[],
        sequence: 0,
    }),
    actions: {
        push(message: Omit<ToastMessage, 'id'>): void {
            this.sequence += 1;
            const toast: ToastMessage = {
                id: this.sequence,
                ...message,
            };

            this.messages.push(toast);

            window.setTimeout(() => {
                this.remove(toast.id);
            }, 3800);
        },

        remove(id: number): void {
            this.messages = this.messages.filter((message) => message.id !== id);
        },
    },
});
