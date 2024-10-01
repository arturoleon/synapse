import { defineConfig } from 'vitepress';

// https://vitepress.dev/reference/site-config
export default defineConfig({
    title: 'Laravel-Synapse',
    description: "AI agents for all!",
    lastUpdated: true,
    themeConfig: {
        // https://vitepress.dev/reference/default-theme-config
        nav: [
            { text: 'Home', link: '/' },
            {
                text: '4.1.1',
                items: [
                    {
                        text: 'Changelog',
                        link: 'https://github.com/pdphilip/laravel-elasticsearch/compare/v4.1.0...v4.1.1',
                    },
                ],
            },
        ],

        sidebar: [
            {
                text: 'Introduction',
                items: [{ text: 'Getting Started', link: '/' }],
            },
            {
                text: 'Eloquent',
                items: [
                    { text: 'The Base model', link: '/eloquent/the-base-model' },
                    { text: 'Querying Models', link: '/eloquent/querying-models' },
                    { text: 'Saving Models', link: '/eloquent/saving-models' },
                    { text: 'Deleting Models', link: '/eloquent/deleting-models' },
                    {
                        text: 'Ordering & Pagination',
                        link: '/eloquent/ordering-and-pagination',
                    },
                    { text: 'Distinct & GroupBy', link: '/eloquent/distinct' },
                    { text: 'Aggregation', link: '/eloquent/aggregation' },
                    { text: 'ES Specific Queries', link: '/eloquent/es-specific' },
                    { text: 'Nested Queries', link: '/eloquent/nested-queries' },
                    { text: 'Full-Text Search', link: '/eloquent/full-text-search' },
                    { text: 'Dynamic Indices', link: '/eloquent/dynamic-indices' },
                ],
            }
        ],

        socialLinks: [
            {
                icon: 'github',
                link: 'https://github.com/use-the-fork/laravel-synapse',
            },
        ],
    },
});