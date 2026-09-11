<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { api, csrf } from './api';
import DashboardScreen from './components/DashboardScreen.vue';
import LoginScreen from './components/LoginScreen.vue';

const booting = ref(true);
const user = ref(null);
const organization = ref(null);
const reviews = ref([]);
const reviewMeta = ref(null);
const loading = ref(false);
const reviewsLoading = ref(false);
const error = ref('');
const fieldErrors = ref({});
let pollTimer = null;

const isSyncing = (status) => ['queued', 'syncing', 'retrying'].includes(status);

function stopPolling() {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

function startPolling() {
    stopPolling();
    pollTimer = window.setInterval(() => refreshOrganization(true), 2000);
}

async function refreshOrganization(silent = false) {
    const previousStatus = organization.value?.status;

    try {
        const payload = await api('/api/organization');
        organization.value = payload.data;

        if (organization.value && isSyncing(organization.value.status)) {
            startPolling();
        } else {
            stopPolling();
        }

        if (organization.value?.status === 'complete' && previousStatus !== 'complete') {
            await loadReviews(1);
        }
    } catch (requestError) {
        if (!silent) {
            error.value = requestError.message;
        }
    }
}

async function loadReviews(page = 1) {
    reviewsLoading.value = true;

    try {
        const payload = await api(`/api/organization/reviews?page=${page}`);
        reviews.value = payload.data;
        reviewMeta.value = payload.meta;
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        reviewsLoading.value = false;
    }
}

async function bootstrap() {
    try {
        const payload = await api('/api/me');
        user.value = payload.data;
        await refreshOrganization();

        if (organization.value?.status === 'complete') {
            await loadReviews(1);
        }
    } catch (requestError) {
        if (requestError.status !== 401) {
            error.value = requestError.message;
        }
    } finally {
        booting.value = false;
    }
}

async function login(credentials) {
    loading.value = true;
    error.value = '';
    fieldErrors.value = {};

    try {
        await csrf();
        const payload = await api('/login', { method: 'POST', body: credentials });
        user.value = payload.data;
        await refreshOrganization();
        if (organization.value?.status === 'complete') {
            await loadReviews(1);
        }
    } catch (requestError) {
        error.value = requestError.message;
        fieldErrors.value = requestError.errors || {};
    } finally {
        loading.value = false;
    }
}

async function logout() {
    loading.value = true;
    error.value = '';

    try {
        await csrf();
        await api('/logout', { method: 'POST' });
        stopPolling();
        user.value = null;
        organization.value = null;
        reviews.value = [];
        reviewMeta.value = null;
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
}

async function saveOrganization(url) {
    loading.value = true;
    error.value = '';
    fieldErrors.value = {};

    try {
        await csrf();
        const payload = await api('/api/organization', {
            method: 'PUT',
            body: { url },
        });
        organization.value = payload.data;
        reviews.value = [];
        reviewMeta.value = null;
        startPolling();
    } catch (requestError) {
        error.value = requestError.message;
        fieldErrors.value = requestError.errors || {};
    } finally {
        loading.value = false;
    }
}

async function syncOrganization() {
    loading.value = true;
    error.value = '';

    try {
        await csrf();
        const payload = await api('/api/organization/sync', { method: 'POST' });
        organization.value = payload.data;
        startPolling();
    } catch (requestError) {
        error.value = requestError.message;
    } finally {
        loading.value = false;
    }
}

onMounted(bootstrap);
onBeforeUnmount(stopPolling);
</script>

<template>
    <main class="min-h-screen">
        <div v-if="booting" class="flex min-h-screen items-center justify-center">
            <div class="flex items-center gap-3 text-sm text-stone-400">
                <span class="size-2 animate-pulse rounded-full bg-lime-300"></span>
                Загружаем рабочее пространство…
            </div>
        </div>

        <LoginScreen
            v-else-if="!user"
            :loading="loading"
            :error="error"
            :field-errors="fieldErrors"
            @submit="login"
        />

        <DashboardScreen
            v-else
            :user="user"
            :organization="organization"
            :reviews="reviews"
            :review-meta="reviewMeta"
            :loading="loading"
            :reviews-loading="reviewsLoading"
            :error="error"
            :field-errors="fieldErrors"
            @logout="logout"
            @save="saveOrganization"
            @sync="syncOrganization"
            @page="loadReviews"
            @dismiss-error="error = ''"
        />
    </main>
</template>
