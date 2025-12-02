import Material from '@primeuix/themes/material';

import type { PrimeVueConfiguration } from 'primevue/config';
import { definePreset } from '@primeuix/themes';

const ADOPreset = definePreset(Material, {
    semantic: {
        colorScheme: {
            light: {
                formFieldPadding: {
                    y: '0.2rem',
                    x: '0.5rem',
                },
                listOptionPadding: "0.2rem 0.5rem",

            }
        }
    }
});


export default {
    theme: {
        preset: ADOPreset,
        options: {
            prefix: 'p',
            darkModeSelector: '.dark-theme',
            cssLayer: false

        },
    },

    pt: {
        datatable: {
            root: { class: 'rounded-md overflow-hidden' },
            table: { class: 'w-full border-collapse !border-0' },
            bodyrow: { class: '[&.p-row-odd]:!bg-almostwhite [&.p-row-odd:hover]:!bg-lightgrey hover:!bg-lightgrey' },
        },

        column: {
            headercell: { class: '!bg-darkgrey !text-white !text-heading px-4 py-2 !border-r !border-r-white last:!border-r-0' },
            sortIcon: { class: '!text-white !flex !items-center !justify-center' },
            columnTitle: { class: '!text-white !text-heading' },
            pcheadercheckbox: {
                box: { class: ' !bg-darkgrey !border-white' },
                root: {
                    class: '!shadow-none hover:!shadow-none'
                },
            },
            pcrowcheckbox: {
                box: {
                    class: '!border-darkgrey ',
                },
                root: {
                    class: '!shadow-none hover:!shadow-none'
                },
            },
            bodycell: {
                class: '!text-normal/5 !text-darkgrey !px-4 !border-0 !border-r !border-r-white last:!border-r-0',
            },

        },

        inputtext: {
            root: { class: '!border-darkgrey !text-darkgrey !outline-0 !p-xs !rounded-md' },
        },

        textarea: {
            root: { class: '!border-darkgrey !text-darkgrey !outline-0 !p-xs !rounded-md' },
        },

        button: {
            root: { class: '!p-xs !rounded-md' }
        },

        menubar: {
            root: { class: '!border-0 !px-0 !rounded-none !content-center' },
        },

        menu: {
            root: { class: '!border-0 !rounded-none' },
            item: { class: 'hover:!bg-transparent focus:!bg-transparent !rounded-md !bg-transparent' },
            itemContent: { class: 'hover:!bg-transparent focus:!bg-transparent !rounded-md !bg-transparent' },
        },

        dialog: {
            header: { class: '!text-darkgrey !text-heading-accent' },
            root: { class: '!rounded-md' },
        },

        dropdown: {
            root: { class: '!rounded-md !border-darkgrey !outline-0' },
            dropDownIcon: { class: '!fill-darkgrey' },
            overlay: { class: '!rounded-md' },
            header: { class: '!p-2' },
            listContainer: { class: '!p-2 !gap-2' },
            option: { class: '!rounded-md hover:!bg-almostwhite !text-normal !p-2 !bg-white' }
        },

        select: {
            option: { class: 'focus:!bg-transparent' }

        },

        toast: {
            root: { class: '!bg-transparent !rounded-md' },
            message: { class: '!bg-transparent !rounded-md' },
            messageContent: { class: '!bg-warning !text-white !rounded-md' },
            messageIcon: { class: '!hidden' },
            detail: { class: '!text-white' },
        }



    }
} as PrimeVueConfiguration;
