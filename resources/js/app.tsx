import '@fontsource/urbanist/400.css';
import '@fontsource/urbanist/500.css';
import '@fontsource/urbanist/600.css';
import '@fontsource/urbanist/700.css';
import '@fontsource/urbanist/800.css';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { MotionConfig } from 'motion/react';
import { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// @routes renders Ziggy's defaults once per full page load. After an Inertia visit into another workspace (switcher,
// sign-up, accepting an invitation) route() must fill {workspace} from the new page, so every page sets it before rendering.
declare const Ziggy: { defaults: Record<string, unknown> };
type PageProps = { workspace?: { slug: string } | null };
const wrapped = new Map<string, ComponentType<PageProps>>();

function withWorkspaceDefault(name: string, Page: ComponentType<PageProps>) {
    if (!wrapped.has(name)) {
        const WithWorkspace = (props: PageProps) => {
            if (props.workspace) Ziggy.defaults.workspace = props.workspace.slug;
            else delete Ziggy.defaults.workspace;

            return <Page {...props} />;
        };
        wrapped.set(name, WithWorkspace);
    }

    return wrapped.get(name)!;
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent<{ default: ComponentType<PageProps> }>(
            `./Pages/${name}.tsx`,
            import.meta.glob<{ default: ComponentType<PageProps> }>('./Pages/**/*.tsx'),
        );

        return withWorkspaceDefault(name, page.default);
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <MotionConfig reducedMotion="user">
                <App {...props} />
            </MotionConfig>,
        );
    },
    progress: {
        color: 'var(--primary)',
    },
});
