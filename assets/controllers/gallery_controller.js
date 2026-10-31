import { Controller } from '@hotwired/stimulus';

/*
 * Galerie de la fiche produit.
 * - Les photos défilent dans un ruban à « scroll-snap » : glisser au doigt, flèches, miniatures, clavier.
 * - Un clic sur une photo l'ouvre en grand dans un <dialog> natif (Échap ferme, focus géré par le navigateur).
 * Sans JavaScript, le ruban défile toujours et chaque photo est un lien vers sa grande version.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['track', 'slide', 'previous', 'next', 'counter', 'thumb', 'dialog', 'lightboxTrack', 'lightboxCounter'];
    static values = { count: Number };

    connect() {
        this.index = 0;
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.update();
    }

    // ---------- Ruban principal ----------

    onScroll() {
        this.index = this.indexOf(this.trackTarget);
        this.update();
    }

    goTo(event) {
        this.scrollTo(this.trackTarget, Number(event.currentTarget.dataset.index));
    }

    previous() {
        this.step(-1);
    }

    next() {
        this.step(1);
    }

    step(delta) {
        const track = this.isOpen() ? this.lightboxTrackTarget : this.trackTarget;
        const target = Math.min(Math.max(this.indexOf(track) + delta, 0), this.countValue - 1);
        this.scrollTo(track, target);
    }

    // ---------- Visionneuse ----------

    open(event) {
        event.preventDefault();
        this.trigger = event.currentTarget;
        const index = Number(event.currentTarget.dataset.index);

        this.dialogTarget.showModal();
        document.documentElement.classList.add('has-lightbox');
        // Positionnement instantané sur la photo cliquée, une fois la visionneuse affichée.
        requestAnimationFrame(() => {
            this.scrollTo(this.lightboxTrackTarget, index, true);
            this.updateLightbox(index);
        });
        this.dialogTarget.addEventListener('close', () => this.onClosed(), { once: true });
    }

    close() {
        this.dialogTarget.close();
    }

    /** Clic hors de la photo (fond sombre) : on ferme. */
    closeOnBackdrop(event) {
        if (event.target === this.dialogTarget || event.target.classList.contains('lightbox__slide')) {
            this.close();
        }
    }

    onKey(event) {
        if ('ArrowLeft' === event.key) {
            event.preventDefault();
            this.previous();
        } else if ('ArrowRight' === event.key) {
            event.preventDefault();
            this.next();
        }
    }

    onLightboxScroll() {
        this.updateLightbox(this.indexOf(this.lightboxTrackTarget));
    }

    onClosed() {
        document.documentElement.classList.remove('has-lightbox');
        // Le ruban principal reprend la photo regardée en grand, puis le focus revient où il était
        // (la visionneuse est déjà masquée : on utilise la dernière position mémorisée).
        const index = this.lightboxIndex ?? this.index;
        this.scrollTo(this.trackTarget, index, true);
        const slideLink = this.slideTargets[index]?.querySelector('a');
        (slideLink ?? this.trigger)?.focus({ preventScroll: true });
    }

    // ---------- Outils ----------

    isOpen() {
        return this.hasDialogTarget && this.dialogTarget.open;
    }

    indexOf(track) {
        return track.clientWidth > 0 ? Math.round(track.scrollLeft / track.clientWidth) : 0;
    }

    scrollTo(track, index, instant = false) {
        track.scrollTo({
            left: index * track.clientWidth,
            behavior: instant || this.reducedMotion ? 'auto' : 'smooth',
        });
    }

    update() {
        if (this.hasCounterTarget) {
            this.counterTarget.textContent = String(this.index + 1);
        }
        if (this.hasPreviousTarget) {
            this.previousTarget.disabled = 0 === this.index;
            this.nextTarget.disabled = this.index >= this.countValue - 1;
        }
        this.thumbTargets.forEach((thumb, i) => {
            if (i === this.index) {
                thumb.setAttribute('aria-current', 'true');
            } else {
                thumb.removeAttribute('aria-current');
            }
        });
    }

    updateLightbox(index) {
        this.lightboxIndex = index;
        if (this.hasLightboxCounterTarget) {
            this.lightboxCounterTarget.textContent = String(index + 1);
        }
    }
}
