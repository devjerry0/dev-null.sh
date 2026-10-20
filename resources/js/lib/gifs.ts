export function isGifUrl(value: string): boolean {
    try {
        const url = new URL(value);

        if (
            url.protocol !== 'https:' ||
            url.username !== '' ||
            url.password !== '' ||
            url.port !== ''
        ) {
            return false;
        }

        return url.pathname.toLowerCase().endsWith('.gif');
    } catch {
        return false;
    }
}

export const GIF_PLAYBACK_MODE = {
    AUTOMATIC: 'auto',
    PLAYING: 'play',
    STOPPED: 'stop',
} as const;

export type GifPlayback =
    (typeof GIF_PLAYBACK_MODE)[keyof typeof GIF_PLAYBACK_MODE];

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
    if (
        isStatic ||
        hasFailed ||
        !isVisible ||
        playback === GIF_PLAYBACK_MODE.STOPPED
    ) {
        return false;
    }

    if (playback === GIF_PLAYBACK_MODE.PLAYING) {
        return true;
    }

    return !reducedMotion;
}
