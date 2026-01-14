<script setup>
/**
 * Login Page Component
 *
 * Handles user authentication via email/password or Microsoft Teams SSO.
 * Uses Vuetify components for consistent Material Design UI.
 * Currently uses dummy authentication for UI development.
 */
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

// Props definition
defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
        default: '',
    },
});

// Reactive state
const email = ref('');
const password = ref('');
const rememberMe = ref(false);
const isLoading = ref(false);
const isPasswordVisible = ref(false);

// Form validation rules
const emailRules = [
    (v) => !!v || 'Email is required',
    (v) => /.+@.+\..+/.test(v) || 'Please enter a valid email',
];

const passwordRules = [
    (v) => !!v || 'Password is required',
    (v) => v.length >= 6 || 'Password must be at least 6 characters',
];

// Computed properties
const isFormValid = computed(() => {
    return email.value.length > 0 && password.value.length >= 6;
});

// Methods
const handleSubmit = () => {
    if (!isFormValid.value) return;

    // Dummy login - redirect to dashboard
    isLoading.value = true;
    setTimeout(() => {
        router.visit('/dashboard');
    }, 800);
};

const handleMicrosoftLogin = () => {
    // Dummy Microsoft Teams SSO login
    isLoading.value = true;
    setTimeout(() => {
        router.visit('/dashboard');
    }, 800);
};
</script>

<template>

    <Head title="Sign In" />

    <v-app theme="darkTheme">
        <v-main class="d-flex align-center justify-center" style="min-height: 100vh;">
            <v-container class="pa-4" fluid>
                <v-row align="center" justify="center">
                    <v-col cols="12" sm="10" md="6" lg="5" xl="4">
                        <!-- Login Card -->
                        <v-card class="mx-auto" max-width="440" rounded="xl" elevation="12" color="surface">
                            <v-card-text class="pa-8 pa-sm-10">
                                <!-- Header -->
                                <div class="text-center mb-8">
                                    <v-icon color="primary" size="48" class="mb-4">mdi-view-dashboard-outline</v-icon>
                                    <h1 class="text-h4 font-weight-bold mb-2">
                                        Sign In
                                    </h1>
                                    <p class="text-body-1 text-medium-emphasis">
                                        Enter your credentials to continue
                                    </p>
                                </div>

                                <!-- Status Alert -->
                                <v-alert v-if="status" type="success" variant="tonal" class="mb-6" rounded="lg"
                                    closable>
                                    {{ status }}
                                </v-alert>

                                <!-- Login Form -->
                                <v-form @submit.prevent="handleSubmit" validate-on="blur">
                                    <!-- Email Field -->
                                    <v-text-field v-model="email" label="Email Address" type="email" name="email"
                                        autocomplete="email" prepend-inner-icon="mdi-email-outline"
                                        placeholder="name@example.com" variant="outlined" density="comfortable"
                                        rounded="lg" :rules="emailRules" :disabled="isLoading" autofocus class="mb-4"
                                        color="primary" bg-color="surface-variant" />

                                    <!-- Password Field -->
                                    <v-text-field v-model="password" label="Password"
                                        :type="isPasswordVisible ? 'text' : 'password'" name="password"
                                        autocomplete="current-password" prepend-inner-icon="mdi-lock-outline"
                                        :append-inner-icon="isPasswordVisible ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                                        placeholder="Enter your password" variant="outlined" density="comfortable"
                                        rounded="lg" :rules="passwordRules" :disabled="isLoading"
                                        @click:append-inner="isPasswordVisible = !isPasswordVisible" class="mb-4"
                                        color="primary" bg-color="surface-variant" />

                                    <!-- Submit Button -->
                                    <v-btn type="submit" color="primary" size="x-large" block rounded="lg"
                                        :loading="isLoading" :disabled="!isFormValid || isLoading"
                                        class="mb-6 text-none font-weight-medium" elevation="2">
                                        Sign In
                                    </v-btn>

                                    <!-- Divider -->
                                    <div class="d-flex align-center my-6">
                                        <v-divider class="flex-grow-1" />
                                        <span class="mx-4 text-medium-emphasis text-body-2 text-no-wrap">or continue with</span>
                                        <v-divider class="flex-grow-1" />
                                    </div>

                                    <!-- Microsoft Teams SSO Button -->
                                    <v-btn variant="outlined" size="x-large" block rounded="lg" :loading="isLoading"
                                        :disabled="isLoading" class="text-none font-weight-medium"
                                        @click="handleMicrosoftLogin">
                                        <template #prepend>
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" class="mr-2">
                                                <path d="M20.625 6.547h-5.187v5.25h5.812v-4.61a.64.64 0 00-.625-.64z"
                                                    fill="#5059C9" />
                                                <path
                                                    d="M15.438 6.547v5.25h-6.75v7.828a.64.64 0 00.625.64h6.062a.64.64 0 00.625-.64V7.187a.64.64 0 00-.562-.64z"
                                                    fill="#7B83EB" />
                                                <circle cx="18.094" cy="4.5" r="2.25" fill="#5059C9" />
                                                <circle cx="12.375" cy="3.75" r="3" fill="#7B83EB" />
                                                <path
                                                    d="M8.063 8.297H2.625a.64.64 0 00-.625.64v7.126a4.125 4.125 0 004.125 4.125h.375a4.125 4.125 0 004.125-4.125V8.938a.64.64 0 00-.562-.64z"
                                                    fill="#4B53BC" />
                                                <path d="M6.563 11.297v4.5" stroke="white" stroke-width="1.5"
                                                    stroke-linecap="round" />
                                                <path d="M4.313 13.547h4.5" stroke="white" stroke-width="1.5"
                                                    stroke-linecap="round" />
                                            </svg>
                                        </template>
                                        Sign in with Microsoft Teams
                                    </v-btn>
                                </v-form>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
/* Button letter spacing */
:deep(.v-btn) {
    letter-spacing: normal !important;
}

:deep(.v-btn--loading .v-btn__content) {
    opacity: 0;
}
</style>
