<script setup lang="ts">
import { AlertCircle, FileX, Inbox, Search } from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface Props {
    type?: 'no-data' | 'no-results' | 'error' | 'coming-soon';
    title?: string;
    description?: string;
    actionLabel?: string;
    actionHref?: string;
    icon?: LucideIcon;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    type: 'no-data',
    size: 'md',
});

const defaultConfig = computed(() => {
    switch (props.type) {
        case 'no-results':
            return {
                icon: Search,
                title: props.title || 'Tidak ada hasil ditemukan',
                description:
                    props.description ||
                    'Coba ubah kata kunci atau filter pencarian Anda.',
            };
        case 'error':
            return {
                icon: AlertCircle,
                title: props.title || 'Terjadi kesalahan',
                description:
                    props.description ||
                    'Terjadi masalah saat memuat data. Silakan coba lagi.',
            };
        case 'coming-soon':
            return {
                icon: Inbox,
                title: props.title || 'Segera hadir',
                description:
                    props.description ||
                    'Fitur ini sedang dalam pengembangan dan akan segera tersedia.',
            };
        default:
            return {
                icon: FileX,
                title: props.title || 'Tidak ada data',
                description:
                    props.description ||
                    'Belum ada data untuk ditampilkan. Mulai dengan menambahkan data baru.',
            };
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return {
                icon: 'size-8',
                spacing: 'p-4',
            };
        case 'lg':
            return {
                icon: 'size-16',
                spacing: 'p-12',
            };
        default:
            return {
                icon: 'size-12',
                spacing: 'p-8',
            };
    }
});

const displayIcon = computed(() => props.icon || defaultConfig.value.icon);
</script>

<template>
    <Card class="border-dashed">
        <CardContent
            :class="[
                'flex flex-col items-center justify-center text-center',
                sizeClasses.spacing,
            ]"
        >
            <component
                :is="displayIcon"
                :class="['text-muted-foreground/50', sizeClasses.icon]"
                aria-hidden="true"
            />
            <div class="mt-4 max-w-sm space-y-2">
                <h3 class="font-semibold text-foreground">
                    {{ defaultConfig.title }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{ defaultConfig.description }}
                </p>
            </div>
            <Button
                v-if="actionLabel && actionHref"
                variant="outline"
                class="mt-6 min-h-[44px]"
                as-child
            >
                <a :href="actionHref">
                    {{ actionLabel }}
                </a>
            </Button>
        </CardContent>
    </Card>
</template>
