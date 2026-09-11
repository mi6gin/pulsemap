<script setup>
import { computed, ref, watch } from 'vue';
import ReviewCard from './ReviewCard.vue';

const props = defineProps({
    user: { type: Object, required: true },
    organization: { type: Object, default: null },
    reviews: { type: Array, default: () => [] },
    reviewMeta: { type: Object, default: null },
    loading: Boolean,
    reviewsLoading: Boolean,
    error: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['logout', 'save', 'sync', 'page', 'dismiss-error']);
const url = ref('');

watch(() => props.organization?.source_url, (value) => {
    url.value = value || '';
}, { immediate: true });

const syncing = computed(() => ['queued', 'syncing', 'retrying'].includes(props.organization?.status));
const statusInfo = computed(() => ({
    queued: { label: 'В очереди', dot: 'bg-sky-300', text: 'Запрос ждёт свободного воркера' },
    syncing: { label: 'Собираем данные', dot: 'bg-lime-300', text: 'Читаем страницы отзывов и обновляем прогресс' },
    retrying: { label: 'Повторяем', dot: 'bg-amber-300', text: 'Источник временно недоступен — сработает повторная попытка' },
    complete: { label: 'Актуально', dot: 'bg-emerald-300', text: 'Последняя синхронизация завершена' },
    failed: { label: 'Нужна проверка', dot: 'bg-red-300', text: 'Не удалось обновить данные' },
}[props.organization?.status] || { label: 'Не подключено', dot: 'bg-stone-500', text: 'Добавьте ссылку на организацию' }));

const pages = computed(() => {
    if (!props.reviewMeta) return [];
    const current = props.reviewMeta.current_page;
    const last = props.reviewMeta.last_page;
    const start = Math.max(1, Math.min(current - 2, last - 4));
    return Array.from({ length: Math.min(5, last) }, (_, index) => start + index);
});

const latestChange = computed(() => props.organization?.history?.find((snapshot) => snapshot.changes));
const changeLabels = {
    name: 'Название', rating: 'Рейтинг', ratings_count: 'Оценки', reviews_count: 'Отзывы',
};

function formatNumber(value) {
    return new Intl.NumberFormat('ru-RU').format(value ?? 0);
}

function formatDate(value) {
    if (!value) return 'ещё не обновлялась';
    return new Intl.DateTimeFormat('ru-RU', {
        day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
    }).format(new Date(value));
}

function goToPage(page) {
    emit('page', page);
    document.querySelector('#reviews')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <div class="min-h-screen">
        <header class="sticky top-0 z-30 border-b border-white/8 bg-[#0b1110]/85 backdrop-blur-xl">
            <div class="mx-auto flex h-17 max-w-7xl items-center justify-between px-5 lg:px-8">
                <div class="flex items-center gap-3">
                    <div class="grid size-9 place-items-center rounded-xl bg-lime-300 text-[#111713]">
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s6-4.35 6-11a6 6 0 1 0-12 0c0 6.65 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/></svg>
                    </div>
                    <span class="font-semibold tracking-tight text-stone-100">PulseMap</span>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-medium text-stone-200">{{ user.name }}</p>
                        <p class="text-xs text-stone-500">{{ user.email }}</p>
                    </div>
                    <button type="button" class="icon-button" aria-label="Выйти" :disabled="loading" @click="emit('logout')">
                        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 17l5-5-5-5M15 12H3m10-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                    </button>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-7xl px-5 py-8 lg:px-8 lg:py-12">
            <div class="mb-9 flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div>
                    <p class="eyebrow">Настройки источника</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-[-.035em] text-white sm:text-4xl">Репутация на карте</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-400">Добавьте ссылку на карточку — отзывы соберутся в фоне и останутся доступны без повторного обращения к Яндексу.</p>
                </div>
                <div v-if="organization" class="flex items-center gap-2 rounded-full border border-white/8 bg-white/[.035] px-3.5 py-2 text-xs text-stone-400">
                    <span class="size-2 rounded-full" :class="statusInfo.dot"></span>
                    {{ statusInfo.label }} · {{ formatDate(organization.last_synced_at) }}
                </div>
            </div>

            <div v-if="error" class="mb-6 flex items-start justify-between gap-4 rounded-2xl border border-red-300/15 bg-red-400/8 px-4 py-3.5 text-sm text-red-100">
                <div class="flex gap-3">
                    <svg class="mt-0.5 size-4.5 shrink-0 text-red-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/></svg>
                    <span>{{ error }}</span>
                </div>
                <button type="button" class="text-red-300 hover:text-white" @click="emit('dismiss-error')">×</button>
            </div>

            <section class="panel p-5 sm:p-6">
                <form class="flex flex-col gap-3 lg:flex-row" @submit.prevent="emit('save', url)">
                    <label class="min-w-0 flex-1">
                        <span class="sr-only">Ссылка на карточку организации</span>
                        <div class="relative">
                            <svg class="absolute left-4 top-1/2 size-4.5 -translate-y-1/2 text-stone-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            <input v-model="url" type="url" class="field pl-11" placeholder="https://yandex.ru/maps/org/…" required>
                        </div>
                        <span v-if="fieldErrors.url" class="mt-2 block text-xs text-red-300">{{ fieldErrors.url[0] }}</span>
                    </label>
                    <button type="submit" class="primary-button min-w-44" :disabled="loading || syncing">
                        <span>{{ organization ? 'Сохранить и обновить' : 'Подключить карточку' }}</span>
                        <span v-if="loading" class="size-4 animate-spin rounded-full border-2 border-[#111713]/30 border-t-[#111713]"></span>
                    </button>
                </form>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-stone-500">
                    <span>Поддерживаются полные и короткие ссылки Яндекс.Карт.</span>
                    <button v-if="organization && !syncing" type="button" class="inline-flex items-center gap-2 text-stone-300 transition hover:text-lime-300" :disabled="loading" @click="emit('sync')">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12a8 8 0 1 1-2.34-5.66L20 8.68M20 4v4.68h-4.68"/></svg>
                        Обновить сейчас
                    </button>
                </div>
            </section>

            <section v-if="organization && syncing" class="mt-6 panel overflow-hidden p-6">
                <div class="flex items-start justify-between gap-5">
                    <div>
                        <div class="flex items-center gap-2.5 text-sm font-medium text-stone-100">
                            <span class="size-2 animate-pulse rounded-full" :class="statusInfo.dot"></span>
                            {{ statusInfo.label }}
                        </div>
                        <p class="mt-2 text-sm text-stone-500">{{ statusInfo.text }}</p>
                    </div>
                    <span class="font-mono text-sm text-lime-300">{{ organization.progress }}%</span>
                </div>
                <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-stone-800">
                    <div class="h-full rounded-full bg-lime-300 transition-all duration-700" :style="{ width: `${organization.progress}%` }"></div>
                </div>
                <p v-if="organization.status === 'retrying' && organization.sync_error" class="mt-4 text-xs leading-5 text-amber-200/75">{{ organization.sync_error }}</p>
            </section>

            <section v-if="organization?.status === 'failed'" class="mt-6 rounded-2xl border border-red-300/15 bg-red-400/6 p-5">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="font-medium text-red-100">Источник не удалось обновить</p>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-red-200/65">{{ organization.sync_error }}</p>
                    </div>
                    <button type="button" class="secondary-button shrink-0" :disabled="loading" @click="emit('sync')">Повторить</button>
                </div>
            </section>

            <template v-if="organization?.name">
                <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="stat-card sm:col-span-2 xl:col-span-1">
                        <p class="stat-label">Организация</p>
                        <p class="mt-4 line-clamp-2 text-lg font-medium leading-6 text-stone-100">{{ organization.name }}</p>
                        <a v-if="organization.canonical_url" :href="organization.canonical_url" target="_blank" rel="noreferrer" class="mt-4 inline-flex items-center gap-1.5 text-xs text-lime-300 hover:text-lime-200">
                            Открыть на карте
                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3h7v7M10 14 21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                        </a>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Средний рейтинг</p>
                        <div class="mt-4 flex items-end gap-2">
                            <span class="text-4xl font-semibold tracking-[-.05em] text-white">{{ organization.rating?.toFixed(1) || '—' }}</span>
                            <svg class="mb-1.5 size-5 text-amber-300" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2.8 2.75 5.57 6.15.9-4.45 4.33 1.05 6.13L12 16.84l-5.5 2.89 1.05-6.13L3.1 9.27l6.15-.9L12 2.8Z"/></svg>
                        </div>
                        <p class="mt-3 text-xs text-stone-500">из 5 возможных</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Оценок</p>
                        <p class="mt-4 text-4xl font-semibold tracking-[-.05em] text-white">{{ formatNumber(organization.ratings_count) }}</p>
                        <p class="mt-3 text-xs text-stone-500">включая оценки без текста</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Отзывов</p>
                        <p class="mt-4 text-4xl font-semibold tracking-[-.05em] text-white">{{ formatNumber(organization.reviews_count) }}</p>
                        <p class="mt-3 text-xs text-stone-500">собрано {{ formatNumber(organization.stored_reviews_count) }}</p>
                    </div>
                </section>

                <section v-if="latestChange" class="mt-6 panel p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="eyebrow">Последние изменения</p>
                            <p class="mt-2 text-sm text-stone-400">{{ formatDate(latestChange.captured_at) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="(change, key) in latestChange.changes" :key="key" class="rounded-full border border-white/8 bg-white/[.035] px-3 py-1.5 text-xs text-stone-300">
                                {{ changeLabels[key] }}: <span class="text-stone-500">{{ change.from }}</span> → <span class="text-lime-300">{{ change.to }}</span>
                            </span>
                        </div>
                    </div>
                </section>

                <section id="reviews" class="scroll-mt-24 pt-12">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <p class="eyebrow">Лента обратной связи</p>
                            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-white">Отзывы</h2>
                        </div>
                        <p v-if="reviewMeta" class="text-xs text-stone-500">Страница {{ reviewMeta.current_page }} из {{ reviewMeta.last_page }} · по 50 отзывов</p>
                    </div>

                    <div v-if="reviewsLoading" class="mt-6 grid gap-3">
                        <div v-for="item in 3" :key="item" class="h-40 animate-pulse rounded-2xl border border-white/6 bg-white/[.025]"></div>
                    </div>
                    <div v-else-if="reviews.length" class="mt-6 grid gap-3">
                        <ReviewCard v-for="review in reviews" :key="review.id" :review="review" />
                    </div>
                    <div v-else class="mt-6 panel py-14 text-center">
                        <div class="mx-auto grid size-11 place-items-center rounded-full bg-stone-800 text-stone-500">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z"/></svg>
                        </div>
                        <p class="mt-4 text-sm text-stone-300">У карточки пока нет текстовых отзывов.</p>
                    </div>

                    <nav v-if="reviewMeta?.last_page > 1" class="mt-7 flex items-center justify-center gap-2" aria-label="Пагинация отзывов">
                        <button type="button" class="page-button" :disabled="reviewMeta.current_page === 1 || reviewsLoading" @click="goToPage(reviewMeta.current_page - 1)">←</button>
                        <button v-for="page in pages" :key="page" type="button" class="page-button" :class="{ active: page === reviewMeta.current_page }" :disabled="reviewsLoading" @click="goToPage(page)">{{ page }}</button>
                        <button type="button" class="page-button" :disabled="reviewMeta.current_page === reviewMeta.last_page || reviewsLoading" @click="goToPage(reviewMeta.current_page + 1)">→</button>
                    </nav>
                </section>
            </template>

            <section v-else-if="!organization" class="mt-6 overflow-hidden rounded-3xl border border-dashed border-white/12 bg-white/[.018] px-6 py-16 text-center">
                <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-lime-300/10 text-lime-300">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s6-4.35 6-11a6 6 0 1 0-12 0c0 6.65 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/></svg>
                </div>
                <h2 class="mt-5 text-lg font-medium text-stone-100">Начните с одной карточки</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-stone-500">Вставьте ссылку выше. Сбор пройдёт в фоне, поэтому страницу можно не держать открытой.</p>
            </section>
        </div>
    </div>
</template>
