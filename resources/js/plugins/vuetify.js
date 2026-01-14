/**
 * Vuetify Plugin Configuration
 * 
 * Configures Vuetify with custom theme and default settings.
 */
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

// Light theme configuration
const lightTheme = {
    dark: false,
    colors: {
        primary: '#4F46E5',      // Indigo-600
        secondary: '#7C3AED',    // Purple-600
        accent: '#06B6D4',       // Cyan-500
        error: '#EF4444',        // Red-500
        warning: '#F59E0B',      // Amber-500
        info: '#3B82F6',         // Blue-500
        success: '#10B981',      // Emerald-500
        background: '#F9FAFB',   // Gray-50
        surface: '#FFFFFF',      // White
    },
};

// Dark theme configuration for Dashboard
const darkTheme = {
    dark: true,
    colors: {
        primary: '#6366F1',      // Indigo-500
        secondary: '#8B5CF6',    // Purple-500
        accent: '#06B6D4',       // Cyan-500
        error: '#EF4444',        // Red-500
        warning: '#F59E0B',      // Amber-500
        info: '#3B82F6',         // Blue-500
        success: '#10B981',      // Emerald-500
        background: '#0F172A',   // Slate-900
        surface: '#1E293B',      // Slate-800
        'surface-variant': '#334155', // Slate-700
        'on-background': '#E2E8F0',   // Slate-200
        'on-surface': '#E2E8F0',      // Slate-200
        'on-surface-variant': '#94A3B8', // Slate-400
    },
};

const vuetify = createVuetify({
    components,
    directives,
    theme: {
        defaultTheme: 'darkTheme',
        themes: {
            lightTheme,
            darkTheme,
        },
    },
    typography: {
        fontFamily: "'Source Sans 3', sans-serif",
    },
    defaults: {
        VBtn: {
            variant: 'flat',
            rounded: 'lg',
        },
        VTextField: {
            variant: 'outlined',
            density: 'comfortable',
            rounded: 'lg',
        },
        VCard: {
            rounded: 'xl',
            elevation: 2,
        },
        VCheckbox: {
            color: 'primary',
        },
    },
});

export default vuetify;
