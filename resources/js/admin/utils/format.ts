export const formatDateTime = (value: string | null): string => {
    if (value === null || value === '') {
        return 'N/A';
    }

    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

export const formatDate = (value: string | null): string => {
    if (value === null || value === '') {
        return 'N/A';
    }

    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    }).format(new Date(value));
};

export const formatPercent = (value: number): string => `${value.toFixed(1)}%`;

export const toTitleCase = (value: string): string =>
    value
        .replace(/_/g, ' ')
        .split(' ')
        .map((token) => token.charAt(0).toUpperCase() + token.slice(1))
        .join(' ');
