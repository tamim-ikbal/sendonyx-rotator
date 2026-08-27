import { cn } from '@/lib/utils';

/**
 * The wordmark files are named after their ink, not after the colour scheme:
 * `logo-dark` is the dark wordmark that belongs on a light background, and
 * `logo-light` is the white one that belongs on a dark background.
 */
const DARK_INK = '/images/logo-dark.png';
const LIGHT_INK = '/images/logo-light.png';

type Props = {
    className?: string;
    /**
     * The background the wordmark sits on. `auto` follows the colour scheme;
     * name a background for a surface that stays one colour in both schemes.
     */
    background?: 'auto' | 'light' | 'dark';
};

/**
 * The Sendonyx wordmark. It already reads as the site name, so nothing here
 * prints `name` next to it.
 *
 * Callers set the height and the width follows, because the artwork is a wide
 * wordmark rather than a square icon.
 */
export default function AppLogo({ className, background = 'auto' }: Props) {
    const base = 'w-auto object-contain';

    if (background !== 'auto') {
        return (
            <img
                src={background === 'dark' ? LIGHT_INK : DARK_INK}
                alt="Sendonyx"
                className={cn(base, className)}
            />
        );
    }

    // Both variants ship in the markup and the colour scheme picks one, so the
    // logo never flashes the wrong ink while the theme is being applied.
    return (
        <>
            <img
                src={DARK_INK}
                alt="Sendonyx"
                className={cn(base, 'dark:hidden', className)}
            />
            <img
                src={LIGHT_INK}
                alt=""
                aria-hidden
                className={cn(base, 'hidden dark:block', className)}
            />
        </>
    );
}
