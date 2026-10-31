import { Controller } from '@hotwired/stimulus';

/*
 * Vidéo YouTube d'un atelier : rien n'est chargé depuis YouTube avant le clic du visiteur.
 * Au clic, la vignette est remplacée par le lecteur youtube-nocookie, lancé automatiquement.
 * Sans JavaScript, la vignette reste un lien vers la vidéo sur YouTube.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['poster'];
    static values = { id: String, title: String };

    play(event) {
        event.preventDefault();

        const iframe = document.createElement('iframe');
        iframe.className = 'video__frame';
        iframe.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(this.idValue)}?autoplay=1&rel=0`;
        iframe.title = this.titleValue;
        iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.allowFullscreen = true;
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';

        this.posterTarget.replaceWith(iframe);
        iframe.focus();
    }
}
