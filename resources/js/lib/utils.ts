import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Keep generated/root-relative Laravel routes inside the cPanel subdirectory.
 * The same build still works at the domain root (local development/Vercel).
 */
export function appUrl(url: string): string {
    if (!url.startsWith('/') || url.startsWith('//')) return url;

    const configured = String(import.meta.env.VITE_APP_BASE_PATH ?? '')
        .trim()
        .replace(/\/$/, '');
    const inferred = typeof window !== 'undefined'
        && window.location.pathname.startsWith('/akademik/simatrps')
        ? '/akademik/simatrps'
        : '';
    const base = configured || inferred;

    if (!base || url === base || url.startsWith(`${base}/`)) return url;

    return `${base}${url}`;
}
