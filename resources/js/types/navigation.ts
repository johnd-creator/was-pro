import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: string;
};

export type NavItem = {
    title: string;
    description?: string;
    href?: string;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
    permissions?: string[];
    items?: NavItem[];
    defaultOpen?: boolean;
    labelOnly?: boolean;
};

export type NavItemWithRequiredHref = NavItem & {
    href: string;
    items?: never;
};

export type NavItemWithItems = NavItem & {
    href?: never;
    items: NavItem[];
};
