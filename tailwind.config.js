import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Brand ramp notes
 * ----------------
 * `navy` and `primary` are the same ramp under two names — both were already in
 * wide use across the views, so they stay aliased rather than forcing a rename.
 * What changed is that the ramp is now a real ramp: `dark` and `deep` used to be
 * literal duplicates of `DEFAULT` (#003B95), which made every
 * `from-navy to-primary` gradient render as a flat block and gave hover states
 * nothing to darken into.
 *
 * `accent` (#A9BD00) is the brand olive. It is an accent in the strict sense —
 * active marks, focus rings, the logo lockup — and should not be used as a
 * fourth semantic colour alongside emerald/amber/rose.
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                heading: ['Montserrat', 'sans-serif'],
                subheading: ['"Open Sans"', 'sans-serif'],
                body: ['"Open Sans"', 'sans-serif'],
                sans: ['"Open Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#EEF3FB',
                    100: '#D6E2F4',
                    200: '#ADC5E9',
                    300: '#7FA3DA',
                    400: '#3F72C4',
                    500: '#005ADA',
                    600: '#0049B0',
                    700: '#003B95',
                    800: '#002F76',
                    900: '#002250',
                    950: '#001733',
                    DEFAULT: '#003B95',
                    light: '#005ADA',
                    dark: '#002F76',
                    deep: '#002250',
                },
                navy: {
                    50: '#EEF3FB',
                    100: '#D6E2F4',
                    200: '#ADC5E9',
                    300: '#7FA3DA',
                    400: '#3F72C4',
                    500: '#005ADA',
                    600: '#0049B0',
                    700: '#003B95',
                    800: '#002F76',
                    900: '#002250',
                    950: '#001733',
                    DEFAULT: '#003B95',
                    light: '#005ADA',
                    dark: '#002F76',
                    deep: '#002250',
                },
                accent: {
                    50: '#FAFCE8',
                    100: '#F1F7C2',
                    200: '#E3EF85',
                    300: '#CFE13C',
                    400: '#C4D800',
                    500: '#A9BD00',
                    600: '#859500',
                    700: '#636F00',
                    800: '#424B00',
                    900: '#242A00',
                    DEFAULT: '#A9BD00',
                    light: '#C4D800',
                    dark: '#859500',
                    deep: '#636F00',
                },
                brand: {
                    blue: '#003B95',
                    'blue-bright': '#005ADA',
                    'blue-dark': '#002F76',
                    green: '#A9BD00',
                    'green-dark': '#859500',
                    'green-deep': '#636F00',
                    'green-darker': '#424B00',
                    'green-deepest': '#242A00',
                    red: '#D51C00',
                    'red-dark': '#951000',
                    'red-deep': '#5A0600',
                },
                dark: '#1E293B',
            },
        },
    },

    plugins: [forms],
};
