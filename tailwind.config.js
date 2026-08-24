import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
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
                    DEFAULT: '#003B95',
                    light: '#005ADA',
                    dark: '#003B95',
                },
                accent: {
                    DEFAULT: '#A9BD00',
                    light: '#C4D800',
                    dark: '#859500',
                    deep: '#636F00',
                },
                brand: {
                    blue: '#003B95',
                    'blue-bright': '#005ADA',
                    'blue-dark': '#003B95',
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
                navy: {
                    DEFAULT: '#003B95',
                    light: '#005ADA',
                    dark: '#003B95',
                    deep: '#003B95',
                },
            },
        },
    },

    plugins: [forms],
};
