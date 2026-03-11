import { defineConfig } from "vitepress";

export default defineConfig({
    title: "Filexus",
    description: "Production-ready Laravel file attachment system",
    base: "/filexus/",

    themeConfig: {
        logo: "/filexus.png",

        nav: [
            { text: "Guide", link: "/getting-started" },
            { text: "API Reference", link: "/api/trait-methods" },
            { text: "GitHub", link: "https://github.com/filexus/filexus" },
        ],

        sidebar: [
            {
                text: "Getting Started",
                items: [
                    { text: "Introduction", link: "/getting-started" },
                    { text: "Installation", link: "/installation" },
                    { text: "Quick Start", link: "/quick-start" },
                ],
            },
            {
                text: "Configuration",
                items: [
                    {
                        text: "Global Configuration",
                        link: "/configuration/global",
                    },
                    {
                        text: "Primary Key Types",
                        link: "/configuration/primary-keys",
                    },
                    {
                        text: "Per-Model Configuration",
                        link: "/configuration/per-model",
                    },
                    {
                        text: "Custom Path Generator",
                        link: "/configuration/custom-path",
                    },
                ],
            },
            {
                text: "Usage",
                items: [
                    { text: "Basic Operations", link: "/usage/basic" },
                    { text: "Collections", link: "/usage/collections" },
                    { text: "File Metadata", link: "/usage/metadata" },
                    { text: "File Expiration", link: "/usage/expiration" },
                    { text: "File Pruning", link: "/usage/pruning" },
                ],
            },
            {
                text: "Advanced",
                items: [
                    { text: "Events", link: "/advanced/events" },
                    { text: "File Manager", link: "/advanced/manager" },
                    { text: "Query Scopes", link: "/advanced/scopes" },
                    {
                        text: "File Deduplication",
                        link: "/advanced/deduplication",
                    },
                ],
            },
            {
                text: "API Reference",
                items: [
                    { text: "HasFiles Trait", link: "/api/trait-methods" },
                    { text: "File Model", link: "/api/file-model" },
                    { text: "FilexusManager", link: "/api/manager" },
                    { text: "Events", link: "/api/events" },
                    { text: "Exceptions", link: "/api/exceptions" },
                ],
            },
        ],

        socialLinks: [
            { icon: "github", link: "https://github.com/filexus/filexus" },
        ],

        editLink: {
            pattern: "https://github.com/filexus/filexus/edit/main/docs/:path",
            text: "Edit this page on GitHub",
        },

        footer: {
            message: "Released under the MIT License.",
            copyright: "Copyright © 2024-present Filexus",
        },

        search: {
            provider: "local",
        },
    },
});
