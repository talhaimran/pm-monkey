/** Spec 7.0f: one button per provider configured on this install; none when no provider is set up. */
const names: Record<string, string> = { google: 'Google', github: 'GitHub' };

export default function SocialButtons({ providers }: { providers: string[] }) {
    if (providers.length === 0) return null;

    return (
        <div className="mt-6">
            <div className="flex items-center gap-3 text-sm text-muted" aria-hidden="true">
                <span className="h-px flex-1 bg-line" />
                or
                <span className="h-px flex-1 bg-line" />
            </div>
            <div className="mt-4 grid gap-2">
                {providers.map((p) => (
                    // A plain link: the provider's page is outside the app, so this is a full page load.
                    <a
                        key={p}
                        href={route('social.redirect', p)}
                        className="flex h-control-h items-center justify-center rounded-ctl border border-line-2 bg-card px-4 text-md text-text hover:bg-card-2"
                    >
                        Continue with {names[p] ?? p}
                    </a>
                ))}
            </div>
        </div>
    );
}
