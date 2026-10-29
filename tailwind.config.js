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
                    black: '#0F0F0F',
                    charcoal: '#1C1C1C',
                    white: '#FFFFFF',
                    alabaster: '#FAF9F6',
                    stone: '#F2F0EA',
                    border: '#E2DFD7',
                    muted: '#6E6B64',
                    gold: {
                        DEFAULT: '#B8923A',
                        hover: '#9E7B2B',
                        light: '#D8BE7E',
                        tint: '#F9F5EB',
                    },
                    danger: '#991B1B',
                    success: '#166534',
                    warning: '#92400E',
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

