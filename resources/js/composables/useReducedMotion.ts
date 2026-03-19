import { tryOnMounted, tryOnUnmounted } from '@vueuse/core';
import { ref, computed } from 'vue';

export function useReducedMotion() {
    const prefersReducedMotion = ref(false);

    const updateReducedMotion = () => {
        prefersReducedMotion.value = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
    };

    tryOnMounted(() => {
        updateReducedMotion();

        const mediaQuery = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        mediaQuery.addEventListener('change', updateReducedMotion);

        tryOnUnmounted(() => {
            mediaQuery.removeEventListener('change', updateReducedMotion);
        });
    });

    const animationDuration = computed(() =>
        prefersReducedMotion.value ? '0.01ms' : '200ms',
    );

    const transitionDuration = computed(() =>
        prefersReducedMotion.value ? '0.01ms' : '150ms',
    );

    return {
        prefersReducedMotion,
        animationDuration,
        transitionDuration,
    };
}
