export function isGifUrl(value: string): boolean {
    try {
        const url = new URL(value);

        return (
            url.protocol === 'https:' &&
            url.username === '' &&
            url.password === '' &&
            url.port === '' &&
            url.pathname.toLowerCase().endsWith('.gif')
        );
    } catch {
        return false;
    }
}

export type GifPlayback = 'auto' | 'play' | 'stop';

export function isGifPlaying({
    playback,
    isVisible,
    reducedMotion,
    isStatic,
    hasFailed,
}: {
    playback: GifPlayback;
    isVisible: boolean;
    reducedMotion: boolean;
    isStatic: boolean;
    hasFailed: boolean;
}): boolean {
    return (
        !isStatic &&
        !hasFailed &&
        isVisible &&
        (playback === 'play' || (playback === 'auto' && !reducedMotion))
    );
}
