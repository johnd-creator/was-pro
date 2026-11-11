<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import { dashboard } from '@/routes';

const page = usePage();
const { context, scopedUrl } = useOrganizationContext();
let removeListener: (() => void) | undefined;
onMounted(() => {
    removeListener = router.on('before', (event) => {
        const url = event.detail.visit.url;
        event.detail.visit.url = new URL(
            scopedUrl(url.href),
            window.location.origin,
        );
    });
});
onUnmounted(() => removeListener?.());

function selectOrganization(event: Event): void {
    const id = (event.target as HTMLSelectElement).value;
    if (id) router.get(dashboard(), { organization_id: id });
}
</script>

<template>
    <section
        v-if="context"
        aria-label="Konteks organisasi"
        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-900"
    >
        <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                {{
                    context.is_global
                        ? 'Administrasi: di luar filter UBP operasional'
                        : context.can_select
                          ? 'Organisasi operasional aktif'
                          : 'Organisasi akun Anda'
                }}
            </p>
            <p
                v-if="context.organization"
                class="text-sm font-semibold text-slate-900 dark:text-slate-100"
            >
                {{ context.organization.name }}
                <span class="font-normal text-slate-500"
                    >({{ context.organization.code }})</span
                >
            </p>
            <p
                v-else
                class="text-sm font-semibold text-amber-800 dark:text-amber-200"
            >
                {{
                    context.can_select
                        ? 'Pilih organisasi sebelum membuka data operasional'
                        : 'Organisasi belum ditetapkan pada akun'
                }}
            </p>
            <p
                v-if="context.is_global && context.organization"
                class="text-xs text-slate-500"
            >
                Pilihan di atas berlaku saat kembali ke Limbah / FABA.
            </p>
        </div>
        <label
            v-if="context.can_select && page.component !== 'Dashboard'"
            class="flex max-w-full flex-col gap-1 text-xs text-slate-600 dark:text-slate-300"
        >
            Pilih organisasi operasional
            <select
                :value="context.organization?.id ?? ''"
                class="min-h-11 max-w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                @change="selectOrganization"
            >
                <option value="" disabled>Pilih organisasi</option>
                <option
                    v-for="organization in context.choices"
                    :key="organization.id"
                    :value="organization.id"
                >
                    {{ organization.name }} ({{ organization.code }})
                </option>
            </select>
        </label>
    </section>
</template>
