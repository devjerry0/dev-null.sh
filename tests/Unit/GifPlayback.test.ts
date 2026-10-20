/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isGifPlaying, isGifUrl } from '../../resources/js/lib/gifs.ts';

const validGifUrls = [
    'https://example.com/animation.gif',
    'https://example.com/animation.GIF?token=abc#frame',
    'https://media2.giphy.com/media/cat/giphy.gif',
    'https://example.com:443/cat.gif',
];

for (const url of validGifUrls) {
    test(`recognizes a direct HTTPS GIF link: ${url}`, () => {
        assert.equal(isGifUrl(url), true);
    });
}

const invalidGifUrls = [
    'not a URL',
    'http://example.com/cat.gif',
    'data:image/gif;base64,R0lGODlh',
    'javascript:alert(1)',
    'https://example.com/page?image=cat.gif',
    'https://example.com/cat.gif/page',
    'https://example.com/cat.gif.html',
    'https://user:secret@example.com/cat.gif',
    'https://example.com:8443/cat.gif',
];

for (const url of invalidGifUrls) {
    test(`does not treat an unsupported link as a GIF: ${url}`, () => {
        assert.equal(isGifUrl(url), false);
    });
}

const playback = {
    playback: 'auto' as const,
    isVisible: true,
    reducedMotion: false,
    isStatic: false,
    hasFailed: false,
};

test('autoplays visible GIFs and suspends them outside the viewport', () => {
    assert.equal(isGifPlaying(playback), true);
    assert.equal(isGifPlaying({ ...playback, isVisible: false }), false);
});

test('reduced motion requires an explicit Play action', () => {
    assert.equal(isGifPlaying({ ...playback, reducedMotion: true }), false);
    assert.equal(
        isGifPlaying({ ...playback, reducedMotion: true, playback: 'play' }),
        true,
    );
});

test('Stop survives leaving and reentering the viewport', () => {
    assert.equal(isGifPlaying({ ...playback, playback: 'stop' }), false);
    assert.equal(
        isGifPlaying({ ...playback, playback: 'stop', isVisible: false }),
        false,
    );
    assert.equal(isGifPlaying({ ...playback, playback: 'stop' }), false);
    assert.equal(isGifPlaying({ ...playback, playback: 'play' }), true);
});

test('static cards and failed GIFs never animate, even after Play', () => {
    assert.equal(isGifPlaying({ ...playback, isStatic: true }), false);
    assert.equal(
        isGifPlaying({ ...playback, playback: 'play', isStatic: true }),
        false,
    );
    assert.equal(isGifPlaying({ ...playback, hasFailed: true }), false);
    assert.equal(
        isGifPlaying({ ...playback, playback: 'play', hasFailed: true }),
        false,
    );
});
