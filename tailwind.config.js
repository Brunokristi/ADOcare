/** @type {import('tailwindcss').Config} */

export default {
    theme: {
        extend: {
            colors: {
                darkgrey: "var(--c-dark-grey)",
                white: "var(--c-white)",
                almostwhite: "var(--c-almost-white)",
                lightgrey: "var(--c-light-grey)",
                accent: "var(--c-accent)",
                warning: "var(--c-warning)",
                success: "var(--c-success)",
                tag1: "var(--c-tag-1)",
                tag2: "var(--c-tag-2)",
                tag3: "var(--c-tag-3)",
            },
            fontFamily: {
                sans: "var(--font-sans)",
                accent: "var(--font-accent)",
            },
            fontSize: {
                'heading-accent': [
                    'var(--ts-heading-accent-size)',
                    {
                        lineHeight: 'var(--ts-heading-accent-line)',
                        fontWeight: 'var(--ts-heading-accent-weight)',
                        fontFamily: 'var(--ts-heading-accent-font)',
                    },
                ],
                'heading': [
                    'var(--ts-heading-size)',
                    {
                        lineHeight: 'var(--ts-heading-line)',
                        fontWeight: 'var(--ts-heading-weight)',
                        fontFamily: 'var(--ts-heading-font)',
                    },
                ],
                'normal': [
                    'var(--ts-normal-size)',
                    {
                        lineHeight: 'var(--ts-normal-line)',
                        fontWeight: 'var(--ts-normal-weight)',
                    },
                ],
                'mini': [
                    'var(--ts-mini-size)',
                    {
                        lineHeight: 'var(--ts-mini-line)',
                        fontWeight: 'var(--ts-mini-weight)',
                    },
                ],
            },
            borderRadius: {
                sm: "var(--radius-sm)",
                md: "var(--radius-md)",
            },
            boxShadow: {
                custom: "var(--shadow)",
            },
            spacing: {
                xs: "var(--space-1)",
                sm: "var(--space-2)",
                md: "var(--space-4)",
                lg: "var(--space-6)",
                xl: "var(--space-8)",
            },
            zIndex: {
                dropdown: "var(--z-dropdown)",
                sticky: "var(--z-sticky)",
                modal: "var(--z-modal)",
                tooltip: "var(--z-tooltip)",
            },
        },
    },
}
