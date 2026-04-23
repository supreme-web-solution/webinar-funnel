import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { Component } from 'vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** Iconify icon name string (e.g. "heroicons:home") or a Vue component */
    icon?: string | Component;
    isActive?: boolean;
    badge?: string | number;
};
