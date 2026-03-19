<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle,
    Clock,
    FileText,
    Truck,
    User,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import EmptyState from '@/components/dashboard/EmptyState.vue';

interface Activity {
    id: string;
    type: 'waste_record' | 'transportation';
    action:
        | 'created'
        | 'submitted'
        | 'approved'
        | 'rejected'
        | 'dispatched'
        | 'delivered';
    description: string;
    user_name: string;
    created_at: string;
    link?: string;
}

interface Props {
    activities: Activity[];
}

const props = defineProps<Props>();

const isEmpty = computed(() => props.activities.length === 0);

function getActivityIcon(action: Activity['action']) {
    const icons = {
        created: FileText,
        submitted: Clock,
        approved: CheckCircle,
        rejected: XCircle,
        dispatched: Truck,
        delivered: CheckCircle,
    };

    return icons[action] || FileText;
}

function getActivityColor(action: Activity['action']) {
    const colors = {
        created:
            'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400',
        submitted:
            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
        approved:
            'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400',
        rejected: 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400',
        dispatched:
            'bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-400',
        delivered:
            'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400',
    };

    return colors[action] || colors.created;
}

function getActivityCtaLabel(activity: Activity): string {
    return activity.type === 'transportation'
        ? 'Lihat transportasi'
        : 'Lihat catatan';
}

function formatTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) {
        return 'Baru saja';
    }

    if (diffMins < 60) {
        return `${diffMins} menit lalu`;
    }

    if (diffHours < 24) {
        return `${diffHours} jam lalu`;
    }

    if (diffDays < 7) {
        return `${diffDays} hari lalu`;
    }

    return date.toLocaleDateString('id-ID');
}
</script>

<template>
    <div v-if="isEmpty" class="space-y-4">
        <EmptyState
            type="no-data"
            title="Belum ada aktivitas"
            description="Aktivitas pencatatan dan transportasi terbaru akan muncul di sini."
            size="sm"
        />
    </div>
    <div v-else class="space-y-4">
        <div
            v-for="(activity, index) in activities"
            :key="activity.id"
            class="flex gap-4"
        >
            <div class="flex flex-col items-center">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-full"
                    :class="getActivityColor(activity.action)"
                >
                    <component
                        :is="getActivityIcon(activity.action)"
                        class="h-4 w-4"
                        :aria-label="`${activity.action} icon`"
                    />
                </div>
                <div
                    v-if="index !== activities.length - 1"
                    class="w-0.5 flex-1 bg-border"
                    aria-hidden="true"
                ></div>
            </div>

            <div
                class="flex-1 rounded-xl border border-transparent pb-4 transition-colors hover:border-border/70 hover:bg-muted/20"
            >
                <div class="space-y-3 rounded-xl px-3 py-2">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-foreground">
                            {{ activity.description }}
                        </p>
                        <div
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1">
                                <User class="h-3 w-3" aria-hidden="true" />
                                {{ activity.user_name }}
                            </span>
                            <span aria-hidden="true">•</span>
                            <span>{{ formatTime(activity.created_at) }}</span>
                        </div>
                    </div>

                    <div v-if="activity.link">
                        <Link
                            :href="activity.link"
                            class="inline-flex items-center gap-2 text-sm font-medium text-primary transition-colors hover:text-primary/80"
                        >
                            {{ getActivityCtaLabel(activity) }}
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
