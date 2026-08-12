/** @type {import('tailwindcss').Config} */
export default {
    // Toggled by hand, not by the OS setting alone: the switch in the header
    // writes the choice to localStorage and puts .dark on <html>. The OS
    // preference is still the default, applied by partials/theme.blade.php
    // before first paint.
    //
    // Note the views themselves carry almost no dark: variants — the neutral
    // palette is remapped wholesale in that same partial. This setting is
    // what makes dark: available for anything written from here on.
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                primary: '#4f46e5',
            },
            fontFamily: {
                sans: ['Plus Jakarta Sans', 'sans-serif'],
                mono: ['Roboto Mono', 'monospace'],
            },
        },
    },
    plugins: [],
};
