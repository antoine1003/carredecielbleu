import { Controller } from '@hotwired/stimulus';

/*
 * Captcha ALTCHA (auto-hébergé) : charge le widget et ses textes français uniquement
 * sur les pages qui ont un formulaire protégé (contrôleur chargé à la demande).
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    async connect() {
        await import('altcha/i18n/fr-fr');
        await import('altcha');
        await customElements.whenDefined('altcha-widget');

        // Le widget se déclenche quand on entre dans le formulaire (auto="onfocus"). S'il arrive alors
        // que le focus y est déjà (bloc remplacé après une erreur, focus remis sur le champ), on lance
        // la vérification tout de suite, sinon la preuve manquerait au prochain envoi.
        const form = this.element.closest('form');
        if (form && form.contains(document.activeElement) && typeof this.element.verify === 'function') {
            this.element.verify();
        }
    }
}
