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
