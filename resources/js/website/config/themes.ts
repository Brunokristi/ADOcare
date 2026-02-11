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
        primary: '#5C9EAD',
        light: '#333333',
        dark: '#5C9EAD',
        secondary: '#5C9EAD',
        warning: '#F72585',
        success: '#ffffff',
    }
}

export const getThemeColors = (themeName?: ThemeName): BrandColors => {
    if (!themeName || !colorThemes[themeName]) {
        return colorThemes.light
    }
    return colorThemes[themeName]
}
