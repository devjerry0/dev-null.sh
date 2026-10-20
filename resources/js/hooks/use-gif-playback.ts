import type { RefObject } from 'react';
import { useEffect, useRef, useState } from 'react';
import { useMediaQuery } from '@/hooks/use-media-query';
import { GIF_PLAYBACK_MODE, isGifPlaying, type GifPlayback } from '@/lib/gifs';
import type { Embed } from '@/types';

const MIN_VISIBLE_RATIO = 0.1;

function useGifVisibility(container: RefObject<HTMLDivElement | null>): boolean {
    const [isVisible, setIsVisible] = useState(false);

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
                    entry.isIntersecting &&
                        entry.intersectionRatio >= MIN_VISIBLE_RATIO,
                ),
            { threshold: MIN_VISIBLE_RATIO },
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, [container]);

    return isVisible;
}

export function useGifPlayback({
    embed,
    isStatic,
}: {
    embed: Embed | null;
    isStatic: boolean;
}) {
    const container = useRef<HTMLDivElement>(null);
    const isVisible = useGifVisibility(container);
    const reducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)');
    const [playback, setPlayback] = useState<GifPlayback>(
        GIF_PLAYBACK_MODE.AUTOMATIC,
    );
    const [hasFailed, setHasFailed] = useState(false);
    const isPlaying =
        embed !== null &&
        isGifPlaying({ playback, isVisible, reducedMotion, isStatic, hasFailed });

    return {
        container,
        isPlaying,
        hasFailed,
        onError: () => setHasFailed(true),
        onToggle: () =>
            setPlayback(
                isPlaying ? GIF_PLAYBACK_MODE.STOPPED : GIF_PLAYBACK_MODE.PLAYING,
            ),
    };
}
