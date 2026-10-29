/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                pos: {
                    black: 'rgb(var(--pos-black) / <alpha-value>)',
                    charcoal: 'rgb(var(--pos-charcoal) / <alpha-value>)',
                    ink: 'rgb(var(--pos-ink) / <alpha-value>)',
                    white: 'rgb(var(--pos-white) / <alpha-value>)',
                    alabaster: 'rgb(var(--pos-alabaster) / <alpha-value>)',
                    stone: 'rgb(var(--pos-stone) / <alpha-value>)',
                    border: 'rgb(var(--pos-border) / <alpha-value>)',
                    muted: 'rgb(var(--pos-muted) / <alpha-value>)',
                    gold: {
                        DEFAULT: 'rgb(var(--pos-gold) / <alpha-value>)',
                        hover: 'rgb(var(--pos-gold-hover) / <alpha-value>)',
                        light: 'rgb(var(--pos-gold-light) / <alpha-value>)',
                        tint: 'rgb(var(--pos-gold-tint) / <alpha-value>)',
                    },
                    danger: '#B91C1C',
                    success: '#15803D',
                    warning: '#B45309',
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                mono: ['JetBrains Mono', 'ui-monospace', 'SFMono-Regular', 'monospace'],
            },
        },
    },
    plugins: [],
};
