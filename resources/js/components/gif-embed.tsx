import { Play, Square } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useMediaQuery } from '@/hooks/use-media-query';
import { isGifPlaying, type GifPlayback } from '@/lib/gifs';
import { hostname } from '@/lib/urls';
import type { Embed, LinkPreview } from '@/types';

export function GifEmbed({
    preview,
    embed = null,
    isStatic = false,
}: {
    preview: LinkPreview;
    embed?: Embed | null;
    isStatic?: boolean;
}) {
    const container = useRef<HTMLDivElement>(null);
    const [isVisible, setIsVisible] = useState(false);
    const [playback, setPlayback] = useState<GifPlayback>('auto');
    const [hasFailed, setHasFailed] = useState(false);
    const [posterFailed, setPosterFailed] = useState(false);
    const reducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)');

    useEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        if (typeof IntersectionObserver === 'undefined') {
            setIsVisible(true);

            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) =>
                setIsVisible(
                    entry.isIntersecting && entry.intersectionRatio >= 0.1,
                ),
            { threshold: 0.1 },
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const isPlaying =
        embed !== null &&
        isGifPlaying({
            playback,
            isVisible,
            reducedMotion,
            isStatic,
            hasFailed,
        });
    const isWaiting = !embed && preview.is_pending;
    const Icon = isPlaying ? Square : Play;
    const details = (
        <>
            <span className="block truncate font-mono text-[13px] text-muted-foreground">
                {embed?.provider ?? hostname(preview.url)}
            </span>
            <span className="block truncate text-[15px] leading-snug font-medium">
                {preview.title ?? 'GIF'}
            </span>
        </>
    );

    return (
        <div
            ref={container}
            onClick={isStatic ? undefined : (event) => event.stopPropagation()}
            className="mt-3 overflow-hidden rounded-lg border"
            data-test="gif-embed"
            data-provider={embed?.provider ?? 'GIF'}
        >
            <div className="relative mx-auto aspect-square w-full max-w-80 overflow-hidden bg-muted">
                {isPlaying && embed ? (
                    <img
                        src={embed.src}
                        alt={preview.title ?? 'Animated GIF'}
                        referrerPolicy="no-referrer"
                        onError={() => setHasFailed(true)}
                        className="absolute inset-0 size-full object-contain"
                        data-test="gif-animation"
                    />
                ) : (
                    <>
                        {embed?.poster && !posterFailed && !hasFailed && (
                            <img
                                src={embed.poster}
                                alt=""
                                loading="lazy"
                                referrerPolicy="no-referrer"
                                onError={() => setPosterFailed(true)}
                                className="absolute inset-0 size-full object-contain"
                                data-test="gif-poster"
                            />
                        )}
                        <span
                            className="absolute inset-0 flex items-center justify-center px-4 text-center text-sm text-muted-foreground"
                            role={hasFailed ? 'status' : undefined}
                        >
                            {hasFailed
                                ? 'GIF could not load'
                                : isWaiting
                                  ? 'Loading GIF…'
                                  : !embed
                                    ? 'GIF preview unavailable'
                                    : !embed.poster || posterFailed
                                      ? 'GIF'
                                      : null}
                        </span>
                    </>
                )}
                {!isStatic && embed && !hasFailed && (
                    <button
                        type="button"
                        onClick={() => setPlayback(isPlaying ? 'stop' : 'play')}
                        aria-label={isPlaying ? 'Stop GIF' : 'Play GIF'}
                        className="absolute right-3 bottom-3 flex min-w-20 cursor-pointer items-center justify-center gap-2 rounded-md bg-black/75 px-3 py-2 text-sm text-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        data-test="gif-playback"
                    >
                        <Icon className="size-4 fill-current" />
                        {isPlaying ? 'Stop' : 'Play'}
                    </button>
                )}
            </div>
            {isStatic ? (
                <div className="border-t px-3 py-2">{details}</div>
            ) : (
                <a
                    href={preview.url}
                    target="_blank"
                    rel="noopener noreferrer nofollow ugc"
                    className="block border-t px-3 py-2 transition-colors hover:bg-accent focus-visible:bg-accent"
                >
                    {details}
                </a>
            )}
        </div>
    );
}
