export interface BrandColors {
    primary: string
    light: string
    dark: string
    secondary: string
    warning: string
    success: string
}

export type ThemeName = 'light' | 'dark' | 'accent'

export const colorThemes: Record<ThemeName, BrandColors> = {
    light: {
        primary: '#ffffff',
        light: '#DEECEF',
        dark: '#5C9EAD',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    },
    dark: {
        primary: '#333333',
        light: '#575252',
        dark: '#ffffff',
        secondary: '#CCCCCC',
        warning: '#F72585',
        success: '#47905D',
    },
    accent: {
        primary: '#E94560',
        light: '#F9F9F9',
        dark: '#1A1A2E',
        secondary: '#3498DB',
        warning: '#0F3460',
        success: '#47905D',
    }
}

export const getThemeColors = (themeName?: ThemeName): BrandColors => {
    if (!themeName || !colorThemes[themeName]) {
        return colorThemes.light
    }
    return colorThemes[themeName]
}
