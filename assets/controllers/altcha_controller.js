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
    }
}
