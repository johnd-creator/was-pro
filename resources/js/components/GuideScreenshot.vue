<script setup lang="ts">
import { Expand, ImageOff, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { GuideScreenshot } from '@/lib/guide-screenshots';

interface Props {
    screenshot: GuideScreenshot;
    title: string;
}

defineProps<Props>();
const failed = ref(false);
const expandedFailed = ref(false);
</script>

<template>
    <figure class="overflow-hidden rounded-xl border bg-muted/30">
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3 text-xs"
        >
            <span class="font-medium">{{ screenshot.context }}</span>
            <span class="rounded-full border bg-background px-2.5 py-1"
                >Role contoh: {{ screenshot.role }}</span
            >
        </div>
        <div
            v-if="failed"
            class="flex items-center gap-3 p-5 text-sm text-muted-foreground"
            role="status"
        >
            <ImageOff class="size-5 shrink-0" aria-hidden="true" />
            Gambar belum dapat dimuat. Langkah panduan tetap dapat dibaca di
            bawah.
        </div>
        <Dialog v-else>
            <DialogTrigger as-child>
                <button
                    type="button"
                    class="block w-full cursor-zoom-in text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="`Perbesar tangkapan layar: ${title}`"
                    @click="expandedFailed = false"
                >
                    <img
                        :src="screenshot.src"
                        :alt="screenshot.alt"
                        :width="screenshot.width"
                        :height="screenshot.height"
                        loading="lazy"
                        decoding="async"
                        class="h-auto w-full bg-white object-contain"
                        @error="failed = true"
                    />
                    <span
                        class="flex min-h-11 items-center justify-center gap-2 border-t bg-background px-4 py-2 text-sm font-medium"
                    >
                        <Expand class="size-4" aria-hidden="true" /> Perbesar
                        gambar
                    </span>
                </button>
            </DialogTrigger>
            <DialogContent
                :show-close-button="false"
                class="flex max-h-[92dvh] flex-col gap-3 p-3 sm:max-w-[min(96vw,100rem)] sm:p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <DialogHeader class="min-w-0 text-left">
                        <DialogTitle class="text-base leading-snug">{{
                            title
                        }}</DialogTitle>
                        <DialogDescription class="text-xs leading-relaxed"
                            >{{ screenshot.context }} · Role contoh:
                            {{ screenshot.role }}. Data contoh, bukan data
                            operasional organisasi Anda.</DialogDescription
                        >
                    </DialogHeader>
                    <DialogClose as-child>
                        <Button
                            variant="outline"
                            class="min-h-11 shrink-0 gap-2"
                            aria-label="Tutup gambar"
                        >
                            <X class="size-4" aria-hidden="true" /><span
                                class="hidden sm:inline"
                                >Tutup</span
                            >
                        </Button>
                    </DialogClose>
                </div>
                <div
                    class="min-h-0 overflow-auto overscroll-contain rounded-lg border bg-white"
                    tabindex="0"
                    aria-label="Tangkapan layar diperbesar, dapat digulir"
                >
                    <p
                        v-if="expandedFailed"
                        class="p-5 text-sm text-slate-600"
                        role="status"
                    >
                        Gambar belum dapat dimuat. Tutup gambar untuk kembali ke
                        panduan.
                    </p>
                    <img
                        v-else
                        :src="screenshot.src"
                        :alt="screenshot.alt"
                        :width="screenshot.width"
                        :height="screenshot.height"
                        decoding="async"
                        class="h-auto w-full min-w-[60rem]"
                        @error="expandedFailed = true"
                    />
                </div>
                <p class="text-xs leading-relaxed text-muted-foreground">
                    {{ screenshot.caption }} Geser untuk melihat detail. Tekan
                    Escape atau Tutup untuk kembali ke panduan.
                </p>
            </DialogContent>
        </Dialog>
        <figcaption
            class="space-y-1 border-t px-4 py-3 text-sm leading-relaxed"
        >
            <p>{{ screenshot.caption }}</p>
            <p class="text-xs text-muted-foreground">
                Data contoh untuk panduan, bukan data operasional organisasi
                Anda. Tampilan dan tindakan yang tersedia mengikuti hak akses
                akun.
            </p>
        </figcaption>
    </figure>
</template>
