import { Controller } from '@hotwired/stimulus';

/*
 * Place le focus sur l'élément dès son insertion dans la page (ex. message de la newsletter
 * après une réponse Turbo Stream), pour les utilisateurs du clavier et des lecteurs d'écran.
 */
export default class extends Controller {
    connect() {
        this.element.focus({ preventScroll: true });
    }
}
