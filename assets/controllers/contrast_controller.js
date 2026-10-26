import { Controller } from '@hotwired/stimulus';

/*
 * Styleguide (dev) : calcule en direct le contraste WCAG de paires de variables CSS.
 * Chaque cible porte data-fg, data-bg (noms de variables sans --) et data-min.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['row'];

    connect() {
        this.rowTargets.forEach((row) => {
            const fg = this.resolve(row.dataset.fg);
            const bg = this.resolve(row.dataset.bg);
            const ratio = this.ratio(fg, bg);
            const min = Number(row.dataset.min);
            const output = row.querySelector('[data-ratio]');

            output.textContent = `${ratio.toFixed(2).replace('.', ',')}:1 — ${ratio >= min ? 'conforme' : 'insuffisant'}`;
            row.dataset.pass = String(ratio >= min);
        });
    }

    resolve(name) {
        const probe = document.createElement('span');
        probe.style.color = `var(--${name})`;
        document.body.append(probe);
        const value = getComputedStyle(probe).color;
        probe.remove();

        return value.match(/[\d.]+/g).slice(0, 3).map(Number);
    }

    luminance(rgb) {
        const [r, g, b] = rgb.map((channel) => {
            const c = channel / 255;

            return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
        });

        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    }

    ratio(a, b) {
        const la = this.luminance(a);
        const lb = this.luminance(b);

        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }
}
