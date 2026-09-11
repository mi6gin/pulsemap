<script setup>
import { computed } from 'vue';

const props = defineProps({ review: { type: Object, required: true } });

const initials = computed(() => props.review.author
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase());

const dateLabel = computed(() => {
    if (!props.review.published_at) return 'Дата не указана';
    return new Intl.DateTimeFormat('ru-RU', {
        day: 'numeric', month: 'long', year: 'numeric',
    }).format(new Date(props.review.published_at));
});
</script>

<template>
    <article class="review-card">
        <div class="flex gap-4">
            <img v-if="review.author_avatar_url" :src="review.author_avatar_url" alt="" class="size-11 shrink-0 rounded-full object-cover" referrerpolicy="no-referrer">
            <div v-else class="grid size-11 shrink-0 place-items-center rounded-full bg-stone-800 text-xs font-semibold text-lime-300">{{ initials }}</div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="truncate font-medium text-stone-100">{{ review.author }}</h3>
                        <p class="mt-1 text-xs text-stone-500">{{ dateLabel }}</p>
                    </div>
                    <div class="flex gap-0.5" :aria-label="`${review.rating} из 5`">
                        <svg v-for="star in 5" :key="star" class="size-4" viewBox="0 0 24 24" :fill="star <= review.rating ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.7" :class="star <= review.rating ? 'text-amber-300' : 'text-stone-700'"><path d="m12 2.8 2.75 5.57 6.15.9-4.45 4.33 1.05 6.13L12 16.84l-5.5 2.89 1.05-6.13L3.1 9.27l6.15-.9L12 2.8Z"/></svg>
                    </div>
                </div>
                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-stone-300">{{ review.text || 'Автор оставил оценку без текста.' }}</p>
            </div>
        </div>
    </article>
</template>
