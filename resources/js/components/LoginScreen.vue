<script setup>
import { reactive } from 'vue';

defineProps({
    loading: Boolean,
    error: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['submit']);
const form = reactive({
    email: 'demo@example.com',
    password: 'password',
});
</script>

<template>
    <section class="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-12">
        <div class="ambient ambient-one"></div>
        <div class="ambient ambient-two"></div>

        <div class="relative z-10 grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/10 bg-black/25 shadow-2xl shadow-black/40 backdrop-blur-xl lg:grid-cols-[1.08fr_.92fr]">
            <div class="hidden min-h-[650px] flex-col justify-between bg-lime-300 p-12 text-[#111713] lg:flex">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 place-items-center rounded-xl bg-[#111713] text-lime-300">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s6-4.35 6-11a6 6 0 1 0-12 0c0 6.65 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/></svg>
                    </div>
                    <span class="text-lg font-semibold tracking-tight">PulseMap</span>
                </div>

                <div class="max-w-md">
                    <p class="mb-5 text-xs font-semibold uppercase tracking-[.22em] text-[#111713]/55">Reputation workspace</p>
                    <h1 class="text-5xl font-semibold leading-[1.04] tracking-[-.05em]">Отзывы говорят.<br>Мы помогаем услышать.</h1>
                    <p class="mt-6 max-w-sm text-base leading-7 text-[#111713]/65">Подключите карточку организации и следите за рейтингом, оценками и отзывами в одном спокойном интерфейсе.</p>
                </div>

                <div class="flex items-center gap-3 text-sm text-[#111713]/55">
                    <span class="inline-flex -space-x-2">
                        <span class="size-8 rounded-full border-2 border-lime-300 bg-[#e56f51]"></span>
                        <span class="size-8 rounded-full border-2 border-lime-300 bg-[#6d7de8]"></span>
                        <span class="size-8 rounded-full border-2 border-lime-300 bg-[#111713]"></span>
                    </span>
                    <span>Данные без лишнего шума</span>
                </div>
            </div>

            <div class="flex min-h-[650px] items-center px-7 py-12 sm:px-12">
                <div class="mx-auto w-full max-w-sm">
                    <div class="mb-10 flex items-center gap-3 lg:hidden">
                        <div class="grid size-10 place-items-center rounded-xl bg-lime-300 text-[#111713]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s6-4.35 6-11a6 6 0 1 0-12 0c0 6.65 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/></svg>
                        </div>
                        <span class="text-lg font-semibold text-stone-100">PulseMap</span>
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-[.2em] text-lime-300">Добро пожаловать</p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white">Войдите в аккаунт</h2>
                    <p class="mt-3 text-sm leading-6 text-stone-400">Демо-доступ уже заполнен — просто нажмите кнопку.</p>

                    <div v-if="error" class="mt-6 rounded-xl border border-red-300/20 bg-red-400/10 px-4 py-3 text-sm text-red-200">{{ error }}</div>

                    <form class="mt-8 flex flex-col gap-5" @submit.prevent="emit('submit', { ...form })">
                        <label class="flex flex-col gap-2 text-sm font-medium text-stone-300">
                            Email
                            <input v-model="form.email" type="email" autocomplete="email" class="field" placeholder="name@company.ru">
                            <span v-if="fieldErrors.email" class="text-xs text-red-300">{{ fieldErrors.email[0] }}</span>
                        </label>

                        <label class="flex flex-col gap-2 text-sm font-medium text-stone-300">
                            Пароль
                            <input v-model="form.password" type="password" autocomplete="current-password" class="field" placeholder="••••••••">
                            <span v-if="fieldErrors.password" class="text-xs text-red-300">{{ fieldErrors.password[0] }}</span>
                        </label>

                        <button type="submit" class="primary-button mt-2" :disabled="loading">
                            <span>{{ loading ? 'Входим…' : 'Войти' }}</span>
                            <svg v-if="!loading" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            <span v-else class="size-4 animate-spin rounded-full border-2 border-[#111713]/30 border-t-[#111713]"></span>
                        </button>
                    </form>

                    <p class="mt-8 text-center text-xs text-stone-500">Один защищённый сид-пользователь · Laravel Sanctum</p>
                </div>
            </div>
        </div>
    </section>
</template>
