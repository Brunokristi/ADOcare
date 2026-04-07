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
                autocomplete: {
                    border: 0,
                },
            }
        }
    }
});

const baseField =
    'rounded-md! h-7! ' +
    'border-darkgrey! ' +
    'text-normal! text-darkgrey! ' +
    'outline-none! ring-0! shadow-none! ' +
    'placeholder:text-lightgrey! ' +
    'focus:outline-none! focus:ring-0! focus:shadow-none!';

const baseNoOutline =
    'outline-none! ring-0! shadow-none! ' +
    'focus:outline-none! focus:ring-0! focus:shadow-none!';

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
            table: { class: 'w-full border-collapse border-0!' },
            bodyrow: { class: '[&.p-row-odd]:bg-almostwhite! [&.p-row-odd:hover]:bg-lightgrey! hover:bg-lightgrey!' },
            tbody: { class: 'overflow-auto' }
        },

        column: {
            headercell: { class: 'bg-darkgrey! text-white! text-heading! px-4! py-2! border-r! border-r-white! last:border-r-0!' },
            sortIcon: { class: 'text-white! flex! items-center! justify-center!' },
            columnTitle: { class: 'text-white! text-heading!' },
            pcheadercheckbox: {
                box: { class: 'bg-darkgrey! border-white!' },
                root: { class: 'shadow-none! hover:shadow-none!' },
            },
            pcrowcheckbox: {
                box: { class: 'border-darkgrey!' },
                root: { class: 'shadow-none! hover:shadow-none!' },
            },
            bodycell: {
                class: 'text-normal/5! text-darkgrey! px-4! py-1! border-0! border-r! border-r-white! last:border-r-0!',
            },
        },

        /* ---------- INPUTS / TEXTAREA ---------- */

        inputtext: {
            root: {
                class: `${baseField} bg-white! rounded-md! h-7! border-darkgrey! text-normal! text-darkgrey! [&.p-disabled]:!opacity-50 [&.p-disabled]:!cursor-not-allowed [&.p-disabled]:!bg-lightgrey!`
            },
        },

        textarea: {
            root: { class: 'bg-white! rounded-md! border-darkgrey! text-normal! text-darkgrey! outline-none! ring-0! shadow-none! focus:outline-none! focus:ring-0! focus:shadow-none!' },
        },

        button: {
            root: { class: `p-xs! rounded-md! ${baseNoOutline}` },
        },

        menubar: {
            root: { class: 'border-0! px-0! rounded-none! content-center!' },
        },

        menu: {
            root: { class: 'border-0! rounded-none!' },
            item: { class: 'hover:bg-transparent! focus:bg-transparent! rounded-md! bg-transparent!' },
            itemContent: { class: 'hover:bg-transparent! focus:bg-transparent! rounded-md! bg-transparent!' },
        },

        dialog: {
            header: { class: 'text-darkgrey! text-heading-accent!' },
            root: { class: 'rounded-md!' },
        },

        toast: {
            root: { class: 'rounded-md!' },

            message: ({ props }: any) => ({
                class: [
                    'rounded-md!',
                    'border-l-4',
                    props?.message?.severity === 'success' && 'bg-success!',
                    props?.message?.severity === 'error' && 'bg-danger!',
                    props?.message?.severity === 'warn' && 'bg-danger!',
                    props?.message?.severity === 'info' && 'bg-darkgrey!',
                ],
            }),

            messageContent: { class: 'text-white! rounded-md! px-3 py-2' },
            messageIcon: { class: 'hidden!' },
            detail: { class: 'text-white!' },
        },


        /* ---------- SELECT ---------- */

        select: {
            root: ({ props }) => ({
                class: `
            ${baseField}
            flex! items-center! gap-1!
            ${props.disabled ? 'bg-transparent! opacity-50!' : ''}
        `
            }),
            dropdown: { class: `${baseNoOutline} border-0! bg-transparent! px-1!` },
            dropDownIcon: { class: 'text-sm! text-darkgrey!' },
            label: { class: 'text-normal! text-darkgrey! truncate!' },
            overlay: { class: 'rounded-md! border-0!' },
            header: { class: 'p-2!' },
            listContainer: { class: 'p-2! gap-2!' },
            option: { class: 'rounded-md! hover:bg-almostwhite! text-normal! p-2! bg-white!' },
            clearIcon: { class: 'text-sm! text-darkgrey!' },
        },


        autocomplete: {
            root: {
                class: 'w-full flex flex-col items-stretch p-0 border-0! bg-transparent!'
            },

            input: {
                class: [
                    'w-full h-full border-0! bg-transparent!',
                    'text-normal! text-darkgrey!',
                    'outline-none! ring-0! shadow-none!',
                    'focus:outline-none! focus:ring-0! focus:shadow-none!'
                ].join(' ')
            },

            inputMultiple: {
                class: [
                    'w-full',
                    'rounded-md! border-0! bg-white!',
                    'px-2! py-2!',
                    'flex! flex-wrap! items-start! content-center! gap-1!',
                    'min-h-[2.5rem] max-h-40 overflow-y-auto',
                    'text-normal! text-darkgrey! outline-none! ring-0! shadow-none!',
                    'focus:outline-none! focus:ring-0! focus:shadow-none!'
                ].join(' ')
            },

            overlay: {
                class: 'rounded-md! p-2! w-auto border-0!'
            },

            option: {
                class: 'rounded-md! hover:bg-almostwhite! text-normal! p-2! bg-white! h-auto! !text-normal'
            },

            dropDown: { class: 'hidden!' },

        },


        datepicker: {
            root: { class: `${baseField} flex! items-center!` },
            input: {
                class: 'w-full! h-full! border-0! bg-transparent! ' +
                    'text-normal! text-darkgrey! ' +
                    'outline-none! ring-0! shadow-none! ' +
                    'focus:outline-none! focus:ring-0! focus:shadow-none!',
            },
            panel: { class: 'rounded-md! text-normal! text-darkgrey! border-0!' },
            selectMonth: { class: 'bg-darkgrey! text-normal! text-white! hover:bg-accent! rounded-md!' },
            selectYear: { class: 'bg-darkgrey! text-normal! text-white! hover:bg-accent! rounded-md!' },
            day: {
                class: [
                    'text-normal! rounded-md! hover:bg-tag3!',
                    '[&.p-datepicker-day-selected]:bg-accent! [&.p-datepicker-day-selected]:text-white!',
                    '[&.p-datepicker-day-selected-range]:bg-accent! [&.p-datepicker-day-selected-range]:text-white!'
                ].join(' ')
            },
            weekDay: { class: 'text-heading! text-darkgrey!' },
            month: { class: 'text-normal! text-darkgrey! [&.p-datepicker-month-selected]:bg-accent! [&.p-datepicker-month-selected]:text-white!' },
        },

        paginator: {
            root: { class: 'border-0! rounded-none! text-mini! p-0! bg-darkgrey! h-7! align-middle!' },
            prevIcon: { class: 'bi bi-chevron!-left text-white!' },
            prev: { class: 'p-1! rounded-md! hover:bg-tag2! text-white! h-6!' },
            nextIcon: { class: 'bi bi-chevron-right! text-white!' },
            next: { class: 'p-1! rounded-md! hover:bg-tag2! text-white! h-6!' },
            firstIcon: { class: 'bi bi-chevron-bar-left! text-white!' },
            first: { class: 'p-1! rounded-md! hover:bg-tag2! text-white! h-6!' },
            lastIcon: { class: 'bi bi-chevron-bar-right! text-white!' },
            last: { class: 'p-1! rounded-md! hover:bg-tag2! text-white! h-6!' },
            page: { class: 'p-1! rounded-md! hover:bg-tag2! text-white! h-6!' },
        },

        checkbox: {
            root: {
                class: `!shadow-none hover:!shadow-none ${baseNoOutline}`,
            },
            box: {
                class: `
            !border-darkgrey
            data-[p-checked=true]:!bg-accent
            data-[p-checked=true]:!border-accent
            `,
            },
            icon: {
                class: '!text-darkgrey !fill-darkgrey',
            },
            input: { class: '!bg-darkgrey' },
        },

        radiobutton: {
            root: {
                class: `!shadow-none hover:!shadow-none ${baseNoOutline}`,
                style: {
                    '--p-radiobutton-icon-checked-color': '#2c3e50',
                    '--p-radiobutton-icon-checked-hover-color': '#2c3e50'
                }
            },
            box: {
                class: `
            !border-darkgrey
            !border-2
            data-[p-checked=true]:!bg-accent
            data-[p-checked=true]:!border-accent
            !bg-transparent
            `,
            },
            icon: {
                class: '!text-darkgrey !fill-darkgrey',
            },
            input: { class: '!bg-darkgrey' },
        },

        carousel: {
            root: { class: 'rounded-md! gap-2! align-items-center!' },
            item: { class: 'bg-white! rounded-md! w-fit! flex-none! gap-2! align-items-center!' },
            viewport: {
                class: 'gap-2! align-items-center!'
            },
            content: { class: 'gap-2! align-items-center!' },
            contentContainer: { class: 'gap-2! align-items-center!' },
        },

        tooltip: {
            root: { class: '!rounded-md !bg-darkgrey !text-white !text-mini !p-1' },
            text: { class: '!text-white !text-mini' },
        },

        tabs: {
            root: {
                class: '!border-0 !p-0'
            },

            navContainer: {
                class: '!border-0'
            },

            nav: {
                class: '!border-0 !gap-1 !p-0'
            },

            navItem: {
                class: '!m-0'
            },

            navLink: {
                class: '!rounded-md !text-darkgrey !px-0 !py-0 !bg-transparent !border-0'
            },

            navLinkActive: {
                class: '!bg-accent !text-white'
            },

            panels: {
                class: '!border-0 !px-0 !py-2'
            }
        },

        TabPanels: {
            root: {
                class: '!border-0 !px-0 !py-2'
            }
        },
    }
} as PrimeVueConfiguration;
